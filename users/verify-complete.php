<?php
session_start();
include('../config/dbcon.php');
include('inc/header.php');
include('inc/navbar.php');

// Check if user is logged in
if (!isset($_SESSION['auth'])) {
    $_SESSION['error'] = "Please log in to access this page.";
    error_log("verify-complete.php - User not logged in");
    header("Location: ../signin.php");
    exit(0);
}

// ——— FIXED: Properly detect verification_method from GET or POST ———
$verification_method = null;

// 1. From URL (GET) - when page loads or redirected back
if (isset($_GET['verification_method']) && !empty(trim($_GET['verification_method']))) {
    $verification_method = trim($_GET['verification_method']);
}

// 2. From form (POST) - during submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verification_method'])) {
    $verification_method = trim($_POST['verification_method']);
}

// 3. If still missing → redirect to selection
if ($verification_method === null) {
    $_SESSION['error'] = "No verification method specified.";
    error_log("verify-complete.php - No verification method found in GET or POST");
    header("Location: verify.php");
    exit(0);
}
// ————————————————————————————————————————————————————————————————

$email = mysqli_real_escape_string($con, $_SESSION['email']);

// Fetch user data
$user_query = "SELECT id, name, balance, country, payment_amount, payment_plan FROM users WHERE email = ? LIMIT 1";
$stmt = mysqli_prepare($con, $user_query);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = "User not found.";
    header("Location: ../signin.php");
    exit(0);
}

$user_data = mysqli_fetch_assoc($result);
$user_id = $user_data['id'];
$user_name = $user_data['name'];
$user_balance = $user_data['balance'];
$user_country = $user_data['country'];
$user_payment_amount = $user_data['payment_amount'];

$payment_plan = (!empty($user_data['payment_plan']) && in_array($user_data['payment_plan'], [1,2,4])) 
    ? (int)$user_data['payment_plan'] 
    : 1;

mysqli_stmt_close($stmt);

if (empty($user_country)) {
    $_SESSION['error'] = "User country not set.";
    header("Location: verify.php");
    exit(0);
}

// Normalize verification method based on country crypto setting
$crypto = 0;
$region_query = "SELECT crypto FROM region_settings WHERE country = ? LIMIT 1";
$stmt = mysqli_prepare($con, $region_query);
mysqli_stmt_bind_param($stmt, "s", $user_country);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if (mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $crypto = $row['crypto'] ?? 0;
}
mysqli_stmt_close($stmt);

if ($verification_method === "Local Bank Deposit/Transfer" && $crypto == 1) {
    $verification_method = "Crypto Deposit/Transfer";
} elseif ($verification_method === "Crypto Deposit/Transfer" && $crypto == 0) {
    $verification_method = "Local Bank Deposit/Transfer";
}

// Get payment amount and currency
$package_query = "SELECT payment_amount, currency FROM region_settings WHERE country = ? LIMIT 1";
$stmt = mysqli_prepare($con, $package_query);
mysqli_stmt_bind_param($stmt, "s", $user_country);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$amount = $user_payment_amount;
$currency = '$';

if (mysqli_num_rows($result) > 0) {
    $pkg = mysqli_fetch_assoc($result);
    $currency = $pkg['currency'] ?? '$';
    if (is_null($user_payment_amount)) {
        $amount = $pkg['payment_amount'];
    }
}
mysqli_stmt_close($stmt);

$installment_amount = $amount / $payment_plan;

// Get current installment status
$installment_status = array_fill(1, $payment_plan, 'pending');
$query = "SELECT installment_number, approval_status FROM deposits WHERE email = ? AND payment_plan = ? ORDER BY installment_number";
$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, "si", $email, $payment_plan);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    if ($row['approval_status'] === 'approved') {
        $installment_status[$row['installment_number']] = 'approved';
    }
}
mysqli_stmt_close($stmt);

// Determine next installment
$installment_number = 1;
for ($i = 1; $i <= $payment_plan; $i++) {
    if ($installment_status[$i] !== 'approved') {
        $installment_number = $i;
        break;
    }
}

$has_approved_deposit = in_array('approved', $installment_status);

// ——— HANDLE PAYMENT SUBMISSION ———
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment'])) {

    $submitted_amount = $_POST['amount'];
    $installment_number = (int)$_POST['installment_number'];
    $created_at = date('Y-m-d H:i:s');

    // File upload validation
    if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "Please upload a valid payment proof.";
        header("Location: verify-complete.php?verification_method=" . urlencode($verification_method));
        exit(0);
    }

    $file_tmp = $_FILES['payment_proof']['tmp_name'];
    $file_name = $_FILES['payment_proof']['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png'];

    if (!in_array($file_ext, $allowed)) {
        $_SESSION['error'] = "Only JPG, JPEG, PNG files are allowed.";
        header("Location: verify-complete.php?verification_method=" . urlencode($verification_method));
        exit(0);
    }

    $upload_dir = '../Uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $new_file_name = uniqid('proof_') . '.' . $file_ext;
    $upload_path = $upload_dir . $new_file_name;

    if (!move_uploaded_file($file_tmp, $upload_path)) {
        $_SESSION['error'] = "Failed to upload proof.";
        header("Location: verify-complete.php?verification_method=" . urlencode($verification_method));
        exit(0);
    }

    // Insert deposit record
    $insert = "INSERT INTO deposits (amount, image, name, email, currency, created_at, updated_at, payment_plan, installment_number, approval_status) 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    $stmt = mysqli_prepare($con, $insert);
    mysqli_stmt_bind_param($stmt, "dssssssii", $submitted_amount, $upload_path, $user_name, $email, $currency, $created_at, $created_at, $payment_plan, $installment_number);

    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        // ——— VERIFICATION LOGIC (YOUR REQUEST) ———
        $should_verify_now = false;

        if ($payment_plan == 1) {
            // One-time payment → verify immediately
            $should_verify_now = true;
        } elseif (in_array($payment_plan, [2, 4])) {
            // Only verify when LAST installment is submitted
            if ($installment_number == $payment_plan) {
                $should_verify_now = true;
            }
        }

        if ($should_verify_now) {
            $update_verify = "UPDATE users SET verify = 1, verify_time = ? WHERE email = ?";
            $vstmt = mysqli_prepare($con, $update_verify);
            mysqli_stmt_bind_param($vstmt, "ss", $created_at, $email);
            mysqli_stmt_execute($vstmt);
            mysqli_stmt_close($vstmt);

            $_SESSION['success'] = "Payment completed! Your account is now verified.";
            error_log("verify-complete.php - Account VERIFIED for $email (full payment done)");
        } else {
            $_SESSION['success'] = $payment_plan > 1
                ? "Installment $installment_number of $payment_plan submitted successfully."
                : "Payment submitted successfully. Awaiting approval.";
        }
    } else {
        $_SESSION['error'] = "Failed to submit payment.";
    }

    // Preserve verification method in redirect
    header("Location: verify-complete.php?verification_method=" . urlencode($verification_method));
    exit(0);
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

    <!-- Success / Error Modals -->
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
                                      FROM region_settings WHERE country = ? AND Channel IS NOT NULL LIMIT 1";
                            $stmt = mysqli_prepare($con, $query);
                            mysqli_stmt_bind_param($stmt, "s", $user_country);
                            mysqli_stmt_execute($stmt);
                            $result = mysqli_stmt_get_result($stmt);
                            if ($row = mysqli_fetch_assoc($result)) {
                                $qr_image = $row['qr_image'] ?? '';
                                $channel_label = $row['Channel'];
                                $channel_name_label = $row['Channel_name'];
                                $channel_number_label = $row['Channel_number'];
                                $channel_value = $row['chnl_value'] ?? $row['Channel'];
                                $channel_name_value = $row['chnl_name_value'] ?? $row['Channel_name'];
                                $channel_number_value = $row['chnl_number_value'] ?? $row['Channel_number'];
                                $method_label = ($row['crypto'] == 1) ? "Crypto Deposit/Transfer" : "Local Bank Deposit/Transfer";
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
                                        <h5>One-Time Payment</h5>
                                        <p>Total Amount: <?= htmlspecialchars($currency) ?><?= number_format($amount, 2) ?></p>
                                    <?php endif; ?>

                                    <p>Send <?= htmlspecialchars($currency) ?><?= number_format($installment_amount, 2) ?> 
                                        <?php if ($payment_plan > 1): ?>for Installment <?= $installment_number ?> of <?= $payment_plan ?><?php endif; ?>
                                        to the details below:</p>

                                    <?php if (!empty($qr_image) && file_exists($qr_image)): ?>
                                        <div class="mt-4">
                                            <?php if ($crypto == 1): ?><h6>Scan QR Code</h6><?php endif; ?>
                                            <img src="<?= htmlspecialchars($qr_image) ?>" class="img-fluid" style="max-width:200px; border:1px solid #ddd; border-radius:8px;">
                                        </div>
                                    <?php endif; ?>

                                    <h6><?= htmlspecialchars($channel_label) ?>: <?= htmlspecialchars($channel_value) ?></h6>
                                    <h6><?= htmlspecialchars($channel_name_label) ?>: <?= htmlspecialchars($channel_name_value) ?></h6>
                                    <h6><?= htmlspecialchars($channel_number_label) ?>: <?= htmlspecialchars($channel_number_value) ?></h6>
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
                                    </form>
                                </div>
                            <?php } else {
                                echo "<p>No payment details found for your country.</p>";
                            } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">Invalid verification method or amount not set.</div>
    <?php endif; ?>
</main>

<?php include('inc/footer.php'); ?>
</html>
