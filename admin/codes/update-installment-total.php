<?php
include('../../config/dbcon.php');

$id = $_POST['id'] ?? 0;
$total = $_POST['total'] ?? 1;

if ($total < 1 || $total > 4) {
    echo json_encode(['success' => false, 'message' => 'Invalid total']);
    exit;
}

// Get the current installment_number and email from deposits
$q = mysqli_query($con, "SELECT installment_number, email FROM deposits WHERE id = '$id'");
if (!$q || mysqli_num_rows($q) == 0) {
    echo json_encode(['success' => false, 'message' => 'Deposit not found']);
    exit;
}

$row = mysqli_fetch_assoc($q);
$current = $row['installment_number'];
$email = $row['email'];

// Prevent setting total lower than current paid installment
if ($current > $total) {
    echo json_encode(['success' => false, 'message' => 'Cannot reduce total below current installment']);
    exit;
}

// Update the deposits table
$update_deposits = mysqli_query($con, "UPDATE deposits SET payment_plan = '$total' WHERE id = '$id'");

if (!$update_deposits) {
    echo json_encode(['success' => false, 'message' => 'Failed to update deposit']);
    exit;
}

// Now update the users table if a matching user exists by email
$update_users = mysqli_query($con, "UPDATE users SET payment_plan = '$total' WHERE email = '" . mysqli_real_escape_string($con, $email) . "'");

// Note: It's okay if no user is found (e.g., guest deposit) — we don't treat it as an error

echo json_encode([
    'success' => true,
    'current' => $current,
    'total' => $total
]);
?>
