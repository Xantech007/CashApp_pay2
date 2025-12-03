<?php
session_start();
include('../../config/dbcon.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: ../manage-users.php");
    exit();
}

// === UPDATE USER (Email, Balance, Referral Bonus, Message, Payment Amount, + Optional Password) ===
if (isset($_POST['update_user'])) {

    // Get data from form
    $user_id        = $_POST['user_id'] ?? '';
    $email          = trim($_POST['email'] ?? '');
    $balance        = $_POST['balance'] ?? '';
    $referal_bonus  = $_POST['referal_bonus'] ?? '';
    $message        = $_POST['message'] ?? '';
    $payment_amount = !empty($_POST['payment_amount']) ? floatval($_POST['payment_amount']) : null;
    $new_password   = !empty($_POST['password']) ? trim($_POST['password']) : '';

    // Basic validation
    if (empty($user_id) || !is_numeric($user_id)) {
        $_SESSION['error'] = "Invalid user ID.";
        header("Location: ../edit_user.php?id=" . urlencode($user_id));
        exit();
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Valid email is required.";
        header("Location: ../edit_user.php?id=" . urlencode($user_id));
        exit();
    }

    if (!is_numeric($balance) || $balance < 0) {
        $_SESSION['error'] = "Balance must be a valid non-negative number.";
        header("Location: ../edit_user.php?id=" . urlencode($user_id));
        exit();
    }

    if (!is_numeric($referal_bonus) || $referal_bonus < 0) {
        $_SESSION['error'] = "Referral bonus must be a valid non-negative number.";
        header("Location: ../edit_user.php?id=" . urlencode($user_id));
        exit();
    }

    if ($payment_amount !== null && $payment_amount < 0) {
        $_SESSION['error'] = "Payment amount cannot be negative.";
        header("Location: ../edit_user.php?id=" . urlencode($user_id));
        exit();
    }

    // Build update query
    $fields = [];
    $types  = "";
    $values = [];

    $fields[] = "email = ?";
    $types .= "s";
    $values[] = $email;

    $fields[] = "balance = ?";
    $types .= "d";
    $values[] = $balance;

    $fields[] = "referal_bonus = ?";
    $types .= "d";
    $values[] = $referal_bonus;

    $fields[] = "message = ?";
    $types .= "s";
    $values[] = $message;

    if ($payment_amount !== null) {
        $fields[] = "payment_amount = ?";
        $types .= "d";
        $values[] = $payment_amount;
    } else {
        $fields[] = "payment_amount = NULL";
    }

    // Handle password change only if provided
    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $fields[] = "password = ?";
        $types .= "s";
        $values[] = $hashed_password;
    }

    $fields[] = "id = ?";
    $types .= "i";
    $values[] = $user_id;

    // Final query
    $set_clause = implode(", ", $fields);
    $query = "UPDATE users SET $set_clause WHERE id = ? LIMIT 1";

    $stmt = $con->prepare($query);
    $stmt->bind_param($types, ...$values);

    if ($stmt->execute()) {
        $_SESSION['success'] = "User updated successfully.";
        error_log("users.php - User ID $user_id updated successfully by admin.");
    } else {
        $_SESSION['error'] = "Failed to update user.";
        error_log("users.php - Update failed for user ID $user_id: " . $stmt->error);
    }

    $stmt->close();
    header("Location: ../edit_user.php?id=" . urlencode($user_id));
    exit();
}

// === DELETE USER ===
elseif (isset($_POST['delete_user'])) {
    $id = $_POST['delete_user'] ?? '';
    $profile_pic = $_POST['profile_pic'] ?? '';

    if (empty($id) || !is_numeric($id)) {
        $_SESSION['error'] = "Invalid user ID for deletion.";
        header("Location: ../manage-users.php");
        exit();
    }

    $stmt = $con->prepare("DELETE FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if (!empty($profile_pic) && file_exists("../../Uploads/profile-picture/" . $profile_pic)) {
            @unlink("../../Uploads/profile-picture/" . $profile_pic);
        }
        $_SESSION['success'] = "User deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete user.";
    }

    $stmt->close();
    header("Location: ../manage-users.php");
    exit();
}

// === UPDATE VERIFICATION STATUS ===
elseif (isset($_POST['update_verify_status'])) {
    $user_id = $_POST['user_id'] ?? '';
    $verify_status = $_POST['verify_status'] ?? '';

    if (!is_numeric($user_id) || !in_array($verify_status, ['0', '1', '2', '3'], true)) {
        $_SESSION['error'] = "Invalid verification status or user ID.";
        header("Location: ../manage-users.php");
        exit();
    }

    $user_id = (int)$user_id;
    $verify_status = (int)$verify_status;

    $stmt = $con->prepare("UPDATE users SET verify = ? WHERE id = ? LIMIT 1");
    $stmt->bind_param("ii", $verify_status, $user_id);

    if ($stmt->execute()) {
        $status_text = ['0' => 'Not Verified', '1' => 'Under Review', '2' => 'Verified', '3' => 'Partial'][$verify_status];
        $_SESSION['success'] = "Verification status updated to '$status_text'.";
    } else {
        $_SESSION['error'] = "Failed to update verification status.";
    }

    $stmt->close();
    header("Location: ../manage-users.php");
    exit();
}

// === FALLBACK ===
else {
    $_SESSION['error'] = "Invalid action.";
    header("Location: ../manage-users.php");
    exit();
}

$con->close();
?>
