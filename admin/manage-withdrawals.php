<?php
session_start();
include('inc/header.php');
include('inc/navbar.php');
include('inc/sidebar.php');
include('../config/dbcon.php');
?>
<style>
    .bg-purple { background-color: #6f42c1 !important; color: white !important; }
    .transition-chevron { transition: transform 0.25s ease; }
    .collapse.show .transition-chevron { transform: rotate(90deg); }
    .badge-pending    { background-color: #ffc107; color: black; }
    .badge-completed  { background-color: #198754; }
    .badge-rejected   { background-color: #dc3545; }
</style>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Manage Withdrawals</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard">Home</a></li>
                <li class="breadcrumb-item">Withdrawals</li>
                <li class="breadcrumb-item active">Manage Withdrawals</li>
            </ol>
        </nav>
    </div>

    <div class="card">
        <div class="card-body">
            <!-- Filters -->
            <div class="row g-3 align-items-center mt-4 mb-4">
                <div class="col-md-4">
                    <form method="GET" class="d-flex gap-2">
                        <div class="input-group">
                            <span class="input-group-text">Date</span>
                            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($_GET['date'] ?? '') ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="?" class="btn btn-outline-secondary">Today</a>
                    </form>
                </div>

                <div class="col-md-4">
                    <form method="GET" class="d-flex gap-2">
                        <input type="text" name="search" class="form-control" placeholder="Search email or amount..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <button type="submit" class="btn btn-success">Search</button>
                        <?php if (!empty($_GET['search'])): ?>
                            <a href="?" class="btn btn-outline-danger">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="col-md-4 text-end">
                    <small class="text-muted">
                        Showing: <strong>
                            <?php
                            if (!empty($_GET['search'])) echo 'Search: ' . htmlspecialchars($_GET['search']);
                            elseif (!empty($_GET['date'])) echo date('d M Y', strtotime($_GET['date']));
                            else echo 'Today';
                            ?>
                        </strong>
                    </small>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-borderless" id="withdrawalsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User Email</th>
                            <th>Amount</th>
                            <th>Channel</th>
                            <th>Account Name</th>
                            <th>Account Number</th>
                            <th>Status</th>
                            <th>Requested</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $where_conditions = [];
                        $params = [];
                        $types = '';

                        if (!empty($_GET['search'])) {
                            $search = '%' . trim($_GET['search']) . '%';
                            $where_conditions[] = "(w.email LIKE ? OR CAST(w.amount AS CHAR) LIKE ?)";
                            $params[] = $search;
                            $params[] = $search;
                            $types .= 'ss';
                        } elseif (!empty($_GET['date'])) {
                            $date = date('Y-m-d', strtotime($_GET['date']));
                            $where_conditions[] = "DATE(w.created_at) = ?";
                            $params[] = $date;
                            $types .= 's';
                        } else {
                            $where_conditions[] = "DATE(w.created_at) = CURDATE()";
                        }

                        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

                        $query = "
                            SELECT w.id, w.email, w.amount, w.channel, w.channel_name, 
                                   w.channel_number, w.status, w.created_at
                            FROM withdrawals w
                            $where_clause
                            ORDER BY w.created_at DESC
                        ";

                        $stmt = mysqli_prepare($con, $query);
                        if ($params) mysqli_stmt_bind_param($stmt, $types, ...$params);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);

                        if (mysqli_num_rows($result) == 0) {
                            echo "<tr><td colspan='9' class='text-center py-5 text-muted'>No withdrawal requests found.</td></tr>";
                        } else {
                            $grouped = [];
                            while ($row = mysqli_fetch_assoc($result)) {
                                $reqDate = date('d M Y', strtotime($row['created_at']));
                                $grouped[$reqDate][] = $row;
                            }

                            foreach ($grouped as $date => $withdrawals):
                                $collapseId = 'group-' . preg_replace('/[^a-z0-9]/', '', strtolower($date));
                            ?>
                                <tr class="table-primary fw-bold bg-light">
                                    <td colspan="9">
                                        <a class="text-dark text-decoration-none d-flex align-items-center"
                                           data-bs-toggle="collapse" href="#<?= $collapseId ?>" role="button">
                                            <i class="bi bi-chevron-right me-2 transition-chevron"></i>
                                            <?= $date ?>
                                            <span class="badge bg-primary ms-2"><?= count($withdrawals) ?> request<?= count($withdrawals)>1?'s':'' ?></span>
                                        </a>
                                    </td>
                                </tr>

                                <tr class="collapse show" id="<?= $collapseId ?>">
                                    <td colspan="9" class="p-0">
                                        <table class="table table-sm table-hover mb-0">
                                            <?php foreach ($withdrawals as $data):
                                                $status = (int)$data['status'];
                                                $statusBadge = match($status) {
                                                    0 => '<span class="badge badge-pending">Pending</span>',
                                                    2 => '<span class="badge badge-completed">Completed</span>',
                                                    3 => '<span class="badge badge-rejected">Rejected</span>',
                                                    default => '<span class="badge bg-secondary">Processed</span>'
                                                };
                                            ?>
                                                <tr data-withdrawal-id="<?= $data['id'] ?>">
                                                    <td><?= htmlspecialchars($data['id']) ?></td>
                                                    <td><?= htmlspecialchars($data['email']) ?></td>
                                                    <td><strong>$<?= number_format($data['amount'], 2) ?></strong></td>
                                                    <td><?= htmlspecialchars($data['channel'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($data['channel_name'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($data['channel_number'] ?? '-') ?></td>
                                                    <td class="status-cell"><?= $statusBadge ?></td>
                                                    <td><?= date('d M Y • H:i', strtotime($data['created_at'])) ?></td>
                                                    <td class="action-cell">
                                                        <?php if ($status === 0): ?>
                                                            <button class="btn btn-sm btn-success approve-btn me-1" data-id="<?= $data['id'] ?>">Approve & Complete</button>
                                                            <button class="btn btn-sm btn-danger reject-btn" data-id="<?= $data['id'] ?>">Reject</button>
                                                        <?php else: ?>
                                                            <small class="text-muted">No action</small>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    </td>
                                </tr>
                            <?php endforeach;
                        }
                        mysqli_stmt_close($stmt);
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include('inc/footer.php'); ?>

<script>
document.addEventListener('click', function(e) {
    const btn = e.target.closest('button');
    if (!btn) return;

    let action = null;
    if (btn.classList.contains('approve-btn')) action = 'approve';
    else if (btn.classList.contains('reject-btn')) action = 'reject';

    if (!action) return;

    const id = btn.dataset.id;
    if (!confirm(`Are you sure you want to ${action === 'approve' ? 'approve and complete' : 'reject'} this withdrawal?`)) return;

    fetch('codes/manage-withdrawals.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=${action}&id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const row = btn.closest('tr');
            const statusCell = row.querySelector('.status-cell');
            const actionCell = row.querySelector('.action-cell');

            if (action === 'approve') {
                statusCell.innerHTML = '<span class="badge badge-completed">Completed</span>';
                actionCell.innerHTML = '<small class="text-muted">No action</small>';
            } else if (action === 'reject') {
                statusCell.innerHTML = '<span class="badge badge-rejected">Rejected</span>';
                actionCell.innerHTML = '<small class="text-muted">No action</small>';
            }

            alert(data.message || 'Action completed successfully');
        } else {
            alert(data.message || 'Failed to update withdrawal');
        }
    })
    .catch(() => alert('Connection error. Please try again.'));
});
</script>
</html>
