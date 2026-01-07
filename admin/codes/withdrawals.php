<?php
session_start();
include('../../config/dbcon.php');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Optional: Add admin session check (uncomment/adapt to your actual admin auth)
if (!isset($_SESSION['admin_logged_in'])) {  // ← replace with your real admin session key
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Set JSON response header for all actions
header('Content-Type: application/json');

// ============================================
// APPROVE WITHDRAWAL (set to Processing)
// ============================================
if (isset($_POST['action']) && $_POST['action'] === 'approve') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid withdrawal ID']);
        exit();
    }

    $con->begin_transaction();

    try {
        // 1. Check current status & get data
        $stmt = $con->prepare("
            SELECT status, email, amount 
            FROM withdrawals 
            WHERE id = ? LIMIT 1
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $withdrawal = $result->fetch_assoc();
        $stmt->close();

        if (!$withdrawal) {
            throw new Exception("Withdrawal request not found");
        }

        if ($withdrawal['status'] != 0) {
            throw new Exception("This withdrawal has already been processed");
        }

        // 2. Update to Processing (status = 1)
        $new_status = 1;
        $stmt = $con->prepare("UPDATE withdrawals SET status = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_status, $id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update withdrawal status");
        }
        $stmt->close();

        $con->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Withdrawal approved (now in processing)',
            'new_status' => $new_status
        ]);
    } catch (Exception $e) {
        $con->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================
// COMPLETE WITHDRAWAL (set to Completed)
// ============================================
elseif (isset($_POST['action']) && $_POST['action'] === 'complete') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid withdrawal ID']);
        exit();
    }

    $con->begin_transaction();

    try {
        // 1. Get current data
        $stmt = $con->prepare("
            SELECT status, email, amount 
            FROM withdrawals 
            WHERE id = ? LIMIT 1
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $withdrawal = $result->fetch_assoc();
        $stmt->close();

        if (!$withdrawal) {
            throw new Exception("Withdrawal request not found");
        }

        if ($withdrawal['status'] !== 1) {
            throw new Exception("Withdrawal must be in processing status before marking as completed");
        }

        // 2. Update to Completed (status = 2)
        $new_status = 2;
        $stmt = $con->prepare("UPDATE withdrawals SET status = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_status, $id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to mark withdrawal as completed");
        }
        $stmt->close();

        $con->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Withdrawal marked as completed',
            'new_status' => $new_status
        ]);
    } catch (Exception $e) {
        $con->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================
// REJECT WITHDRAWAL (set to Rejected + refund balance)
// ============================================
elseif (isset($_POST['action']) && $_POST['action'] === 'reject') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid withdrawal ID']);
        exit();
    }

    $con->begin_transaction();

    try {
        // 1. Get current data
        $stmt = $con->prepare("
            SELECT status, email, amount 
            FROM withdrawals 
            WHERE id = ? LIMIT 1
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $withdrawal = $result->fetch_assoc();
        $stmt->close();

        if (!$withdrawal) {
            throw new Exception("Withdrawal request not found");
        }

        // Allow rejection from Pending (0) or Processing (1)
        if (!in_array($withdrawal['status'], [0, 1])) {
            throw new Exception("This withdrawal cannot be rejected (already completed or rejected)");
        }

        // 2. Refund balance if it was pending/processing
        if (in_array($withdrawal['status'], [0, 1])) {
            $stmt = $con->prepare("
                UPDATE users 
                SET balance = balance + ? 
                WHERE email = ?
            ");
            $stmt->bind_param("ds", $withdrawal['amount'], $withdrawal['email']);
            if (!$stmt->execute()) {
                throw new Exception("Failed to refund user balance");
            }
            $stmt->close();
        }

        // 3. Set status to Rejected (3)
        $new_status = 3;
        $stmt = $con->prepare("UPDATE withdrawals SET status = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_status, $id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to reject withdrawal");
        }
        $stmt->close();

        $con->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Withdrawal rejected & balance refunded',
            'new_status' => $new_status
        ]);
    } catch (Exception $e) {
        $con->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================
// FALLBACK - Invalid action
// ============================================
else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$con->close();
?>
