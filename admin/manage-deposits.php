<?php
session_start();
include('inc/header.php');
include('inc/navbar.php');
include('inc/sidebar.php');
include('../config/dbcon.php');
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Manage Deposits</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard">Home</a></li>
                <li class="breadcrumb-item">Users</li>
                <li class="breadcrumb-item active">Manage Deposits</li>
            </ol>
        </nav>
    </div>

    <div class="card">
        <div class="card-body">
            <!-- Date Filter -->
            <div class="row align-items-center mt-4 mb-3">
                <div class="col-md-4">
                    <form method="GET" id="dateForm">
                        <div class="input-group">
                            <span class="input-group-text">Filter by Date</span>
                            <input type="date" name="date" class="form-control" 
                                   value="<?= htmlspecialchars($_GET['date'] ?? date('Y-m-d')) ?>" 
                                   onchange="this.form.submit()">
                            <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='manage-deposits.php'">
                                Today
                            </button>
                        </div>
                    </form>
                </div>
                <div class="col-md-8 text-end">
                    <small class="text-muted">
                        Showing deposits for: 
                        <strong>
                            <?= $_GET['date'] ?? 'Today (' . date('d M Y') . ')' ?>
                        </strong>
                    </small>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="mb-3">
                <input type="text" id="searchInput" class="form-control" placeholder="Search by name or email..." style="max-width: 400px;">
            </div>

            <div class="table-responsive">
                <table class="table table-borderless" id="depositsTable">
                    <thead>
                        <tr>
                            <th>Amount</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Installment</th>
                            <th>Payment Proof</th>
                            <th>Status</th>
                            <th>Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Determine selected date (default: today)
                        $selected_date = $_GET['date'] ?? date('Y-m-d');
                        $selected_date = date('Y-m-d', strtotime($selected_date)); // sanitize

                        // Query only deposits from the selected date (adjusted +5 hours)
                        $query = "SELECT d.id, d.amount, d.currency, d.name, d.email, d.image, d.approval_status, 
                                         d.created_at, d.payment_plan, d.installment_number, u.id AS user_id
                                  FROM deposits d
                                  LEFT JOIN users u ON d.email = u.email
                                  WHERE DATE(DATE_ADD(d.created_at, INTERVAL 5 HOUR)) = ?
                                  ORDER BY d.created_at DESC";

                        $stmt = mysqli_prepare($con, $query);
                        mysqli_stmt_bind_param($stmt, "s", $selected_date);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);

                        if (mysqli_num_rows($result) == 0) {
                            echo "<tr><td colspan='8' class='text-center py-5'>No deposits found for this date.</td></tr>";
                        } else {
                            while ($data = mysqli_fetch_assoc($result)) {
                                $dateTime = new DateTime($data['created_at']);
                                $dateTime->modify('+5 hours');
                                $time = $dateTime->format('H:i:s');

                                $deposit_id         = htmlspecialchars($data['id']);
                                $amount             = htmlspecialchars($data['amount']);
                                $currency           = htmlspecialchars($data['currency'] ?? '$');
                                $name               = htmlspecialchars($data['name']);
                                $email              = htmlspecialchars($data['email'] ?? 'No Email');
                                $image              = htmlspecialchars($data['image']);
                                $approval_status    = htmlspecialchars($data['approval_status']);
                                $payment_plan       = (int)($data['payment_plan'] ?? 1);
                                $installment_number = (int)($data['installment_number'] ?? 1);
                                $user_id            = htmlspecialchars($data['user_id'] ?? '');

                                $display_status = ucfirst($approval_status);
                                $installment_display = $payment_plan > 1 ? "$installment_number/$payment_plan" : "One-Time";
                                ?>
                                <tr class="deposit-row">
                                    <td><?= $currency ?><?= number_format($amount, 2) ?></td>
                                    <td class="deposit-name"><?= $name ?></td>
                                    <td class="deposit-email"><?= $email ?></td>
                                    <td>
                                        <span class="badge bg-info text-light installment-badge"
                                              data-deposit-id="<?= $deposit_id ?>"
                                              data-payment-plan="<?= $payment_plan ?>"
                                              data-installment-number="<?= $installment_number ?>"
                                              style="cursor: pointer;"
                                              onclick="openInstallmentModal(<?= $deposit_id ?>, <?= $payment_plan ?>, <?= $installment_number ?>)">
                                            <?= $installment_display ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($image): ?>
                                            <img src="../Uploads/<?= $image ?>" width="50" height="50" alt="Proof" class="rounded">
                                        <?php else: ?>
                                            No Image
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $approval_status === 'pending' ? 'bg-warning' : ($approval_status === 'approved' ? 'bg-success' : 'bg-danger') ?> text-light status-badge"
                                              data-deposit-id="<?= $deposit_id ?>"
                                              data-current-status="<?= $approval_status ?>"
                                              style="cursor: pointer;"
                                              onclick="openStatusModal(<?= $deposit_id ?>, '<?= $approval_status ?>')">
                                            <?= $display_status ?>
                                        </span>
                                    </td>
                                    <td><?= $time ?></td>
                                    <td>
                                        <?php if ($image): ?>
                                            <a href="../Uploads/<?= $image ?>" download class="btn btn-light btn-sm me-1">Download</a>
                                        <?php endif; ?>
                                        <?php if ($user_id): ?>
                                            <a href="edit-user.php?id=<?= urlencode($user_id) ?>" class="btn btn-light btn-sm">Edit</a>
                                        <?php else: ?>
                                            <span class="text-muted">No User</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                            }
                        }
                        mysqli_stmt_close($stmt);
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modals (Status & Installment) - Same as before -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Deposit Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <select id="newStatusSelect" class="form-select">
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <input type="hidden" id="depositId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveStatusButton">Save</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="installmentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Installment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Total Installments</label>
                        <input type="number" id="paymentPlanInput" class="form-control" min="1" value="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Current Installment</label>
                        <input type="number" id="installmentNumberInput" class="form-control" min="1" value="1">
                    </div>
                    <input type="hidden" id="installmentDepositId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveInstallmentButton">Save</button>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include('inc/footer.php'); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search filter
    document.getElementById('searchInput').addEventListener('input', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('.deposit-row').forEach(row => {
            const name = row.querySelector('.deposit-name')?.textContent.toLowerCase() || '';
            const email = row.querySelector('.deposit-email')?.textContent.toLowerCase() || '';
            row.style.display = (name.includes(term) || email.includes(term)) ? '' : 'none';
        });
    });

    // Modal Functions
    window.openStatusModal = function(id, status) {
        document.getElementById('newStatusSelect').value = status;
        document.getElementById('depositId').value = id;
        new bootstrap.Modal(document.getElementById('statusModal')).show();
    };

    window.openInstallmentModal = function(id, plan, num) {
        document.getElementById('paymentPlanInput').value = plan;
        document.getElementById('installmentNumberInput').value = num;
        document.getElementById('installmentDepositId').value = id;
        new bootstrap.Modal(document.getElementById('installmentModal')).show();
    };

    // Save Status
    document.getElementById('saveStatusButton').onclick = function() {
        const id = document.getElementById('depositId').value;
        const status = document.getElementById('newStatusSelect').value;
        const badge = document.querySelector(`.status-badge[data-deposit-id="${id}"]`);

        fetch('update-deposit-status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `deposit_id=${id}&approval_status=${status}`
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                badge.className = `badge text-light status-badge ${status === 'pending' ? 'bg-warning' : status === 'approved' ? 'bg-success' : 'bg-danger'}`;
                badge.dataset.currentStatus = status;
                bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
            }
        });
    };

    // Save Installment
    document.getElementById('saveInstallmentButton').onclick = function() {
        const id = document.getElementById('installmentDepositId').value;
        const plan = document.getElementById('paymentPlanInput').value;
        const num = document.getElementById('installmentNumberInput').value;

        if (num > plan) return alert('Installment cannot exceed total plan');

        fetch('update-deposit-installment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `deposit_id=${id}&payment_plan=${plan}&installment_number=${num}`
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const badge = document.querySelector(`.installment-badge[data-deposit-id="${id}"]`);
                badge.textContent = plan > 1 ? `${num}/${plan}` : 'One-Time';
                badge.dataset.paymentPlan = plan;
                badge.dataset.installmentNumber = num;
                bootstrap.Modal.getInstance(document.getElementById('installmentModal')).hide();
            }
        });
    };
});
</script>
</html>
