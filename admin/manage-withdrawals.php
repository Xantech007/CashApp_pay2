<?php
session_start();
include('inc/header.php');
include('inc/navbar.php');
include('inc/sidebar.php');
include('../config/dbcon.php');
?>

<style>
    .badge-pending   { background-color: #ffc107; color: black; }
    .badge-approved  { background-color: #198754; }
    .badge-rejected  { background-color: #dc3545; }
    .transition-chevron { transition: transform 0.25s ease; }
    .collapse.show .transition-chevron { transform: rotate(90deg); }
</style>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Manage Withdrawals</h1>
    </div>

    <div class="card">
        <div class="card-body">

            <div class="table-responsive mt-4">
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
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
<?php
$query = "SELECT * FROM withdrawals ORDER BY created_at DESC";
$result = mysqli_query($con, $query);

if (mysqli_num_rows($result) === 0) {
    echo "<tr><td colspan='9' class='text-center text-muted py-4'>No withdrawals found</td></tr>";
}

while ($row = mysqli_fetch_assoc($result)) {
    $status = (int)$row['status'];

    $statusBadge = match ($status) {
        0 => '<span class="badge badge-pending">Pending</span>',
        1 => '<span class="badge badge-approved">Approved</span>',
        2 => '<span class="badge badge-rejected">Rejected</span>',
        default => '<span class="badge bg-secondary">Unknown</span>',
    };
?>
<tr>
    <td><?= $row['id'] ?></td>
    <td><?= htmlspecialchars($row['email']) ?></td>
    <td><strong>$<?= number_format($row['amount'], 2) ?></strong></td>
    <td><?= htmlspecialchars($row['channel']) ?></td>
    <td><?= htmlspecialchars($row['channel_name']) ?></td>
    <td><?= htmlspecialchars($row['channel_number']) ?></td>
    <td class="status-cell"><?= $statusBadge ?></td>
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
<?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include('inc/footer.php'); ?>

<script>
document.addEventListener('click', function (e) {
    const btn = e.target.closest('button');
    if (!btn) return;

    let action = null;
    if (btn.classList.contains('approve-btn')) action = 'approve';
    if (btn.classList.contains('reject-btn')) action = 'reject';
    if (!action) return;

    const id = btn.dataset.id;
    if (!confirm(`Are you sure you want to ${action}?`)) return;

    fetch('codes/manage-withdrawals.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=${action}&id=${id}`
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            alert(res.message);
            return;
        }

        const row = btn.closest('tr');
        row.querySelector('.action-cell').innerHTML = '<small class="text-muted">No action</small>';

        if (action === 'approve') {
            row.querySelector('.status-cell').innerHTML =
                '<span class="badge badge-approved">Approved</span>';
        } else {
            row.querySelector('.status-cell').innerHTML =
                '<span class="badge badge-rejected">Rejected</span>';
        }
    })
    .catch(() => alert('Network error'));
});
</script>
