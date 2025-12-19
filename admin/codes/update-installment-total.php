<?php
session_start();
include('../../config/dbcon.php');

header('Content-Type: application/json');

if (!isset($_SESSION['auth']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$total = isset($_POST['total']) ? (int)$_POST['total'] : 0;

if ($id <= 0 || $total < 1 || $total > 10) { // adjust max as needed
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

// First, get the email from the deposit
$query = "SELECT email, installment_number FROM deposits WHERE id = ?";
$stmt = mysqli_prepare($con, $query);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Deposit not found']);
    exit;
}

$deposit = mysqli_fetch_assoc($result);
$email = $deposit['email'];
$current_installment = $deposit['installment_number'];

mysqli_stmt_close($stmt);

// Prevent setting total less than already completed installments
if ($total < $current_installment) {
    echo json_encode([
        'success' => false,
        'message' => "Total cannot be less than current installment ($current_installment)"
    ]);
    exit;
}

// Update the payment_plan in the users table
$update_user = "UPDATE users SET payment_plan = ? WHERE email = ?";
$stmt_user = mysqli_prepare($con, $update_user);
if (!$stmt_user) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare user update']);
    exit;
}

mysqli_stmt_bind_param($stmt_user, "is", $total, $email);
$user_updated = mysqli_stmt_execute($stmt_user);
mysqli_stmt_close($stmt_user);

if (!$user_updated) {
    echo json_encode(['success' => false, 'message' => 'Failed to update user payment plan']);
    exit;
}

// Optional: Also update this specific deposit's payment_plan for consistency
$update_deposit = "UPDATE deposits SET payment_plan = ? WHERE id = ?";
$stmt_deposit = mysqli_prepare($con, $update_deposit);
mysqli_stmt_bind_param($stmt_deposit, "ii", $total, $id);
mysqli_stmt_execute($stmt_deposit);
mysqli_stmt_close($stmt_deposit);
// (Ignore errors here if you don't care about old deposits)

echo json_encode([
    'success' => true,
    'total' => $total,
    'current' => $current_installment,
    'message' => 'Payment plan updated successfully for user'
]);
?>
