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
                <!-- Date Filter -->
                <div class="col-md-5">
                    <form method="GET" class="d-flex gap-2">
                        <div class="input-group">
                            <span class="input-group-text">Registered On</span>
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

                <div class="col-md-2 text-end">
                    <small class="text-muted">
                        Showing:
                        <strong>
                            <?php
                            if (!empty($_GET['search'])) echo htmlspecialchars($_GET['search']);
                            elseif (!empty($_GET['date'])) echo date('d M Y', strtotime($_GET['date']));
                            else echo 'Today';
                            ?>
                        </strong>
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
                            <th>Photo</th>
                            <th>Verification</th>
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
                            $where_conditions[] = "DATE(created_at) = ?";
                            $params[] = $date;
                            $types .= 's';
                        }

                        // Search filter (overrides date)
                        if (!empty($_GET['search'])) {
                            $search = '%' . trim($_GET['search']) . '%';
                            $where_conditions[] = "(name LIKE ? OR email LIKE ?)";
                            $params[] = $search;
                            $params[] = $search;
                            $types .= 'ss';
                        }

                        // Default: today only
                        if (empty($_GET['date']) && empty($_GET['search'])) {
                            $today = date('Y-m-d');
                            $where_conditions[] = "DATE(created_at) = ?";
                            $params[] = $today;
                            $types .= 's';
                        }

                        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

                        $query = "SELECT id, name, email, refered_by, image, verify, created_at 
                                  FROM users 
                                  $where_clause 
                                  ORDER BY created_at DESC, id DESC";

                        $stmt = mysqli_prepare($con, $query);
                        if ($params) mysqli_stmt_bind_param($stmt, $types, ...$params);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);

                        if (mysqli_num_rows($result) == 0) {
                            echo "<tr><td colspan='7' class='text-center py-5 text-muted'>No users found.</td></tr>";
                        } else {
                            $grouped = [];
                            while ($user = mysqli_fetch_assoc($result);
                            while ($user) {
                                $reg_date = date('d M Y', strtotime($user['created_at']));
                                $grouped[$reg_date][] = $user;
                            }

                            foreach ($grouped as $date => $users) {
                                $count = count($users);
                                $collapseId = 'collapse-' . preg_replace('/[^a-z0-9]/', '', strtolower($date));
                                ?>
                                <!-- Date Group Header -->
                                <tr class="table-primary fw-bold bg-light">
                                    <td colspan="7">
                                        <a class="text-dark text-decoration-none d-flex align-items-center"
                                           data-bs-toggle="collapse" href="#<?= $collapseId ?>" role="button">
                                            <i class="bi bi-chevron-right me-2 transition-chevron"></i>
                                            <?= $date ?> 
                                            <span class="badge bg-primary ms-2"><?= $count ?> user<?= $count > 1 ? 's' : '' ?></span>
                                        </a>
                                    </td>
                                </tr>

                                <!-- Users in this date -->
                                <tr class="collapse show" id="<?= $collapseId ?>">
                                    <td colspan="7" class="p-0">
                                        <table class="table table-sm table-hover mb-0 w-100">
                                            <?php foreach ($users as $user):
                                                $verify = (int)($user['verify'] ?? 0);
                                                $verify_text = match ($verify) {
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
                                                             width="50" height="50" class="rounded-circle object-fit-cover" alt="Profile">
                                                    </td>
                                                    <td>
                                                        <span class="badge <?= $badge_class ?> verify-badge"
                                                              data-user-id="<?= $user['id'] ?>"
                                                              data-current="<?= $verify ?>">
                                                            <?= $verify_text ?>
                                                        </span>
                                                        <button type="button" class="btn btn-outline-primary btn-sm mt-1"
                                                                onclick="openVerifyModal(<?= $user['id'] ?>, <?= $verify ?>, '<?= addslashes(htmlspecialchars($user['name'])) ?>')">
                                                            Change
                                                        </button>
                                                    </td>
                                                    <td>
                                                        <a href="edit-user?id=<?= $user['id'] ?>" class="btn btn-light btn-sm">Edit</a>
                                                        <form action="codes/users.php" method="POST" style="display:inline;">
                                                            <input type="hidden" name="profile_pic" value="<?= htmlspecialchars($user['image'] ?? '') ?>">
                                                            <button type="submit" name="delete_user" value="<?= $user['id'] ?>"
                                                                    class="btn btn-outline-danger btn-sm ms-1"
                                                                    onclick="return confirm('Delete user permanently?')">
                                                                Delete
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    </td>
                                </tr>
                                <?php
                            }
                        }
                        mysqli_stmt_close($stmt);
                        ?>
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
                    <h5 class="modal-title">Change Verification Status - <span id="modalUserName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <select id="verifyStatusSelect" class="form-select">
                        <option value="0">Not Verified</option>
                        <option value="1">Under Review</option>
                        <option value="3">Partial</option>
                        <option value="2">Verified</option>
                    </select>
                    <input type="hidden" id="verifyUserId">
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
function openVerifyModal(userId, currentStatus, userName) {
    document.getElementById('modalUserName').textContent = userName;
    document.getElementById('verifyStatusSelect').value = currentStatus;
    document.getElementById('verifyUserId').value = userId;
    new bootstrap.Modal('#verifyModal').show();
}

document.getElementById('saveVerifyBtn').onclick = function() {
    const userId = document.getElementById('verifyUserId').value;
    const newStatus = document.getElementById('verifyStatusSelect').value;

    fetch('codes/update-verify-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `user_id=${userId}&verify_status=${newStatus}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const badge = document.querySelector(`.verify-badge[data-user-id="${userId}"]`);
            const texts = {0:'Not Verified', 1:'Under Review', 2:'Verified', 3:'Partial'};
            const classes = {0:'bg-danger', 1:'bg-warning text-dark', 2:'bg-success', 3:'bg-purple'};

            badge.textContent = texts[newStatus];
            badge.className = `badge verify-badge ${classes[newStatus]}`;
            badge.dataset.current = newStatus;

            bootstrap.Modal.getInstance('#verifyModal').hide();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(() => alert('Network error'));
};
</script>
