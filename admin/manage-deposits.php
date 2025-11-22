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

                        // Date filter (UPDATED TO +6 HOURS)
                        if (!empty($_GET['date']) && empty($_GET['search'])) {
                            $date = date('Y-m-d', strtotime($_GET['date']));
                            $where_conditions[] = "DATE(DATE_ADD(d.created_at, INTERVAL 6 HOUR)) = ?";
                            $params[] = $date;
                            $types .= 's';
                        }

                        // Search overrides date
                        if (!empty($_GET['search'])) {
                            $search = '%' . trim($_GET['search']) . '%';
                            $where_conditions[] = "(d.name LIKE ? OR d.email LIKE ?)";
                            $params[] = $search;
                            $params[] = $search;
                            $types .= 'ss';
                        }

                        // Default "Today" (UPDATED +6 hours)
                        if (empty($_GET['date']) && empty($_GET['search'])) {
                            $today = date('Y-m-d');
                            $where_conditions[] = "DATE(DATE_ADD(d.created_at, INTERVAL 6 HOUR)) = ?";
                            $params[] = $today;
                            $types .= 's';
                        }

                        $where_clause = !empty($where_conditions)
                            ? 'WHERE ' . implode(' AND ', $where_conditions)
                            : '';

                        $query = "SELECT d.id, d.amount, d.currency, d.name, d.email, d.image,
                                         d.approval_status, d.created_at,
                                         d.payment_plan, d.installment_number,
                                         u.id AS user_id
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

                            // DISPLAY FIX: ADD +6 HOURS
                            $dateTime = new DateTime($data['created_at']);
                            $dateTime->modify('+6 hours');

                            $formatted_date = $dateTime->format('d M Y');
                            $time = $dateTime->format('H:i');

                            $deposit_id  = htmlspecialchars($data['id']);
                            $amount      = htmlspecialchars($data['amount']);
                            $currency    = htmlspecialchars($data['currency'] ?? '$');
                            $name        = htmlspecialchars($data['name']);
                            $email       = htmlspecialchars($data['email'] ?? 'No Email');
                            $image       = htmlspecialchars($data['image']);
                            $status      = htmlspecialchars($data['approval_status']);
                            $plan        = (int)($data['payment_plan'] ?? 1);
                            $installment = (int)($data['installment_number'] ?? 1);
                            $user_id     = htmlspecialchars($data['user_id'] ?? '');

                            $display_status = ucfirst($status);
                            $installment_text = $plan > 1 ? "$installment/$plan" : "1/1";

                            $installment_options = [1,2,4];
                            $status_options = ['pending','approved','rejected'];
                        ?>

                        <tr>
                            <td><?= $currency ?><?= number_format($amount, 2) ?></td>
                            <td><?= $name ?></td>
                            <td><?= $email ?></td>

                            <!-- INSTALLMENT DROPDOWN -->
                            <td>
                                <span class="badge bg-info text-light installment-badge me-2"
                                      data-deposit-id="<?= $deposit_id ?>"
                                      data-current="<?= $installment ?>">
                                    <?= $installment_text ?>
                                </span>

                                <select class="form-select form-select-sm d-inline"
                                        style="width:auto"
                                        onchange="updateInstallment(<?= $deposit_id ?>, this.value)">
                                    <?php foreach ($installment_options as $opt): ?>
                                        <option value="<?= $opt ?>" <?= $opt == $installment ? 'selected' : '' ?>>
                                            <?= $opt ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>

                            <!-- PROOF -->
                            <td>
                                <?php if ($image): ?>
                                    <img src="../Uploads/<?= $image ?>" width="50" height="50" class="rounded" alt="Proof">
                                <?php else: echo "No Image"; endif; ?>
                            </td>

                            <!-- STATUS DROPDOWN -->
                            <td>
                                <?php
                                $badgeClass = [
                                    'pending' => 'bg-warning',
                                    'approved' => 'bg-success',
                                    'rejected' => 'bg-danger'
                                ][$status];
                                ?>

                                <span class="badge <?= $badgeClass ?> status-badge me-2"
                                      data-deposit-id="<?= $deposit_id ?>"
                                      data-current="<?= $status ?>">
                                    <?= ucfirst($status) ?>
                                </span>

                                <select class="form-select form-select-sm d-inline"
                                        style="width:auto"
                                        onchange="updateDepositStatus(<?= $deposit_id ?>, this.value)">
                                    <?php foreach ($status_options as $st): ?>
                                        <option value="<?= $st ?>" <?= $st == $status ? 'selected' : '' ?>>
                                            <?= ucfirst($st) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
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

                        <?php } mysqli_stmt_close($stmt); ?>

                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include('inc/footer.php'); ?>

<!-- AJAX UPDATERS -->
<script>
function updateInstallment(id, value) {
    const badge = document.querySelector(`.installment-badge[data-deposit-id="${id}"]`);

    fetch('codes/update-installment.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `id=${id}&value=${value}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            badge.textContent = `${value}/${value}`;
            badge.dataset.current = value;
        } else {
            alert("Error updating installment");
        }
    })
    .catch(() => alert("Connection error"));
}

function updateDepositStatus(id, value) {
    const badge = document.querySelector(`.status-badge[data-deposit-id="${id}"]`);
    const classMap = {
        pending: 'bg-warning',
        approved: 'bg-success',
        rejected: 'bg-danger'
    };

    fetch('codes/update-deposit-status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `id=${id}&value=${value}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            badge.textContent = value.charAt(0).toUpperCase() + value.slice(1);
            badge.className = `badge status-badge me-2 ${classMap[value]}`;
            badge.dataset.current = value;
        } else {
            alert("Error updating status");
        }
    })
    .catch(() => alert("Connection error"));
}
</script>

</html>
