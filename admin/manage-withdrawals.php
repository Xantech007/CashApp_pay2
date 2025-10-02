<?php
session_start();
include('inc/header.php');
include('inc/sidebar.php');
include('inc/navbar.php');
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Pending Withdrawals</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard">Home</a></li>
                <li class="breadcrumb-item">Users</li>
                <li class="breadcrumb-item active">Pending Withdrawals</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <div class="card">
        <div class="card-body">
            <!-- Bordered Table -->
            <div class="table-responsive">
                <table class="table table-borderless">
                    <thead>
                        <tr>
                            <th scope="col">Amount</th>
                            <th scope="col">Channel</th>
                            <th scope="col">Channel Name</th>
                            <th scope="col">Channel Number</th>
                            <th scope="col">Status</th>
                            <th scope="col">Date</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        include('../config/dbcon.php'); // Include database connection
                        // Generate CSRF token for form security
                        if (empty($_SESSION['csrf_token'])) {
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        }
                        // Query withdrawals table only
                        $query = "SELECT id, amount, channel, channel_name, channel_number, status, created_at, email 
                                  FROM withdrawals 
                                  WHERE status = '0'";
                        $query_run = mysqli_query($con, $query);
                        if (mysqli_num_rows($query_run) > 0) {
                            foreach ($query_run as $data) {
                                // Fetch currency from region_settings based on user's email
                                $email = $data['email'];
                                $user_query = "SELECT country FROM users WHERE email = ? LIMIT 1";
                                $stmt = $con->prepare($user_query);
                                $stmt->bind_param("s", $email);
                                $stmt->execute();
                                $user_result = $stmt->get_result();
                                $currency = '$'; // Default currency
                                if ($user_result && $user_result->num_rows > 0) {
                                    $user = $user_result->fetch_assoc();
                                    $country = $user['country'];
                                    $region_query = "SELECT currency FROM region_settings WHERE country = ? LIMIT 1";
                                    $region_stmt = $con->prepare($region_query);
                                    $region_stmt->bind_param("s", $country);
                                    $region_stmt->execute();
                                    $region_result = $region_stmt->get_result();
                                    if ($region_result && $region_result->num_rows > 0) {
                                        $region = $region_result->fetch_assoc();
                                        $currency = $region['currency'] ?? '$';
                                    }
                                    $region_stmt->close();
                                }
                                $stmt->close();
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($currency) ?><?= number_format($data['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($data['channel']) ?: 'N/A' ?></td>
                            <td><?= htmlspecialchars($data['channel_name']) ?: 'N/A' ?></td>
                            <td><?= htmlspecialchars($data['channel_number']) ?: 'N/A' ?></td>
                            <td>
                                <?php
                                switch ($data['status']) {
                                    case 0:
                                        echo '<span class="badge bg-warning text-light">Pending</span>';
                                        break;
                                    case 1:
                                        echo '<span class="badge bg-success text-light">Approved</span>';
                                        break;
                                    case 2:
                                        echo '<span class="badge bg-danger text-light">Rejected</span>';
                                        break;
                                }
                                ?>
                            </td>
                            <td><?= date('d-M-Y', strtotime($data['created_at'])) ?></td>
                            <td>
                                <button type="button" class="btn btn-primary btn-sm update-status-btn" 
                                        data-bs-toggle="modal" data-bs-target="#statusModal" 
                                        data-id="<?= htmlspecialchars($data['id']) ?>">
                                    Update Status
                                </button>
                            </td>
                        </tr>
                        <?php
                            }
                        } else {
                        ?>
                        <tr>
                            <td colspan="7" class="text-center">No pending withdrawals found.</td>
                        </tr>
                        <?php
                        }
                        $con->close();
                        ?>
                    </tbody>
                </table>
            </div>
            <!-- End Bordered Table -->
        </div>
    </div>

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="statusModalLabel">Update Withdrawal Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="codes/withdrawals.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="withdrawal_id" id="withdrawal_id">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="mb-3">
                            <label for="status" class="form-label">Select Status</label>
                            <select class="form-select" name="status" id="status" required>
                                <option value="">-- Select Status --</option>
                                <option value="0">Pending</option>
                                <option value="1">Approved</option>
                                <option value="2">Rejected</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" name="update_status">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main><!-- End #main -->

<!-- JavaScript to Pass Withdrawal ID to Modal -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    var buttons = document.querySelectorAll('.update-status-btn');
    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            var withdrawalId = this.getAttribute('data-id');
            document.getElementById('withdrawal_id').value = withdrawalId;
        });
    });
});
</script>

<?php include('inc/footer.php'); ?>
</html>
