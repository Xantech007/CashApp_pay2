<?php
session_start();
include('../config/dbcon.php');
include('inc/header.php');
include('inc/navbar.php');

if (!isset($_SESSION['auth'])) {
    $_SESSION['error'] = "Please log in to access this page.";
    header("Location: ../signin.php");
    exit(0);
}

$verification_method = $_GET['verification_method'] ?? null;
$email = mysqli_real_escape_string($con, $_SESSION['email']);
$user_query = "SELECT id, name, balance, country, payment_amount, payment_plan, verify FROM users WHERE email = '$email' LIMIT 1";
$user_result = mysqli_query($con, $user_query);
if (!$user_result || mysqli_num_rows($user_result) == 0) {
    $_SESSION['error'] = "User not found.";
    header("Location: ../signin.php");
    exit(0);
}
$user = mysqli_fetch_assoc($user_result);
$user_country = $user['country'];
$payment_plan = $user['payment_plan'] ?? 1;
$payment_plan = ($payment_plan > 0) ? (int)$payment_plan : 1;
$user_verified = (bool)$user['verify'];

if (empty($user_country)) {
    $_SESSION['error'] = "Country not set.";
    header("Location: verify.php");
    exit(0);
}

// Fetch region settings
$region_query = "SELECT payment_amount, currency, crypto, Channel, Channel_name, Channel_number, 
                 chnl_value, chnl_name_value, chnl_number_value, qr_image 
                 FROM region_settings WHERE country = ? AND Channel IS NOT NULL LIMIT 1";
$stmt = mysqli_prepare($con, $region_query);
mysqli_stmt_bind_param($stmt, "s", $user_country);
mysqli_stmt_execute($stmt);
$region_result = mysqli_stmt_get_result($stmt);
$region = mysqli_num_rows($region_result) > 0 ? mysqli_fetch_assoc($region_result) : null;

if (!$region) {
    $_SESSION['error'] = "No payment details found for your country.";
    header("Location: verify.php");
    exit(0);
}

$currency = $region['currency'] ?? '$';
$amount = $user['payment_amount'] ?? $region['payment_amount'];
$installment_amount = $amount / $payment_plan;
$crypto = $region['crypto'] ?? 0;
$method_label = $crypto ? "Crypto Deposit/Transfer" : "Local Bank Deposit/Transfer";

// Fetch deposit status (for both one-time and installments)
$deposit_status = [];
$deposit_query = "SELECT installment_number, approval_status FROM deposits 
                  WHERE email = ? AND payment_plan = ? ORDER BY installment_number";
$stmt = mysqli_prepare($con, $deposit_query);
mysqli_stmt_bind_param($stmt, "si", $email, $payment_plan);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $deposit_status[$row['installment_number']] = $row['approval_status'];
}

// Determine current installment (next one to pay)
$current_installment = 1;
$all_approved = true;
for ($i = 1; $i <= $payment_plan; $i++) {
    if (!isset($deposit_status[$i]) || $deposit_status[$i] !== 'approved') {
        $current_installment = $i;
        $all_approved = false;
        break;
    }
}
if ($all_approved && $payment_plan > 1) $current_installment = $payment_plan;

// Check if any deposit exists (for one-time or installments)
$has_any_deposit = !empty($deposit_status);
$has_approved_deposit = in_array('approved', $deposit_status, true);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment'])) {
    $installment_number = (int)($_POST['installment_number'] ?? 1);

    // File upload handling (unchanged - secure)
    if ($_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK || empty($_FILES['payment_proof']['name'])) {
        $_SESSION['error'] = "Please upload payment proof.";
    } else {
        $file_tmp = $_FILES['payment_proof']['tmp_name'];
        $file_name = $_FILES['payment_proof']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];

        if (!in_array($file_ext, $allowed) || $_FILES['payment_proof']['size'] > 5*1024*1024) {
            $_SESSION['error'] = "Invalid file. Only JPG/PNG allowed, max 5MB.";
        } else {
            $upload_dir = '../Uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $new_name = uniqid('proof_') . '.' . $file_ext;
            $upload_path = $upload_dir . $new_name;

            if (move_uploaded_file($file_tmp, $upload_path)) {
                $insert = "INSERT INTO deposits (amount, image, name, email, currency, created_at, updated_at, 
                           payment_plan, installment_number, approval_status) 
                           VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, 'pending')";
                $stmt = mysqli_prepare($con, $insert);
                mysqli_stmt_bind_param($stmt, "dssssii", $installment_amount, $upload_path, $user['name'], $email, $currency, $payment_plan, $installment_number);
                
                if (mysqli_stmt_execute($stmt)) {
                    // NEW VERIFICATION LOGIC
                    $verify_now = false;
                    if ($payment_plan == 1) {
                        $verify_now = true; // One-time: verify immediately
                    } elseif (in_array($payment_plan, [2, 4]) && $installment_number == $payment_plan) {
                        $verify_now = true; // Last installment
                    }

                    if ($verify_now) {
                        $update = "UPDATE users SET verify = 1, verify_time = NOW() WHERE email = ?";
                        $ustmt = mysqli_prepare($con, $update);
                        mysqli_stmt_bind_param($ustmt, "s", $email);
                        mysqli_stmt_execute($ustmt);
                        mysqli_stmt_close($ustmt);
                        $user_verified = true;
                    }

                    $_SESSION['success'] = $payment_plan > 1 
                        ? "Installment $installment_number of $payment_plan submitted successfully!" . ($verify_now ? " Your account is now verified!" : "")
                        : "Payment submitted and your account has been verified!";
                } else {
                    $_SESSION['error'] = "Failed to save payment.";
                }
                mysqli_stmt_close($stmt);
            } else {
                $_SESSION['error'] = "Failed to upload file.";
            }
        }
    }
    header("Location: verify-complete.php?verification_method=" . urlencode($method_label));
    exit(0);
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Verification Payment</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../users/index.php">Home</a></li>
                <li class="breadcrumb-item">Verify</li>
                <li class="breadcrumb-item active">Payment</li>
            </ol>
        </nav>
    </div>

    <!-- Success / Error Modals -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="modal fade show" style="display:block;" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Success</h5>
                    </div>
                    <div class="modal-body"><?= htmlspecialchars($_SESSION['success']) ?></div>
                    <div class="modal-footer">
                        <button class="btn btn-primary" onclick="window.location.href='withdrawals.php'">Continue</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="modal fade show" style="display:block;" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">Error</h5>
                    </div>
                    <div class="modal-body"><?= htmlspecialchars($_SESSION['error']) ?></div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-9">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h4><?= $payment_plan == 1 ? "One-Time Payment" : "$payment_plan Installment Plan" ?></h4>
                    </div>
                    <div class="card-body">

                        <!-- User Verification Status -->
                        <?php if ($user_verified): ?>
                            <div class="alert alert-success text-center">
                                <strong>Your account is VERIFIED!</strong>
                            </div>
                        <?php endif; ?>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <p><strong>Total Amount:</strong> <?= $currency . number_format($amount, 2) ?></p>
                                <?php if ($payment_plan > 1): ?>
                                    <p><strong>Each Installment:</strong> <?= $currency . number_format($installment_amount, 2) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 text-end">
                                <p><strong>Amount to Pay Now:</strong><br>
                                    <span class="fs-4 text-primary"><?= $currency . number_format($installment_amount, 2) ?></span>
                                    <?php if ($payment_plan > 1): ?>
                                        <br><small>Installment <?= $current_installment ?> of <?= $payment_plan ?></small>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <!-- Payment Status Display -->
                        <div class="mb-4">
                            <h5>Payment Status</h5>
                            <ul class="list-group">
                                <?php for ($i = 1; $i <= $payment_plan; $i++): ?>
                                    <?php
                                    $status = $deposit_status[$i] ?? 'not_paid';
                                    $badge = match($status) {
                                        'approved' => '<span class="badge bg-success">Approved</span>',
                                        'pending'  => '<span class="badge bg-warning">Pending Review</span>',
                                        default    => '<span class="badge bg-secondary">Not Paid</span>'
                                    };
                                    ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?= $payment_plan == 1 ? "One-Time Payment" : "Installment $i" ?>
                                        <span>
                                            <?= $badge ?>
                                            <?php if ($status === 'not_paid' && $i == $current_installment): ?>
                                                <strong class="text-primary"> ← Pay Now</strong>
                                            <?php endif; ?>
                                        </span>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </div>

                        <!-- QR Code & Payment Details -->
                        <?php if (!empty($region['qr_image']) && file_exists($region['qr_image'])): ?>
                            <div class="text-center mb-4">
                                <p><strong><?= $crypto ? "Scan to Pay with Crypto" : "Payment QR Code" ?></strong></p>
                                <img src="<?= htmlspecialchars($region['qr_image']) ?>" class="img-fluid rounded" style="max-width: 220px;">
                            </div>
                        <?php endif; ?>

                        <div class="row text-center mb-4">
                            <div class="col-md-4"><strong><?= htmlspecialchars($region['Channel']) ?>:</strong><br><?= htmlspecialchars($region['chnl_value'] ?? $region['Channel']) ?></div>
                            <div class="col-md-4"><strong><?= htmlspecialchars($region['Channel_name']) ?>:</strong><br><?= htmlspecialchars($region['chnl_name_value'] ?? $region['Channel_name']) ?></div>
                            <div class="col-md-4"><strong><?= htmlspecialchars($region['Channel_number']) ?>:</strong><br><?= htmlspecialchars($region['chnl_number_value'] ?? $region['Channel_number']) ?></div>
                        </div>

                        <!-- Upload Form (Only if not all paid) -->
                        <?php if (!$all_approved || !$has_any_deposit): ?>
                            <hr>
                            <form action="" method="POST" enctype="multipart/form-data" class="text-center">
                                <input type="hidden" name="verification_method" value="<?= htmlspecialchars($method_label) ?>">
                                <input type="hidden" name="amount" value="<?= $installment_amount ?>">
                                <input type="hidden" name="installment_number" value="<?= $current_installment ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label"><strong>Upload Payment Proof</strong> (JPG/PNG, max 5MB)</label>
                                    <input type="file" name="payment_proof" class="form-control" accept="image/*" required>
                                </div>
                                
                                <button type="submit" name="verify_payment" class="btn btn-success btn-lg">
                                    Submit Payment Proof
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <strong>All payments completed and approved!</strong><br>
                                You can now withdraw your earnings.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include('inc/footer.php'); ?><?php
session_start();
include('../config/dbcon.php');
include('inc/header.php');
include('inc/navbar.php');

if (!isset($_SESSION['auth'])) {
    $_SESSION['error'] = "Please log in to access this page.";
    header("Location: ../signin.php");
    exit(0);
}

$verification_method = $_GET['verification_method'] ?? null;
$email = mysqli_real_escape_string($con, $_SESSION['email']);
$user_query = "SELECT id, name, balance, country, payment_amount, payment_plan, verify FROM users WHERE email = '$email' LIMIT 1";
$user_result = mysqli_query($con, $user_query);
if (!$user_result || mysqli_num_rows($user_result) == 0) {
    $_SESSION['error'] = "User not found.";
    header("Location: ../signin.php");
    exit(0);
}
$user = mysqli_fetch_assoc($user_result);
$user_country = $user['country'];
$payment_plan = $user['payment_plan'] ?? 1;
$payment_plan = ($payment_plan > 0) ? (int)$payment_plan : 1;
$user_verified = (bool)$user['verify'];

if (empty($user_country)) {
    $_SESSION['error'] = "Country not set.";
    header("Location: verify.php");
    exit(0);
}

// Fetch region settings
$region_query = "SELECT payment_amount, currency, crypto, Channel, Channel_name, Channel_number, 
                 chnl_value, chnl_name_value, chnl_number_value, qr_image 
                 FROM region_settings WHERE country = ? AND Channel IS NOT NULL LIMIT 1";
$stmt = mysqli_prepare($con, $region_query);
mysqli_stmt_bind_param($stmt, "s", $user_country);
mysqli_stmt_execute($stmt);
$region_result = mysqli_stmt_get_result($stmt);
$region = mysqli_num_rows($region_result) > 0 ? mysqli_fetch_assoc($region_result) : null;

if (!$region) {
    $_SESSION['error'] = "No payment details found for your country.";
    header("Location: verify.php");
    exit(0);
}

$currency = $region['currency'] ?? '$';
$amount = $user['payment_amount'] ?? $region['payment_amount'];
$installment_amount = $amount / $payment_plan;
$crypto = $region['crypto'] ?? 0;
$method_label = $crypto ? "Crypto Deposit/Transfer" : "Local Bank Deposit/Transfer";

// Fetch deposit status (for both one-time and installments)
$deposit_status = [];
$deposit_query = "SELECT installment_number, approval_status FROM deposits 
                  WHERE email = ? AND payment_plan = ? ORDER BY installment_number";
$stmt = mysqli_prepare($con, $deposit_query);
mysqli_stmt_bind_param($stmt, "si", $email, $payment_plan);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $deposit_status[$row['installment_number']] = $row['approval_status'];
}

// Determine current installment (next one to pay)
$current_installment = 1;
$all_approved = true;
for ($i = 1; $i <= $payment_plan; $i++) {
    if (!isset($deposit_status[$i]) || $deposit_status[$i] !== 'approved') {
        $current_installment = $i;
        $all_approved = false;
        break;
    }
}
if ($all_approved && $payment_plan > 1) $current_installment = $payment_plan;

// Check if any deposit exists (for one-time or installments)
$has_any_deposit = !empty($deposit_status);
$has_approved_deposit = in_array('approved', $deposit_status, true);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment'])) {
    $installment_number = (int)($_POST['installment_number'] ?? 1);

    // File upload handling (unchanged - secure)
    if ($_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK || empty($_FILES['payment_proof']['name'])) {
        $_SESSION['error'] = "Please upload payment proof.";
    } else {
        $file_tmp = $_FILES['payment_proof']['tmp_name'];
        $file_name = $_FILES['payment_proof']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];

        if (!in_array($file_ext, $allowed) || $_FILES['payment_proof']['size'] > 5*1024*1024) {
            $_SESSION['error'] = "Invalid file. Only JPG/PNG allowed, max 5MB.";
        } else {
            $upload_dir = '../Uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $new_name = uniqid('proof_') . '.' . $file_ext;
            $upload_path = $upload_dir . $new_name;

            if (move_uploaded_file($file_tmp, $upload_path)) {
                $insert = "INSERT INTO deposits (amount, image, name, email, currency, created_at, updated_at, 
                           payment_plan, installment_number, approval_status) 
                           VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, 'pending')";
                $stmt = mysqli_prepare($con, $insert);
                mysqli_stmt_bind_param($stmt, "dssssii", $installment_amount, $upload_path, $user['name'], $email, $currency, $payment_plan, $installment_number);
                
                if (mysqli_stmt_execute($stmt)) {
                    // NEW VERIFICATION LOGIC
                    $verify_now = false;
                    if ($payment_plan == 1) {
                        $verify_now = true; // One-time: verify immediately
                    } elseif (in_array($payment_plan, [2, 4]) && $installment_number == $payment_plan) {
                        $verify_now = true; // Last installment
                    }

                    if ($verify_now) {
                        $update = "UPDATE users SET verify = 1, verify_time = NOW() WHERE email = ?";
                        $ustmt = mysqli_prepare($con, $update);
                        mysqli_stmt_bind_param($ustmt, "s", $email);
                        mysqli_stmt_execute($ustmt);
                        mysqli_stmt_close($ustmt);
                        $user_verified = true;
                    }

                    $_SESSION['success'] = $payment_plan > 1 
                        ? "Installment $installment_number of $payment_plan submitted successfully!" . ($verify_now ? " Your account is now verified!" : "")
                        : "Payment submitted and your account has been verified!";
                } else {
                    $_SESSION['error'] = "Failed to save payment.";
                }
                mysqli_stmt_close($stmt);
            } else {
                $_SESSION['error'] = "Failed to upload file.";
            }
        }
    }
    header("Location: verify-complete.php?verification_method=" . urlencode($method_label));
    exit(0);
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Verification Payment</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../users/index.php">Home</a></li>
                <li class="breadcrumb-item">Verify</li>
                <li class="breadcrumb-item active">Payment</li>
            </ol>
        </nav>
    </div>

    <!-- Success / Error Modals -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="modal fade show" style="display:block;" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Success</h5>
                    </div>
                    <div class="modal-body"><?= htmlspecialchars($_SESSION['success']) ?></div>
                    <div class="modal-footer">
                        <button class="btn btn-primary" onclick="window.location.href='withdrawals.php'">Continue</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="modal fade show" style="display:block;" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">Error</h5>
                    </div>
                    <div class="modal-body"><?= htmlspecialchars($_SESSION['error']) ?></div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-9">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h4><?= $payment_plan == 1 ? "One-Time Payment" : "$payment_plan Installment Plan" ?></h4>
                    </div>
                    <div class="card-body">

                        <!-- User Verification Status -->
                        <?php if ($user_verified): ?>
                            <div class="alert alert-success text-center">
                                <strong>Your account is VERIFIED!</strong>
                            </div>
                        <?php endif; ?>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <p><strong>Total Amount:</strong> <?= $currency . number_format($amount, 2) ?></p>
                                <?php if ($payment_plan > 1): ?>
                                    <p><strong>Each Installment:</strong> <?= $currency . number_format($installment_amount, 2) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 text-end">
                                <p><strong>Amount to Pay Now:</strong><br>
                                    <span class="fs-4 text-primary"><?= $currency . number_format($installment_amount, 2) ?></span>
                                    <?php if ($payment_plan > 1): ?>
                                        <br><small>Installment <?= $current_installment ?> of <?= $payment_plan ?></small>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <!-- Payment Status Display -->
                        <div class="mb-4">
                            <h5>Payment Status</h5>
                            <ul class="list-group">
                                <?php for ($i = 1; $i <= $payment_plan; $i++): ?>
                                    <?php
                                    $status = $deposit_status[$i] ?? 'not_paid';
                                    $badge = match($status) {
                                        'approved' => '<span class="badge bg-success">Approved</span>',
                                        'pending'  => '<span class="badge bg-warning">Pending Review</span>',
                                        default    => '<span class="badge bg-secondary">Not Paid</span>'
                                    };
                                    ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?= $payment_plan == 1 ? "One-Time Payment" : "Installment $i" ?>
                                        <span>
                                            <?= $badge ?>
                                            <?php if ($status === 'not_paid' && $i == $current_installment): ?>
                                                <strong class="text-primary"> ← Pay Now</strong>
                                            <?php endif; ?>
                                        </span>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </div>

                        <!-- QR Code & Payment Details -->
                        <?php if (!empty($region['qr_image']) && file_exists($region['qr_image'])): ?>
                            <div class="text-center mb-4">
                                <p><strong><?= $crypto ? "Scan to Pay with Crypto" : "Payment QR Code" ?></strong></p>
                                <img src="<?= htmlspecialchars($region['qr_image']) ?>" class="img-fluid rounded" style="max-width: 220px;">
                            </div>
                        <?php endif; ?>

                        <div class="row text-center mb-4">
                            <div class="col-md-4"><strong><?= htmlspecialchars($region['Channel']) ?>:</strong><br><?= htmlspecialchars($region['chnl_value'] ?? $region['Channel']) ?></div>
                            <div class="col-md-4"><strong><?= htmlspecialchars($region['Channel_name']) ?>:</strong><br><?= htmlspecialchars($region['chnl_name_value'] ?? $region['Channel_name']) ?></div>
                            <div class="col-md-4"><strong><?= htmlspecialchars($region['Channel_number']) ?>:</strong><br><?= htmlspecialchars($region['chnl_number_value'] ?? $region['Channel_number']) ?></div>
                        </div>

                        <!-- Upload Form (Only if not all paid) -->
                        <?php if (!$all_approved || !$has_any_deposit): ?>
                            <hr>
                            <form action="" method="POST" enctype="multipart/form-data" class="text-center">
                                <input type="hidden" name="verification_method" value="<?= htmlspecialchars($method_label) ?>">
                                <input type="hidden" name="amount" value="<?= $installment_amount ?>">
                                <input type="hidden" name="installment_number" value="<?= $current_installment ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label"><strong>Upload Payment Proof</strong> (JPG/PNG, max 5MB)</label>
                                    <input type="file" name="payment_proof" class="form-control" accept="image/*" required>
                                </div>
                                
                                <button type="submit" name="verify_payment" class="btn btn-success btn-lg">
                                    Submit Payment Proof
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <strong>All payments completed and approved!</strong><br>
                                You can now withdraw your earnings.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include('inc/footer.php'); ?>
