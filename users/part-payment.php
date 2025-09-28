<?php
session_start();
include('../config/dbcon.php');
include('inc/header.php');
include('inc/navbar.php');

// Check if user is logged in
if (!isset($_SESSION['auth'])) {
    $_SESSION['error'] = "Please log in to access this page.";
    error_log("part-payment.php - User not logged in, redirecting to signin.php");
    header("Location: ../signin.php");
    exit(0);
}

// Get user details
$email = mysqli_real_escape_string($con, $_SESSION['email']);
$user_query = "SELECT id, name, country, payment_plan FROM users WHERE email = '$email' LIMIT 1";
$user_query_run = mysqli_query($con, $user_query);
if ($user_query_run && mysqli_num_rows($user_query_run) > 0) {
    $user_data = mysqli_fetch_assoc($user_query_run);
    $user_id = $user_data['id'];
    $user_name = $user_data['name'];
    $user_country = $user_data['country'];
    $current_payment_plan = $user_data['payment_plan'] ?? 1;
} else {
    $_SESSION['error'] = "User not found.";
    error_log("part-payment.php - User not found for email: $email");
    header("Location: ../signin.php");
    exit(0);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['payment_plan'])) {
        $payment_plan = trim($_POST['payment_plan']);
        if (in_array($payment_plan, ['1', '2', '4'])) {
            // Check for existing approved payments
            $check_payments_query = "SELECT COUNT(*) as approved_count FROM deposits WHERE email = ? AND approval_status = 'approved'";
            $check_stmt = mysqli_prepare($con, $check_payments_query);
            mysqli_stmt_bind_param($check_stmt, "s", $email);
            mysqli_stmt_execute($check_stmt);
            $result = mysqli_stmt_get_result($check_stmt);
            $approved_count = mysqli_fetch_assoc($result)['approved_count'];
            mysqli_stmt_close($check_stmt);

            if ($approved_count > 0) {
                $_SESSION['error'] = "Cannot change payment plan because you have already made approved payments.";
                error_log("part-payment.php - Attempt to change payment plan blocked due to $approved_count approved payments for email: $email");
                header("Location: part-payment.php");
                exit(0);
            }

            // Clear pending deposits to reset payment plan
            $delete_pending_query = "DELETE FROM deposits WHERE email = ? AND approval_status = 'pending'";
            $delete_stmt = mysqli_prepare($con, $delete_pending_query);
            mysqli_stmt_bind_param($delete_stmt, "s", $email);
            mysqli_stmt_execute($delete_stmt);
            mysqli_stmt_close($delete_stmt);

            // Update payment_plan in users table
            $update_query = "UPDATE users SET payment_plan = ? WHERE email = ?";
            $stmt = mysqli_prepare($con, $update_query);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "is", $payment_plan, $email);
                if (mysqli_stmt_execute($stmt)) {
                    // Optionally update deposits table
                    $insert_deposit_query = "INSERT INTO deposits (user_id, name, email, payment_plan, amount, status, approval_status, created_at)
                                             VALUES (?, ?, ?, ?, 0.00, 0, 'pending', NOW())
                                             ON DUPLICATE KEY UPDATE payment_plan = ?, updated_at = NOW()";
                    $deposit_stmt = mysqli_prepare($con, $insert_deposit_query);
                    if ($deposit_stmt) {
                        mysqli_stmt_bind_param($deposit_stmt, "issii", $user_id, $user_name, $email, $payment_plan, $payment_plan);
                        mysqli_stmt_execute($deposit_stmt);
                        mysqli_stmt_close($deposit_stmt);
                    }

                    $_SESSION['payment_plan'] = $payment_plan;
                    error_log("part-payment.php - Payment plan updated to $payment_plan for user: $user_name, email: $email, previous plan: $current_payment_plan");
                    header("Location: verify-complete.php?verification_method=" . urlencode($_GET['verification_method'] ?? 'Local Bank Deposit/Transfer'));
                    exit(0);
                } else {
                    $_SESSION['error'] = "Failed to update payment plan.";
                    error_log("part-payment.php - Failed to update users.payment_plan: " . mysqli_error($con));
                }
                mysqli_stmt_close($stmt);
            } else {
                $_SESSION['error'] = "Failed to prepare update query.";
                error_log("part-payment.php - Failed to prepare update query: " . mysqli_error($con));
            }
        } else {
            $_SESSION['error'] = "Invalid payment plan selected.";
            error_log("part-payment.php - Invalid payment plan: $payment_plan");
        }
        header("Location: part-payment.php");
        exit(0);
    }
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Select Payment Plan</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../users/index.php">Home</a></li>
                <li class="breadcrumb-item">Verify</li>
                <li class="breadcrumb-item active">Payment Plan</li>
            </ol>
        </nav>
    </div>

    <!-- Error Messages -->
    <?php if (isset($_SESSION['error'])) { ?>
        <div class="modal fade show" id="errorModal" tabindex="-1" style="display: block;" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Error</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?= htmlspecialchars($_SESSION['error']) ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" onclick="window.location.href='part-payment.php'">Ok</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    <?php }
    unset($_SESSION['error']);
    ?>

    <div class="container text-center">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card text-center">
                    <div class="card-header">
                        Choose a Payment Plan
                    </div>
                    <div class="card-body mt-2">
                        <p>Select how many installments you would like to pay for the verification amount.</p>
                        <p>Current Payment Plan: 
                            <?php
                            if ($current_payment_plan == 1) {
                                echo "One Time Payment";
                            } elseif ($current_payment_plan == 2) {
                                echo "2 Times Payment";
                            } elseif ($current_payment_plan == 4) {
                                echo "4 Times Payment";
                            } else {
                                echo "None Selected";
                            }
                            ?>
                        </p>
                        <form action="part-payment.php" method="POST" id="paymentPlanForm">
                            <div class="d-flex flex-column align-items-center mt-3">
                                <button type="submit" name="payment_plan" value="1" class="btn btn-primary mb-2 w-50">One Time Payment</button>
                                <button type="submit" name="payment_plan" value="2" class="btn btn-primary mb-2 w-50">2 Times Payment</button>
                                <button type="submit" name="payment_plan" value="4" class="btn btn-primary mb-2 w-50">4 Times Payment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include('inc/footer.php'); ?>
