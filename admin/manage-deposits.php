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

    <!-- SEARCH BAR -->
    <div class="card mb-3">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-auto flex-grow-1">
                    <input type="text" name="q" class="form-control" placeholder="Search by name or email..." 
                           value="<?= isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
                <?php if (!empty($_GET['q'])): ?>
                <div class="col-auto">
                    <a href="?" class="btn btn-outline-secondary">Clear</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
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
                            <th>Date</th>
                            <th>Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // === PAGINATION & SEARCH ===
                        $limit = 25;
                        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                        $offset = ($page - 1) * $limit;
                        $search = trim($_GET['q'] ?? '');

                        // Build WHERE
                        $where = '';
                        $params = [];
                        $types = '';
                        if ($search !== '') {
                            $where = "WHERE d.name LIKE ? OR d.email LIKE ?";
                            $like = "%{$search}%";
                            $params = [$like, $like];
                            $types = 'ss';
                        }

                        // Count total
                        $count_sql = "SELECT COUNT(*) AS total FROM deposits d LEFT JOIN users u ON d.email = u.email $where";
                        $stmt = $con->prepare($count_sql);
                        if ($params) $stmt->bind_param($types, ...$params);
                        $stmt->execute();
                        $total = $stmt->get_result()->fetch_assoc()['total'];
                        $total_pages = max(1, ceil($total / $limit));

                        // Fetch data
                        $sql = "SELECT d.id, d.amount, d.currency, d.name, d.email, d.image, d.approval_status, 
                                       d.created_at, d.payment_plan, d.installment_number, u.id AS user_id 
                                FROM deposits d 
                                LEFT JOIN users u ON d.email = u.email 
                                $where
                                ORDER BY d.created_at DESC 
                                LIMIT ? OFFSET ?";
                        $stmt = $con->prepare($sql);
                        if ($params) {
                            $stmt->bind_param($types . 'ii', ...$params, $limit, $offset);
                        } else {
                            $stmt->bind_param('ii', $limit, $offset);
                        }
                        $stmt->execute();
                        $query_run = $stmt->get_result();

                        if ($query_run->num_rows > 0) {
                            foreach ($query_run as $data) {
                                $deposit_id = $data['id'];
                                $amount = $data['amount'];
                                $currency = $data['currency'] ?? '$';
                                $name = $data['name'];
                                $email = $data['email'] ?? 'No Email';
                                $image = $data['image'];
                                $approval_status = $data['approval_status'];
                                $payment_plan = (int)($data['payment_plan'] ?? 1);
                                $installment_number = (int)($data['installment_number'] ?? 1);
                                $display_status = ucfirst($approval_status);
                                $installment_display = $payment_plan > 1 ? "$installment_number/$payment_plan" : "One-Time";

                                $dateTime = new DateTime($data['created_at']);
                                $dateTime->modify('+5 hours');
                                $created_at = $dateTime->format('d-M-Y');
                                $time = $dateTime->format('H:i:s');
                                $user_id = $data['user_id'] ?? '';
                        ?>
                                <tr>
                                    <td><?= htmlspecialchars($currency) ?> <?= number_format($amount, 2) ?></td>
                                    <td><?= htmlspecialchars($name) ?></td>
                                    <td><?= htmlspecialchars($email) ?></td>
                                    <td>
                                        <span class="badge bg-info text-light" 
                                              style="cursor: pointer;" 
                                              onclick="openInstallmentModal(<?= $deposit_id ?>, <?= $payment_plan ?>, <?= $installment_number ?>)">
                                            <?= $installment_display ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($image): ?>
                                            <img src="../Uploads/<?= htmlspecialchars($image) ?>" style="width:50px;height:50px" alt="Proof">
                                        <?php else: ?>
                                            No Image
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge 
                                            <?= $approval_status === 'pending' ? 'bg-warning' : 
                                               ($approval_status === 'approved' ? 'bg-success' : 'bg-danger') ?> text-light" 
                                            style="cursor: pointer;" 
                                            onclick="openStatusModal(<?= $deposit_id ?>, '<?= $approval_status ?>')">
                                            <?= $display_status ?>
                                        </span>
                                    </td>
                                    <td><?= $created_at ?></td>
                                    <td><?= $time ?></td>
                                    <td>
                                        <?php if ($image): ?>
                                            <a href="../Uploads/<?= htmlspecialchars($image) ?>" download class="btn btn-light btn-sm me-1">Download</a>
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
                        } else {
                            echo '<tr><td colspan="9" class="text-center">No deposits found.</td></tr>';
                        }
                        $stmt->close();
                        ?>
                    </tbody>
                </table>

                <!-- PAGINATION -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mt-4">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>">Previous</a>
                        </li>
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($search) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Keep your original modals unchanged -->
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

<!-- Your original JS (unchanged) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Keep your existing JS — only modals
    window.openStatusModal = function(depositId, currentStatus) {
        const modal = new bootstrap.Modal(document.getElementById('statusModal'));
        document.getElementById('newStatusSelect').value = currentStatus;
        document.getElementById('depositId').value = depositId;
        modal.show();
    };

    window.openInstallmentModal = function(depositId, paymentPlan, installmentNumber) {
        const modal = new bootstrap.Modal(document.getElementById('installmentModal'));
        document.getElementById('paymentPlanInput').value = paymentPlan;
        document.getElementById('installmentNumberInput').value = installmentNumber;
        document.getElementById('installmentDepositId').value = depositId;
        modal.show();
    };

    // Your save logic (unchanged)
    document.getElementById('saveStatusButton').addEventListener('click', function() {
        const depositId = document.getElementById('depositId').value;
        const newStatus = document.getElementById('newStatusSelect').value;
        fetch('update-deposit-status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `deposit_id=${depositId}&approval_status=${newStatus}`
        }).then(r => r.json()).then(data => {
            if (data.success) location.reload();
            else alert('Error: ' + data.message);
        });
    });

    document.getElementById('saveInstallmentButton').addEventListener('click', function() {
        const depositId = document.getElementById('installmentDepositId').value;
        const paymentPlan = document.getElementById('paymentPlanInput').value;
        const installmentNumber = document.getElementById('installmentNumberInput').value;
        fetch('update-deposit-installment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `deposit_id=${depositId}&payment_plan=${paymentPlan}&installment_number=${installmentNumber}`
        }).then(r => r.json()).then(data => {
            if (data.success) location.reload();
            else alert('Error: ' + data.message);
        });
    });
});
</script>
</html>
