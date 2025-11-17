<?php
session_start();
include('../config/dbcon.php');
include('inc/header.php');
include('inc/navbar.php');

// Check login
if (!isset($_SESSION['auth'])) {
    $_SESSION['error'] = "Please log in to access this page.";
    header("Location: ../signin.php");
    exit(0);
}

// Initialize variables
$verification_method = $_GET['verification_method'] ?? null;
$user_id = $user_name = $user_balance = $user_country = $amount = $currency = null;
$crypto = 0;
$payment_plan = 1;
$installment_amount = null;
$email = mysqli_real_escape_string($con, $_SESSION['email']);

// Fetch user data
$user_query = "SELECT id, name, balance, country, payment_amount, payment_plan FROM users WHERE email = '$email' LIMIT 1";
$user_result = mysqli_query($con, $user_query);

if (!$user_result || mysqli_num_rows($user_result) == 0) {
    $_SESSION['error'] = "User not found.";
    header("Location: ../signin.php");
    exit(0);
}

$user_data = mysqli_fetch_assoc($user_result);
$user_id = $user_data['id'];
$user_name = $user_data['name'];
$user_balance = $user_data['balance'];
$user_country = $user_data['country'];
$user_payment_amount = $user_data['payment_amount'];

$payment_plan = (!empty($user_data['payment_plan']) && is_numeric($user_data['payment_plan']) && $user_data['payment_plan'] > 0)
    ? (int)$user_data['payment_plan'] : 1;

if (empty($user_country)) {
    $_SESSION['error'] = "Country not set.";
    header("Location: verify.php");
    exit(0);
}

// Determine verification method label
if (in_array($verification_method, ["Local Bank Deposit/Transfer", "Crypto Deposit/Transfer"])) {
    $region_q = mysqli_query($con, "SELECT crypto FROM region_settings WHERE country = '" . mysqli_real_escape_string($con, $user_country) . "' LIMIT 1");
    if ($region_q && mysqli_num_rows($region_q) > 0) {
        $crypto = mysqli_fetch_assoc($region_q)['crypto'] ?? 0;
        $verification_method = ($crypto == 1) ? "Crypto Deposit/Transfer" : "Local Bank Deposit/Transfer";
    }
}

// Fetch payment amount & currency
$pkg_query = mysqli_query($con, "SELECT payment_amount, currency, crypto FROM region_settings WHERE country = '" . mysqli_real_escape_string($con, $user_country) . "' LIMIT 1");
if ($pkg_query && mysqli_num_rows($pkg_query) > 0) {
    $pkg = mysqli_fetch_assoc($pkg_query);
    $currency = $pkg['currency'] ?? '$';
    $amount = !is_null($user_payment_amount) ? $user_payment_amount : $pkg['payment_amount'];
    $installment_amount = $amount / $payment_plan;
} else {
    $_SESSION['error'] = "No payment package found for your country.";
    header("Location: verify.php");
    exit(0);
}

// Fetch deposit history for status
$installment_status = array_fill(1, $payment_plan, 'pending');
$status_query = "SELECT installment_number, approval_status FROM deposits WHERE email = ? AND payment_plan = ? ORDER BY installment_number";
$stmt = mysqli_prepare($con, $status_query);
mysqli_stmt_bind_param($stmt, "si", $email, $payment_plan);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($res)) {
    $installment_status[$row['installment_number']] = $row['approval_status'];
}
mysqli_stmt_close($stmt);

// Determine current installment
$installment_number = 1;
for ($i = 1; $i <= $payment_plan; $i++) {
    if ($installment_status[$i] !== 'approved') {
        $installment_number = $i;
        break;
    }
}

$has_any_deposit = mysqli_num_rows(mysqli_query($con, "SELECT 1 FROM deposits WHERE email = '$email' AND payment_plan = $payment_plan LIMIT 1")) > 0;

// ==================== HANDLE FORM SUBMISSION ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment'])) {

    $verification_method = trim($_POST['verification_method']);
    $installment_number = (int)($_POST['installment_number'] ?? 1);

    // File upload validation
    if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "Please upload a valid payment proof.";
        header("Location: verify-complete.php?verification_method=" . urlencode($verification_method));
        exit(0);
    }

    $file = $_FILES['payment_proof'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png'];
    if (!in_array($ext, $allowed) || !in_array(mime_content_type($file['tmp_name']), ['image/jpeg', 'image/png'])) {
        $_SESSION['error'] = "Only JPG/PNG images allowed.";
        header("Location: verify-complete.php?verification_method=" . urlencode($verification_method));
        exit(0);
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        $_SESSION['error'] = "File too large (max 5MB).";
        header("Location: verify-complete.php?verification_method=" . urlencode($verification_method));
        exit(0);
    }

    $upload_dir = '../Uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $new_name = uniqid('proof_') . '.' . $ext;
    $upload_path = $upload_dir . $new_name;

    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        $_SESSION['error'] = "Failed to upload file.";
        header("Location: verify-complete.php?verification_method=" . urlencode($verification_method));
        exit(0);
    }

    // Insert deposit record
    $insert = "INSERT INTO deposits (amount, image, name, email, currency, created_at, updated_at, payment_plan, installment_number, approval_status) 
               VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, 'pending')";
    $stmt = mysqli_prepare($con, $insert);
    mysqli_stmt_bind_param($stmt, "dssssii", $installment_amount, $upload_path, $user_name, $email, $currency, $payment_plan, $installment_number);
    
    if (mysqli_stmt_execute($stmt)) {
        // === NEW VERIFICATION LOGIC ===
        $verify_now = false;

        if ($payment_plan == 1) {
            $verify_now = true; // One-time → verify immediately
        } elseif (in_array($payment_plan, [2, 4]) && $installment_number == $payment_plan) {
            $verify_now = true; // Last installment → verify
        }

        if ($verify_now) {
            $update = "UPDATE users SET verify = 1, verify_time = NOW() WHERE email = ?";
            $ustmt = mysqli_prepare($con, $update);
            mysqli_stmt_bind_param($ustmt, "s", $email);
            mysqli_stmt_execute($ustmt);
            mysqli_stmt_close($ustmt);
        }

        $_SESSION['success'] = $payment_plan == 1 
            ? "Payment submitted and your account has been verified!" 
            : "Installment $installment_number of $payment_plan submitted." . ($verify_now ? " Account verified!" : " Awaiting approval.");
    } else {
        $_SESSION['error'] = "Submission failed. Try again.";
    }

    mysqli_stmt_close($stmt);
    header("Location: verify-complete.php?verification_method=" . urlencode($verification_method));
    exit(0);
}

if (!$verification_method) {
    $_SESSION['error'] = "No verification method selected.";
    header("Location: verify.php");
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
                <li class="breadcrumb-item active">Payment</li>
            </ol>
        </nav>
    </div>

    <!-- Success Modal -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="modal fade show" id="successModal" style="display:block;" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Success</h5>
                    </div>
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

    <!-- Error Modal -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="modal fade show" id="errorModal" style="display:block;" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">Error</h5>
                    </div>
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

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-primary text-white text-center">
                        <h4>Complete Your Verification Payment</h4>
                    </div>
                    <div class="card-body p-4">

                        <?php
                        $details_query = "SELECT currency, Channel, Channel_name, Channel_number, chnl_value, chnl_name_value, chnl_number_value, crypto, qr_image
                                          FROM region_settings WHERE country = ? AND Channel IS NOT NULL LIMIT 1";
                        $stmt = mysqli_prepare($con, $details_query);
                        mysqli_stmt_bind_param($stmt, "s", $user_country);
                        mysqli_stmt_execute($stmt);
                        $res = mysqli_stmt_get_result($stmt);
                        if (mysqli_num_rows($res) > 0):
                            $data = mysqli_fetch_assoc($res);
                            $method_label = ($data['crypto'] == 1) ? "Crypto Deposit/Transfer" : "Local Bank Deposit/Transfer";
                        ?>

                        <div class="text-center mb-4">
                            <h5>Payment Plan: <strong><?= $payment_plan == 1 ? 'One-Time Payment' : "$payment_plan Installments" ?></strong></h5>
                            <p class="lead">Total Amount: <strong><?= $currency ?><?= number_format($amount, 2) ?></strong></p>
                            <?php if ($payment_plan > 1): ?>
                                <p>Each Installment: <strong><?= $currency ?><?= number_format($installment_amount, 2) ?></strong></p>
                            <?php endif; ?>
                        </div>

                        <!-- Unified Payment Status (Same Styling for All) -->
                        <h6 class="mt-4 text-primary fw-bold">Payment Status</h6>
                        <ul class="list-group mb-4">
                            <?php if ($payment_plan == 1): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center <?= $installment_status[1] === 'approved' ? 'list-group-item-success' : '' ?>">
                                    One-Time Payment (<?= $currency ?><?= number_format($installment_amount, 2) ?>)
                                    <span>
                                        <?php if ($installment_status[1] === 'approved'): ?>
                                            <i class="bi bi-check-circle-fill text-success fs-4"></i> <strong class="text-success">Approved</strong>
                                        <?php elseif ($has_any_deposit): ?>
                                            <i class="bi bi-hourglass-split text-warning fs-4"></i> <strong class="text-warning">Pending Approval</strong>
                                        <?php else: ?>
                                            <i class="bi bi-clock text-secondary fs-4"></i> <strong class="text-secondary">Not Submitted</strong>
                                        <?php endif; ?>
                                    </span>
                                </li>
                            <?php else: ?>
                                <?php for ($i = 1; $i <= $payment_plan; $i++): ?>
                                    <?php $submitted = mysqli_num_rows(mysqli_query($con, "SELECT 1 FROM deposits WHERE email='$email' AND payment_plan=$payment_plan AND installment_number=$i")) > 0; ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center <?= $installment_status[$i] === 'approved' ? 'list-group-item-success' : '' ?>">
                                        Installment <?= $i ?> of <?= $payment_plan ?> (<?= $currency ?><?= number_format($installment_amount, 2) ?>)
                                        <span>
                                            <?php if ($installment_status[$i] === 'approved'): ?>
                                                <i class="bi bi-check-circle-fill text-success fs-4"></i> <strong class="text-success">Approved</strong>
                                            <?php elseif ($submitted): ?>
                                                <i class="bi bi-hourglass-split text-warning fs-4"></i> <strong class="text-warning">Pending</strong>
                                            <?php else: ?>
                                                <i class="bi bi-clock text-secondary fs-4"></i> <strong class="text-secondary">Not Paid</strong>
                                            <?php endif; ?>
                                        </span>
                                    </li>
                                <?php endfor; ?>
                            <?php endif; ?>
                        </ul>

                        <div class="alert alert-info">
                            <strong>Send exactly <?= $currency ?><?= number_format($installment_amount, 2) ?></strong>
                            <?php if ($payment_plan > 1): ?> for <strong>Installment <?= $installment_number ?> of <?= $payment_plan ?></strong><?php endif; ?>
                            to the details below:
                        </div>

                        <!-- QR Code -->
                        <?php if (!empty($data['qr_image']) && file_exists($data['qr_image'])): ?>
                            <div class="text-center my-4">
                                <img src="<?= htmlspecialchars($data['qr_image']) ?>" class="img-fluid rounded shadow" style="max-width: 220px;">
                                <p class="mt-2 text-muted"><small>Scan to pay quickly</small></p>
                            </div>
                        <?php endif; ?>

                        <!-- Payment Details -->
                        <div class="bg-light p-4 rounded mb-4">
                            <h6 class="fw-bold"><?= htmlspecialchars($data['Channel']) ?>:</h6>
                            <p class="fs-5"><?= htmlspecialchars($data['chnl_value'] ?? $data['Channel']) ?></p>

                            <h6 class="fw-bold mt-3"><?= htmlspecialchars($data['Channel_name']) ?>:</h6>
                            <p class="fs-5"><?= htmlspecialchars($data['chnl_name_value'] ?? $data['Channel_name']) ?></p>

                            <h6 class="fw-bold mt-3"><?= htmlspecialchars($data['Channel_number']) ?>:</h6>
                            <p class="fs-5"><?= htmlspecialchars($data['chnl_number_value'] ?? $data['Channel_number']) ?></p>
                        </div>

                        <!-- Upload Form -->
                        <form action="" method="POST" enctype="multipart/form-data" id="verifyForm">
                            <input type="hidden" name="verification_method" value="<?= htmlspecialchars($method_label) ?>">
                            <input type="hidden" name="amount" value="<?= $installment_amount ?>">
                            <input type="hidden" name="installment_number" value="<?= $installment_number ?>">

                            <div class="mb-3">
                                <label class="form-label fw-bold">Upload Payment Proof</label>
                                <input type="file" name="payment_proof" class="form-control form-control-lg" accept="image/jpeg,image/jpg,image/png" required>
                                <small class="text-muted">Supported: JPG, PNG (Max 5MB)</small>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                                <button type="submit" name="verify_payment" class="btn btn-success btn-lg px-5">
                                    Submit Proof
                                </button>
                                <?php if (!$has_any_deposit || $installment_number == 1): ?>
                                    <a href="part-payment.php?verification_method=<?= urlencode($method_label) ?>" class="btn btn-outline-warning btn-lg">
                                        Change Plan
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <?php else: ?>
                            <div class="alert alert-danger text-center">
                                No payment details found for your country. Please contact support.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.getElementById('verifyForm')?.addEventListener('submit', function(e) {
    const file = document.querySelector('[name="payment_proof"]').files[0];
    if (!file) {
        e.preventDefault();
        alert('Please select a payment proof image.');
    }
});
</script>

<?php include('inc/footer.php'); ?>
