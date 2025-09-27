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

    // Fetch the email associated with the deposit_id
    $email_query = "SELECT email FROM deposits WHERE id = ?";
    $email_stmt = mysqli_prepare($con, $email_query);
    if ($email_stmt === false) {
        error_log("update-deposit.php - Failed to prepare email query: " . mysqli_error($con));
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($con)]);
        exit;
    }

    mysqli_stmt_bind_param($email_stmt, 'i', $deposit_id);
    if (!mysqli_stmt_execute($email_stmt)) {
        error_log("update-deposit.php - Failed to execute email query: " . mysqli_error($con));
        echo json_encode(['success' => false, 'message' => 'Failed to fetch deposit details: ' . mysqli_error($con)]);
        mysqli_stmt_close($email_stmt);
        exit;
    }

    $result = mysqli_stmt_get_result($email_stmt);
    if (mysqli_num_rows($result) === 0) {
        error_log("update-deposit.php - No deposit found for deposit_id: $deposit_id");
        echo json_encode(['success' => false, 'message' => 'Deposit not found']);
        mysqli_stmt_close($email_stmt);
        exit;
    }

    $deposit_data = mysqli_fetch_assoc($result);
    $email = $deposit_data['email'];
    mysqli_stmt_close($email_stmt);

    // Start a transaction to ensure atomicity
    mysqli_begin_transaction($con);

    try {
        // Update the deposits table
        $deposit_query = "UPDATE deposits SET payment_plan = ?, installment_number = ? WHERE id = ?";
        $deposit_stmt = mysqli_prepare($con, $deposit_query);
        if ($deposit_stmt === false) {
            throw new Exception('Failed to prepare deposit update query: ' . mysqli_error($con));
        }

        mysqli_stmt_bind_param($deposit_stmt, 'iii', $payment_plan, $installment_number, $deposit_id);
        if (!mysqli_stmt_execute($deposit_stmt)) {
            throw new Exception('Failed to update deposit: ' . mysqli_error($con));
        }
        mysqli_stmt_close($deposit_stmt);

        // Update the users table with the new payment_plan
        $user_query = "UPDATE users SET payment_plan = ? WHERE email = ?";
        $user_stmt = mysqli_prepare($con, $user_query);
        if ($user_stmt === false) {
            throw new Exception('Failed to prepare user update query: ' . mysqli_error($con));
        }

        mysqli_stmt_bind_param($user_stmt, 'is', $payment_plan, $email);
        if (!mysqli_stmt_execute($user_stmt)) {
            throw new Exception('Failed to update user payment plan: ' . mysqli_error($con));
        }
        mysqli_stmt_close($user_stmt);

        // Commit the transaction
        mysqli_commit($con);

        error_log("update-deposit.php - Successfully updated deposit_id: $deposit_id and user email: $email with payment_plan: $payment_plan, installment_number: $installment_number");
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        // Rollback the transaction on error
        mysqli_rollback($con);
        error_log("update-deposit.php - Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    error_log("update-deposit.php - Invalid request: " . print_r($_POST, true));
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

mysqli_close($con);
?>
