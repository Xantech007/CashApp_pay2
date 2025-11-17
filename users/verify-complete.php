<?php
session_start();
include('../config/dbcon.php');
include('inc/header.php');
include('inc/navbar.php');

// Check if user is logged in
if (!isset($_SESSION['auth'])) {
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

// Debug session and request method
error_log("verify-complete.php - Session email: " . ($_SESSION['email'] ?? 'not set'));
error_log("verify-complete.php - Request method: {$_SERVER['REQUEST_METHOD']}");

// Get verification_method from GET if available
if (isset($_GET['verification_method']) && !empty(trim($_GET['verification_method']))) {
    $verification_method = trim($_GET['verification_method']);
}

// Get user data from users table
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

    // Use payment_plan from users table
    $payment_plan = !is_null($user_data['payment_plan']) && is_numeric($user_data['payment_plan']) && $user_data['payment_plan'] > 0
        ? (int)$user_data['payment_plan']
        : 1;

    error_log("verify-complete.php - Payment plan fetched from users table: $payment_plan");
} else {
    $_SESSION['error'] = "User not found.";
    error_log("verify-complete.php - User not found for email: $email");
    header("Location: ../signin.php");
    exit(0);
}

// Check if user_country is set
if (empty($user_country)) {
    $_SESSION['error'] = "User country not set.";
    error_log("verify-complete.php - User country is empty for email: $email");
    header("Location: verify.php");
    exit(0);
}

// Fetch crypto setting from region_settings
if ($verification_method === "Local Bank Deposit/Transfer" || $verification_method === "Crypto Deposit/Transfer") {
    $region_query = "SELECT crypto FROM region_settings WHERE country = '" . mysqli_real_escape_string($con, $user_country) . "' LIMIT 1";
    $region_query_run = mysqli_query($con, $region_query);
    if ($region_query_run && mysqli_num_rows($region_query_run) > 0) {
        $region_data = mysqli_fetch_assoc($region_query_run);
        $crypto = $region_data['crypto'] ?? 0;
        $verification_method = ($crypto == 1) ? "Crypto Deposit/Transfer" : "Local Bank Deposit/Transfer";
    }
}

// Fetch amount and currency from region_settings
$package_query = "SELECT payment_amount, currency, crypto FROM region_settings WHERE country = '" . mysqli_real_escape_string($con, $user_country) . "' LIMIT 1";
$package_query_run = mysqli_query($con, $package_query);

if ($package_query_run && mysqli_num_rows($package_query_run) > 0) {
    $package_data = mysqli_fetch_assoc($package_query_run);
    $currency = $package_data['currency'] ?? '$';
    $crypto = $package_data['crypto'] ?? 0;
    $amount = !is_null($user_payment_amount) ? $user_payment_amount : $package_data['payment_amount'];
    $installment_amount = $amount / $payment_plan;

    error_log("verify-complete.php - Payment details: amount={$amount}, currency={$currency}, crypto={$crypto}, payment_plan={$payment_plan}, installment_amount={$installment_amount}");
} else {
    $_SESSION['error'] = "No payment details found for your country.";
    error_log("verify-complete.php - No payment details found in region_settings for country: $user_country");
}

// Fetch installment status
$installment_status = array_fill(1, $payment_plan, 'pending');
$installment_query = "SELECT installment_number, approval_status
                     FROM deposits
                     WHERE email = ? AND payment_plan = ?
                     ORDER BY installment_number";
$installment_stmt = mysqli_prepare($con, $installment_query);
if ($installment_stmt) {
    mysqli_stmt_bind_param($installment_stmt, "si", $email, $payment_plan);
    mysqli_stmt_execute($installment_stmt);
    $result = mysqli_stmt_get_result($installment_stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        if ($row['approval_status'] === 'approved') {
            $installment_status[$row['installment_number']] = 'approved';
        }
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

$has_approved_deposit = in_array('approved', $installment_status);

// ==================== HANDLE POST REQUEST ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("verify-complete.php - POST data: " . print_r($_POST, true));
    error_log("verify-complete.php - FILES data: " . print_r($_FILES, true));

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
        $email_escaped = mysqli_real_escape_string($con, $_SESSION['email']);
        $created_at = date('Y-m-d H:i:s');
        $installment_number = isset($_POST['installment_number']) ? (int)$_POST['installment_number'] : 1;

        // Re-fetch currency
        $currency_query = "SELECT currency FROM region_settings WHERE country = '" . mysqli_real_escape_string($con, $user_country) . "' LIMIT 1";
        $currency_result = mysqli_query($con, $currency_query);
        if ($currency_result && mysqli_num_rows($currency_result) > 0) {
            $currency = mysqli_fetch_assoc($currency_result)['currency'] ?? '$';
        }

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
        $success = false;

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "dssssssii", $submitted_amount, $upload_path, $name, $email_escaped, $currency, $created_at, $created_at, $payment_plan, $installment_number);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        if ($success) {
            // === NEW VERIFICATION LOGIC ===
            $verify_now = false;

            if ($payment_plan == 1) {
                // One-time payment → verify immediately
                $verify_now = true;
            } elseif (in_array($payment_plan, [2, 4]) && $installment_number == $payment_plan) {
                // Last installment of 2 or 4 → verify now
                $verify_now = true;
            }

            if ($verify_now) {
                $update_verify = "UPDATE users SET verify = 1, verify_time = ? WHERE email = ?";
                $update_stmt = mysqli_prepare($con, $update_verify);
                if ($update_stmt) {
                    mysqli_stmt_bind_param($update_stmt, "ss", $created_at, $email_escaped);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);
                    error_log("verify-complete.php - User verified (verify=1) on submission: $email_escaped");
                }
            }

            // Success message
            if ($payment_plan > 1) {
                $_SESSION['success'] = "Payment submitted for installment $installment_number of $payment_plan.";
                if ($verify_now) {
                    $_SESSION['success'] .= " Your account is now verified!";
                } else {
                    $_SESSION['success'] .= " Awaiting approval.";
                }
            } else {
                $_SESSION['success'] = "Payment submitted and your account has been verified!";
            }
        } else {
            $_SESSION['error'] = "Failed to submit payment. Please try again.";
        }

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

<!-- HTML Content remains exactly the same -->
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
                    <div class="modal-header"><h5 class="modal-title">Success</h5></div>
                    <div class="modal-body"><?= htmlspecialchars($_SESSION['success']) ?></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" onclick="window.location.href='withdrawals.php'">OK</button>
                    </div>
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
                    <div class="modal-header"><h5 class="modal-title">Error</h5></div>
                    <div class="modal-body"><?= htmlspecialchars($_SESSION['error']) ?></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" onclick="window.location.href='withdrawals.php'">OK</button>
                    </div>
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
                                      FROM region_settings WHERE country = ? AND Channel IS NOT NULL LIMIT 1";
                            $stmt = mysqli_prepare($con, $query);
                            mysqli_stmt_bind_param($stmt, "s", $user_country);
                            mysqli_stmt_execute($stmt);
                            $result = mysqli_stmt_get_result($stmt);
                            if ($result && mysqli_num_rows($result) > 0):
                                $data = mysqli_fetch_assoc($result);
                                $qr_image = $data['qr_image'];
                                $method_label = ($data['crypto'] == 1) ? "Crypto Deposit/Transfer" : "Local Bank Deposit/Transfer";
                                ?>
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
                                                    <span>
                                                        <?php if ($installment_status[$i] === 'approved'): ?>
                                                            Approved
                                                        <?php else: ?>
                                                            Pending
                                                        <?php endif; ?>
                                                    </span>
                                                </li>
                                            <?php endfor; ?>
                                        </ul>
                                    <?php else: ?>
                                        <h5>Payment Plan: One-Time Payment</h5>
                                        <p>Total Amount: <?= htmlspecialchars($currency) ?><?= number_format($amount, 2) ?></p>
                                    <?php endif; ?>

                                    <p>
                                        Send <?= htmlspecialchars($currency) ?><?= number_format($installment_amount, 2) ?>
                                        <?php if ($payment_plan > 1): ?> for Installment <?= $installment_number ?> of <?= $payment_plan ?><?php endif; ?>
                                        to the <?= htmlspecialchars($method_label) ?> details below.
                                    </p>

                                    <?php if (!empty($qr_image) && file_exists($qr_image)): ?>
                                        <div class="mt-4">
                                            <?php if ($data['crypto'] == 1): ?>
                                                <h6>Scan QR Code</h6>
                                            <?php endif; ?>
                                            <img src="<?= htmlspecialchars($qr_image) ?>" class="img-fluid" style="max-width: 220px; border: 1px solid #ddd; border-radius: 8px;">
                                        </div>
                                    <?php endif; ?>

                                    <h6><?= htmlspecialchars($data['Channel']) ?>: <?= htmlspecialchars($data['chnl_value'] ?? $data['Channel']) ?></h6>
                                    <h6><?= htmlspecialchars($data['Channel_name']) ?>: <?= htmlspecialchars($data['chnl_name_value'] ?? $data['Channel_name']) ?></h6>
                                    <h6><?= htmlspecialchars($data['Channel_number']) ?>: <?= htmlspecialchars($data['chnl_number_value'] ?? $data['Channel_number']) ?></h6>
                                </div>

                                <div class="mt-4">
                                    <form action="" method="POST" enctype="multipart/form-data" id="verifyForm">
                                        <input type="hidden" name="verification_method" value="<?= htmlspecialchars($method_label) ?>">
                                        <input type="hidden" name="amount" value="<?= $installment_amount ?>">
                                        <input type="hidden" name="installment_number" value="<?= $installment_number ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Upload Payment Proof (JPG/PNG)</label>
                                            <input type="file" class="form-control" name="payment_proof" accept="image/jpeg,image/jpg,image/png" required>
                                        </div>
                                        <button type="submit" name="verify_payment" class="btn btn-primary">Submit Payment</button>
                                        <?php if (!$has_approved_deposit): ?>
                                            <a href="part-payment.php?verification_method=<?= urlencode($method_label) ?>" class="btn btn-warning ms-2">Change Plan</a>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            <?php else: ?>
                                <p>No payment details available. Contact support.</p>
                            <?php endif; ?>
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

<script>
document.getElementById('verifyForm')?.addEventListener('submit', function(e) {
    const file = document.querySelector('[name="payment_proof"]').files[0];
    if (!file) {
        e.preventDefault();
        alert('Please upload a payment proof.');
    }
});
</script>

<?php include('inc/footer.php'); ?>
