<?php
session_start();
include('../../config/dbcon.php'); // make sure this path is correct

if (isset($_POST['update_qr'])) {

    // Get region ID
    $region_id = mysqli_real_escape_string($con, $_POST['region_id']);

    // Check if file is uploaded
    if (empty($_FILES['qr_image']['name'])) {
        $_SESSION['error'] = "Please select a QR/image file to upload.";
        header("Location: ../edit-region-qr.php?id=$region_id");
        exit();
    }

    // Relative path to be stored in the database
    $db_path = "../Uploads/qr_codes/";

    // Absolute server path to save the file
    $server_path = dirname(__DIR__) . "/Uploads/qr_codes/";

    // Create directory if it doesn't exist
    if (!is_dir($server_path)) {
        mkdir($server_path, 0777, true);
    }

    // Generate unique filename
    $filename = time() . '_' . basename($_FILES['qr_image']['name']);

    // Full server path to move the uploaded file
    $full_server_path = $server_path . $filename;

    // Full path to store in database
    $full_db_path = $db_path . $filename;

    // Move the uploaded file to server
    if (!move_uploaded_file($_FILES['qr_image']['tmp_name'], $full_server_path)) {
        $_SESSION['error'] = "Failed to upload QR/image file.";
        header("Location: ../edit-region-qr.php?id=$region_id");
        exit();
    }

    // Update the region record with new QR path
    $query = "UPDATE region_settings SET qr_image='$full_db_path' WHERE id='$region_id'";
    if (mysqli_query($con, $query)) {
        $_SESSION['success'] = "QR code/image updated successfully.";
    } else {
        $_SESSION['error'] = "Database update failed: " . mysqli_error($con);
    }

    header("Location: ../region_settings.php");
    exit();
}
?>
