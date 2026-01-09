<?php
session_start();
include('../../config/dbcon.php'); // adjust if needed

if (isset($_POST['update_qr'])) {

    $region_id = mysqli_real_escape_string($con, $_POST['region_id']);

    // Check if a file was uploaded
    if (empty($_FILES['qr_image']['name'])) {
        $_SESSION['error'] = "Please select a QR/image file to upload.";
        header("Location: ../edit-region-qr.php?id=$region_id");
        exit();
    }

    // Relative path for DB
    $db_path = "../Uploads/qr_codes/";

    // Absolute server path (Uploads/ is at same level as admin/)
    $server_path = realpath(__DIR__ . '/../../Uploads/qr_codes/') . '/';

    // Create folder if it doesn't exist
    if (!is_dir($server_path)) {
        if (!mkdir($server_path, 0777, true)) {
            $_SESSION['error'] = "Failed to create folder for uploads.";
            header("Location: ../edit-region-qr.php?id=$region_id");
            exit();
        }
    }

    // Use original filename
    $filename = basename($_FILES['qr_image']['name']);
    $full_server_path = $server_path . $filename;
    $full_db_path = $db_path . $filename;

    // Check for PHP upload errors
    if ($_FILES['qr_image']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "Upload error code: " . $_FILES['qr_image']['error'];
        header("Location: ../edit-region-qr.php?id=$region_id");
        exit();
    }

    // Move uploaded file
    if (!move_uploaded_file($_FILES['qr_image']['tmp_name'], $full_server_path)) {
        $_SESSION['error'] = "Failed to move uploaded file. Check folder permissions: $server_path";
        header("Location: ../edit-region-qr.php?id=$region_id");
        exit();
    }

    // Update DB
    $query = "UPDATE region_settings SET qr_image='$full_db_path' WHERE id='$region_id'";
    if (mysqli_query($con, $query)) {
        $_SESSION['success'] = "QR code/image updated successfully.";
    } else {
        $_SESSION['error'] = "Database update failed: " . mysqli_error($con);
    }

    header("Location: ../region_settings.php");
    exit();
}
