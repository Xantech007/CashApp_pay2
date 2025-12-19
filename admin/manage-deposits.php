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
            <!-- Filters -->
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
                <!-- Showing Filter Info -->
                <div class="col-md-2 text-end">
                    <small class="text-muted">
                        <?php
                        if (!empty($_GET['search'])) {
                            echo "<strong>" . htmlspecialchars($_GET['search']) . "</strong>";
                        } elseif (!empty($_GET['date'])) {
                            echo "<strong>" . date('d M Y', strtotime($_GET['date'])) . "</strong>";
                        } else {
                            echo "<strong>Today (+6 hrs)</strong>";
                        }
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
                        /* FILTERS */
                        $where = [];
                        $params = [];
                        $types = '';

                        if (!empty($_GET['date']) && empty($_GET['search'])) {
                            $d = date('Y-m-d', strtotime($_GET['date']));
                            $where[] = "DATE(DATE_ADD(d.created_at, INTERVAL 6 HOUR)) = ?";
                            $params[] = $d;
                            $types .= 's';
                        }
                        if (!empty($_GET['search'])) {
                            $s = '%' . trim($_GET['search']) . '%';
                            $where[] = "(d.name LIKE ? OR d.email LIKE ?)";
                            $params[] = $s;
                            $params[] = $s;
                            $types .= 'ss';
                        }
                        if (empty($_GET['date']) && empty($_GET['search'])) {
                            $today = date('Y-m-d', strtotime('+6 hours'));
                            $where[] = "DATE(DATE_ADD(d.created_at, INTERVAL 6 HOUR)) = ?";
                            $params[] = $today;
                            $types .= 's';
                        }

                        $where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

                        $query = "
                            SELECT d.id, d.amount, d.currency, d.name, d.email, d.image,
                                   d.approval_status, d.created_at,
                                   d.payment_plan AS deposit_plan,
                                   u.payment_plan AS user_plan,
                                   u.id AS user_id
                            FROM deposits d
                            LEFT JOIN users u ON d.email = u.email
                            $where_sql
                            ORDER BY d.created_at DESC
                        ";

                        $stmt = mysqli_prepare($con, $query);
                        if (!empty($params)) {
                            mysqli_stmt_bind_param($stmt, $types, ...$params);
                        }
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);

                        if (mysqli_num_rows($result) == 0) {
                            echo "<tr><td colspan='9' class='text-center py-5 text-muted'>No deposits found.</td></tr>";
                        }

                        // Pre-fetch approved installments per email + payment_plan to compute current
                        $approved_counts = [];
                        $email_plans = [];

                        $all_rows = [];
                        while ($row = mysqli_fetch_assoc($result)) {
                            $all_rows[] = $row;
                            $email = $row['email'];
                            $plan = $row['user_plan'] ?? $row['deposit_plan'] ?? 1;
                            $email_plans[$email] = $plan;
                        }

                        if (!empty($all_rows)) {
                            $emails = array_unique(array_column($all_rows, 'email'));
                            $in_placeholders = str_repeat('?,', count($emails) - 1) . '?';
                            $types_in = str_repeat('s', count($emails));

                            $approved_query = "
                                SELECT email, payment_plan, COUNT(DISTINCT installment_number) AS approved_count
                                FROM deposits
                                WHERE email IN ($in_placeholders)
                                  AND approval_status = 'approved'
                                GROUP BY email, payment_plan
                            ";
                            $approved_stmt = mysqli_prepare($con, $approved_query);
                            mysqli_stmt_bind_param($approved_stmt, $types_in, ...$emails);
                            mysqli_stmt_execute($approved_stmt);
                            $approved_res = mysqli_stmt_get_result($approved_stmt);

                            while ($ar = mysqli_fetch_assoc($approved_res)) {
                                $key = $ar['email'] . '|' . $ar['payment_plan'];
                                $approved_counts[$key] = (int)$ar['approved_count'];
                            }
                            mysqli_stmt_close($approved_stmt);
                        }

                        foreach ($all_rows as $row) {
                            $dt = new DateTime($row['created_at']);
                            $dt->modify('+6 hours');

                            $id = $row['id'];
                            $amount = $row['amount'];
                            $currency = $row['currency'] ?? '$';
                            $name = $row['name'];
                            $email = $row['email'];
                            $img = $row['image'];
                            $status = $row['approval_status'];
                            $user_id = $row['user_id'];

                            // Determine total installments
                            $total_installments = max(1, (int)($row['user_plan'] ?? $row['deposit_plan'] ?? 1));

                            // Determine current (next) installment
                            $plan_key = $email . '|' . $total_installments;
                            $approved_so_far = $approved_counts[$plan_key] ?? 0;
                            $current_installment = $approved_so_far + 1;

                            // If all are approved, still show total as total
                            if ($current_installment > $total_installments) {
                                $current_installment = $total_installments;
                            }

                            $badge_status_class = [
                                'pending' => 'bg-warning',
                                'approved' => 'bg-success',
                                'rejected' => 'bg-danger'
                            ][$status];
                        ?>
                        <tr>
                            <td><?= $currency . number_format($amount, 2) ?></td>
                            <td><?= htmlspecialchars($name) ?></td>
                            <td><?= htmlspecialchars($email) ?></td>
                            <!-- INSTALLMENT -->
                            <td>
                                <span class="badge bg-info text-light me-2"
                                      id="badge-<?= $id ?>">
                                    <?= $current_installment ?> / <?= $total_installments ?>
                                </span>
                                <span class="text-muted small">Next:</span>
                                <strong><?= $current_installment ?></strong>
                                <span class="mx-1">/</span>
                                <!-- Editable Total Installments -->
                                <select class="form-select form-select-sm d-inline installment-total"
                                        style="width:auto"
                                        data-email="<?= htmlspecialchars($email) ?>"
                                        data-current-total="<?= $total_installments ?>"
                                        onchange="updateTotalInstallments(this, <?= $id ?>)">
                                    <?php for ($i = 1; $i <= 10; $i++): // Allow up to 10 ?>
                                        <option value="<?= $i ?>" <?= ($total_installments == $i ? 'selected' : '') ?>>
                                            <?= $i ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                            <!-- PROOF -->
                            <td>
                                <?php if ($img && file_exists('../Uploads/' . basename($img))): ?>
                                    <img src="../Uploads/<?= htmlspecialchars(basename($img)) ?>" width="50" height="50" class="rounded">
                                <?php else: ?>
                                    No Image
                                <?php endif; ?>
                            </td>
                            <!-- STATUS -->
                            <td>
                                <span class="badge <?= $badge_status_class ?> me-2"
                                      id="status-badge-<?= $id ?>">
                                    <?= ucfirst($status) ?>
                                </span>
                                <select class="form-select form-select-sm d-inline"
                                        style="width:auto"
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
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include('inc/footer.php'); ?>

<!-- AJAX SCRIPTS -->
<script>
function updateTotalInstallments(selectElem, depositId) {
    const email = selectElem.dataset.email;
    const newTotal = selectElem.value;
    const oldTotal = selectElem.dataset.currentTotal;

    if (newTotal < oldTotal) {
        if (!confirm(`Reducing total from ${oldTotal} to ${newTotal} may cause inconsistencies. Continue?`)) {
            selectElem.value = oldTotal;
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
            alert("Payment plan updated successfully.");
            location.reload(); // Refresh to recalculate current installments
        } else {
            alert("Error: " + (d.message || "Failed to update."));
            selectElem.value = oldTotal;
        }
    })
    .catch(() => {
        alert("Network error.");
        selectElem.value = oldTotal;
    });
}

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
            const text = value.charAt(0).toUpperCase() + value.slice(1);
            badge.innerText = text;
            badge.className = `badge me-2 ${
                value === 'approved' ? 'bg-success' :
                value === 'pending' ? 'bg-warning' : 'bg-danger'
            }`;
            // Optionally reload if status change affects current installment
            if (value === 'approved') {
                setTimeout(() => location.reload(), 800);
            }
        }
    });
}
</script>
</html>
