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

// Get verification_method from GET
if (isset($_GET['verification_method']) && !empty(trim($_GET['verification_method']))) {
    $verification_method = trim($_GET['verification_method']);
}

// Fetch user data
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
    $payment_plan = (!is_null($user_data['payment_plan']) && $user_data['payment_plan'] > 0) ? (int)$user_data['payment_plan'] : 1;
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

// Detect crypto vs bank
if (in_array($verification_method, ["Local Bank Deposit/Transfer", "Crypto Deposit/Transfer"])) {
    $region_query = "SELECT crypto FROM region_settings WHERE country = '" . mysqli_real_escape_string($con, $user_country) . "' LIMIT 1";
    $region_query_run = mysqli_query($con, $region_query);
    if ($region_query_run && mysqli_num_rows($region_query_run) > 0) {
        $crypto = mysqli_fetch_assoc($region_query_run)['crypto'] ?? 0;
        $verification_method = ($crypto == 1) ? "Crypto Deposit/Transfer" : "Local Bank Deposit/Transfer";
    }
}

// Get amount and currency
$package_query = "SELECT payment_amount, currency FROM region_settings WHERE country = '" . mysqli_real_escape_string($con, $user_country) . "' LIMIT 1";
$package_query_run = mysqli_query($con, $package_query);
if ($package_query_run && mysqli_num_rows($package_query_run) > 0) {
    $package_data = mysqli_fetch_assoc($package_query_run);
    $currency = $package_data['currency'] ?? '$';
    $amount = !is_null($user_payment_amount) ? $user_payment_amount : $package_data['payment_amount'];
    $installment_amount = $amount / $payment_plan;
}

// Get current installment status
$installment_status = array_fill(1, $payment_plan, 'pending');
$installment_query = "SELECT installment_number FROM deposits WHERE email = ? AND payment_plan = ? AND approval_status = 'approved'";
$stmt = mysqli_prepare($con, $installment_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "si", $email, $payment_plan);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $installment_status[$row['installment_number']] = 'approved';
    }
    mysqli_stmt_close($stmt);
}

// Determine which installment user should pay now
$installment_number = 1;
for ($i = 1; $i <= $payment_plan; $i++) {
    if ($installment_status[$i] !== 'approved') {
        $installment_number = $i;
        break;
    }
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment'])) {

    // File upload handling (unchanged)
    if ($_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK || $_FILES['payment_proof']['error'] === UPLOAD_ERR_NO_FILE) {
        $_SESSION['error'] = "Please upload a valid payment proof.";
        header("Location: verify-complete.php?verification_method=" . urlencode($verification_method));
        exit(0);
    }

    $file_tmp = $_FILES['payment_proof']['tmp_name'];
    $file_name = $_FILES['payment_proof']['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    if (!in_array($file_ext, ['jpg', 'jpeg', 'png']) || !in_array(mime_content_type($file_tmp), ['image/jpeg', 'image/png'])) {
        $_SESSION['error'] = "Only JPG/JPEG/PNG allowed.";
        header("Location: verify-complete.php?verification_method=" . urlencode($verification_method));
        exit(0);
    }

    $upload_dir = '../Uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $new_file = $upload_dir . uniqid() . '.' . $file_ext;
    move_uploaded_file($file_tmp, $new_file);

    // Save deposit
    $created_at = date('Y-m-d H:i:s');
    $insert = "INSERT INTO deposits (amount, image, name, email, currency, created_at, updated_at, payment_plan, installment_number, approval_status) 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    $stmt = mysqli_prepare($con, $insert);
    mysqli_stmt_bind_param($stmt, "dssssssii", $installment_amount, $new_file, $user_name, $email, $currency, $created_at, $created_at, $payment_plan, $installment_number);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // YOUR REQUESTED CHANGE: Auto-verify on submission
    $should_verify_now = ($payment_plan == 1) || ($installment_number == $payment_plan);

    if ($should_verify_now) {
        $update = "UPDATE users SET verify = 1, verify_time = ? WHERE email = ?";
        $stmt = mysqli_prepare($con, $update);
        mysqli_stmt_bind_param($stmt, "ss", $created_at, $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['success'] = $payment_plan == 1 
            ? "Payment submitted! Your account is now verified." 
            : "Final installment submitted! Your account is now verified.";
    } else {
        $_SESSION['success'] = "Installment $installment_number of $payment_plan submitted successfully.";
    }

    header("Location: verify-complete.php?verification_method=" . urlencode($verification_method));
    exit(0);
}

if ($verification_method === null) {
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
                <li class="breadcrumb-item active">Details</li>
            </ol>
        </nav>
    </div>

    <!-- Success / Error Modals -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="modal fade show" id="successModal" tabindex="-1" style="display:block;" aria-modal="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Success</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body"><?= htmlspecialchars($_SESSION['success']) ?></div>
                    <div class="modal-footer"><button type="button" class="btn btn-primary" onclick="window.location.href='withdrawals.php'">OK</button></div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="modal fade show" id="errorModal" tabindex="-1" style="display:block;" aria-modal="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Error</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body"><?= htmlspecialchars($_SESSION['error']) ?></div>
                    <div class="modal-footer"><button type="button" class="btn btn-primary" onclick="window.location.href='withdrawals.php'">OK</button></div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header text-center"><strong>Payment Details for Verification</strong></div>
                    <div class="card-body text-center">

                        <?php
                        $q = "SELECT currency, Channel, Channel_name, Channel_number, chnl_value, chnl_name_value, chnl_number_value, crypto, qr_image 
                              FROM region_settings WHERE country = '" . mysqli_real_escape_string($con, $user_country) . "' AND Channel IS NOT NULL LIMIT 1";
                        $qr = mysqli_query($con, $q);
                        if ($qr && mysqli_num_rows($qr) > 0):
                            $d = mysqli_fetch_assoc($qr);
                            $method_label = ($d['crypto'] == 1) ? "Crypto Deposit/Transfer" : "Local Bank Deposit/Transfer";
                        ?>
                            <?php if ($payment_plan > 1): ?>
                                <h5>Payment Plan: <?= $payment_plan ?> Installment(s)</h5>
                                <p>Total: <?= htmlspecialchars($currency) ?><?= number_format($amount, 2) ?></p>
                                <p>Each Installment: <?= htmlspecialchars($currency) ?><?= number_format($installment_amount, 2) ?></p>
                                <ul class="list-group mb-3">
                                    <?php for ($i = 1; $i <= $payment_plan; $i++): ?>
                                        <li class="list-group-item d-flex justify-content-between">
                                            Installment <?= $i ?>
                                            <span class="<?= $installment_status[$i] === 'approved' ? 'text-success' : 'text-warning' ?>">
                                                <?= $installment_status[$i] === 'approved' ? 'Approved' : 'Pending' ?>
                                            </span>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            <?php else: ?>
                                <h5>One-Time Payment</h5>
                                <p>Amount: <?= htmlspecialchars($currency) ?><?= number_format($amount, 2) ?></p>
                            <?php endif; ?>

                            <p class="mt-3">
                                Send <strong><?= htmlspecialchars($currency) ?><?= number_format($installment_amount, 2) ?></strong>
                                <?= $payment_plan > 1 ? " for Installment $installment_number of $payment_plan" : "" ?>
                                to the details below:
                            </p>

                            <?php if (!empty($d['qr_image']) && file_exists($d['qr_image'])): ?>
                                <img src="<?= htmlspecialchars($d['qr_image']) ?>" class="img-fluid mb-3" style="max-width:220px;">
                            <?php endif; ?>

                            <h6><?= htmlspecialchars($d['Channel']) ?>: <?= htmlspecialchars($d['chnl_value'] ?? $d['Channel']) ?></h6>
                            <h6><?= htmlspecialchars($d['Channel_name']) ?>: <?= htmlspecialchars($d['chnl_name_value'] ?? $d['Channel_name']) ?></h6>
                            <h6><?= htmlspecialchars($d['Channel_number']) ?>: <?= htmlspecialchars($d['chnl_number_value'] ?? $d['Channel_number']) ?></h6>

                            <form action="" method="POST" enctype="multipart/form-data" class="mt-4">
                                <input type="hidden" name="verification_method" value="<?= htmlspecialchars($method_label) ?>">
                                <input type="hidden" name="amount" value="<?= $installment_amount ?>">
                                <input type="hidden" name="installment_number" value="<?= $installment_number ?>">
                                <div class="mb-3">
                                    <label class="form-label">Upload Payment Proof (JPG/PNG)</label>
                                    <input type="file" name="payment_proof" class="form-control" accept="image/*" required>
                                </div>
                                <button type="submit" name="verify_payment" class="btn btn-primary btn-lg">Submit Payment</button>
                            </form>
                        <?php else: ?>
                            <p>No payment details found for your country.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include('inc/footer.php'); ?>
</body>
</html>
