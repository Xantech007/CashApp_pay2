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

if ($id <= 0 || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$con->begin_transaction();

try {
    $stmt = $con->prepare("SELECT status, email, amount FROM withdrawals WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $wd = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$wd) throw new Exception('Withdrawal not found');
    if ((int)$wd['status'] !== 0) throw new Exception('Already processed');

    if ($action === 'approve') {
        // status = 1 (Approved)
        $stmt = $con->prepare("UPDATE withdrawals SET status = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    if ($action === 'reject') {
        // refund user
        $stmt = $con->prepare("UPDATE users SET balance = balance + ? WHERE email = ?");
        $stmt->bind_param("ds", $wd['amount'], $wd['email']);
        $stmt->execute();
        $stmt->close();

        // status = 2 (Rejected)
        $stmt = $con->prepare("UPDATE withdrawals SET status = 2 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    $con->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $con->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$con->close();
