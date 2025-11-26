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

                        /* FILTERS -------------------------------------- */
                        $where = [];
                        $params = [];
                        $types = '';

                        /* DATE FILTER ---------------------------------- */
                        if (!empty($_GET['date']) && empty($_GET['search'])) {
                            $d = date('Y-m-d', strtotime($_GET['date']));
                            $where[] = "DATE(DATE_ADD(d.created_at, INTERVAL 6 HOUR)) = ?";
                            $params[] = $d;
                            $types .= 's';
                        }

                        /* SEARCH FILTER -------------------------------- */
                        if (!empty($_GET['search'])) {
                            $s = '%' . trim($_GET['search']) . '%';
                            $where[] = "(d.name LIKE ? OR d.email LIKE ?)";
                            $params[] = $s;
                            $params[] = $s;
                            $types .= 'ss';
                        }

                        /* DEFAULT = TODAY + 6 HOURS ------------------- */
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
                                   d.payment_plan, d.installment_number,
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

                        while ($row = mysqli_fetch_assoc($result)) {

                            /* DISPLAY +6 HOURS ------------------------- */
                            $dt = new DateTime($row['created_at']);
                            $dt->modify('+6 hours');

                            $id = $row['id'];
                            $amount = $row['amount'];
                            $currency = $row['currency'] ?? '$';
                            $name = $row['name'];
                            $email = $row['email'];
                            $img = $row['image'];
                            $status = $row['approval_status'];
                            $current = (int)$row['installment_number'];
                            $total = (int)$row['payment_plan'];
                            $user_id = $row['user_id'];

                            $badge_status_class = [
                                'pending' => 'bg-warning',
                                'approved' => 'bg-success',
                                'rejected' => 'bg-danger'
                            ][$status];
                        ?>

                        <tr>
                            <td><?= $currency . number_format($amount, 2) ?></td>
                            <td><?= $name ?></td>
                            <td><?= $email ?></td>

                            <!-- INSTALLMENT -->
                            <td>
                                <span class="badge bg-info text-light me-2"
                                      id="badge-<?= $id ?>">
                                      <?= $current . " / " . $total ?>
                                </span>

                                <!-- CURRENT -->
                                <select class="form-select form-select-sm d-inline installment-current"
                                        style="width:auto"
                                        data-id="<?= $id ?>"
                                        onchange="updateCurrent(<?= $id ?>)">
                                    <?php for ($i = 1; $i <= 4; $i++): ?>
                                        <option value="<?= $i ?>" <?= ($current == $i ? 'selected' : '') ?>>
                                            <?= $i ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>

                                <span class="mx-1">/</span>

                                <!-- TOTAL -->
                                <select class="form-select form-select-sm d-inline installment-total"
                                        style="width:auto"
                                        data-id="<?= $id ?>"
                                        onchange="updateTotal(<?= $id ?>)">
                                    <?php for ($i = 1; $i <= 4; $i++): ?>
                                        <option value="<?= $i ?>" <?= ($total == $i ? 'selected' : '') ?>>
                                            <?= $i ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </td>

                            <!-- PROOF -->
                            <td>
                                <?php if ($img): ?>
                                    <img src="../Uploads/<?= $img ?>" width="50" height="50" class="rounded">
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
                                    <option value="pending"  <?= $status=='pending'?'selected':'' ?>>Pending</option>
                                    <option value="approved" <?= $status=='approved'?'selected':'' ?>>Approved</option>
                                    <option value="rejected" <?= $status=='rejected'?'selected':'' ?>>Rejected</option>
                                </select>
                            </td>

                            <td><?= $dt->format('d M Y') ?></td>
                            <td><?= $dt->format('H:i') ?></td>

                            <td>
                                <?php if ($img): ?>
                                    <a href="../Uploads/<?= $img ?>" download class="btn btn-light btn-sm me-1">Download</a>
                                <?php endif; ?>

                                <?php if ($user_id): ?>
                                    <a href="edit-user.php?id=<?= $user_id ?>" class="btn btn-light btn-sm">Edit</a>
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

<!-- AJAX SCRIPTS -->
<script>

function validateInstallments(id) {
    let c = document.querySelector(`.installment-current[data-id="${id}"]`).value;
    let t = document.querySelector(`.installment-total[data-id="${id}"]`).value;

    if (parseInt(c) > parseInt(t)) {
        alert("Current installment cannot be greater than total.");
        return false;
    }
    return true;
}

function updateCurrent(id) {

    if (!validateInstallments(id)) {
        location.reload();
        return;
    }

    let current = document.querySelector(`.installment-current[data-id="${id}"]`).value;

    fetch("codes/update-installment-current.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: `id=${id}&current=${current}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            document.getElementById(`badge-${id}`).innerText =
                current + " / " + d.total;
        }
    });
}

function updateTotal(id) {

    if (!validateInstallments(id)) {
        location.reload();
        return;
    }

    let total = document.querySelector(`.installment-total[data-id="${id}"]`).value;

    fetch("codes/update-installment-total.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: `id=${id}&total=${total}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            document.getElementById(`badge-${id}`).innerText =
                d.current + " / " + total;
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

            badge.className =
                `badge me-2 ${
                    value === 'approved' ? 'bg-success' :
                    value === 'pending'  ? 'bg-warning' :
                                           'bg-danger'
                }`;
        }
    });
}

</script>

</html>
