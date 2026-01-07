<?php
session_start();
include('../config/dbcon.php');

if (isset($_POST['withdraw'])) {
    // Sanitize inputs
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $amount = floatval($_POST['amount']);           // Original requested amount (always in USD)
    $balance = floatval($_POST['balance']);
    $channel = mysqli_real_escape_string($con, $_POST['channel']);
    $channel_name = mysqli_real_escape_string($con, $_POST['channel_name']);
    $channel_number = mysqli_real_escape_string($con, $_POST['channel_number']);

    // === VERIFY USER STATUS ===
    $verify_query = "SELECT verify, country FROM users WHERE email = ? LIMIT 1";
    $stmt = $con->prepare($verify_query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $verify_result = $stmt->get_result();

    if ($verify_result && $verify_result->num_rows > 0) {
        $user = $verify_result->fetch_assoc();
        $user_country = $user['country'];
        $verify_status = (int)$user['verify'];

        // BLOCK WITHDRAWAL BASED ON verify VALUE
        if ($verify_status == 0) {
            $_SESSION['error'] = "Verify Your Account and Try Again.";
            header("Location: ../users/withdrawals.php");
            exit(0);
        } elseif ($verify_status == 1) {
            $_SESSION['error'] = "Verification Under Review, Try Again Later.";
            header("Location: ../users/withdrawals.php");
            exit(0);
        } elseif ($verify_status == 3) {
            $_SESSION['error'] = "An error occurred while converting to your local currency.";
            header("Location: ../users/withdrawals.php");
            exit(0);
        } elseif ($verify_status != 2) {
            $_SESSION['error'] = "Invalid verification status.";
            header("Location: ../users/withdrawals.php");
            exit(0);
        }
        // Only verify == 2 continues
    } else {
        $_SESSION['error'] = "User not found.";
        header("Location: ../users/withdrawals.php");
        exit(0);
    }
    $stmt->close();

    // === INPUT VALIDATION ===
    if (empty($channel) || empty($channel_name) || empty($channel_number)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: ../users/withdrawals.php");
        exit(0);
    }

    if ($amount < 50) {
        $_SESSION['error'] = "Minimum withdrawal is set at $50";
        header("Location: ../users/withdrawals.php");
        exit(0);
    }

    if ($amount > $balance) {
        $_SESSION['error'] = "Request failed due to insufficient balance!";
        header("Location: ../users/withdrawals.php");
        exit(0);
    }

    // === FETCH CURRENCY & RATE ===
    $payment_query = "SELECT currency, alt_currency, crypto, alt_rate 
                      FROM region_settings 
                      WHERE country = ? LIMIT 1";
    $stmt = $con->prepare($payment_query);
    $stmt->bind_param("s", $user_country);
    $stmt->execute();
    $payment_result = $stmt->get_result();

    if ($payment_result && $payment_result->num_rows > 0) {
        $payment = $payment_result->fetch_assoc();
        $base_currency   = $payment['currency'] ?? 'USD';
        $alt_currency    = $payment['alt_currency'] ?? 'USD';
        $crypto          = (int)($payment['crypto'] ?? 0);
        $rate            = floatval($payment['alt_rate'] ?? 1.0);
        $target_currency = $crypto ? $alt_currency : $base_currency;

        // Amount user will actually receive after conversion
        $received_amount = $crypto ? $amount * $rate : $amount * $rate;
    } else {
        $_SESSION['error'] = "Failed to fetch payment details for your region.";
        header("Location: ../users/withdrawals.php");
        exit(0);
    }
    $stmt->close();

    // === INSERT WITHDRAWAL REQUEST ===
    $query = "INSERT INTO withdrawals (
        email, 
        amount,           -- amount user will receive (local/crypto)
        usd_amount,       -- original requested amount in USD
        currency,         -- currency of the received amount
        channel, 
        channel_name, 
        channel_number, 
        status, 
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())";

    $stmt = $con->prepare($query);
    
    $stmt->bind_param(
        "sddssss",
        $email,
        $received_amount,
        $amount,               // ← original USD amount
        $target_currency,
        $channel,
        $channel_name,
        $channel_number
    );

    if ($stmt->execute()) {
        // === UPDATE USER BALANCE (always deduct in USD) ===
        $new_balance = $balance - $amount;

        $update_query = "UPDATE users SET balance = ? WHERE email = ?";
        $update_stmt = $con->prepare($update_query);
        $update_stmt->bind_param("ds", $new_balance, $email);

        if ($update_stmt->execute()) {
            $_SESSION['success'] = "Withdrawal request of USD " . number_format($amount, 2) . " submitted successfully!\n" .
                                 "You will receive approximately " . $target_currency . " " . number_format($received_amount, 2);
            header("Location: ../users/withdrawals.php");
            exit(0);
        } else {
            $_SESSION['error'] = "Withdrawal recorded but failed to update user balance.";
            // In production: consider logging + manual correction
        }
        $update_stmt->close();
    } else {
        $_SESSION['error'] = "Failed to submit withdrawal request. Please try again.";
    }
    $stmt->close();
}

// === DELETE WITHDRAWAL REQUEST ===
if (isset($_POST['delete'])) {
    $id = mysqli_real_escape_string($con, $_POST['delete']);
    
    $delete_query = "DELETE FROM withdrawals WHERE id = ? LIMIT 1";
    $stmt = $con->prepare($delete_query);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Withdrawal request deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete withdrawal request.";
    }
    $stmt->close();

    header("Location: ../users/withdrawals.php");
    exit(0);
}

$con->close();
?>
