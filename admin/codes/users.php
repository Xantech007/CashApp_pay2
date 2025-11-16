<?php
session_start();
include('../../config/dbcon.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    error_log("users.php - Invalid request method");
    header("Location: ../manage-users.php");
    exit(0);
}

// === UPDATE USER (Balance, Bonus, Email, Message, Payment Amount, Password) ===
if (isset($_POST['update_user'])) {
    $id = $_POST['update_user'];
    $email = trim($_POST['email']);
    $bonus = $_POST['referal_bonus'];
    $balance = $_POST['balance'];
    $message = $_POST['message'] ?? '';
    $payment_amount = !empty($_POST['payment_amount']) ? $_POST['payment_amount'] : null;
    $new_password = !empty($_POST['password']) ? $_POST['password'] : null;

    // Validate required inputs
    if (empty($id) || empty($email) || !is_numeric($bonus) || !is_numeric($balance)) {
        $_SESSION['error'] = "All required fields must be valid.";
        error_log("users.php - Invalid input for update_user: ID=$id, Email=$email");
        header("Location: ../edit-user.php?id=" . urlencode($id));
        exit(0);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
        error_log("users.php - Invalid email: $email");
        header("Location: ../edit-user.php?id=" . urlencode($id));
        exit(0);
    }

    if ($balance < 0 || $bonus < 0) {
        $_SESSION['error'] = "Balance and referral bonus cannot be negative.";
        error_log("users.php - Negative values: Balance=$balance, Bonus=$bonus");
        header("Location: ../edit-user.php?id=" . urlencode($id));
        exit(0);
    }

    if ($payment_amount !== null && (!is_numeric($payment_amount) || $payment_amount < 0)) {
        $_SESSION['error'] = "Payment amount must be a positive number or left blank.";
        error_log("users.php - Invalid payment_amount: $payment_amount");
        header("Location: ../edit-user.php?id=" . urlencode($id));
        exit(0);
    }

    if ($new_password !== null && strlen($new_password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters long.";
        error_log("users.php - Password too short for user ID: $id");
        header("Location: ../edit-user.php?id=" . urlencode($id));
        exit(0);
    }

    // Begin building dynamic query
    $types = "ddssi";
    $params = [$balance, $bonus, $email, $message, $id];
    $set Clauses = ["balance = ?", "referal_bonus = ?", "email = ?", "message = ?"];

    if ($payment_amount !== null) {
        $set Clauses[] = "payment_amount = ?";
        $params[] = $payment_amount;
        $types .= "d";
    }

    if ($new_password !== null) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $set Clauses[] = "password = ?";
        $params[] = $hashed_password;
        $types .= "s";
    }

    $set_clause = implode(", ", $set Clauses);
    $query = "UPDATE users SET $set_clause WHERE id = ? LIMIT 1";
    array_push($params, $id); // id at the end
    $types .= "i";

    $stmt = $con->prepare($query);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $_SESSION['success'] = "User updated successfully.";
        error_log("users.php - User updated: ID=$id, Email=$email, Balance=$balance, Bonus=$bonus" . 
                  ($payment_amount !== null ? ", PaymentAmount=$payment_amount" : "") . 
                  ($new_password !== null ? ", Password=Updated" : ""));
    } else {
        $_SESSION['error'] = "Failed to update user.";
        error_log("users.php - Update error: " . $stmt->error);
    }
    $stmt->close();
    header("Location: ../edit-user.php?id=" . urlencode($id));
    exit(0);
}

// === DELETE USER ===
elseif (isset($_POST['delete_user'])) {
    $id = $_POST['delete_user'];
    $profile_pic = $_POST['profile_pic'] ?? '';

    if (empty($id) || !is_numeric($id)) {
        $_SESSION['error'] = "Invalid user ID.";
        error_log("users.php - Invalid delete_user ID: $id");
        header("Location: ../manage-users.php");
        exit(0);
    }

    $id = (int)$id;

    $stmt = $con->prepare("DELETE FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if (!empty($profile_pic) && file_exists("../../Uploads/profile-picture/" . $profile_pic)) {
            @unlink("../../Uploads/profile-picture/" . $profile_pic);
        }
        $_SESSION['success'] = "User deleted successfully.";
        error_log("users.php - User deleted: ID=$id");
    } else {
        $_SESSION['error'] = "Failed to delete user.";
        error_log("users.php - Delete error: " . $stmt->error);
    }
    $stmt->close();
    header("Location: ../manage-users.php");
    exit(0);
}

// === UPDATE VERIFICATION STATUS ===
elseif (isset($_POST['update_verify_status'])) {
    $user_id = $_POST['user_id'];
    $verify_status = $_POST['verify_status'];

    if (!is_numeric($user_id) || !in_array($verify_status, ['0', '1', '2', '3'])) {
        $_SESSION['error'] = "Invalid user ID or verification status.";
        error_log("users.php - Invalid verify update: UserID=$user_id, Status=$verify_status");
        header("Location: ../manage-users.php");
        exit(0);
    }

    $user_id = (int)$user_id;
    $verify_status = (int)$verify_status;

    $stmt = $con->prepare("UPDATE users SET verify = ? WHERE id = ? LIMIT 1");
    $stmt->bind_param("ii", $verify_status, $user_id);

    if ($stmt->execute()) {
        $status_text = match ($verify_status) {
            0 => 'Not Verified',
            1 => 'Under Review',
            2 => 'Verified',
            3 => 'Partial',
            default => 'Unknown'
        };
        $_SESSION['success'] = "Verification status updated to '$status_text'.";
        error_log("users.php - Verify status updated: UserID=$user_id, Status=$verify_status");
    } else {
        $_SESSION['error'] = "Failed to update verification status.";
        error_log("users.php - Verify update error: " . $stmt->error);
    }
    $stmt->close();
    header("Location: ../manage-users.php");
    exit(0);
}

// === FALLBACK ===
else {
    $_SESSION['error'] = "Invalid action.";
    error_log("users.php - No valid POST action");
    header("Location: ../manage-users.php");
    exit(0);
}

$con->close();
?>
