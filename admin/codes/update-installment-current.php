<?php
include('../../config/dbcon.php');
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $current = (int)($_POST['current'] ?? 1);

    if ($id <= 0 || $current < 1 || $current > 4) {
        echo json_encode(['success' => false]);
        exit;
    }

    $stmt = mysqli_prepare($con, "UPDATE deposits SET installment_number = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $current, $id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => $success]);
}
?>
