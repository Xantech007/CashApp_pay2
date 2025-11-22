<?php
include('../../config/dbcon.php');

$id = $_POST['id'] ?? 0;
$current = $_POST['current'] ?? 1;

if ($current < 1 || $current > 4) {
    echo json_encode(['success' => false]);
    exit;
}

// ensure current <= total
$q = mysqli_query($con, "SELECT payment_plan FROM deposits WHERE id='$id'");
$row = mysqli_fetch_assoc($q);
$total = $row['payment_plan'];

if ($current > $total) {
    echo json_encode(['success' => false]);
    exit;
}

$q = mysqli_query($con, "UPDATE deposits SET installment_number='$current' WHERE id='$id'");

echo json_encode([
    'success' => $q ? true : false,
    'total' => $total
]);
