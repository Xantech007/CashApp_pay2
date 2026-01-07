<?php
session_start();
include('../../config/dbcon.php');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

mysqli_begin_transaction($con);

try {
    // Lock withdrawal row
    $stmt = mysqli_prepare(
        $con,
        "SELECT status, email, amount FROM withdrawals WHERE id = ? FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $wd = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$wd) {
        throw new Exception('Withdrawal not found');
    }

    if ((int)$wd['status'] !== 0) {
        throw new Exception('Withdrawal already processed');
    }

    /* ========= APPROVE ========= */
    if ($action === 'approve') {
        $stmt = mysqli_prepare(
            $con,
            "UPDATE withdrawals SET status = 1 WHERE id = ? AND status = 0"
        );
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) !== 1) {
            throw new Exception('Approve failed (status changed)');
        }

        mysqli_stmt_close($stmt);
    }

    /* ========= REJECT ========= */
    if ($action === 'reject') {
        // Refund user balance
        $stmt = mysqli_prepare(
            $con,
            "UPDATE users SET balance = balance + ? WHERE email = ?"
        );
        mysqli_stmt_bind_param($stmt, "ds", $wd['amount'], $wd['email']);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) !== 1) {
            throw new Exception('Refund failed');
        }

        mysqli_stmt_close($stmt);

        // Update withdrawal status
        $stmt = mysqli_prepare(
            $con,
            "UPDATE withdrawals SET status = 2 WHERE id = ? AND status = 0"
        );
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) !== 1) {
            throw new Exception('Reject failed (status changed)');
        }

        mysqli_stmt_close($stmt);
    }

    mysqli_commit($con);
    echo json_encode(['success' => true]);
    exit;

} catch (Exception $e) {
    mysqli_rollback($con);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
