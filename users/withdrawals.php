<?php
session_start();
include('../config/dbcon.php');
include('inc/header.php');
include('inc/navbar.php');
?>
<!-- ======= Sidebar ======= -->
<main id="main" class="main">
    <div class="pagetitle">
        <?php
        $email = mysqli_real_escape_string($con, $_SESSION['email']);
        
        // Fetch user data
        $query = "SELECT balance, verify, message, country, verify_time FROM users WHERE email = ? LIMIT 1";
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $balance = $row['balance'];
            $verify = (int)($row['verify'] ?? 0);
            $message = $row['message'] ?? '';
            $user_country = $row['country'];
            $verify_time = $row['verify_time'];

            // Auto-expire verification after 315 minutes
            if ($verify == 1 && !empty($verify_time)) {
                $current_time = new DateTime('now', new DateTimeZone('Africa/Lagos'));
                $verify_time_dt = new DateTime($verify_time, new DateTimeZone('Africa/Lagos'));
                $interval = $current_time->diff($verify_time_dt);
                $total_minutes = ($interval->days * 1440) + ($interval->h * 60) + $interval->i;

                if ($total_minutes >= 315) {
                    $update = "UPDATE users SET verify = 0 WHERE email = ?";
                    $up_stmt = mysqli_prepare($con, $update);
                    mysqli_stmt_bind_param($up_stmt, "s", $email);
                    mysqli_stmt_execute($up_stmt);
                    mysqli_stmt_close($up_stmt);
                    $verify = 0;
                }
            }
        } else {
            $_SESSION['error'] = "User not found.";
            header("Location: ../signin.php");
            exit();
        }
        mysqli_stmt_close($stmt);

        // Fetch payment channel details
        $payment_query = "SELECT crypto, Channel, Channel_name, Channel_number, currency,
                                 alt_channel, alt_ch_name, alt_ch_number, alt_currency
                          FROM region_settings
                          WHERE country = ?
                          LIMIT 1";
        $pay_stmt = mysqli_prepare($con, $payment_query);
        mysqli_stmt_bind_param($pay_stmt, "s", $user_country);
        mysqli_stmt_execute($pay_stmt);
        $payment_result = mysqli_stmt_get_result($pay_stmt);

        $channel_label = 'Bank';
        $channel_name_label = 'Account Name';
        $channel_number_label = 'Account Number';
        $currency = '$';

        if ($payment_data = mysqli_fetch_assoc($payment_result)) {
            if ($payment_data['crypto'] == 1) {
                $channel_label = $payment_data['alt_channel'] ?? 'Crypto';
                $channel_name_label = $payment_data['alt_ch_name'] ?? 'Crypto Name';
                $channel_number_label = $payment_data['alt_ch_number'] ?? 'Crypto Address';
                $currency = $payment_data['alt_currency'] ?? 'USD';
            } else {
                $channel_label = $payment_data['Channel'] ?? 'Bank';
                $channel_name_label = $payment_data['Channel_name'] ?? 'Account Name';
                $channel_number_label = $payment_data['Channel_number'] ?? 'Account Number';
                $currency = $payment_data['currency'] ?? 'USD';
            }
        }
        mysqli_stmt_close($pay_stmt);
        ?>

        <h1>Available Balance: <?= $currency ?><?= number_format($balance, 2) ?></h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Home</a></li>
                <li class="breadcrumb-item">Users</li>
                <li class="breadcrumb-item active">Withdrawals</li>
            </ol>
        </nav>
    </div>

    <!-- Messages -->
    <?php if (!empty(trim($message))): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong><?= htmlspecialchars($message) ?></strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php
    if (isset($_SESSION['error'])) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error']) . '</div>';
        unset($_SESSION['error']);
    }
    if (isset($_SESSION['success'])) {
        echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
        unset($_SESSION['success']);
    }
    ?>

    <!-- Withdrawal Request Card -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Request Withdrawal</h5>
            <p>Minimum withdrawal amount is $50. Please provide correct payment details.</p>

            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#withdrawalModal">
                New Withdrawal Request
            </button>

            <!-- Modal -->
            <div class="modal fade" id="withdrawalModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Withdrawal Request (Min $50)</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form action="../codes/withdrawals.php" method="POST" id="withdrawForm">
                                <div class="mb-3">
                                    <label class="form-label">Amount (USD)</label>
                                    <input type="number" name="amount" class="form-control" min="50" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><?= htmlspecialchars($channel_label) ?></label>
                                    <input type="text" name="channel" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><?= htmlspecialchars($channel_name_label) ?></label>
                                    <input type="text" name="channel_name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><?= htmlspecialchars($channel_number_label) ?></label>
                                    <input type="text" name="channel_number" class="form-control" required>
                                </div>
                                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                                <input type="hidden" name="balance" value="<?= $balance ?>">
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" form="withdrawForm" name="withdraw" class="btn btn-primary">Submit Request</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Withdrawal History -->
    <div class="pagetitle">
        <h1>Withdrawal History</h1>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                <h5 class="card-title mb-0">All Requests</h5>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-primary active filter-btn" data-status="all">All</button>
                    <button type="button" class="btn btn-outline-warning filter-btn" data-status="0">Pending</button>
                    <button type="button" class="btn btn-outline-success filter-btn" data-status="1">Completed</button>
                    <button type="button" class="btn btn-outline-danger filter-btn" data-status="2">Rejected</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover" id="withdrawalsTable">
                    <thead>
                        <tr>
                            <th>Amount</th>
                            <th><?= htmlspecialchars($channel_label) ?></th>
                            <th><?= htmlspecialchars($channel_name_label) ?></th>
                            <th><?= htmlspecialchars($channel_number_label) ?></th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT id, amount, channel, channel_name, channel_number, status, created_at
                                  FROM withdrawals 
                                  WHERE email = ?
                                  ORDER BY created_at DESC";
                        $stmt = mysqli_prepare($con, $query);
                        mysqli_stmt_bind_param($stmt, "s", $email);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);

                        if (mysqli_num_rows($result) > 0):
                            while ($row = mysqli_fetch_assoc($result)):
                        ?>
                                <tr data-status="<?= $row['status'] ?>">
                                    <td><?= $currency ?><?= number_format($row['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($row['channel'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['channel_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['channel_number'] ?? '-') ?></td>
                                    <td>
                                        <?php
                                        switch ($row['status']) {
                                            case 0: echo '<span class="badge bg-warning">Pending</span>'; break;
                                            case 1: echo '<span class="badge bg-success">Completed</span>'; break;
                                            case 2: echo '<span class="badge bg-danger">Rejected</span>'; break;
                                            default: echo '<span class="badge bg-secondary">Unknown</span>'; break;
                                        }
                                        ?>
                                    </td>
                                    <td><?= date('d M Y • H:i', strtotime($row['created_at'])) ?></td>
                                    <td>
                                        <?php if ($row['status'] == 0): ?>
                                            <form action="../codes/withdrawals.php" method="POST" 
                                                  onsubmit="return confirm('Delete this pending request?');" class="d-inline">
                                                <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                                <button type="submit" name="delete" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                        <?php
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">No withdrawal requests found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($verify === 0 || $verify === 1): ?>
        <div class="action-buttons mt-4">
            <a href="verify.php" class="btn btn-warning btn-lg w-100 w-md-auto">Verify Your Account</a>
        </div>
    <?php endif; ?>

</main>

<!-- Filter JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const buttons = document.querySelectorAll('.filter-btn');
    const rows = document.querySelectorAll('#withdrawalsTable tbody tr[data-status]');

    buttons.forEach(button => {
        button.addEventListener('click', function () {
            buttons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');

            const status = this.dataset.status;

            rows.forEach(row => {
                if (status === 'all') {
                    row.style.display = '';
                } else {
                    row.style.display = (row.dataset.status === status) ? '' : 'none';
                }
            });
        });
    });
});
</script>

<?php include('inc/footer.php'); ?>
</body>
</html>
