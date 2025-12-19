<?php
include('../../config/dbcon.php');
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $payment_plan = (int)($_POST['payment_plan'] ?? 1);

    if (empty($email) || $payment_plan < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }

    $stmt = mysqli_prepare($con, "UPDATE users SET payment_plan = ? WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "is", $payment_plan, $email);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => $success]);
}
?>
