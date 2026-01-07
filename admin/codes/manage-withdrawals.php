<?php
session_start();
include('../../config/dbcon.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input parameters']);
    exit;
}

mysqli_begin_transaction($con);

try {
    // Lock the withdrawal row to prevent race conditions
    $stmt = mysqli_prepare(
        $con,
        "SELECT status, email, usd_amount 
         FROM withdrawals 
         WHERE id = ? 
         FOR UPDATE"
    );
    
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $wd = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$wd) {
        throw new Exception('Withdrawal request not found');
    }

    if ((int)$wd['status'] !== 0) {
        throw new Exception('Withdrawal has already been processed');
    }

    /* ========= APPROVE ========= */
    if ($action === 'approve') {
        $stmt = mysqli_prepare(
            $con,
            "UPDATE withdrawals 
             SET status = 1, processed_at = NOW() 
             WHERE id = ? AND status = 0"
        );
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        
        if (mysqli_stmt_affected_rows($stmt) !== 1) {
            throw new Exception('Failed to approve withdrawal - status already changed');
        }
        mysqli_stmt_close($stmt);
    }

    /* ========= REJECT + REFUND ========= */
    if ($action === 'reject') {
        // Refund using the USD amount (this is the value that was deducted from user's balance)
        $refund_amount = (float)$wd['usd_amount'];

        if ($refund_amount <= 0) {
            throw new Exception('Invalid refund amount (usd_amount ≤ 0)');
        }

        $stmt = mysqli_prepare(
            $con,
            "UPDATE users 
             SET balance = balance + ? 
             WHERE email = ?"
        );
        mysqli_stmt_bind_param($stmt, "ds", $refund_amount, $wd['email']);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) !== 1) {
            throw new Exception('Refund failed - user not found or multiple rows affected');
        }
        mysqli_stmt_close($stmt);

        // Mark withdrawal as rejected
        $stmt = mysqli_prepare(
            $con,
            "UPDATE withdrawals 
             SET status = 2, processed_at = NOW() 
             WHERE id = ? AND status = 0"
        );
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) !== 1) {
            throw new Exception('Failed to reject withdrawal - status already changed');
        }
        mysqli_stmt_close($stmt);
    }

    mysqli_commit($con);
    
    echo json_encode([
        'success' => true,
        'message' => $action === 'approve' ? 'Withdrawal approved' : 'Withdrawal rejected and balance refunded'
    ]);

} catch (Exception $e) {
    mysqli_rollback($con);
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

exit;
