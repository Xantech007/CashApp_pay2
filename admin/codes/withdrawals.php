<?php
session_start();
include('../../config/dbcon.php');

// Verify CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid CSRF token.";
        header("Location: ../admin/pending_withdrawals.php");
        exit;
    }

    $withdrawal_id = mysqli_real_escape_string($con, $_POST['withdrawal_id']);
    $status = mysqli_real_escape_string($con, $_POST['status']);

    // Validate status
    if (!in_array($status, ['0', '1', '2'])) {
        $_SESSION['error'] = "Invalid status selected.";
        header("Location: ../admin/pending_withdrawals.php");
        exit;
    }

    // Fetch withdrawal details to get the user's email and amount
    $query = "SELECT email, amount FROM withdrawals WHERE id = ?";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "i", $withdrawal_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $email = $row['email'];
        $amount = $row['amount'];
    } else {
        $_SESSION['error'] = "Withdrawal not found.";
        header("Location: ../admin/pending_withdrawals.php");
        exit;
    }
    mysqli_stmt_close($stmt);

    // Fetch currency from region_settings
    $currency = '$'; // Default currency
    $user_query = "SELECT country FROM users WHERE email = ? LIMIT 1";
    $stmt = mysqli_prepare($con, $user_query);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $user_result = mysqli_stmt_get_result($stmt);
    if ($user = mysqli_fetch_assoc($user_result)) {
        $country = $user['country'];
        $region_query = "SELECT currency FROM region_settings WHERE country = ? LIMIT 1";
        $region_stmt = mysqli_prepare($con, $region_query);
        mysqli_stmt_bind_param($region_stmt, "s", $country);
        mysqli_stmt_execute($region_stmt);
        $region_result = mysqli_stmt_get_result($region_stmt);
        if ($region = mysqli_fetch_assoc($region_result)) {
            $currency = $region['currency'] ?? '$';
        }
        mysqli_stmt_close($region_stmt);
    }
    mysqli_stmt_close($stmt);

    // Update withdrawal status
    $update_query = "UPDATE withdrawals SET status = ? WHERE id = ?";
    $stmt = mysqli_prepare($con, $update_query);
    mysqli_stmt_bind_param($stmt, "ii", $status, $withdrawal_id);
    if (mysqli_stmt_execute($stmt)) {
        // If approved, deduct amount from user's balance
        if ($status == '1') {
            $balance_query = "UPDATE users SET balance = balance - ? WHERE email = ?";
            $balance_stmt = mysqli_prepare($con, $balance_query);
            mysqli_stmt_bind_param($balance_stmt, "ds", $amount, $email);
            mysqli_stmt_execute($balance_stmt);
            mysqli_stmt_close($balance_stmt);
        }
        // If rejected, credit amount back to user's balance and update message
        if ($status == '2') {
            $balance_query = "UPDATE users SET balance = balance + ?, message = ? WHERE email = ?";
            $message = "Your withdrawal request of $currency$amount has been rejected.";
            $balance_stmt = mysqli_prepare($con, $balance_query);
            mysqli_stmt_bind_param($balance_stmt, "dss", $amount, $message, $email);
            mysqli_stmt_execute($balance_stmt);
            mysqli_stmt_close($balance_stmt);
        }
        $_SESSION['success'] = "Withdrawal status updated successfully.";
    } else {
        $_SESSION['error'] = "Failed to update withdrawal status.";
    }
    mysqli_stmt_close($stmt);
    header("Location: ../admin/pending_withdrawals.php");
    exit;
}
?>
