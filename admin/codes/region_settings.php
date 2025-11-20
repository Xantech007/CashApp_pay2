<?php
session_start();
include('../../config/dbcon.php');
include('../inc/countries.php');

// === SECURITY: Check login & authorization ===
if (!isset($_SESSION['id'])) {
    $_SESSION['error'] = "Please log in to access this page.";
    header("Location: ../signin.php");
    exit(0);
}

// For update/delete: verify auth_id matches session
if (isset($_POST['auth_id']) && $_POST['auth_id'] != $_SESSION['id']) {
    $_SESSION['error'] = "Unauthorized action.";
    header("Location: ../region_settings.php");
    exit(0);
}

// === ROBUST IMAGE UPLOAD FUNCTION ===
function handleImageUpload($file_key, $upload_dir) {
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // No file uploaded
    }

    $file = $_FILES[$file_key];
    $file_tmp = $file['tmp_name'];
    $file_name = $file['name'];
    $file_size = $file['size'];
    $file_error = $file['error'];

    // Log for debugging
    error_log("Upload attempt: $file_name | Size: $file_size | Error code: $file_error");

    if ($file_error !== UPLOAD_ERR_OK) {
        error_log("PHP Upload Error Code: $file_error");
        return 'upload_failed';
    }

    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png'];
    $allowed_mimes = ['image/jpeg', 'image/jpg', 'image/png'];

    // Verify real MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $od($detected_mime = finfo_file($finfo, $file_tmp));
    finfo_close($finfo);

    if (!in_array($file_ext, $allowed_ext) || !in_array($detected_mime, $allowed_mimes)) {
        return 'invalid_type';
    }

    if ($file_size > 5 * 1024 * 1024) { // 5MB
        return 'size_exceeded';
    }

    // Fix path using absolute directory
    $upload_dir = rtrim(realpath($upload_dir) ?: __DIR__ . '/' . $upload_dir, '/') . '/';

    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("Failed to create directory: $upload_dir");
            return 'dir_create_failed';
        }
    }

    if (!is_writable($upload_dir)) {
        error_log("Upload directory not writable: $upload_dir");
        return 'dir_not_writable';
    }

    $new_filename = 'qr_' . uniqid() . '_' . time() . '.' . $file_ext;
    $destination = $upload_dir . $new_filename;

    if (move_uploaded_file($file_tmp, $destination)) {
        error_log("Image uploaded successfully: $destination");
        return $destination;
    } else {
        error_log("move_uploaded_file() failed: $file_tmp → $destination");
        return 'upload_failed';
    }
}

// === ADD NEW REGION ===
if (isset($_POST['add_region'])) {
    $country = trim($_POST['country'] ?? '');
    $currency = strtoupper(trim($_POST['currency'] ?? ''));
    $alt_currency = strtoupper(trim($_POST['alt_currency'] ?? ''));
    $crypto = isset($_POST['crypto']) ? 1 : 0;
    $Channel = trim($_POST['Channel'] ?? '');
    $alt_channel = trim($_POST['alt_channel'] ?? '');
    $Channel_name = trim($_POST['Channel_name'] ?? '');
    $alt_ch_name = trim($_POST['alt_ch_name'] ?? '');
    $Channel_number = trim($_POST['Channel_number'] ?? '');
    $alt_ch_number = trim($_POST['alt_ch_number'] ?? '');
    $chnl_value = trim($_POST['chnl_value'] ?? '');
    $chnl_name_value = trim($_POST['chnl_name_value'] ?? '');
    $chnl_number_value = trim($_POST['chnl_number_value'] ?? '');
    $payment_amount = $_POST['payment_amount'] ?? '';
    $rate = $_POST['rate'] ?? '';
    $alt_rate = trim($_POST['alt_rate'] ?? '');

    // Handle image
    $upload_dir = '../../Uploads/qr_codes/';
    $qr_image = null;
    $upload_result = handleImageUpload('qr_image', $upload_dir);

    if (is_string($upload_result)) {
        $errors = [
            'invalid_type' => 'Invalid file type. Only JPG, JPEG, PNG allowed.',
            'size_exceeded' => 'File too large. Maximum 5MB allowed.',
            'dir_create_failed' => 'Server error: Cannot create upload folder.',
            'dir_not_writable' => 'Server error: Upload folder not writable.',
            'upload_failed' => 'Image upload failed. Please try again.'
        ];
        $_SESSION['error'] = $errors[$upload_result] ?? 'Image upload failed.';
        header("Location: ../region_settings.php");
        exit(0);
    }

    if ($upload_result !== null) {
        $qr_image = $upload_result;
    }

    // Validation
    if (empty($country) || empty($currency) || empty($Channel) || empty($Channel_name) || empty($Channel_number) || empty($payment_amount) || empty($rate)) {
        $_SESSION['error'] = "Please fill all required fields.";
        if ($qr_image) @unlink($qr_image);
        header("Location: ../region_settings.php");
        exit(0);
    }

    if (!isset($countries) || !in_array($country, $countries)) {
        $_SESSION['error'] = "Invalid country selected.";
        if ($qr_image) @unlink($qr_image);
        header("Location: ../region_settings.php");
        exit(0);
    }

    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
        $_SESSION['error'] = "Currency must be 3 capital letters (e.g., USD, NGN).";
        if ($qr_image) @unlink($qr_image);
        header("Location: ../region_settings.php");
        exit(0);
    }

    if (!empty($alt_currency) && !preg_match('/^[A-Z]{3}$/', $alt_currency)) {
        $_SESSION['error'] = "Alt currency must be 3 capital letters.";
        if ($qr_image) @unlink($qr_image);
        header("Location: ../region_settings.php");
        exit(0);
    }

    if (!is_numeric($payment_amount) || $payment_amount <= 0) {
        $_SESSION['error'] = "Payment amount must be a positive number.";
        if ($qr_image) @unlink($qr_image);
        header("Location: ../region_settings.php");
        exit(0);
    }

    if (!is_numeric($rate) || $rate <= 0) {
        $_SESSION['error'] = "Rate must be a positive number.";
        if ($qr_image) @unlink($qr_image);
        header("Location: ../region_settings.php");
        exit(0);
    }

    // Check duplicate country
    $check = $con->prepare("SELECT id FROM region_settings WHERE country = ?");
    $check->bind_param("s", $country);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['error'] = "Region for this country already exists.";
        if ($qr_image) @unlink($qr_image);
        header("Location: ../region_settings.php");
        exit(0);
    }
    $check->close();

    // Insert
    $stmt = $con->prepare("INSERT INTO region_settings 
        (country, currency, alt_currency, crypto, Channel, alt_channel, Channel_name, alt_ch_name, 
         Channel_number, alt_ch_number, chnl_value, chnl_name_value, chnl_number_value, 
         payment_amount, rate, alt_rate, qr_image) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("sssisssssssssdssd", $country, $currency, $alt_currency, $crypto, 
        $Channel, $alt_channel, $Channel_name, $alt_ch_name, $Channel_number, $alt_ch_number, 
        $chnl_value, $chnl_name_value, $chnl_number_value, $payment_amount, $rate, $alt_rate, $qr_image);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Region added successfully.";
    } else {
        $_SESSION['error'] = "Failed to add region.";
        error_log("Add region failed: " . $stmt->error);
        if ($qr_image) @unlink($qr_image);
    }
    $stmt->close();
    header("Location: ../region_settings.php");
    exit(0);
}

// === UPDATE REGION ===
if (isset($_POST['update_region'])) {
    $region_id = (int)($_POST['region_id'] ?? 0);
    $existing_qr_image = $_POST['existing_qr_image'] ?? null;

    // Fetch current record to validate
    $fetch = $con->prepare("SELECT country, qr_image FROM region_settings WHERE id = ?");
    $fetch->bind_param("i", $region_id);
    $fetch->execute();
    $result = $fetch->get_result();
    if (!$result->num_rows) {
        $_SESSION['error'] = "Region not found.";
        header("Location: ../region_settings.php");
        exit(0);
    }
    $current = $result->fetch_assoc();
    $fetch->close();

    // Same field processing as add...
    $country = trim($_POST['country'] ?? '');
    $currency = strtoupper(trim($_POST['currency'] ?? ''));
    $alt_currency = strtoupper(trim($_POST['alt_currency'] ?? ''));
    $crypto = isset($_POST['crypto']) ? 1 : 0;
    $Channel = trim($_POST['Channel'] ?? '');
    $alt_channel = trim($_POST['alt_channel'] ?? '');
    $Channel_name = trim($_POST['Channel_name'] ?? '');
    $alt_ch_name = trim($_POST['alt_ch_name'] ?? '');
    $Channel_number = trim($_POST['Channel_number'] ?? '');
    $alt_ch_number = trim($_POST['alt_ch_number'] ?? '');
    $chnl_value = trim($_POST['chnl_value'] ?? '');
    $chnl_name_value = trim($_POST['chnl_name_value'] ?? '');
    $chnl_number_value = trim($_POST['chnl_number_value'] ?? '');
    $payment_amount = $_POST['payment_amount'] ?? '';
    $rate = $_POST['rate'] ?? '';
    $alt_rate = trim($_POST['alt_rate'] ?? '');

    $upload_dir = '../../Uploads/qr_codes/';
    $qr_image = $existing_qr_image;

    $upload_result = handleImageUpload('qr_image', $upload_dir);

    if (is_string($upload_result)) {
        $errors = [
            'invalid_type' => 'Invalid file type. Only JPG, JPEG, PNG allowed.',
            'size_exceeded' => 'File too large (max 5MB).',
            'upload_failed' => 'Image upload failed. Please try again.'
        ];
        $_SESSION['error'] = $errors[$upload_result] ?? 'Image upload failed.';
        header("Location: ../edit-region.php?id=$region_id");
        exit(0);
    }

    if ($upload_result !== null) {
        $qr_image = $upload_result;
        // Delete old image
        if ($existing_qr_image && file_exists($existing_qr_image) && $existing_qr_image !== $qr_image) {
            @unlink($existing_qr_image);
        }
    }

    // Validation (same as add, but allow same country)
    if (empty($country) || empty($currency) || empty($Channel) || empty($Channel_name) || empty($Channel_number)) {
        $_SESSION['error'] = "Please fill all required fields.";
        if ($qr_image !== $existing_qr_image && $qr_image) @unlink($qr_image);
        header("Location: ../edit-region.php?id=$region_id");
        exit(0);
    }

    // Check duplicate country (excluding current record)
    $check = $con->prepare("SELECT id FROM region_settings WHERE country = ? AND id != ?");
    $check->bind_param("si", $country, $region_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['error'] = "Another region already uses this country.";
        if ($qr_image !== $existing_qr_image && $qr_image) @unlink($qr_image);
        header("Location: ../edit-region.php?id=$region_id");
        exit(0);
    }
    $check->close();

    // Update
    $stmt = $con->prepare("UPDATE region_settings SET 
        country=?, currency=?, alt_currency=?, crypto=?, Channel=?, alt_channel=?, 
        Channel_name=?, alt_ch_name=?, Channel_number=?, alt_ch_number=?, 
        chnl_value=?, chnl_name_value=?, chnl_number_value=?, 
        payment_amount=?, rate=?, alt_rate=?, qr_image=? 
        WHERE id=?");

    $stmt->bind_param("sssisssssssssdssdi", $country, $currency, $alt_currency, $crypto,
        $Channel, $alt_channel, $Channel_name, $alt_ch_name, $Channel_number, $alt_ch_number,
        $chnl_value, $chnl_name_value, $chnl_number_value, $payment_amount, $rate, $alt_rate, $qr_image, $region_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Region updated successfully.";
        header("Location: ../region_settings.php");
    } else {
        $_SESSION['error'] = "Failed to update region.";
        if ($qr_image !== $existing_qr_image && $qr_image) @unlink($qr_image);
        header("Location: ../edit-region.php?id=$region_id");
    }
    $stmt->close();
    exit(0);
}

// === DELETE REGION ===
if (isset($_POST['delete'])) {
    $region_id = (int)$_POST['delete'];

    $stmt = $con->prepare("SELECT qr_image FROM region_settings WHERE id = ?");
    $stmt->bind_param("i", $region_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if (!empty($row['qr_image']) && file_exists($row['qr_image'])) {
            @unlink($row['qr_image']);
        }
    }
    $stmt->close();

    $delete = $con->prepare("DELETE FROM region_settings WHERE id = ?");
    $delete->bind_param("i", $region_id);
    $delete->execute() ?
        $_SESSION['success'] = "Region deleted successfully." :
        $_SESSION['error'] = "Failed to delete region.";
    $delete->close();

    header("Location: ../region_settings.php");
    exit(0);
}

// === INVALID REQUEST ===
$_SESSION['error'] = "Invalid request.";
header("Location: ../region_settings.php");
exit(0);
?>
