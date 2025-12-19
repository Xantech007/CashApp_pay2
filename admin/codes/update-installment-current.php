<?php
session_start();
include('../../config/dbcon.php');

header('Content-Type: application/json');

// === Security: Only allow admins ===
if (!isset($_SESSION['auth']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$id      = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$current = isset($_POST['current']) ? (int)$_POST['current'] : 0;

if ($id <= 0 || $current < 1 || $current > 10) {  // Adjust max as needed
    echo json_encode(['success' => false, 'message' => 'Invalid installment number']);
    exit;
}

// Step 1: Get email and user's current payment_plan
$query = "SELECT d.email, d.installment_number AS old_current, u.payment_plan 
          FROM deposits d
          LEFT JOIN users u ON d.email = u.email
          WHERE d.id = ?";
$stmt = mysqli_prepare($con, $query);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => false, 'message' => 'Deposit not found']);
    exit;
}

$row = mysqli_fetch_assoc($result);
$user_email     = $row['email'];
$user_plan      = (int)($row['payment_plan'] ?? 1);  // Fallback to 1 if null
$old_current    = (int)$row['old_current'];

mysqli_stmt_close($stmt);

// Step 2: Validation - current cannot exceed user's active payment_plan
if ($current > $user_plan) {
    echo json_encode([
        'success' => false,
        'message' => "Cannot set installment $current — user's plan is only $user_plan installment(s)"
    ]);
    exit;
}

// Step 3: Update only the deposit record
$update = "UPDATE deposits SET installment_number = ? WHERE id = ?";
$stmt = mysqli_prepare($con, $update);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare update']);
    exit;
}

mysqli_stmt_bind_param($stmt, "ii", $current, $id);
$executed = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if ($executed) {
    echo json_encode([
        'success' => true,
        'current' => $current,
        'total'   => $user_plan,
        'message' => 'Installment number updated successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update installment number'
    ]);
}
?>
