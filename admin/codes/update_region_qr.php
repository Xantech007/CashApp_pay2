<?php
session_start();
include('../../config/dbcon.php');

if (isset($_POST['update_qr'])) {

    $region_id = mysqli_real_escape_string($con, $_POST['region_id']);

    if (empty($_FILES['qr_image']['name'])) {
        $_SESSION['error'] = "Please select an image.";
        header("Location: ../edit-region-qr.php?id=$region_id");
        exit();
    }

    $folder = "../../uploads/regions/";
    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }

    $filename = time() . '_' . basename($_FILES['qr_image']['name']);
    $path = $folder . $filename;

    if (!move_uploaded_file($_FILES['qr_image']['tmp_name'], $path)) {
        $_SESSION['error'] = "Failed to upload image.";
        header("Location: ../edit-region-qr.php?id=$region_id");
        exit();
    }

    $query = "UPDATE region_settings SET qr_image='$path' WHERE id='$region_id'";

    if (mysqli_query($con, $query)) {
        $_SESSION['success'] = "QR code updated successfully.";
    } else {
        $_SESSION['error'] = "Database update failed.";
    }

    header("Location: ../region_settings.php");
    exit();
}
