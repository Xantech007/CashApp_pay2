<?php
session_start();
include('../../config/dbcon.php');

function clean($data) {
    return htmlspecialchars(trim($data));
}

/*
|--------------------------------------------------------------------------
| ADD REGION
|--------------------------------------------------------------------------
*/
if (isset($_POST['add_region'])) {

    $country              = clean($_POST['country']);
    $currency             = clean($_POST['currency']);
    $alt_currency         = clean($_POST['alt_currency']);
    $crypto               = isset($_POST['crypto']) ? 1 : 0;
    $Channel              = clean($_POST['Channel']);
    $alt_channel          = clean($_POST['alt_channel']);
    $Channel_name         = clean($_POST['Channel_name']);
    $alt_ch_name          = clean($_POST['alt_ch_name']);
    $Channel_number       = clean($_POST['Channel_number']);
    $alt_ch_number        = clean($_POST['alt_ch_number']);
    $chnl_value           = clean($_POST['chnl_value']);
    $chnl_name_value      = clean($_POST['chnl_name_value']);
    $chnl_number_value    = clean($_POST['chnl_number_value']);
    $payment_amount       = clean($_POST['payment_amount']);
    $rate                 = clean($_POST['rate']);
    $alt_rate             = clean($_POST['alt_rate']);
    $auth_id              = $_POST['auth_id'];

    // Image upload
    $image_path = '';
    if (!empty($_FILES['qr_image']['name'])) {
        $folder = "../../uploads/regions/";
        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        $filename = time() . '_' . $_FILES['qr_image']['name'];
        $image_path = $folder . $filename;

        move_uploaded_file($_FILES['qr_image']['tmp_name'], $image_path);
    }

    $query = "INSERT INTO region_settings 
        (country, currency, alt_currency, crypto, Channel, alt_channel, Channel_name, alt_ch_name,
         Channel_number, alt_ch_number, chnl_value, chnl_name_value, chnl_number_value,
         payment_amount, rate, alt_rate, qr_image)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param(
        $stmt,
        "sssissssssssssdds",
        $country,
        $currency,
        $alt_currency,
        $crypto,
        $Channel,
        $alt_channel,
        $Channel_name,
        $alt_ch_name,
        $Channel_number,
        $alt_ch_number,
        $chnl_value,
        $chnl_name_value,
        $chnl_number_value,
        $payment_amount,
        $rate,
        $alt_rate,
        $image_path
    );

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Region added successfully.";
    } else {
        $_SESSION['error'] = "Failed to add region.";
    }

    header("Location: ../region-settings.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| UPDATE REGION
|--------------------------------------------------------------------------
*/
if (isset($_POST['update_region'])) {

    $id                   = $_POST['id'];
    $country              = clean($_POST['country']);
    $currency             = clean($_POST['currency']);
    $alt_currency         = clean($_POST['alt_currency']);
    $crypto               = isset($_POST['crypto']) ? 1 : 0;
    $Channel              = clean($_POST['Channel']);
    $alt_channel          = clean($_POST['alt_channel']);
    $Channel_name         = clean($_POST['Channel_name']);
    $alt_ch_name          = clean($_POST['alt_ch_name']);
    $Channel_number       = clean($_POST['Channel_number']);
    $alt_ch_number        = clean($_POST['alt_ch_number']);
    $chnl_value           = clean($_POST['chnl_value']);
    $chnl_name_value      = clean($_POST['chnl_name_value']);
    $chnl_number_value    = clean($_POST['chnl_number_value']);
    $payment_amount       = clean($_POST['payment_amount']);
    $rate                 = clean($_POST['rate']);
    $alt_rate             = clean($_POST['alt_rate']);

    // Image update
    if (!empty($_FILES['qr_image']['name'])) {
        $folder = "../../uploads/regions/";
        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        $filename = time() . '_' . $_FILES['qr_image']['name'];
        $image_path = $folder . $filename;
        move_uploaded_file($_FILES['qr_image']['tmp_name'], $image_path);

        $img_sql = ", qr_image='$image_path'";
    } else {
        $img_sql = "";
    }

    $query = "UPDATE region_settings SET
        country='$country',
        currency='$currency',
        alt_currency='$alt_currency',
        crypto='$crypto',
        Channel='$Channel',
        alt_channel='$alt_channel',
        Channel_name='$Channel_name',
        alt_ch_name='$alt_ch_name',
        Channel_number='$Channel_number',
        alt_ch_number='$alt_ch_number',
        chnl_value='$chnl_value',
        chnl_name_value='$chnl_name_value',
        chnl_number_value='$chnl_number_value',
        payment_amount='$payment_amount',
        rate='$rate',
        alt_rate='$alt_rate'
        $img_sql
        WHERE id='$id'";

    if (mysqli_query($con, $query)) {
        $_SESSION['success'] = "Region updated successfully.";
    } else {
        $_SESSION['error'] = "Update failed.";
    }

    header("Location: ../region-settings.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| DELETE REGION
|--------------------------------------------------------------------------
*/
if (isset($_POST['delete'])) {

    $id = $_POST['delete'];

    $query = "DELETE FROM region_settings WHERE id='$id'";
    if (mysqli_query($con, $query)) {
        $_SESSION['success'] = "Region deleted successfully.";
    } else {
        $_SESSION['error'] = "Delete failed.";
    }

    header("Location: ../region-settings.php");
    exit();
}
