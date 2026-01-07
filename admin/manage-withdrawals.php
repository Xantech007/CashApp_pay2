<?php
session_start();
include('inc/header.php');
include('inc/navbar.php');
include('inc/sidebar.php');
include('../config/dbcon.php');
?>

<style>
    .badge-pending { background-color: #ffc107; color: #000; }
    .badge-approved { background-color: #198754; }
    .badge-rejected { background-color: #dc3545; }
</style>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Manage Withdrawals</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard">Home</a></li>
                <li class="breadcrumb-item">Withdrawals</li>
                <li class="breadcrumb-item active">Manage</li>
            </ol>
        </nav>
    </div>

    <div class="card">
        <div class="card-body">

            <!-- FILTERS -->
            <div class="row g-3 align-items-center mt-4 mb-4">

                <!-- DATE FILTER -->
                <div class="col-md-4">
                    <form method="GET" class="d-flex gap-2">
                        <div class="input-group">
                            <span class="input-group-text">Date</span>
                            <input type="date" name="date" class="form-control"
                                   value="<?= htmlspecialchars($_GET['date'] ?? '') ?>">
                        </div>
                        <button class="btn btn-primary">Filter</button>
                        <a href="?" class="btn btn-outline-secondary">Today</a>
                    </form>
                </div>

                <!-- SEARCH -->
                <div class="col-md-4">
                    <form method="GET" class="d-flex gap-2">
                        <input type="text" name="search" class="form-control"
                               placeholder="Search email or amount"
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <button class="btn btn-success">Search</button>
                        <?php if (!empty($_GET['search'])): ?>
                            <a href="?" class="btn btn-outline-danger">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- VIEW ALL -->
                <div class="col-md-4 text-end">
                    <form method="GET">
                        <input type="hidden" name="view" value="all">
                        <button class="btn btn-dark">View All Withdrawals</button>
                    </form>

                    <small class="text-muted d-block mt-2">
                        Showing:
                        <strong>
                            <?php
                            if (!empty($_GET['search'])) {
                                echo 'Search: ' . htmlspecialchars($_GET['search']);
                            } elseif (!empty($_GET['date'])) {
                                echo date('d M Y', strtotime($_GET['date']));
                            } elseif (!empty($_GET['view']) && $_GET['view'] === 'all') {
                                echo 'All Withdrawals';
                            } else {
                                echo 'Today';
                            }
                            ?>
                        </strong>
                    </small>
                </div>

            </div>

            <!-- TABLE -->
            <div class="table-responsive">
                <table class="table table-borderless">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Email</th>
                            <th>Amount</th>
                            <th>Channel</th>
                            <th>Account Name</th>
                            <th>Account Number</th>
                            <th>Status</th>
                            <th>Requested</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>

<?php
$where = [];
$params = [];
$types = '';

if (!empty($_GET['search'])) {
    $search = '%' . trim($_GET['search']) . '%';
    $where[] = "(w.email LIKE ? OR CAST(w.amount AS CHAR) LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $types .= 'ss';

} elseif (!empty($_GET['date'])) {
    $where[] = "DATE(w.created_at) = ?";
    $params[] = $_GET['date'];
    $types .= 's';

} elseif (!empty($_GET['view']) && $_GET['view'] === 'all') {
    // no filter
} else {
    $where[] = "DATE(w.created_at) = CURDATE()";
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT w.*
    FROM withdrawals w
    $whereSql
    ORDER BY w.created_at DESC
";

$stmt = mysqli_prepare($con, $sql);
if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo "<tr><td colspan='9' class='text-center text-muted py-5'>No withdrawals found</td></tr>";
}

while ($row = mysqli_fetch_assoc($result)) {

    $status = (int)$row['status'];
    $badge = match ($status) {
        0 => '<span class="badge badge-pending">Pending</span>',
        1 => '<span class="badge badge-approved">Approved</span>',
        2 => '<span class="badge badge-rejected">Rejected</span>',
        default => '<span class="badge bg-secondary">Unknown</span>'
    };

    $currency = $row['currency'] ?: '$';
?>

<tr>
    <td><?= $row['id'] ?></td>
    <td><?= htmlspecialchars($row['email']) ?></td>
    <td><strong><?= $currency . number_format($row['amount'], 2) ?></strong></td>
    <td><?= htmlspecialchars($row['channel'] ?? '-') ?></td>
    <td><?= htmlspecialchars($row['channel_name'] ?? '-') ?></td>
    <td><?= htmlspecialchars($row['channel_number'] ?? '-') ?></td>
    <td class="status-cell"><?= $badge ?></td>
    <td><?= date('d M Y • H:i', strtotime($row['created_at'])) ?></td>
    <td class="action-cell">
        <?php if ($status === 0): ?>
            <button class="btn btn-sm btn-success approve-btn" data-id="<?= $row['id'] ?>">Approve</button>
            <button class="btn btn-sm btn-danger reject-btn" data-id="<?= $row['id'] ?>">Reject</button>
        <?php else: ?>
            <small class="text-muted">No action</small>
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
document.addEventListener('click', e => {
    const btn = e.target.closest('button');
    if (!btn) return;

    let action = btn.classList.contains('approve-btn') ? 'approve' :
                 btn.classList.contains('reject-btn') ? 'reject' : null;
    if (!action) return;

    if (!confirm(`Are you sure you want to ${action}?`)) return;

    fetch('codes/manage-withdrawals.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=${action}&id=${btn.dataset.id}`
    })
    .then(r => r.json())
    .then(d => {
        if (!d.success) return alert(d.message);
        const row = btn.closest('tr');
        row.querySelector('.action-cell').innerHTML = '<small class="text-muted">No action</small>';
        row.querySelector('.status-cell').innerHTML =
            action === 'approve'
            ? '<span class="badge badge-approved">Approved</span>'
            : '<span class="badge badge-rejected">Rejected</span>';
    });
});
</script>

</body>
</html>
