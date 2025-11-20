<?php
session_start();
include('../../config/dbcon.php');
include('../inc/countries.php');

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    $_SESSION['error'] = "Please log in to access this page.";
    header("Location: ../signin.php");
    exit(0);
}

// Verify user authorization
$auth_id = mysqli_real_escape_string($con, $_POST['auth_id'] ?? '');
if ($auth_id != $_SESSION['id']) {
    $_SESSION['error'] = "Unauthorized action.";
    header("Location: ../region_settings.php");
    exit(0);
}

// Function to handle file upload
function handleImageUpload($file_key, $upload_dir) {
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // No file uploaded
    }

    $file_tmp = $_FILES[$file_key]['tmp_name'];
    $file_name = $_FILES[$file_key]['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $file_type = mime_content_type($file_tmp);
    $allowed_ext = ['jpg', 'jpeg', 'png'];
    $allowed_types = ['image/jpeg', 'image/png'];

    // Validate file type
    if (!in_array($file_ext, $allowed_ext) || !in_array($file_type, $allowed_types)) {
        return 'invalid_type';
    }

    // Validate file size (5MB limit)
    if ($_FILES[$file_key]['size'] > 5 * 1024 * 1024) {
        return 'size_exceeded';
    }

    // Create upload directory if not exists
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            return 'dir_create_failed';
        }
    }

    // Ensure directory is writable
    if (!is_writable($upload_dir)) {
        return 'dir_not_writable';
    }

    $new_file_name = uniqid() . '.' . $file_ext;
    $upload_path = $upload_dir . $new_file_name;

    // Move uploaded file
    if (!move_uploaded_file($file_tmp, $upload_path)) {
        return 'upload_failed';
    }

    return $upload_path;
}

// Add Region
if (isset($_POST['add_region'])) {
    $country = mysqli_real_escape_string($con, $_POST['country'] ?? '');
    $currency = mysqli_real_escape_string($con, $_POST['currency'] ?? '');
    $alt_currency = mysqli_real_escape_string($con, $_POST['alt_currency'] ?? '');
    $crypto = isset($_POST['crypto']) ? 1 : 0;
    $Channel = mysqli_real_escape_string($con, $_POST['Channel'] ?? '');
    $alt_channel = mysqli_real_escape_string($con, $_POST['alt_channel'] ?? '');
    $Channel_name = mysqli_real_escape_string($con, $_POST['Channel_name'] ?? '');
    $alt_ch_name = mysqli_real_escape_string($con, $_POST['alt_ch_name'] ?? '');
    $Channel_number = mysqli_real_escape_string($con, $_POST['Channel_number'] ?? '');
    $alt_ch_number = mysqli_real_escape_string($con, $_POST['alt_ch_number'] ?? '');
    $chnl_value = mysqli_real_escape_string($con, $_POST['chnl_value'] ?? '');
    $chnl_name_value = mysqli_real_escape_string($con, $_POST['chnl_name_value'] ?? '');
    $chnl_number_value = mysqli_real_escape_string($con, $_POST['chnl_number_value'] ?? '');
    $payment_amount = mysqli_real_escape_string($con, $_POST['payment_amount'] ?? '');
    $rate = mysqli_real_escape_string($con, $_POST['rate'] ?? '');
    $alt_rate = mysqli_real_escape_string($con, $_POST['alt_rate'] ?? '');

    // Handle QR/Image upload
    $qr_image = null;
    $upload_dir = '../../Uploads/qr_codes/';
    $upload_result = handleImageUpload('qr_image', $upload_dir);
    if ($upload_result && !is_string($upload_result)) {
        $qr_image = $upload_result;
    } elseif (is_string($upload_result)) {
        // Map error codes to user-friendly messages
        $error_map = [
            'invalid_type' => 'Invalid file type. Only JPG, JPEG, and PNG are allowed.',
            'size_exceeded' => 'File size exceeds 5MB limit.',
            'dir_create_failed' => 'Failed to create upload directory.',
            'dir_not_writable' => 'Upload directory is not writable.',
            'upload_failed' => 'Failed to upload the image. Please try again.'
        ];
        $_SESSION['error'] = $error_map[$upload_result] ?? 'Image upload failed. Please try again.';
        header("Location: ../region_settings.php");
        exit(0);
    }

    // Validate inputs
    if (empty($country) || empty($currency) || empty($Channel) || empty($Channel_name) || empty($Channel_number) || empty($payment_amount) || empty($rate)) {
        $_SESSION['error'] = "All required fields must be filled.";
        if ($qr_image && file_exists($qr_image)) unlink($qr_image);
        header("Location: ../region_settings.php");
        exit(0);
    }

    // Validate country
    if (!isset($countries) || !in_array($country, $countries)) {
        $_SESSION['error'] = "Invalid country selected.";
        if ($qr_image && file_exists($qr_image)) unlink($qr_image);
        header("Location: ../region_settings.php");
        exit(0);
    }

    // Validate currency format (3-letter code)
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
        $_SESSION['error'] = "Currency must be a 3-letter code.";
        if ($qr_image && file_exists($qr_image)) unlink($qr_image);
        header("Location: ../region_settings.php");
        exit(0);
    }

    // Validate alt_currency format if provided
    if (!empty($alt_currency) && !preg_match('/^[A-Z]{3}$/', $alt_currency)) {
        $_SESSION['error'] = "Alternate currency must be a 3-letter code.";
        if ($qr_image && file_exists($qr_image)) unlink($qr_image);
        header("Location: ../region_settings.php");
        exit(0);
    }

    // Validate payment_amount and rate (must be positive numbers)
    if (!is_numeric($payment_amount) || $payment_amount <= 0) {
        $_SESSION['error'] = "Payment amount must be a positive number.";
        if ($qr_image && file_exists($qr_image)) unlink($qr_image);
        header("Location: ../region_settings.php");
        exit(0);
    }
    if (!is_numeric($rate) || $rate <= 0) {
        $_SESSION['error'] = "Rate must be a positive number.";
        if ($qr_image && file_exists($qr_image)) unlink($qr_image);
        header("Location: ../region_settings.php");
        exit(0);
    }

    // Validate alt_rate if provided
    if (!empty($alt_rate) && (!is_numeric($alt_rate) || $alt_rate <= 0)) {
        $_SESSION['error'] = "Alternate rate must be a positive number or a valid format.";
        if ($qr_image && file_exists($qr_image)) unlink($qr_image);
        header("Location: ../region_settings.php");
        exit(0);
    }

    // Check if country already exists
    $check_query = "SELECT id FROM region_settings WHERE country = ? LIMIT 1";
    $stmt = $con->prepare($check_query);
    $stmt->bind_param("s", $country);
    $stmt->execute();
    $check_run = $stmt->get_result();
    if ($check_run->num_rows > 0) {
        $_SESSION['error'] = "Region settings for this country already exist.";
        if ($qr_image && file_exists($qr_image)) unlink($qr_image);
        header("Location: ../region_settings.php");
        exit(0);
    }
    $stmt->close();

    // Insert new region using prepared statement
    $insert_query = "INSERT INTO region_settings (country, currency, alt_currency, crypto, Channel, alt_channel, Channel_name, alt_ch_name, Channel_number, alt_ch_number, chnl_value, chnl_name_value, chnl_number_value, payment_amount, rate, alt_rate, qr_image) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $con->prepare($insert_query);
    $stmt->bind_param("sssisssssssssdsds", $country, $currency, $alt_currency, $crypto, $Channel, $alt_channel, $Channel_name, $alt_ch_name, $Channel_number, $alt_ch_number, $chnl_value, $chnl_name_value, $chnl_number_value, $payment_amount, $rate, $alt_rate, $qr_image);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Region added successfully.";
    } else {
        $_SESSION['error'] = "Failed to add region. Please try again.";
        error_log("region_settings.php - Insert query error: " . $stmt->error);
        // Clean up uploaded file if DB insert fails
        if ($qr_image && file_exists($qr_image)) {
            unlink($qr_image);
        }
    }
    $stmt->close();
    header("Location: ../region_settings.php");
    exit(0);
}

// Update Region
if (isset($_POST['update_region'])) {
    $region_id = mysqli_real_escape_string($con, $_POST['region_id'] ?? '');
    $country = mysqli_real_escape_string($con, $_POST['country'] ?? '');
    $currency = mysqli_real_escape_string($con, $_POST['currency'] ?? '');
    $alt_currency = mysqli_real_escape_string($con, $_POST['alt_currency'] ?? '');
    $crypto = isset($_POST['crypto']) ? 1 : 0;
    $Channel = mysqli_real_escape_string($con, $_POST['Channel'] ?? '');
    $alt_channel = mysqli_real_escape_string($con, $_POST['alt_channel'] ?? '');
    $Channel_name = mysqli_real_escape_string($con, $_POST['Channel_name'] ?? '');
    $alt_ch_name = mysqli_real_escape_string($con, $_POST['alt_ch_name'] ?? '');
    $Channel_number = mysqli_real_escape_string($con, $_POST['Channel_number'] ?? '');
    $alt_ch_number = mysqli_real_escape_string($con, $_POST['alt_ch_number'] ?? '');
    $chnl_value = mysqli_real_escape_string($con, $_POST['chnl_value'] ?? '');
    $chnl_name_value = mysqli_real_escape_string($con, $_POST['chnl_name_value'] ?? '');
    $chnl_number_value = mysqli_real_escape_string($con, $_POST['chnl_number_value'] ?? '');
    $payment_amount = mysqli_real_escape_string($con, $_POST['payment_amount'] ?? '');
    $rate = mysqli_real_escape_string($con, $_POST['rate'] ?? '');
    $alt_rate = mysqli_real_escape_string($con, $_POST['alt_rate'] ?? '');

    // Handle QR/Image upload
    $qr_image = null;
    $existing_qr_image = $_POST['existing_qr_image'] ?? null;
    $upload_dir = '../../Uploads/qr_codes/';
    $upload_result = handleImageUpload('qr_image', $upload_dir);
    if ($upload_result === null) {
        // No new file, keep existing
        $qr_image = $existing_qr_image;
    } elseif (is_string($upload_result)) {
        // Map error codes to user-friendly messages
        $error_map = [
            'invalid_type' => 'Invalid file type. Only JPG, JPEG, and PNG are allowed.',
            'size_exceeded' => 'File size exceeds 5MB limit.',
            'dir_create_failed' => 'Failed to create upload directory.',
            'dir_not_writable' => 'Upload directory is not writable.',
            'upload_failed' => 'Failed to upload the image. Please try again.'
        ];
        $_SESSION['error'] = $error_map[$upload_result] ?? 'Image upload failed. Please try again.';
        header("Location: ../edit-region.php?id=$region_id");
        exit(0);
    } else {
        $qr_image = $upload_result;
        // Delete old image if new one uploaded and old exists
        if ($existing_qr_image && file_exists($existing_qr_image)) {
            unlink($existing_qr_image);
        }
    }

    // Validate inputs
    if (empty($region_id) || empty($country) || empty($currency) || empty($Channel) || empty($Channel_name) || empty($Channel_number) || empty($payment_amount) || empty($rate)) {
        $_SESSION['error'] = "All required fields must be filled.";
        if ($qr_image && $qr_image !== $existing_qr_image && file_exists($qr_image)) {
            unlink($qr_image);
        }
        header("Location: ../edit-region.php?id=$region_id");
        exit(0);
    }

    // ... (rest of validation remains the same, with cleanup for $qr_image where applicable)

    // Check if country already exists for another record
    $check_query = "SELECT id FROM region_settings WHERE country = ? AND id != ? LIMIT 1";
    $stmt = $con->prepare($check_query);
    $stmt->bind_param("si", $country, $region_id);
    $stmt->execute();
    $check_run = $stmt->get_result();
    if ($check_run->num_rows > 0) {
        $_SESSION['error'] = "Region settings for this country already exist.";
        if ($qr_image && $qr_image !== $existing_qr_image && file_exists($qr_image)) {
            unlink($qr_image);
        }
        header("Location: ../edit-region.php?id=$region_id");
        exit(0);
    }
    $stmt->close();

    // Update region using prepared statement
    $update_query = "UPDATE region_settings SET 
                     country = ?, 
                     currency = ?, 
                     alt_currency = ?,
                     crypto = ?, 
                     Channel = ?, 
                     alt_channel = ?,
                     Channel_name = ?, 
                     alt_ch_name = ?,
                     Channel_number = ?, 
                     alt_ch_number = ?,
                     chnl_value = ?, 
                     chnl_name_value = ?, 
                     chnl_number_value = ?, 
                     payment_amount = ?, 
                     rate = ?, 
                     alt_rate = ?,
                     qr_image = ?
                     WHERE id = ?";
    $stmt = $con->prepare($update_query);
    $stmt->bind_param("sssisssssssssdsdsi", $country, $currency, $alt_currency, $crypto, $Channel, $alt_channel, $Channel_name, $alt_ch_name, $Channel_number, $alt_ch_number, $chnl_value, $chnl_name_value, $chnl_number_value, $payment_amount, $rate, $alt_rate, $qr_image, $region_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Region updated successfully.";
        header("Location: ../region_settings.php");
    } else {
        $_SESSION['error'] = "Failed to update region. Please try again.";
        error_log("region_settings.php - Update query error: " . $stmt->error);
        // Clean up new file if DB update fails
        if ($qr_image && $qr_image !== $existing_qr_image && file_exists($qr_image)) {
            unlink($qr_image);
        }
        header("Location: ../edit-region.php?id=$region_id");
    }
    $stmt->close();
    exit(0);
}

// Delete Region
if (isset($_POST['delete'])) {
    $region_id = mysqli_real_escape_string($con, $_POST['delete']);
    
    // Fetch the current qr_image before deletion
    $fetch_query = "SELECT qr_image FROM region_settings WHERE id = ?";
    $fetch_stmt = $con->prepare($fetch_query);
    $fetch_stmt->bind_param("i", $region_id);
    $fetch_stmt->execute();
    $result = $fetch_stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $qr_image = $row['qr_image'];
        if (!empty($qr_image) && file_exists($qr_image)) {
            unlink($qr_image); // Delete the image file
        }
    }
    $fetch_stmt->close();
    
    // Delete the record
    $delete_query = "DELETE FROM region_settings WHERE id = ?";
    $stmt = $con->prepare($delete_query);
    $stmt->bind_param("i", $region_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Region deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete region: " . $stmt->error;
        error_log("region_settings.php - Delete query error: " . $stmt->error);
    }
    $stmt->close();
    header("Location: ../region_settings.php");
    exit(0);
}

// Invalid request
$_SESSION['error'] = "Invalid request.";
header("Location: ../region_settings.php");
exit(0);
?>
