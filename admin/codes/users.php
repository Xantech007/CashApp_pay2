<?php
session_start();
include('../../config/dbcon.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: ../manage-users.php");
    exit();
}

// === UPDATE USER ===
if (isset($_POST['update_user'])) {

    $user_id        = $_POST['user_id'] ?? '';
    $email          = trim($_POST['email'] ?? '');
    $balance        = $_POST['balance'] ?? '';
    $referal_bonus  = $_POST['referal_bonus'] ?? '';
    $message        = $_POST['message'] ?? '';
    $payment_amount = !empty($_POST['payment_amount']) ? floatval($_POST['payment_amount']) : null;
    $new_password   = !empty($_POST['password']) ? trim($_POST['password']) : '';

    // Validation
    if (!is_numeric($user_id) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please check the required fields.";
        header("Location: ../edit_user.php?id=$user_id");
        exit();
    }

    if (!is_numeric($balance) || $balance < 0 || !is_numeric($referal_bonus) || $referal_bonus < 0) {
        $_SESSION['error'] = "Balance and bonus cannot be negative.";
        header("Location: ../edit_user.php?id=$user_id");
        exit();
    }

    if ($payment_amount !== null && $payment_amount < 0) {
        $_SESSION['error'] = "Payment amount cannot be negative.";
        header("Location: ../edit_user.php?id=$user_id");
        exit();
    }

    // Build the query dynamically (compatible with PHP 5.6+)
    $sql = "UPDATE users SET 
            email = ?, 
            balance = ?, 
            referal_bonus = ?, 
            message = ?";

    $params = [$email, $balance, $referal_bonus, $message];
    $types  = "sdds";

    if ($payment_amount !== null) {
        $sql .= ", payment_amount = ?";
        $params[] = $payment_amount;
        $types .= "d";
    } else {
        $sql .= ", payment_amount = NULL";
    }

    if (!empty($new_password)) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $sql .= ", password = ?";
        $params[] = $hashed;
        $types .= "s";
    }

    $sql .= " WHERE id = ? LIMIT 1";
    $params[] = $user_id;
    $types .= "i";

    $stmt = $con->prepare($sql);
    $stmt->bind_param($types, ...$params);   // Works on PHP 7.0+
    // If your server is very old (<7.0), replace the line above with this:
    // call_user_func_array([$stmt, 'bind_param'], array_merge([&$types], array_map(function(&$v){return $v;}, $params)));

    if ($stmt->execute()) {
        $_SESSION['success'] = "User updated successfully.";
    } else {
        $_SESSION['error'] = "Failed to update user.";
    }
    $stmt->close();

    header("Location: ../edit_user.php?id=$user_id");
    exit();
}

// === DELETE USER ===
elseif (isset($_POST['delete_user'])) {
    $id = $_POST['delete_user'] ?? '';
    $profile_pic = $_POST['profile_pic'] ?? '';

    if (!is_numeric($id)) {
        $_SESSION['error'] = "Invalid user ID.";
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

    if (!is_numeric($user_id) || !in_array($verify_status, ['0','1','2','3'], true)) {
        $_SESSION['error'] = "Invalid data.";
        header("Location: ../manage-users.php");
        exit();
    }

    $stmt = $con->prepare("UPDATE users SET verify = ? WHERE id = ? LIMIT 1");
    $stmt->bind_param("ii", $verify_status, $user_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['success'] = "Verification status updated.";
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
