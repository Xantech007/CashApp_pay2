<?php
include('../../config/dbcon.php');

$id = $_POST['id'] ?? 0;
$total = $_POST['total'] ?? 1;

if ($total < 1 || $total > 4) {
    echo json_encode(['success' => false]);
    exit;
}

// get current
$q = mysqli_query($con, "SELECT installment_number FROM deposits WHERE id='$id'");
$row = mysqli_fetch_assoc($q);
$current = $row['installment_number'];

if ($current > $total) {
    echo json_encode(['success' => false]);
    exit;
}

$q = mysqli_query($con, "UPDATE deposits SET payment_plan='$total' WHERE id='$id'");

echo json_encode([
    'success' => $q ? true : false,
    'current' => $current
]);
