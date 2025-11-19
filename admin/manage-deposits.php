<?php
session_start();
include('inc/header.php');
include('inc/navbar.php');
include('inc/sidebar.php');
include('../config/dbcon.php'); // Include database connection
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
    </div><!-- End Page Title -->

    <div class="card">
        <div class="card-body">
            <!-- Search Bar -->
            <div class="mb-3 mt-4">
                <input type="text" id="searchInput" class="form-control" placeholder="Search by name or email..." style="max-width: 400px;">
            </div>

            <!-- Bordered Table -->
            <div class="table-responsive">
                <table class="table table-borderless" id="depositsTable">
                    <thead>
                        <tr>
                            <th scope="col">Amount</th>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Installment</th>
                            <th scope="col">Payment Proof</th>
                            <th scope="col">Status</th>
                            <th scope="col">Date</th>
                            <th scope="col">Time</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT d.id, d.amount, d.currency, d.name, d.email, d.image, d.approval_status, 
                                         d.created_at, d.payment_plan, d.installment_number, u.id AS user_id
                                  FROM deposits d
                                  LEFT JOIN users u ON d.email = u.email
                                  ORDER BY d.created_at DESC";

                        $query_run = mysqli_query($con, $query);

                        if (!$query_run) {
                            echo "<tr><td colspan='9'>Error: " . mysqli_error($con) . "</td></tr>";
                        } elseif (mysqli_num_rows($query_run) == 0) {
                            echo "<tr><td colspan='9' class='text-center py-4'>No deposits found.</td></tr>";
                        } else {
                            $grouped = [];
                            while ($data = mysqli_fetch_assoc($query_run)) {
                                $dateTime = new DateTime($data['created_at']);
                                $dateTime->modify('+5 hours');
                                $dateKey = $dateTime->format('d M Y'); // e.g., 19 Nov 2025
                                $time = $dateTime->format('H bright:i:s');

                                $data['formatted_date'] = $dateKey;
                                $data['formatted_time'] = $time;
                                $grouped[$dateKey][] = $data;
                            }

                            foreach ($grouped as $date => $deposits) {
                                $depositCount = count($deposits);
                                $collapseId = 'collapse-' . preg_replace('/[^a-zA-Z0-9]/', '', $date);
                                ?>
                                <!-- Date Group Header -->
                                <tr class="table-primary fw-bold bg-light">
                                    <td colspan="9">
                                        <a class="text-dark text-decoration-none d-flex align-items-center" 
                                           data-bs-toggle="collapse" 
                                           href="#<?= $collapseId ?>" 
                                           role="button" 
                                           aria-expanded="true">
                                            <i class="bi bi-chevron-right me-2 transition-chevron"></i>
                                            <?= htmlspecialchars($date) ?> 
                                            <span class="badge bg-primary ms-2"><?= $depositCount ?> deposit<?= $depositCount > 1 ? 's' : '' ?></span>
                                        </a>
                                    </td>
                                </tr>

                                <tr class="collapse show" id="<?= $collapseId ?>">
                                    <td colspan="9" class="p-0 border-0">
                                        <table class="table table-sm table-hover mb-0">
                                            <?php foreach ($deposits as $data):
                                                $deposit_id       = htmlspecialchars($data['id']);
                                                $amount           = htmlspecialchars($data['amount']);
                                                $currency         = htmlspecialchars($data['currency'] ?? '$');
                                                $name             = htmlspecialchars($data['name']);
                                                $email            = htmlspecialchars($data['email'] ?? 'No Email');
                                                $image            = htmlspecialchars($data['image']);
                                                $approval_status  = htmlspecialchars($data['approval_status']);
                                                $payment_plan     = (int)($data['payment_plan'] ?? 1);
                                                $installment_number = (int)($data['installment_number'] ?? 1);
                                                $user_id          = htmlspecialchars($data['user_id'] ?? '');

                                                $display_status = ucfirst($approval_status);
                                                $installment_display = $payment_plan > 1 ? "$installment_number/$payment_plan" : "One-Time";
                                            ?>
                                                <tr>
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
                                                    <td><?= $data['formatted_date'] ?></td>
                                                    <td><?= $data['formatted_time'] ?></td>
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
                                            <?php endforeach; ?>
                                        </table>
                                    </td>
                                </tr>
                                <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <!-- End Bordered Table -->
        </div>
    </div>

    <!-- Status Change Modal -->
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

    <!-- Installment Change Modal -->
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
                        <label class="form-label">Current Installment Number</label>
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

<style>
    .transition-chevron {
        transition: transform 0.25s ease;
    }
    .collapse.show .transition-chevron {
        transform: rotate(90deg);
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Basic real-time search (works on name/email)
    document.getElementById('searchInput').addEventListener('input', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('#depositsTable tbody tr').forEach(row => {
            const name = row.querySelector('.deposit-name')?.textContent.toLowerCase() || '';
            const email = row.querySelector('.deposit-email')?.textContent.toLowerCase() || '';
            const isVisible = name.includes(term) || email.includes(term);
            row.style.display = isVisible ? '' : 'none';
        });
    });

    // Open Status Modal
    window.openStatusModal = function(depositId, currentStatus) {
        document.getElementById('newStatusSelect').value = currentStatus;
        document.getElementById('depositId').value = depositId;
        new bootstrap.Modal(document.getElementById('statusModal')).show();
    };

    // Open Installment Modal
    window.openInstallmentModal = function(depositId, paymentPlan, installmentNumber) {
        document.getElementById('paymentPlanInput').value = paymentPlan;
        document.getElementById('installmentNumberInput').value = installmentNumber;
        document.getElementById('installmentDepositId').value = depositId;
        new bootstrap.Modal(document.getElementById('installmentModal')).show();
    };

    // Save Status
    document.getElementById('saveStatusButton').addEventListener('click', function() {
        const depositId = document.getElementById('depositId').value;
        const newStatus = document.getElementById('newStatusSelect').value;
        const badge = document.querySelector(`.status-badge[data-deposit-id="${depositId}"]`);
        const currentStatus = badge.getAttribute('data-current-status');

        if (newStatus === currentStatus) {
            bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
            return;
        }

        fetch('update-deposit-status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `deposit_id=${depositId}&approval_status=${newStatus}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                badge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                badge.className = `badge text-light status-badge ${newStatus === 'pending' ? 'bg-warning' : newStatus === 'approved' ? 'bg-success' : 'bg-danger'}`;
                badge.setAttribute('data-current-status', newStatus);
                bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
            } else {
                alert('Error: ' + data.message);
            }
        });
    });

    // Save Installment
    document.getElementById('saveInstallmentButton').addEventListener('click', function() {
        const depositId = document.getElementById('installmentDepositId').value;
        const paymentPlan = parseInt(document.getElementById('paymentPlanInput').value);
        const installmentNumber = parseInt(document.getElementById('installmentNumberInput').value);

        if (installmentNumber > paymentPlan) {
            alert('Current installment cannot exceed total plan.');
            return;
        }

        fetch('update-deposit-installment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `deposit_id=${depositId}&payment_plan=${paymentPlan}&installment_number=${installmentNumber}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const badge = document.querySelector(`.installment-badge[data-deposit-id="${depositId}"]`);
                badge.textContent = paymentPlan > 1 ? `${installmentNumber}/${paymentPlan}` : 'One-Time';
                badge.setAttribute('data-payment-plan', paymentPlan);
                badge.setAttribute('data-installment-number', installmentNumber);
                bootstrap.Modal.getInstance(document.getElementById('installmentModal')).hide();
            } else {
                alert('Error: ' + data.message);
            }
        });
    });
});
</script>
</html>
