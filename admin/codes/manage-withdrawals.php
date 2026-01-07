<?php
session_start();
include('../../config/dbcon.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

header('Content-Type: application/json');

// APPROVE (sets status = 1 - Approved/Completed)
if (isset($_POST['action']) && $_POST['action'] === 'approve') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid withdrawal ID']);
        exit();
    }

    $con->begin_transaction();

    try {
        $stmt = $con->prepare("SELECT status FROM withdrawals WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $wd = $result->fetch_assoc();
        $stmt->close();

        if (!$wd) {
            throw new Exception("Withdrawal not found");
        }

        if ($wd['status'] !== 0) {
            throw new Exception("This withdrawal has already been processed");
        }

        $new_status = 1; // Approved
        $stmt = $con->prepare("UPDATE withdrawals SET status = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_status, $id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to approve withdrawal");
        }
        $stmt->close();

        $con->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Withdrawal approved successfully',
            'new_status' => $new_status
        ]);
    } catch (Exception $e) {
        $con->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// REJECT (sets status = 2 + refund)
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

        if ($wd['status'] !== 0) {
            throw new Exception('This withdrawal cannot be rejected (already processed)');
        }

        // Refund balance
        $stmt = $con->prepare("UPDATE users SET balance = balance + ? WHERE email = ?");
        $stmt->bind_param("ds", $wd['amount'], $wd['email']);
        if (!$stmt->execute()) throw new Exception('Failed to refund balance');
        $stmt->close();

        $new_status = 2; // Rejected
        $stmt = $con->prepare("UPDATE withdrawals SET status = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_status, $id);
        if (!$stmt->execute()) throw new Exception('Failed to reject withdrawal');
        $stmt->close();

        $con->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Withdrawal rejected and balance refunded'
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
