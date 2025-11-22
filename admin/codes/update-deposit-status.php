<?php
include('../../config/dbcon.php');

$id = $_POST['id'] ?? 0;
$status = $_POST['status'] ?? 'pending';

$allowed = ['pending','approved','rejected'];
if (!in_array($status, $allowed)) {
    echo json_encode(['success' => false]);
    exit;
}

$q = mysqli_query($con, "UPDATE deposits SET approval_status='$status' WHERE id='$id'");

echo json_encode(['success' => $q ? true : false]);
