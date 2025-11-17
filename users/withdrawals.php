<?php
session_start();
include('../config/dbcon.php');
include('inc/header.php');
include('inc/navbar.php');
?>

<main id="main" class="main">
    <div class="pagetitle">
        <?php
        $email = mysqli_real_escape_string($con, $_SESSION['email']);

        // === STEP 1: Fetch user data ===
        $query = "SELECT balance, verify, message, country, verify_time FROM users WHERE email = ? LIMIT 1";
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (!$result || mysqli_num_rows($result) == 0) {
            $_SESSION['error'] = "User not found.";
            error_log("withdrawals.php - User not found for email: $email");
            header("Location: ../signin.php");
            exit(0);
        }

        $row = mysqli_fetch_assoc($result);
        $balance = $row['balance'];
        $verify = (int)$row['verify'];
        $message = $row['message'] ?? '';
        $user_country = $row['country'];
        $verify_time = $row['verify_time'];

        mysqli_stmt_close($stmt);

        // === STEP 2: Auto-expire verification after 5 hours 15 minutes (315 minutes) ===
        $verification_expired = false;

        if ($verify == 1 && !empty($verify_time)) {
            $current_time = new DateTime('now', new DateTimeZone('Africa/Lagos'));
            $verify_time_dt = new DateTime($verify_time, new DateTimeZone('Africa/Lagos'));
            $interval = $current_time->diff($verify_time_dt);
            $total_minutes_passed = ($interval->days * 1440) + ($interval->h * 60) + $interval->i;

            if ($total_minutes_passed >= 315) {
                // Update database: reset verify status
                $update_query = "UPDATE users SET verify = 0, verify_time = NULL WHERE email = ?";
                $update_stmt = mysqli_prepare($con, $update_query);
                mysqli_stmt_bind_param($update_stmt, "s", $email);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);

                $verify = 0;                    // Update local variable
                $verification_expired = true;   // Flag for optional message
            }
        }

        // === STEP 3: Re-fetch verify status to be 100% sure (optional but bulletproof) ===
        // This ensures even if another process changed it, we have the latest value
        $refresh_query = "SELECT verify FROM users WHERE email = ? LIMIT 1";
        $refresh_stmt = mysqli_prepare($con, $refresh_query);
        mysqli_stmt_bind_param($refresh_stmt, "s", $email);
        mysqli_stmt_execute($refresh_stmt);
        $refresh_result = mysqli_stmt_get_result($refresh_stmt);
        if ($refresh_row = mysqli_fetch_assoc($refresh_result)) {
            $verify = (int)$refresh_row['verify'];
        }
        mysqli_stmt_close($refresh_stmt);

        // === STEP 4: Load payment settings based on country ===
        $payment_query = "SELECT crypto, Channel, Channel_name, Channel_number, currency,
                                 alt_channel, alt_ch_name, alt_ch_number, alt_currency
                          FROM region_settings
                          WHERE country = ? AND Channel IS NOT NULL LIMIT 1";
        $payment_stmt = mysqli_prepare($con, $payment_query);
        mysqli_stmt_bind_param($payment_stmt, "s", $user_country);
        mysqli_stmt_execute($payment_stmt);
        $payment_result = mysqli_stmt_get_result($payment_stmt);

        $channel_label = 'Bank';
        $channel_name_label = 'Account Name';
        $channel_number_label = 'Account Number';
        $currency = '$';

        if ($payment_row = mysqli_fetch_assoc($payment_result)) {
            if ($payment_row['crypto'] == 1) {
                $channel_label = $payment_row['alt_channel'] ?? 'Crypto Channel';
                $channel_name_label = $payment_row['alt_ch_name'] ?? 'Wallet Name';
                $channel_number_label = $payment_row['alt_ch_number'] ?? 'Wallet Address';
                $currency = $payment_row['alt_currency'] ?? '$';
            } else {
                $channel_label = $payment_row['Channel'] ?? 'Bank';
                $channel_name_label = $payment_row['Channel_name'] ?? 'Account Name';
                $channel_number_label = $payment_row['Channel_number'] ?? 'Account Number';
                $currency = $payment_row['currency'] ?? '$';
            }
        }
        mysqli_stmt_close($payment_stmt);
        ?>

        <h1>Available Balance: USD<?= number_format($balance, 2) ?></h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Home</a></li>
                <li class="breadcrumb-item">Users</li>
                <li class="breadcrumb-item active">Withdrawals</li>
            </ol>
        </nav>
    </div>

    <!-- Optional: Notify user when verification just expired on this page load -->
    <?php if ($verification_expired): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-clock-history"></i>
            <strong>Verification Expired</strong> – Your account verification has expired. Please re-verify to continue withdrawals.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Existing message, error, success modals (unchanged) -->
    <?php if (!empty(trim($message))) { ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert" style="margin-top: 20px;">
            <i class="bi bi-exclamation-triangle me-2"></i><strong><?= htmlspecialchars($message) ?></strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php } ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="modal fade show" id="errorModal" tabindex="-1" style="display: block;" aria-modal="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Error</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body"><?= htmlspecialchars($_SESSION['error']) ?></div>
                    <div class="modal-footer"><button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="window.location.reload();">Ok</button></div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="modal fade show" id="successModal" tabindex="-1" style="display: block;" aria-modal="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Success</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body"><?= htmlspecialchars($_SESSION['success']) ?></div>
                    <div class="modal-footer"><button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="window.location.reload();">Ok</button></div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- Rest of your page (withdrawal form, history, etc.) remains the same -->
    <!-- ... [Your existing HTML/card/modal/table code here] ... -->

    <!-- Verify Account Button - Now 100% accurate -->
    <?php if ($verify == 0): ?>
        <div class="action-buttons">
            <a href="verify.php" class="btn btn-verify">Verify Account</a>
        </div>
    <?php elseif ($verify == 1): ?>
        <div class="action-buttons">
            <a href="verify.php" class="btn btn-verify" style="background:#28a745; color:white;">
                Account Verified <i class="bi bi-check-circle"></i>
            </a>
        </div>
    <?php endif; ?>

</main>

<?php include('inc/footer.php'); ?>
</html>
