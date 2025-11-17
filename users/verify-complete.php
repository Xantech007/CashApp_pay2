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

// Get verification_method from GET if available
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

// Fetch region settings
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

// Fetch current installment status
$installment_status = array_fill(1, $payment_plan, 'pending');
$installment_query = "SELECT installment_number, approval_status FROM deposits WHERE email = ? AND payment_plan = ? ORDER BY installment_number";
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

// Determine next installment number
$installment_number = 1;
for ($i = 1; $i <= $payment_plan; $i++) {
    if ($installment_status[$i] !== 'approved') {
        $installment_number = $i;
        break;
    }
}

$has_approved_deposit = in_array('approved', $installment_status);

// Handle POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment'])) {
    $verification_method = trim($_POST['verification_method']);
    $submitted_amount = $_POST['amount'];
    $installment_number = (int)$_POST['installment_number'];
    $created_at = date('Y-m-d H:i:s');

    // File upload handling (unchanged, kept secure)
    if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "Please upload a valid payment proof.";
        header("Location: verify-complete.php?verification_method=" . urlencode($verification_method));
        exit(0);
    }

    $file_tmp = $_FILES['payment_proof']['tmp_name'];
    $file_name = $_FILES['payment_proof']['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png'];
    if (!in_array($file_ext, $allowed_ext)) {
        $_SESSION['error'] = "Only JPG, JPEG, PNG allowed.";
        header("Location: verify-complete.php?verification_method=" . urlencode($verification_method));
        exit(0);
    }

    $upload_dir = '../Uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $new_file_name = uniqid() . '.' . $file_ext;
    $upload_path = $upload_dir . $new_file_name;

    if (!move_uploaded_file($file_tmp, $upload_path)) {
        $_SESSION['error'] = "Failed to upload proof.";
        header("Location: verify-complete.php?verification_method=" . urlencode($verification_method));
        exit(0);
    }

    // Insert deposit record
    $insert_query = "INSERT INTO deposits (amount, image, name, email, currency, created_at, updated_at, payment_plan, installment_number, approval_status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    $stmt = mysqli_prepare($con, $insert_query);
    mysqli_stmt_bind_param($stmt, "dssssssii", $submitted_amount, $upload_path, $user_name, $email, $currency, $created_at, $created_at, $payment_plan, $installment_number);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        // NEW LOGIC: Update verify = 1 only when FULL payment is complete
        $should_verify_now = false;

        if ($payment_plan == 1) {
            // One-time payment → verify immediately after submission
            $should_verify_now = true;
        } elseif (in_array($payment_plan, [2, 4])) {
            // Installments → verify only when LAST installment is submitted
            if ($installment_number == $payment_plan) {
                $should_verify_now = true;
            }
        }

        if ($should_verify_now) {
            $update_verify = "UPDATE users SET verify = 1, verify_time = ? WHERE email = ?";
            $verify_stmt = mysqli_prepare($con, $update_verify);
            mysqli_stmt_bind_param($verify_stmt, "ss", $created_at, $email);
            mysqli_stmt_execute($verify_stmt);
            mysqli_stmt_close($verify_stmt);

            $_SESSION['success'] = "Payment submitted successfully! Your account has been verified.";
            error_log("verify-complete.php - Account VERIFIED for $email (full payment completed)");
        } else {
            $_SESSION['success'] = $payment_plan > 1 
                ? "Installment $installment_number of $payment_plan submitted. Awaiting approval."
                : "Payment submitted. Awaiting approval.";
        }
    } else {
        $_SESSION['error'] = "Failed to submit payment.";
    }

    header("Location: verify-complete.php?verification_method=" . urlencode($verification_method));
    exit(0);
}

if ($verification_method === null) {
    $_SESSION['error'] = "No verification method specified.";
    header("Location: verify.php");
    exit(0);
}
?>

<!-- HTML Part remains exactly the same as your original -->
<!-- Only the PHP logic above was updated -->

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

    <!-- Success/Error Messages (unchanged) -->
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

    <!-- Rest of your HTML (payment form, QR, etc.) remains 100% unchanged -->
    <!-- ... your existing HTML from <div class="container text-center"> onwards ... -->
</main>

<?php include('inc/footer.php'); ?>
</html>
