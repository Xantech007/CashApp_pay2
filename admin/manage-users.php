<?php
session_start();
include('inc/header.php');
include('inc/navbar.php');
include('inc/sidebar.php');
include('../config/dbcon.php');
?>

<style>
    .bg-purple {
        background-color: #6f42c1 !important;
        color: white !important;
    }
</style>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Manage Users</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard">Home</a></li>
                <li class="breadcrumb-item">Users</li>
                <li class="breadcrumb-item active">Manage Users</li>
            </ol>
        </nav>
    </div>

    <div class="card">
        <div class="card-body">
            <!-- Filters -->
            <div class="row g-3 align-items-center my-4">
                <!-- Search -->
                <div class="col-md-6">
                    <form method="GET" class="d-flex gap-2">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by name or email..." 
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <button type="submit" class="btn btn-success">Search</button>
                        <?php if (!empty($_GET['search'])): ?>
                            <a href="manage-users.php" class="btn btn-outline-danger">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="col-md-6 text-end">
                    <small class="text-muted">
                        <?php if (!empty($_GET['search'])): ?>
                            Results for: <strong>"<?= htmlspecialchars($_GET['search']) ?>"</strong>
                        <?php else: ?>
                            Showing all users
                        <?php endif; ?>
                    </small>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-borderless" id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Referred By</th>
                            <th>Profile</th>
                            <th>Verification Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $where = "";
                        $params = [];
                        $types = "";

                        if (!empty($_GET['search'])) {
                            $search = '%' . trim($_GET['search']) . '%';
                            $where = "WHERE name LIKE ? OR email LIKE ?";
                            $params = [$search, $search];
                            $types = "ss";
                        }

                        $query = "SELECT id, name, email, refered_by, image, verify, created_at 
                                  FROM users 
                                  $where 
                                  ORDER BY id DESC";

                        $stmt = mysqli_prepare($con, $query);
                        if ($params) {
                            mysqli_stmt_bind_param($stmt, $types, ...$params);
                        }
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);

                        if (mysqli_num_rows($result) == 0) {
                            echo '<tr><td colspan="7" class="text-center py-5 text-muted">No users found.</td></tr>';
                        }

                        while ($user = mysqli_fetch_assoc($result)):
                            $verify = (int)$user['verify'];

                            $status_text = match ($verify) {
                                0 => 'Not Verified',
                                1 => 'Under Review',
                                2 => 'Verified',
                                3 => 'Partial',
                                default => 'Not Verified'
                            };

                            $badge_class = match ($verify) {
                                0 => 'bg-danger',
                                1 => 'bg-warning text-dark',
                                2 => 'bg-success',
                                3 => 'bg-purple',
                                default => 'bg-danger'
                            };
                        ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td><?= htmlspecialchars($user['name']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= htmlspecialchars($user['refered_by'] ?? '-') ?></td>
                                <td>
                                    <img src="../Uploads/profile-picture/<?= htmlspecialchars($user['image'] ?? 'default.png') ?>"
                                         class="rounded-circle"
                                         width="50" height="50"
                                         style="object-fit: cover;"
                                         alt="Profile">
                                </td>
                                <td>
                                    <span class="badge <?= $badge_class ?> verify-badge"
                                          data-user-id="<?= $user['id'] ?>"
                                          data-current="<?= $verify ?>">
                                        <?= $status_text ?>
                                    </span>
                                    <button type="button"
                                            class="btn btn-outline-primary btn-sm mt-1 verify-btn"
                                            data-user-id="<?= $user['id'] ?>"
                                            data-current="<?= $verify ?>">
                                        Change
                                    </button>
                                </td>
                                <td>
                                    <a href="edit-user.php?id=<?= $user['id'] ?>" class="btn btn-light btn-sm me-1">Edit</a>
                                    <button type="button"
                                            class="btn btn-outline-danger btn-sm"
                                            onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['image'] ?? '') ?>')">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Single Shared Verification Modal -->
    <div class="modal fade" id="verifyModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Verification Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="verifyUserId">
                    <div class="mb-3">
                        <label class="form-label">Verification Status</label>
                        <select id="verifyStatusSelect" class="form-select">
                            <option value="0">Not Verified</option>
                            <option value="1">Under Review</option>
                            <option value="3">Partial</option>
                            <option value="2">Verified</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveVerifyBtn">Save</button>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include('inc/footer.php'); ?>

<script>
// Open modal and set user ID + current status
document.querySelectorAll('.verify-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const userId = this.dataset.userId;
        const current = this.dataset.current;

        document.getElementById('verifyUserId').value = userId;
        document.getElementById('verifyStatusSelect').value = current;

        new bootstrap.Modal('#verifyModal').show();
    });
});

// Save verification status via AJAX (real-time update)
document.getElementById('saveVerifyBtn').addEventListener('click', function() {
    const userId = document.getElementById('verifyUserId').value;
    const newStatus = document.getElementById('verifyStatusSelect').value;

    fetch('codes/update-verify.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `user_id=${userId}&verify=${newStatus}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const badge = document.querySelector(`.verify-badge[data-user-id="${userId}"]`);
            const text = {
                '0': 'Not Verified',
                '1': 'Under Review',
                '2': 'Verified',
                '3': 'Partial'
            }[newStatus];

            const bg = {
                '0': 'bg-danger',
                '1': 'bg-warning text-dark',
                '2': 'bg-success',
                '3': 'bg-purple'
            }[newStatus];

            badge.textContent = text;
            badge.className = `badge ${bg} verify-badge`;
            badge.dataset.current = newStatus;

            // Update button data too
            const btn = document.querySelector(`.verify-btn[data-user-id="${userId}"]`);
            btn.dataset.current = newStatus;

            bootstrap.Modal.getInstance('#verifyModal').hide();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(() => alert('Network error. Please try again.'));
});

// Delete user with confirmation
function deleteUser(id, image) {
    if (!confirm('Delete this user permanently? This cannot be undone.')) return;

    fetch('codes/users.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `delete_user=${id}&profile_pic=${encodeURIComponent(image)}`
    })
    .then(r => r.text())
    .then(() => location.reload()); // Simple reload after delete
}
</script>
</html>
