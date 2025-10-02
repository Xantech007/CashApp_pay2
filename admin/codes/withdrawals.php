<?php
session_start();
include('../config/dbcon.php');

// Function to validate CSRF token
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Handle status update
if (isset($_POST['update_status'])) {
    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['message'] = "Invalid CSRF token.";
        header("Location: ../pending_withdrawals.php");
        exit();
    }

    $withdrawal_id = filter_input(INPUT_POST, 'withdrawal_id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_VALIDATE_INT);

    // Validate inputs
    if ($withdrawal_id === false || $status === false || !in_array($status, [0, 1, 2])) {
        $_SESSION['message'] = "Invalid input data.";
        header("Location: ../pending_withdrawals.php");
        exit();
    }

    // Update the withdrawal status
    $query = "UPDATE withdrawals SET status = ? WHERE id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("ii", $status, $withdrawal_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Withdrawal status updated successfully.";
    } else {
        $_SESSION['message'] = "Failed to update withdrawal status.";
    }

    $stmt->close();
    $con->close();
    header("Location: ../pending_withdrawals.php");
    exit();
} else {
    $_SESSION['message'] = "Invalid request.";
    header("Location: ../pending_withdrawals.php");
    exit();
}
?>
