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
                <div class="col-md-5">
                    <form method="GET" class="d-flex gap-2">
                        <div class="input-group">
                            <span class="input-group-text">Date</span>
                            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($_GET['date'] ?? '') ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Go</button>
                        <a href="?" class="btn btn-outline-secondary">Today</a>
                    </form>
                </div>
                <div class="col-md-5">
                    <form method="GET" class="d-flex gap-2">
                        <input type="text" name="search" class="form-control" placeholder="Search by name or email..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <button type="submit" class="btn btn-success">Search</button>
                        <?php if (!empty($_GET['search'])): ?>
                            <a href="?" class="btn btn-outline-danger">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="col-md-2 text-end">
                    <small class="text-muted">
                        <?php
                        if (!empty($_GET['search'])) echo "<strong>" . htmlspecialchars($_GET['search']) . "</strong>";
                        elseif (!empty($_GET['date'])) echo "<strong>" . date('d M Y', strtotime($_GET['date'])) . "</strong>";
                        else echo "<strong>Today (+6 hrs)</strong>";
                        ?>
                    </small>
                </div>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-borderless" id="depositsTable">
                    <thead>
                        <tr>
                            <th>Amount</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Installments</th>
                            <th>Proof</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
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
                                   d.approval_status, d.created_at, d.installment_number,
                                   d.payment_plan AS deposit_plan,
                                   u.payment_plan AS user_plan, u.id AS user_id
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
                            $recorded_current = (int)$row['installment_number'];
                            $user_id    = $row['user_id'];

                            // Total: from users table first, fallback to deposit_plan, restrict to 1,2,4
                            $total = (int)($row['user_plan'] ?? $row['deposit_plan'] ?? 1);
                            $allowed = [1,2,4];
                            if (!in_array($total, $allowed)) $total = 1;

                            // Fetch approved installments for this user + total plan
                            $approved_query = "SELECT installment_number FROM deposits 
                                               WHERE email = ? AND payment_plan = ? AND approval_status = 'approved'";
                            $astmt = mysqli_prepare($con, $approved_query);
                            mysqli_stmt_bind_param($astmt, "si", $email, $total);
                            mysqli_stmt_execute($astmt);
                            $ares = mysqli_stmt_get_result($astmt);
                            $approved = [];
                            while ($a = mysqli_fetch_assoc($ares)) $approved[] = (int)$a['installment_number'];
                            mysqli_stmt_close($astmt);

                            // Compute true current (next pending)
                            $true_current = 1;
                            for ($i = 1; $i <= $total; $i++) {
                                if (!in_array($i, $approved)) {
                                    $true_current = $i;
                                    break;
                                }
                            }
                            if (count($approved) >= $total) $true_current = $total; // completed

                            $badge_class = ['pending'=>'bg-warning', 'approved'=>'bg-success', 'rejected'=>'bg-danger'][$status];
                        ?>
                        <tr>
                            <td><?= $currency . number_format($amount, 2) ?></td>
                            <td><?= htmlspecialchars($name) ?></td>
                            <td><?= htmlspecialchars($email) ?></td>

                            <!-- Installments Column -->
                            <td>
                                <!-- True Current / Total Badge -->
                                <span class="badge bg-primary text-light d-block mb-2" id="badge-<?= $id ?>">
                                    Current: <?= $true_current ?> / <?= $total ?>
                                    <?php if ($true_current > $total || count($approved) >= $total): ?>
                                        <i class="bi bi-check-all text-success"></i> Completed
                                    <?php endif; ?>
                                </span>

                                <!-- Visual Status -->
                                <div class="small mb-2">
                                    <?php for ($i = 1; $i <= $total; $i++): ?>
                                        <span class="me-2">
                                            <strong><?= $i ?></strong>:
                                            <?php if (in_array($i, $approved)): ?>
                                                <i class="bi bi-check-circle-fill text-success" title="Approved"></i>
                                            <?php else: ?>
                                                <i class="bi bi-hourglass-split text-warning" title="Pending"></i>
                                            <?php endif; ?>
                                        </span>
                                    <?php endfor; ?>
                                </div>

                                <!-- Editable Current (this deposit's recorded number) -->
                                <small>Recorded #:</small>
                                <select class="form-select form-select-sm d-inline w-auto installment-current"
                                        data-id="<?= $id ?>" data-total="<?= $total ?>"
                                        onchange="updateCurrent(<?= $id ?>, this.value, <?= $total ?>)">
                                    <?php for ($i = 1; $i <= 4; $i++): ?>
                                        <option value="<?= $i ?>" <?= $i == $recorded_current ? 'selected' : '' ?>
                                            <?= $i > $total ? 'disabled' : '' ?>><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>

                                <!-- Editable Total -->
                                <small class="ms-2">Total:</small>
                                <select class="form-select form-select-sm d-inline w-auto installment-total"
                                        data-email="<?= htmlspecialchars($email) ?>" data-old-total="<?= $total ?>"
                                        onchange="updateTotal(this, <?= $id ?>)">
                                    <?php foreach ([1,2,4] as $t): ?>
                                        <option value="<?= $t ?>" <?= $t == $total ? 'selected' : '' ?>><?= $t ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>

                            <!-- Proof -->
                            <td>
                                <?php if ($img && file_exists('../Uploads/'.basename($img))): ?>
                                    <img src="../Uploads/<?= htmlspecialchars(basename($img)) ?>" width="50" height="50" class="rounded">
                                <?php else: ?> No Image <?php endif; ?>
                            </td>

                            <!-- Status -->
                            <td>
                                <span class="badge <?= $badge_class ?> me-2" id="status-badge-<?= $id ?>">
                                    <?= ucfirst($status) ?>
                                </span>
                                <select class="form-select form-select-sm d-inline" style="width:auto"
                                        onchange="updateDepositStatus(<?= $id ?>, this.value)">
                                    <option value="pending" <?= $status=='pending'?'selected':'' ?>>Pending</option>
                                    <option value="approved" <?= $status=='approved'?'selected':'' ?>>Approved</option>
                                    <option value="rejected" <?= $status=='rejected'?'selected':'' ?>>Rejected</option>
                                </select>
                            </td>

                            <td><?= $dt->format('d M Y') ?></td>
                            <td><?= $dt->format('H:i') ?></td>

                            <td>
                                <?php if ($img && file_exists('../Uploads/'.basename($img))): ?>
                                    <a href="../Uploads/<?= htmlspecialchars(basename($img)) ?>" download class="btn btn-light btn-sm me-1">Download</a>
                                <?php endif; ?>
                                <?php if ($user_id): ?>
                                    <a href="edit-user.php?id=<?= $user_id ?>" class="btn btn-light btn-sm">Edit User</a>
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

<!-- AJAX Scripts -->
<script>
function updateCurrent(id, newCurrent, total) {
    newCurrent = parseInt(newCurrent);
    if (newCurrent > total) {
        alert("Current installment cannot exceed total installments.");
        location.reload();
        return;
    }

    fetch("codes/update-installment-current.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: `id=${id}&current=${newCurrent}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            document.getElementById(`badge-${id}`).innerHTML = `Current: ${newCurrent} / ${total}`;
        } else {
            alert("Failed to update.");
            location.reload();
        }
    });
}

function updateTotal(select, depositId) {
    const email = select.dataset.email;
    const newTotal = parseInt(select.value);
    const oldTotal = parseInt(select.dataset.oldTotal);

    if (newTotal < oldTotal) {
        if (!confirm(`Reducing total from ${oldTotal} to ${newTotal} may cause issues. Continue?`)) {
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
            alert("Total updated. Page will refresh.");
            location.reload();
        } else {
            alert("Error updating total.");
            select.value = oldTotal;
        }
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
            badge.innerText = value.charAt(0).toUpperCase() + value.slice(1);
            badge.className = `badge me-2 ${value==='approved'?'bg-success':(value==='pending'?'bg-warning':'bg-danger')}`;
            if (value === 'approved') setTimeout(() => location.reload(), 1000);
        }
    });
}
</script>
</html>
