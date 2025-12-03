<?php
session_start();
require_once('../../config/dbcon.php');

// === SECURITY: Prevent direct access & enforce POST only ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    error_log("users.php - Attempted non-POST access from " . $_SERVER['REMOTE_ADDR']);
    header("Location: ../manage-users.php");
    exit();
}

// === OPTIONAL: CSRF Protection (Highly Recommended) ===
// Uncomment and implement get_csrf_token() in a helper if not already done
/*
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error'] = "Invalid CSRF token.";
    error_log("users.php - CSRF token mismatch");
    header("Location: ../manage-users.php");
    exit();
}
*/

// ==================================================================
// 1. UPDATE USER (Balance, Bonus, Email, Password, Message, Payment Amount)
// ==================================================================
if (isset($_POST['update_user'])) {
    $user_id = (int)$_POST['user_id']; // Always use hidden user_id, not button value
    $email = trim($_POST['email'] ?? '');
    $balance = floatval($_POST['balance'] ?? 0);
    $referal_bonus = floatval($_POST['referal_bonus'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    $payment_amount = !empty($_POST['payment_amount']) ? floatval($_POST['payment_amount']) : null;
    $new_password = !empty($_POST['password']) ? trim($_POST['password']) : null;

    // === Validation ===
    if ($user_id <= 0) {
        $_SESSION['error'] = "Invalid user ID.";
        header("Location: ../edit_user.php?id=$user_id");
        exit();
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Valid email is required.";
        header("Location: ../edit_user.php?id=$user_id");
        exit();
    }

    if ($balance < 0 || $referal_bonus < 0) {
        $_SESSION['error'] = "Balance and referral bonus cannot be negative.";
        header("Location: ../edit_user.php?id=$user_id");
        exit();
    }

    if ($payment_amount !== null && $payment_amount < 0) {
        $_SESSION['error'] = "Payment amount cannot be negative.";
        header("Location: ../edit_user.php?id=$user_id");
        exit();
    }

    if ($new_password && strlen($new_password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters.";
        header("Location: ../edit_user.php?id=$user_id");
        exit();
    }

    // === Build Dynamic Query ===
    $fields = [];
    $types = '';
    $params = [];

    $fields[] = "email = ?";
    $types .= "s";
    $params[] = $email;

    $fields[] = "balance = ?";
    $types .= "d";
    $params[] = $balance;

    $fields[] = "referal_bonus = ?";
    $types .= "d";
    $params[] = $referal_bonus;

    $fields[] = "message = ?";
    $types .= "s";
    $params[] = $message;

    if ($payment_amount !== null) {
        $fields[] = "payment_amount = ?";
        $types .= "d";
        $params[] = $payment_amount;
    }

    if ($new_password) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $fields[] = "password = ?";
        $types .= "s";
        $params[] = $hashed_password;
    }

    // Final WHERE
    $fields[] = "id = ?";
    $types .= "i";
    $params[] = $user_id;

    $set_clause = implode(", ", $fields);
    $query = "UPDATE users SET $set_clause WHERE id = ? LIMIT 1";

    $stmt = $con->prepare($query);
    if (!$stmt) {
        error_log("users.php - Prepare failed: " . $con->error);
        $_SESSION['error'] = "Database error occurred.";
        header("Location: ../edit_user.php?id=$user_id");
        exit();
    }

    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $_SESSION['success'] = "User updated successfully.";
        error_log("users.php - User ID $user_id updated successfully by admin");
    } else {
        $_SESSION['error'] = "Failed to update user.";
        error_log("users.php - Update failed for User ID $user_id: " . $stmt->error);
    }

    $stmt->close();
    header("Location: ../edit_user.php?id=$user_id");
    exit();
}

// ==================================================================
// 2. DELETE USER
// ==================================================================
elseif (isset($_POST['delete_user'])) {
    $user_id = (int)$_POST['delete_user'];
    $profile_pic = $_POST['profile_pic'] ?? '';

    if ($user_id <= 0) {
        $_SESSION['error'] = "Invalid user ID for deletion.";
        header("Location: ../manage-users.php");
        exit();
    }

    $stmt = $con->prepare("DELETE FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        // Delete profile picture if exists
        if (!empty($profile_pic)) {
            $path = "../../Uploads/profile-picture/" . basename($profile_pic);
            if (file_exists($path)) {
                unlink($path);
            }
        }

        $_SESSION['success'] = "User deleted successfully.";
        error_log("users.php - User ID $user_id deleted by admin");
    } else {
        $_SESSION['error'] = "Failed to delete user.";
        error_log("users.php - Delete failed for User ID $user_id: " . $stmt->error);
    }

    $stmt->close();
    header("Location: ../manage-users.php");
    exit();
}

// ==================================================================
// 3. UPDATE VERIFICATION STATUS (0=Not, 1=Review, 2=Verified, 3=Partial)
// ==================================================================
elseif (isset($_POST['update_verify_status'])) {
    $user_id = (int)$_POST['user_id'];
    $verify_status = (int)$_POST['verify_status'];

    if ($user_id <= 0 || !in_array($verify_status, [0, 1, 2, 3])) {
        $_SESSION['error'] = "Invalid verification status or user ID.";
        header("Location: ../manage-users.php");
        exit();
    }

    $stmt = $con->prepare("UPDATE users SET verify = ? WHERE id = ? LIMIT 1");
    $stmt->bind_param("ii", $verify_status, $user_id);

    if ($stmt->execute()) {
        $status_map = [0 => 'Not Verified', 1 => 'Under Review', 2 => 'Verified', 3 => 'Partial'];
        $status_text = $status_map[$verify_status] ?? 'Unknown';

        $_SESSION['success'] = "Verification status updated to '$status_text'.";
        error_log("users.php - User ID $user_id verification set to $verify_status ($status_text)");
    } else {
        $_SESSION['error'] = "Failed to update verification status.";
    }

    $stmt->close();
    header("Location: ../manage-users.php");
    exit();
}

// ==================================================================
// 4. FALLBACK - Invalid Action
// ==================================================================
else {
    $_SESSION['error'] = "No valid action specified.";
    error_log("users.php - No valid POST action from " . $_SERVER['REMOTE_ADDR']);
    header("Location: ../manage-users.php");
    exit();
}

// Close connection (optional, PHP closes automatically)
$con->close();
?>
