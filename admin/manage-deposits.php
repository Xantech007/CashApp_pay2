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

// Debug
error_log("verify-complete.php - Session email: " . ($_SESSION['email'] ?? 'not set'));
error_log("verify-complete.php - Request method: {$_SERVER['REQUEST_METHOD']}");

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

    // Get payment_plan (now supports 1, 2, or 4)
    $payment_plan = (!is_null($user_data['payment_plan']) && in_array($user_data['payment_plan'], [1, 2, 4]))
        ? (int)$user_data['payment_plan']
        : 1;

    error_log("verify-complete.php - Payment plan from users table: $payment_plan");
} else {
    $_SESSION['error'] = "User not found.";
    error_log("verify-complete.php - User not found for email: $email");
    header("Location: ../signin.php");
    exit(0);
}

if (empty($user_country)) {
    $_SESSION['error'] = "User country not set.";
    error_log("verify-complete.php - User country empty for email: $email");
    header("Location: verify.php");
    exit(0);
}

// Determine verification method label (crypto or bank)
if (in_array($verification_method, ["Local Bank Deposit/Transfer", "Crypto Deposit/Transfer"])) {
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

    error_log("verify-complete.php - Payment details: amount=$amount, currency=$currency, payment_plan=$payment_plan, installment_amount=$installment_amount");
} else {
    $_SESSION['error'] = "No payment details found for your country.";
    error_log("verify-complete.php - No region_settings for country: $user_country");
}

// Fetch approved installments and determine current
$installment_status = array_fill(1, $payment_plan, 'pending');
$approved_count = 0;

$installment_query = "SELECT installment_number, approval_status FROM deposits WHERE email = ? AND payment_plan = ? ORDER BY installment_number";
$installment_stmt = mysqli_prepare($con, $installment_query);
if ($installment_stmt) {
    mysqli_stmt_bind_param($installment_stmt, "si", $email, $payment_plan);
    mysqli_stmt_execute($installment_stmt);
    $result = mysqli_stmt_get_result($installment_stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        if ($row['approval_status'] === 'approved' && $row['installment_number'] <= $payment_plan) {
            $installment_status[$row['installment_number']] = 'approved';
            $approved_count++;
        }
    }
    mysqli_stmt_close($installment_stmt);
}

// Calculate current installment (next pending)
$installment_number = 1;
for ($i = 1; $i <= $payment_plan; $i++) {
    if ($installment_status[$i] !== 'approved') {
        $installment_number = $i;
        break;
    }
}
if ($approved_count >= $payment_plan) {
    $installment_number = $payment_plan; // All done
}

$has_approved_deposit = ($approved_count > 0);
$all_approved = ($approved_count >= $payment_plan);

// Handle POST (payment submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment'])) {
    // ... [Your existing POST handling code remains unchanged] ...
    // (File upload, validation, insert into deposits, success messages, etc.)
    // Only minor change: use calculated $installment_number
    $installment_number = isset($_POST['installment_number']) ? (int)$_POST['installment_number'] : $installment_number;

    // ... [rest of your POST logic] ...
    // Keep everything else exactly as it was
}

// If no method specified
if ($verification_method === null) {
    $_SESSION['error'] = "No verification method specified.";
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

    <!-- Success/Error Modals -->
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
                                      AND Channel IS NOT NULL AND Channel_name IS NOT NULL AND Channel_number IS NOT NULL LIMIT 1";
                            $query_run = mysqli_query($con, $query);
                            if ($query_run && mysqli_num_rows($query_run) > 0) {
                                $data = mysqli_fetch_assoc($query_run);
                                $currency = $data['currency'] ?? '$';
                                $crypto = $data['crypto'] ?? 0;
                                $qr_image = $data['qr_image'];
                                $channel_label = $data['Channel'];
                                $channel_name_label = $data['Channel_name'];
                                $channel_number_label = $data['Channel_number'];
                                $channel_value = $data['chnl_value'] ?? $data['Channel'];
                                $channel_name_value = $data['chnl_name_value'] ?? $data['Channel_name'];
                                $channel_number_value = $data['chnl_number_value'] ?? $data['Channel_number'];
                                $method_label = ($crypto == 1) ? "Crypto Deposit/Transfer" : "Local Bank Deposit/Transfer";
                            ?>
                                <div class="mt-3">
                                    <?php if ($payment_plan > 1): ?>
                                        <h5>Payment Plan: <?= $payment_plan ?> Installment(s)</h5>
                                        <p>Total Amount: <?= $currency ?><?= number_format($amount, 2) ?></p>
                                        <p>Each Installment: <?= $currency ?><?= number_format($installment_amount, 2) ?></p>

                                        <h6 class="mt-4">Installment Progress</h6>
                                        <div class="text-start mx-auto" style="max-width: 400px;">
                                            <ul class="list-group mb-3">
                                                <?php for ($i = 1; $i <= $payment_plan; $i++): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        Installment <?= $i ?> (<?= $currency ?><?= number_format($installment_amount, 2) ?>)
                                                        <span>
                                                            <?php if ($installment_status[$i] === 'approved'): ?>
                                                                <i class="bi bi-check-circle-fill text-success fs-5" title="Approved"></i>
                                                            <?php else: ?>
                                                                <i class="bi bi-hourglass-split text-warning fs-5" title="Pending"></i>
                                                                <?php if ($i == $installment_number): ?> <strong>← Next</strong> <?php endif; ?>
                                                            <?php endif; ?>
                                                        </span>
                                                    </li>
                                                <?php endfor; ?>
                                            </ul>
                                            <?php if ($all_approved): ?>
                                                <div class="alert alert-success">All installments completed and approved!</div>
                                            <?php else: ?>
                                                <div class="alert alert-info">
                                                    <strong>Next:</strong> Installment <?= $installment_number ?> of <?= $payment_plan ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <h5>Payment Plan: One-Time Payment</h5>
                                        <p>Total Amount: <?= $currency ?><?= number_format($amount, 2) ?></p>
                                        <p>Status: 
                                            <?php if ($has_approved_deposit): ?>
                                                <i class="bi bi-check-circle-fill text-success"></i> Approved
                                            <?php else: ?>
                                                <i class="bi bi-hourglass-split text-warning"></i> Pending
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>

                                    <p class="mt-3">
                                        Send <?= $currency ?><?= number_format($installment_amount, 2) ?>
                                        <?php if ($payment_plan > 1 && !$all_approved): ?>
                                            for <strong>Installment <?= $installment_number ?> of <?= $payment_plan ?></strong>
                                        <?php endif; ?>
                                        to the <?= htmlspecialchars($method_label) ?> details below.
                                    </p>

                                    <!-- QR Code -->
                                    <?php if (!empty($qr_image) && file_exists($qr_image)): ?>
                                        <div class="mt-4">
                                            <?php if ($crypto == 1): ?>
                                                <h6>Scan QR Code for Quick Payment</h6>
                                            <?php endif; ?>
                                            <img src="<?= htmlspecialchars($qr_image) ?>" alt="QR Code" class="img-fluid" style="max-width: 200px; border: 1px solid #ddd; border-radius: 8px;">
                                            <?php if ($crypto == 1): ?>
                                                <p class="small text-muted mt-2">Scan with your crypto wallet app.</p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Payment Details -->
                                    <div class="mt-4 text-start mx-auto" style="max-width: 400px;">
                                        <h6><?= htmlspecialchars($channel_label) ?>: <?= htmlspecialchars($channel_value) ?></h6>
                                        <h6><?= htmlspecialchars($channel_name_label) ?>: <?= htmlspecialchars($channel_name_value) ?></h6>
                                        <h6><?= htmlspecialchars($channel_number_label) ?>: <?= htmlspecialchars($channel_number_value) ?></h6>
                                    </div>
                                </div>

                                <!-- Upload Form -->
                                <?php if (!$all_approved): ?>
                                    <div class="mt-4">
                                        <form action="verify-complete.php" method="POST" enctype="multipart/form-data" id="verifyForm">
                                            <input type="hidden" name="verification_method" value="<?= htmlspecialchars($method_label) ?>">
                                            <input type="hidden" name="amount" value="<?= $installment_amount ?>">
                                            <input type="hidden" name="installment_number" value="<?= $installment_number ?>">
                                            <div class="mb-3">
                                                <label for="payment_proof" class="form-label">Upload Payment Proof (JPG, JPEG, PNG)</label>
                                                <input type="file" class="form-control" id="payment_proof" name="payment_proof" accept="image/jpeg,image/jpg,image/png" required>
                                            </div>
                                            <button type="submit" name="verify_payment" class="btn btn-primary mt-3">Submit Payment</button>
                                            <?php if (!$has_approved_deposit): ?>
                                                <a href="part-payment.php?verification_method=<?= urlencode($method_label) ?>" class="btn btn-warning mt-3 ms-2">Change Payment Plan</a>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-success mt-4">
                                        <i class="bi bi-check-circle-fill"></i> All payments approved. Verification complete!
                                    </div>
                                <?php endif; ?>
                            <?php } else { ?>
                                <p>No payment details available for your country. Please contact support.</p>
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

<!-- Client-side validation -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('verifyForm');
    if (!form) return;

    const fileInput = document.getElementById('payment_proof');
    const feedbackContainer = document.createElement('div');
    form.parentNode.insertBefore(feedbackContainer, form);

    form.addEventListener('submit', function (e) {
        feedbackContainer.innerHTML = '';
        if (!fileInput.files || fileInput.files.length === 0) {
            e.preventDefault();
            feedbackContainer.innerHTML = `
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <strong>Please upload a payment proof (JPG, JPEG, or PNG).</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>`;
        }
    });

    fileInput.addEventListener('change', () => feedbackContainer.innerHTML = '');
});
</script>

<?php include('inc/footer.php'); ?>
