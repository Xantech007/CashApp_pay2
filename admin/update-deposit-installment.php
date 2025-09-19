<?php
session_start();
include('../config/dbcon.php'); // Include database connection

// Check if the request is POST and required fields are set
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deposit_id'], $_POST['payment_plan'], $_POST['installment_number'])) {
    $deposit_id = (int)$_POST['deposit_id'];
    $payment_plan = (int)$_POST['payment_plan'];
    $installment_number = (int)$_POST['installment_number'];

    // Validate inputs
    if ($deposit_id <= 0 || $payment_plan <= 0 || $installment_number <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid input values']);
        exit;
    }
    if ($installment_number > $payment_plan) {
        echo json_encode(['success' => false, 'message' => 'Current installment number cannot exceed total payment plan']);
        exit;
    }

    // Prepare and execute the update query
    $query = "UPDATE deposits SET payment_plan = ?, installment_number = ? WHERE id = ?";
    $stmt = mysqli_prepare($con, $query);
    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($con)]);
        exit;
    }

    mysqli_stmt_bind_param($stmt, 'iii', $payment_plan, $installment_number, $deposit_id);
    $result = mysqli_stmt_execute($stmt);

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update installment details: ' . mysqli_error($con)]);
    }

    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

mysqli_close($con);
?>
