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
            <!-- Filters (unchanged) -->
            <div class="row g-3 align-items-center mt-4 mb-4">
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
                <div class="col-md-2 text-end">
                    <small class="text-muted">
                        <?php
                        echo !empty($_GET['search']) ? "<strong>" . htmlspecialchars($_GET['search']) . "</strong>" :
                             (!empty($_GET['date']) ? "<strong>" . date('d M Y', strtotime($_GET['date'])) . "</strong>" :
                             "<strong>Today (+6 hrs)</strong>");
                        ?>
                    </small>
                </div>
            </div>

            <!-- TABLE -->
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
                        // Same filters as before...
                        $where = []; $params = []; $types = '';
                        if (!empty($_GET['date']) && empty($_GET['search'])) {
                            $d = date('Y-m-d', strtotime($_GET['date']));
                            $where[] = "DATE(DATE_ADD(d.created_at, INTERVAL 6 HOUR)) = ?";
                            $params[] = $d; $types .= 's';
                        }
                        if (!empty($_GET['search'])) {
                            $s = '%' . trim($_GET['search']) . '%';
                            $where[] = "(d.name LIKE ? OR d.email LIKE ?)";
                            $params[] = $s; $params[] = $s; $types .= 'ss';
                        }
                        if (empty($_GET['date']) && empty($_GET['search'])) {
                            $today = date('Y-m-d', strtotime('+6 hours'));
                            $where[] = "DATE(DATE_ADD(d.created_at, INTERVAL 6 HOUR)) = ?";
                            $params[] = $today; $types .= 's';
                        }
                        $where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

                        $query = "
                            SELECT d.id, d.amount, d.currency, d.name, d.email, d.image,
                                   d.approval_status, d.created_at,
                                   COALESCE(u.payment_plan, 1) AS total_installments,
                                   u.current_installment,
                                   u.id AS user_id
                            FROM deposits d
                            LEFT JOIN users u ON d.email = u.email
                            $where_sql
                            ORDER BY d.created_at DESC
                        ";
                        $stmt = mysqli_prepare($con, $query);
                        if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);

                        if (mysqli_num_rows($result) == 0) {
                            echo "<tr><td colspan='9' class='text-center py-5 text-muted'>No deposits found.</td></tr>";
                        }

                        while ($row = mysqli_fetch_assoc($result)) {
                            $dt = new DateTime($row['created_at']);
                            $dt->modify('+6 hours');

                            $id         = $row['id'];
                            $amount     = $row['amount'];
                            $currency   = $row['currency'] ?? '$';
                            $name       = $row['name'];
                            $email      = $row['email'];
                            $img        = $row['image'];
                            $status     = $row['approval_status'];
                            $user_id    = $row['user_id'];

                            // Total from users table (fallback 1)
                            $total      = in_array($row['total_installments'], [1,2,4]) ? (int)$row['total_installments'] : 1;

                            // Current installment: use stored value, fallback to computed if null
                            $current    = $row['current_installment'] ?? 1;
                            if ($current > $total) $current = $total; // safety

                            $badge_class = [
                                'pending' => 'bg-warning',
                                'approved' => 'bg-success',
                                'rejected' => 'bg-danger'
                            ][$status];
                        ?>
                        <tr>
                            <td><?= $currency . number_format($amount, 2) ?></td>
                            <td><?= htmlspecialchars($name) ?></td>
                            <td><?= htmlspecialchars($email) ?></td>
                            <td>
                                <span class="badge bg-info text-light me-2" id="badge-<?= $id ?>">
                                    <?= $current ?> / <?= $total ?>
                                </span>

                                <!-- Editable Current -->
                                <select class="form-select form-select-sm d-inline installment-current"
                                        style="width: auto;"
                                        data-email="<?= htmlspecialchars($email) ?>"
                                        data-deposit-id="<?= $id ?>"
                                        onchange="updateCurrentInstallment(this, <?= $id ?>, <?= $total ?>)">
                                    <?php for ($i = 1; $i <= $total; $i++): ?>
                                        <option value="<?= $i ?>" <?= $current == $i ? 'selected' : '' ?>><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="mx-1">/</span>

                                <!-- Editable Total (only 1, 2, 4) -->
                                <select class="form-select form-select-sm d-inline installment-total"
                                        style="width: auto;"
                                        data-email="<?= htmlspecialchars($email) ?>"
                                        data-deposit-id="<?= $id ?>"
                                        data-old-total="<?= $total ?>"
                                        onchange="updateTotalInstallments(this, <?= $id ?>)">
                                    <?php foreach ([1, 2, 4] as $val): ?>
                                        <option value="<?= $val ?>" <?= $total == $val ? 'selected' : '' ?>><?= $val ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <?php if ($img && file_exists('../Uploads/' . basename($img))): ?>
                                    <img src="../Uploads/<?= htmlspecialchars(basename($img)) ?>" width="50" height="50" class="rounded">
                                <?php else: ?>
                                    No Image
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $badge_class ?> me-2" id="status-badge-<?= $id ?>">
                                    <?= ucfirst($status) ?>
                                </span>
                                <select class="form-select form-select-sm d-inline"
                                        onchange="updateDepositStatus(<?= $id ?>, this.value)">
                                    <option value="pending" <?= $status=='pending'?'selected':'' ?>>Pending</option>
                                    <option value="approved" <?= $status=='approved'?'selected':'' ?>>Approved</option>
                                    <option value="rejected" <?= $status=='rejected'?'selected':'' ?>>Rejected</option>
                                </select>
                            </td>
                            <td><?= $dt->format('d M Y') ?></td>
                            <td><?= $dt->format('H:i') ?></td>
                            <td>
                                <?php if ($img && file_exists('../Uploads/' . basename($img))): ?>
                                    <a href="../Uploads/<?= htmlspecialchars(basename($img)) ?>" download class="btn btn-light btn-sm me-1">Download</a>
                                <?php endif; ?>
                                <?php if ($user_id): ?>
                                    <a href="edit-user.php?id=<?= $user_id ?>" class="btn btn-light btn-sm">Edit User</a>
                                <?php else: ?>
                                    <span class="text-muted">No User</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php } mysqli_stmt_close($stmt); ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include('inc/footer.php'); ?>

<script>
// Update Current Installment
function updateCurrentInstallment(select, depositId, maxTotal) {
    const newCurrent = parseInt(select.value);
    const email = select.dataset.email;

    fetch("codes/update-current-installment.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: `email=${encodeURIComponent(email)}&current=${newCurrent}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            document.getElementById(`badge-${depositId}`).innerText = newCurrent + " / " + maxTotal;
        } else {
            alert("Failed to update current installment.");
            location.reload();
        }
    });
}

// Update Total Installments (1, 2, or 4 only)
function updateTotalInstallments(select, depositId) {
    const newTotal = parseInt(select.value);
    const oldTotal = parseInt(select.dataset.oldTotal);
    const email = select.dataset.email;

    // If reducing total and current > new total, warn
    const currentSelect = select.parentElement.querySelector('.installment-current');
    const currentVal = parseInt(currentSelect.value);

    if (currentVal > newTotal) {
        if (!confirm(`Current installment (${currentVal}) exceeds new total (${newTotal}). It will be reset to ${newTotal}. Continue?`)) {
            select.value = oldTotal;
            return;
        }
    }

    fetch("codes/update-payment-plan.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: `email=${encodeURIComponent(email)}&payment_plan=${newTotal}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            alert("Total installments updated.");
            location.reload(); // Refresh to update current dropdown options
        } else {
            alert("Error updating total.");
            select.value = oldTotal;
        }
    });
}

// Update Deposit Status
function updateDepositStatus(id, value) {
    fetch("codes/update-deposit-status.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: `id=${id}&status=${value}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const badge = document.getElementById(`status-badge-${id}`);
            badge.innerText = value.charAt(0).toUpperCase() + value.slice(1);
            badge.className = `badge me-2 ${value === 'approved' ? 'bg-success' : value === 'pending' ? 'bg-warning' : 'bg-danger'}`;
            if (value === 'approved') setTimeout(() => location.reload(), 1000);
        }
    });
}
</script>
</html>
