<?php
session_start();
include('../../config/dbcon.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

header('Content-Type: application/json');

// APPROVE (to Processing)
if (isset($_POST['action']) && $_POST['action'] === 'approve') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid withdrawal ID']);
        exit();
    }

    $stmt = $con->prepare("SELECT status FROM withdrawals WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $wd = $result->fetch_assoc();
    $stmt->close();

    if (!$wd) {
        echo json_encode(['success' => false, 'message' => 'Withdrawal not found']);
        exit();
    }
    if ($wd['status'] != 0) {
        echo json_encode(['success' => false, 'message' => 'This withdrawal has already been processed']);
        exit();
    }

    $new_status = 1; // Processing
    $stmt = $con->prepare("UPDATE withdrawals SET status = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_status, $id);
    $success = $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Withdrawal approved (now in processing)' : 'Failed to update status'
    ]);
    exit();
}

// COMPLETE
elseif (isset($_POST['action']) && $_POST['action'] === 'complete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid withdrawal ID']);
        exit();
    }

    $stmt = $con->prepare("SELECT status FROM withdrawals WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $wd = $result->fetch_assoc();
    $stmt->close();

    if (!$wd || $wd['status'] !== 1) {
        echo json_encode(['success' => false, 'message' => 'Withdrawal must be in processing status']);
        exit();
    }

    $new_status = 2; // Completed
    $stmt = $con->prepare("UPDATE withdrawals SET status = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_status, $id);
    $success = $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Withdrawal marked as completed' : 'Failed to update status'
    ]);
    exit();
}

// REJECT + Refund
elseif (isset($_POST['action']) && $_POST['action'] === 'reject') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid withdrawal ID']);
        exit();
    }

    $con->begin_transaction();

    try {
        $stmt = $con->prepare("SELECT status, email, amount FROM withdrawals WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $wd = $result->fetch_assoc();
        $stmt->close();

        if (!$wd) throw new Exception('Withdrawal not found');
        if (!in_array($wd['status'], [0, 1])) {
            throw new Exception('This withdrawal cannot be rejected (already completed/rejected)');
        }

        // Refund balance if pending or processing
        if (in_array($wd['status'], [0, 1])) {
            $stmt = $con->prepare("UPDATE users SET balance = balance + ? WHERE email = ?");
            $stmt->bind_param("ds", $wd['amount'], $wd['email']);
            if (!$stmt->execute()) throw new Exception('Failed to refund balance');
            $stmt->close();
        }

        $new_status = 3; // Rejected
        $stmt = $con->prepare("UPDATE withdrawals SET status = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_status, $id);
        if (!$stmt->execute()) throw new Exception('Failed to reject withdrawal');
        $stmt->close();

        $con->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Withdrawal rejected & balance refunded'
        ]);
    } catch (Exception $e) {
        $con->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Fallback
else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$con->close();
?>
