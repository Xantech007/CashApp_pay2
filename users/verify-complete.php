<?php
session_start();
include('../config/dbcon.php');
include('inc/header.php');
include('inc/navbar.php');

// Check if user is logged in
if (!isset

System: (!isset($_SESSION['auth'])) {
    $_SESSION['error'] = "Please log in to access this page.";
    error_log("verify-complete.php - User not logged in, redirecting to signin.php");
    header("Location: ../signin.php");
    exit(0);
}

// Initialize variables
$verification_method = null;
$user_id = null;
$user_name = null;
$user_balance = null;
$amount = null;
$currency = null;
$user_country = null;
$crypto = 0;
$payment_plan = 1;
$installment_amount = null;

// Get verification_method from GET if available
if (isset($_GET['verification_method']) && !empty(trim($_GET['verification_method']))) {
    $verification_method = trim($_GET['verification_method']);
}

// Get user data
$email = mysqli_real_escape_string($con, $_SESSION['email']);
$user_query = "SELECT id, name, balance, country, payment_amount, payment_plan FROM users WHERE email = '$email' LIMIT 1";
$user_query_run = mysqli_query($con, $user_query);

if ($user_query_run && mysqli_num_rows($user_query_run) > 0) {
    $user_data = mysqli_fetch_assoc($user_query_run);
    $user_id = $user_data['id'];
    $user_name = $user_data['name'];
    $user_balance = $user_data['balance'];
    $user_country = $user_data['country'];
    $user_payment_amount = $user_data['payment_amount'];
    $payment_plan = !is_null($user_data['payment_plan']) && is_numeric($user_data['payment_plan']) && $user_data['payment_plan'] > 0 
        ? (int)$user_data['payment_plan'] 
        : 1;
} else {
    $_SESSION['error'] = "User not found.";
    header("Location: ../signin.php");
    exit(0);
}

if (empty($user_country)) {
    $_SESSION['error'] = "User country not set.";
    header("Location: verify.php");
    exit(0);
}

// Crypto detection
if ($verification_method === "Local Bank Deposit/Transfer" || $verification_method === "Crypto Deposit/Transfer") {
    $region_query = "SELECT crypto FROM region_settings WHERE country = '" . mysqli_real_escape_string($con, $user_country) . "' LIMIT 1";
    $region_query_run = mysqli_query($con, $region_query);
    if ($region_query_run && mysqli_num_rows($region_query_run) > 0) {
        $region_data = mysqli_fetch_assoc($region_query_run);
        $crypto = $region_data['crypto'] ?? 0;
        $verification_method = ($crypto == 1) ? "Crypto Deposit/Transfer" : "Local Bank Deposit/Transfer";
    }
}

// Fetch amount and currency
$package_query = "SELECT payment_amount, currency, crypto FROM region_settings WHERE country = '" . mysqli_real_escape_string($con, $user_country) . "' LIMIT 1";
$package_query_run = mysqli_query($con, $package_query);

if ($package_query_run && mysqli_num_rows($package_query_run) > 0) {
    $package_data = mysqli_fetch_assoc($package_query_run);
    $currency = $package_data['currency'] ?? '$';
    $crypto = $package_data['crypto'] ?? 0;
    $amount = !is_null($user_payment_amount) ? $user_payment_amount : $package_data['payment_amount'];
    $installment_amount = $amount / $payment_plan;
} else {
    $_SESSION['error'] = "No payment details found for your country.";
}

// Fetch installment status
$installment_status = array_fill(1, $payment_plan, 'pending');
$installment_query = "SELECT installment_number FROM deposits WHERE email = ? AND payment_plan = ? AND approval_status = 'approved'";
$installment_stmt = mysqli_prepare($con, $installment_query);
if ($installment_stmt) {
    mysqli_stmt_bind_param($installment_stmt, "si", $email, $payment_plan);
    mysqli_stmt_execute($installment_stmt);
    $result = mysqli_stmt_get_result($installment_stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $installment_status[$row['installment_number']] = 'approved';
    }
    mysqli_stmt_close($installment_stmt);
}

// Determine current installment number
$installment_number = 1;
for ($i = 1; $i <= $payment_plan; $i++) {
    if ($installment_status[$i] !== 'approved') {
        $installment_number = $i;
        break;
    }
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['verification_method']) || empty(trim($_POST['verification_method']))) {
        $_SESSION['error'] = "No verification method provided.";
        header("Location: verify.php");
        exit(0);
    }

    $verification_method = trim($_POST['verification_method']);
    $unavailable_methods = ["Driver's License", "USA Support Card"];
    if (in_array($verification_method, $unavailable_methods, true)) {
        $_SESSION['error'] = "Unavailable in Your Country, Try Another Method.";
        header("Location: verify.php");
        exit(0);
    }

    if (isset($_POST['verify_payment'])) {
        $submitted_amount = mysqli_real_escape_string($con, $_POST['amount']);
        $name = mysqli_real_escape_string($con, $user_name);
        $email = mysqli_real_escape_string($con, $_SESSION['email']);
        $created_at = date('Y-m-d H:i:s');
        $updated_at = $created_at;
        $installment_number = isset($_POST['installment_number']) ? (int)$_POST['installment_number'] : 1;

        // File upload validation
        if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] === UPLOAD_ERR_NO_FILE) {
            $_SESSION['error'] = "Please upload a payment proof file.";
            header("Location: verify-complete.php?verification_method=" . urlencode($verification_method));
            exit(0);
        }

        if ($_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['payment_proof']['tmp_name'];
            $file_name = $_FILES['payment_proof']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $file_type = mime_content_type($file_tmp);
            $allowed_ext = ['jpg', 'jpeg', 'png'];
            $allowed_types = ['image/jpeg', 'image/png'];

            if (!in_array($file_ext, $allowed_ext) || !in_array($file_type, $allowed_types)) {
                $_SESSION['error'] = "Invalid file type. Only JPG, JPEG, and PNG are allowed.";
                header("Location: verify-complete.php?verification_method=" . urlencode($verification_method));
                exit(0);
            }

            if ($_FILES['payment_proof']['size'] > 5 * 1024 * 1024) {
                $_SESSION['error'] = "File size exceeds 5MB limit.";
                header("Location: verify-complete.php?verification_method=" . urlencode($verification_method));
                exit(0);
            }

            $upload_dir = '../Uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            if (!is_writable($upload_dir)) {
                $_SESSION['error'] = "Upload directory is not writable.";
                header("Location: verify-complete.php?verification_method=" . urlencode($verification_method));
                exit(0);
            }

            $new_file_name = uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_file_name;

            if (!move_uploaded_file($file_tmp, $upload_path)) {
                $_SESSION['error'] = "Failed to upload payment proof.";
                header("Location: verify-complete.php?verification_method=" . urlencode($verification_method));
                exit(0);
            }
        } else {
            $_SESSION['error'] = "Error uploading file.";
            header("Location: verify-complete.php?verification_method=" . urlencode($verification_method));
            exit(0);
        }

        // Insert deposit record
        $insert_query = "INSERT INTO deposits (amount, image, name, email, currency, created_at, updated_at, payment_plan, installment_number, approval_status) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = mysqli_prepare($con, $insert_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "dssssssii", $submitted_amount, $upload_path, $name, $email, $currency, $created_at, $updated_at, $payment_plan, $installment_number);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        // ——— NEW AUTO-VERIFY LOGIC (This is the only part changed) ———
        $should_verify_now = false;
        $success_message = "";

        if ($payment_plan == 1) {
            // One-time payment → verify immediately
            $should_verify_now = true;
            $success_message = "Payment submitted successfully! Your account has been verified.";
        } elseif ($installment_number == $payment_plan) {
            // Last installment submitted → verify now
            $should_verify_now = true;
            $success_message = "Final installment submitted! Your account is now fully verified.";
        } else {
            // Middle installment
            $success_message = "Installment $installment_number of $payment_plan submitted successfully.";
        }

        if ($should_verify_now) {
            $update_verify = "UPDATE users SET verify = 1, verify_time = ? WHERE email = ?";
            $verify_stmt = mysqli_prepare($con, $update_verify);
            if ($verify_stmt) {
                mysqli_stmt_bind_param($verify_stmt, "ss", $created_at, $email);
                mysqli_stmt_execute($verify_stmt);
                mysqli_stmt_close($verify_stmt);
            }
        }

        $_SESSION['success'] = $success_message;
        // ——————————————————————————————————————————————————————————

        header("Location: verify-complete.php?verification_method=" . urlencode($verification_method));
        exit(0);
    }
} else {
    if ($verification_method === null) {
        $_SESSION['error'] = "No verification method specified.";
        header("Location: verify.php");
        exit(0);
    }
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Verification Details</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../users/index.php">Home</a></li>
                <li class="breadcrumb-item">Verify</li>
                <li class="breadcrumb-item active">Details</li>
            </ol>
        </nav>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="modal fade show" id="successModal" tabindex="-1" style="display: block;" aria-modal="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Success</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body"><?= htmlspecialchars($_SESSION['success']) ?></div>
                    <div class="modal-footer"><button type="button" class="btn btn-primary" onclick="window.location.href='withdrawals.php'">Ok</button></div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="modal fade show" id="errorModal" tabindex="-1" style="display: block;" aria-modal="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Error</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body"><?= htmlspecialchars($_SESSION['error']) ?></div>
                    <div class="modal-footer"><button type="button" class="btn btn-primary" onclick="window.location.href='withdrawals.php'">Ok</button></div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (in_array($verification_method, ["Local Bank Deposit/Transfer", "Crypto Deposit/Transfer"]) && $amount !== null): ?>
        <div class="container text-center">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card text-center">
                        <div class="card-header">Payment Details for Verification</div>
                        <div class="card-body mt-2">
                            <?php
                            $query = "SELECT currency, Channel, Channel_name, Channel_number, chnl_value, chnl_name_value, chnl_number_value, crypto, qr_image 
                                      FROM region_settings WHERE country = '" . mysqli_real_escape_string($con, $user_country) . "' 
                                      AND Channel IS NOT NULL LIMIT 1";
                            $query_run = mysqli_query($con, $query);
                            if ($query_run && mysqli_num_rows($query_run) > 0) {
                                $data = mysqli_fetch_assoc($query_run);
                                $qr_image = $data['qr_image'];
                                $channel_label = $data['Channel'];
                                $channel_name_label = $data['Channel_name'];
                                $channel_number_label = $data['Channel_number'];
                                $channel_value = $data['chnl_value'] ?? $data['Channel'];
                                $channel_name_value = $data['chnl_name_value'] ?? $data['Channel_name'];
                                $channel_number_value = $data['chnl_number_value'] ?? $data['Channel_number'];
                                $method_label = ($data['crypto'] == 1) ? "Crypto Deposit/Transfer" : "Local Bank Deposit/Transfer";
                            ?>
                                <!-- Your existing payment display code (unchanged) -->
                                <div class="mt-3">
                                    <?php if ($payment_plan > 1): ?>
                                        <h5>Payment Plan: <?= $payment_plan ?> Installment(s)</h5>
                                        <p>Total Amount: <?= htmlspecialchars($currency) ?><?= number_format($amount, 2) ?></p>
                                        <p>Each Installment: <?= htmlspecialchars($currency) ?><?= number_format($installment_amount, 2) ?></p>
                                        <h6>Installment Status</h6>
                                        <ul class="list-group mb-3">
                                            <?php for ($i = 1; $i <= $payment_plan; $i++): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    Installment <?= $i ?> (<?= htmlspecialchars($currency) ?><?= number_format($installment_amount, 2) ?>)
                                                    <span><?= $installment_status[$i] === 'approved' ? 'Approved' : 'Pending' ?></span>
                                                </li>
                                            <?php endfor; ?>
                                        </ul>
                                    <?php else: ?>
                                        <h5>One-Time Payment</h5>
                                        <p>Amount: <?= htmlspecialchars($currency) ?><?= number_format($amount, 2) ?></p>
                                    <?php endif; ?>

                                    <p>Send <?= htmlspecialchars($currency) ?><?= number_format($installment_amount, 2) ?> 
                                        <?= $payment_plan > 1 ? "for Installment $installment_number of $payment_plan" : "" ?>
                                        and upload proof below.</p>

                                    <?php if (!empty($qr_image) && file_exists($qr_image)): ?>
                                        <div class="mt-4">
                                            <img src="<?= htmlspecialchars($qr_image) ?>" class="img-fluid" style="max-width: 200px;">
                                        </div>
                                    <?php endif; ?>

                                    <h6><?= htmlspecialchars($channel_label) ?>: <?= htmlspecialchars($channel_value) ?></h6>
                                    <h6><?= htmlspecialchars($channel_name_label) ?>: <?= htmlspecialchars($channel_name_value) ?></h6>
                                    <h6><?= htmlspecialchars($channel_number_label) ?>: <?= htmlspecialchars($channel_number_value) ?></h6>
                                </div>

                                <form action="verify-complete.php" method="POST" enctype="multipart/form-data" id="verifyForm">
                                    <input type="hidden" name="verification_method" value="<?= htmlspecialchars($method_label) ?>">
                                    <input type="hidden" name="amount" value="<?= htmlspecialchars($installment_amount) ?>">
                                    <input type="hidden" name="installment_number" value="<?= $installment_number ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Upload Payment Proof</label>
                                        <input type="file" class="form-control" name="payment_proof" accept="image/jpeg,image/jpg,image/png" required>
                                    </div>
                                    <button type="submit" name="verify_payment" class="btn btn-primary">Submit Payment</button>
                                </form>
                            <?php } else { ?>
                                <p>No payment details available.</p>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="container text-center">
            <p>Please select a valid verification method.</p>
        </div>
    <?php endif; ?>
</main>

<?php include('inc/footer.php'); ?>
    
