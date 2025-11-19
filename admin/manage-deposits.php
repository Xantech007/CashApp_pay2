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
            <!-- Filters: Date + Search -->
            <div class="row g-3 align-items-center mt-4 mb-4">
                <!-- Date Filter -->
                <div class="col-md-5">
                    <form method="GET" class="d-flex gap-2">
                        <div class="input-group">
                            <span class="input-group-text">Date</span>
                            <input type="date" name="date" class="form-control" 
                                   value="<?= htmlspecialchars($_GET['date'] ?? '') ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Go</button>
                        <a href="?" class="btn btn-outline-secondary">Today</a>
                    </form>
                </div>

                <!-- Search Filter -->
                <div class="col-md-5">
                    <form method="GET" class="d-flex gap-2">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by name or email..." 
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <button type="submit" class="btn btn-success">Search</button>
                        <?php if (!empty($_GET['search'])): ?>
                            <a href="?" class="btn btn-outline-danger">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Showing Info -->
                <div class="col-md-2 text-end">
                    <small class="text-muted">
                        <?php
                        if (!empty($_GET['search'])) {
                            echo "<strong>" . htmlspecialchars($_GET['search']) . "</strong>";
                        } elseif (!empty($_GET['date'])) {
                            echo "<strong>" . date('d M Y', strtotime($_GET['date'])) . "</strong>";
                        } else {
                            echo "<strong>Today</strong>";
                        }
                        ?>
                    </small>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-borderless" id="depositsTable">
                    <thead>
                        <tr>
                            <th>Amount</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Installment</th>
                            <th>Proof</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $where_conditions = [];
                        $params = [];
                        $types = '';

                        // Date filter
                        if (!empty($_GET['date']) && empty($_GET['search'])) {
                            $date = date('Y-m-d', strtotime($_GET['date']));
                            $where_conditions[] = "DATE(DATE_ADD(d.created_at, INTERVAL 5 HOUR)) = ?";
                            $params[] = $date;
                            $types .= 's';
                        }

                        // Search filter (overrides date if both present)
                        if (!empty($_GET['search'])) {
                            $search = '%' . trim($_GET['search']) . '%';
                            $where_conditions[] = "(d.name LIKE ? OR d.email LIKE ?)";
                            $params[] = $search;
                            $params[] = $search;
                            $types .= 'ss';
                        }

                        // Default: today only if no filters
                        if (empty($_GET['date']) && empty($_GET['search'])) {
                            $today = date('Y-m-d');
                            $where_conditions[] = "DATE(DATE_ADD(d.created_at, INTERVAL 5 HOUR)) = ?";
                            $params[] = $today;
                            $types .= 's';
                        }

                        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

                        $query = "SELECT d.id, d.amount, d.currency, d.name, d.email, d.image, d.approval_status, 
                                         d.created_at, d.payment_plan, d.installment_number, u.id AS user_id
                                  FROM deposits d
                                  LEFT JOIN users u ON d.email = u.email
                                  $where_clause
                                  ORDER BY d.created_at DESC";

                        $stmt = mysqli_prepare($con, $query);
                        if (!empty($params)) {
                            mysqli_stmt_bind_param($stmt, $types, ...$params);
                        }
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);

                        if (mysqli_num_rows($result) == 0) {
                            echo "<tr><td colspan='9' class='text-center py-5 text-muted'>No deposits found.</td></tr>";
                        }

                        while ($data = mysqli_fetch_assoc($result)) {
                            $dateTime = new DateTime($data['created_at']);
                            $dateTime->modify('+5 hours');
                            $formatted_date = $dateTime->format('d M Y');
                            $time = $dateTime->format('H:i');

                            $deposit_id         = htmlspecialchars($data['id']);
                            $amount             = htmlspecialchars($data['amount']);
                            $currency           = htmlspecialchars($data['currency'] ?? '$');
                            $name               = htmlspecialchars($data['name']);
                            $email              = htmlspecialchars($data['email'] ?? 'No Email');
                            $image              = htmlspecialchars($data['image']);
                            $status             = htmlspecialchars($data['approval_status']);
                            $plan               = (int)($data['payment_plan'] ?? 1);
                            $installment        = (int)($data['installment_number'] ?? 1);
                            $user_id            = htmlspecialchars($data['user_id'] ?? '');

                            $display_status = ucfirst($status);
                            $installment_text = $plan > 1 ? "$installment/$plan" : "One-Time";
                            ?>
                            <tr>
                                <td><?= $currency ?><?= number_format($amount, 2) ?></td>
                                <td><?= $name ?></td>
                                <td><?= $email ?></td>
                                <td>
                                    <span class="badge bg-info text-light installment-badge"
                                          data-deposit-id="<?= $deposit_id ?>"
                                          data-payment-plan="<?= $plan ?>"
                                          data-installment-number="<?= $installment ?>"
                                          style="cursor:pointer"
                                          onclick="openInstallmentModal(<?= $deposit_id ?>, <?= $plan ?>, <?= $installment ?>)">
                                        <?= $installment_text ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($image): ?>
                                        <img src="../Uploads/<?= $image ?>" width="50" height="50" class="rounded" alt="Proof">
                                    <?php else: echo "No Image"; endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $status === 'pending' ? 'bg-warning' : ($status === 'approved' ? 'bg-success' : 'bg-danger') ?> text-light status-badge"
                                          data-deposit-id="<?= $deposit_id ?>"
                                          data-current-status="<?= $status ?>"
                                          style="cursor:pointer"
                                          onclick="openStatusModal(<?= $deposit_id ?>, '<?= $status ?>')">
                                        <?= $display_status ?>
                                    </span>
                                </td>
                                <td><?= $formatted_date ?></td>
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
                        mysqli_stmt_close($stmt);
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modals (Status & Installment) -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Status</h5>
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
                    <h5 class="modal-title">Change Installment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Total Installments</label>
                        <input type="number" id="paymentPlanInput" class="form-control" min="1" value="1">
                    </div>
                    <div class="mb-3">
                        <label>Current Installment</label>
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
    // Open modals
    window.openStatusModal = (id, status) => {
        document.getElementById('newStatusSelect').value = status;
        document.getElementById('depositId').value = id;
        new bootstrap.Modal('#statusModal').show();
    };

    window.openInstallmentModal = (id, plan, num) => {
        document.getElementById('paymentPlanInput').value = plan;
        document.getElementById('installmentNumberInput').value = num;
        document.getElementById('installmentDepositId').value = id;
        new bootstrap.Modal('#installmentModal').show();
    };

    // Save Status
    document.getElementById('saveStatusButton').onclick = () => {
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
                bootstrap.Modal.getInstance('#statusModal').hide();
            }
        });
    };

    // Save Installment
    document.getElementById('saveInstallmentButton').onclick = () => {
        const id = document.getElementById('installmentDepositId').value;
        const plan = document.getElementById('paymentPlanInput').value;
        const num = document.getElementById('installmentNumberInput').value;

        if (parseInt(num) > parseInt(plan)) return alert('Invalid installment');

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
                bootstrap.Modal.getInstance('#installmentModal').hide();
            }
        });
    };
</script>
</html>
