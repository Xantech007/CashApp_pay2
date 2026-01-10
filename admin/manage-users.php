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
            <div class="row g-3 align-items-center mt-4 mb-4">

                <div class="col-md-5">
                    <form method="GET" class="d-flex gap-2">
                        <div class="input-group">
                            <span class="input-group-text">Registration Date</span>
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
                               placeholder="Search name or email..."
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
                            <th>Balance</th>
                            <th>Profile</th>
                            <th>Verification Status</th>
                            <th>Edit</th>
                            <th>Delete</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php

                        $where_conditions = [];
                        $params = [];
                        $types = '';

                        // Search filter
                        if (!empty($_GET['search'])) {
                            $search = '%' . trim($_GET['search']) . '%';
                            $where_conditions[] = "(name LIKE ? OR email LIKE ?)";
                            $params[] = $search;
                            $params[] = $search;
                            $types .= 'ss';
                        }

                        // Date filter
                        elseif (!empty($_GET['date'])) {
                            $date = date('Y-m-d', strtotime($_GET['date']));
                            $where_conditions[] = "DATE(DATE_ADD(created_at, INTERVAL 9 HOUR)) = ?";
                            $params[] = $date;
                            $types .= 's';
                        }

                        // Default: today (+9 hours)
                        else {
                            $where_conditions[] =
                                "DATE(DATE_ADD(created_at, INTERVAL 9 HOUR)) = DATE(DATE_ADD(NOW(), INTERVAL 9 HOUR))";
                        }

                        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

                        // Main Query
                        $query = "
                            SELECT id, name, email, image, verify, balance,
                                   DATE_ADD(created_at, INTERVAL 9 HOUR) AS created_at
                            FROM users
                            $where_clause
                            ORDER BY created_at DESC
                        ";

                        $stmt = mysqli_prepare($con, $query);
                        if ($params) mysqli_stmt_bind_param($stmt, $types, ...$params);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);

                        if (mysqli_num_rows($result) == 0) {
                            echo "<tr><td colspan='9' class='text-center py-5 text-muted'>No users found.</td></tr>";
                        } else {

                            $grouped = [];

                            while ($user = mysqli_fetch_assoc($result)) {
                                $regDate = date('d M Y', strtotime($user['created_at']));
                                $grouped[$regDate][] = $user;
                            }

                            foreach ($grouped as $date => $users) {
                                $collapseId = 'group-' . preg_replace('/[^a-z0-9]/', '', strtolower($date));
                                ?>

                                <tr class="table-primary fw-bold bg-light">
                                    <td colspan="9">
                                        <a class="text-dark text-decoration-none d-flex align-items-center"
                                           data-bs-toggle="collapse" href="#<?= $collapseId ?>" role="button">
                                            <i class="bi bi-chevron-right me-2 transition-chevron"></i>
                                            <?= $date ?>
                                            <span class="badge bg-primary ms-2"><?= count($users) ?> user<?= count($users)>1?'s':'' ?></span>
                                        </a>
                                    </td>
                                </tr>

                                <tr class="collapse show" id="<?= $collapseId ?>">
                                    <td colspan="9" class="p-0">
                                        <table class="table table-sm table-hover mb-0">

                                            <?php foreach ($users as $data): ?>
                                                <tr>

                                                    <td><?= htmlspecialchars($data['id']) ?></td>
                                                    <td><?= htmlspecialchars($data['name']) ?></td>
                                                    <td><?= htmlspecialchars($data['email']) ?></td>

                                                    <td>$<?= number_format($data['balance'] ?? 0, 2) ?></td>

                                                    <td>
                                                        <img src="../Uploads/profile-picture/<?= htmlspecialchars($data['image'] ?? 'default.png') ?>"
                                                             width="50" height="50" class="rounded-circle object-fit-cover">
                                                    </td>

                                                    <td>
                                                        <?php
                                                        $verify = (int)($data['verify'] ?? 0);
                                                        $statusText = ['Not Verified', 'Under Review', 'Verified', 'Partial'][$verify] ?? 'Not Verified';
                                                        $badgeClass = match($verify) {
                                                            0 => 'bg-danger',
                                                            1 => 'bg-warning text-dark',
                                                            2 => 'bg-success',
                                                            3 => 'bg-purple',
                                                            default => 'bg-danger'
                                                        };
                                                        ?>
                                                        <span class="badge <?= $badgeClass ?> verify-badge me-2"
                                                              data-user-id="<?= $data['id'] ?>"
                                                              data-current="<?= $verify ?>">
                                                            <?= $statusText ?>
                                                        </span>

                                                        <select class="form-select form-select-sm d-inline-block" style="width:auto"
                                                                onchange="updateVerify(<?= $data['id'] ?>, this.value)">
                                                            <option value="0" <?= $verify==0?'selected':'' ?>>Not Verified</option>
                                                            <option value="1" <?= $verify==1?'selected':'' ?>>Under Review</option>
                                                            <option value="3" <?= $verify==3?'selected':'' ?>>Partial</option>
                                                            <option value="2" <?= $verify==2?'selected':'' ?>>Verified</option>
                                                        </select>

                                                    </td>

                                                    <td>
                                                        <a href="edit-user?id=<?= $data['id'] ?>" class="btn btn-light btn-sm">Edit</a>
                                                    </td>

                                                    <td>
                                                        <form action="codes/users.php" method="POST" style="display:inline">
                                                            <input type="hidden" name="profile_pic" value="<?= htmlspecialchars($data['image'] ?? '') ?>">
                                                            <button type="submit" name="delete_user" value="<?= $data['id'] ?>"
                                                                    class="btn btn-outline-danger btn-sm"
                                                                    onclick="return confirm('Delete permanently?')">
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
</main>

<?php include('inc/footer.php'); ?>

<script>
function updateVerify(userId, newStatus) {
    newStatus = parseInt(newStatus);
    const badge = document.querySelector(`.verify-badge[data-user-id="${userId}"]`);
    const current = parseInt(badge.dataset.current);
    if (newStatus === current) return;

    fetch('codes/update-verify.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `user_id=${userId}&verify=${newStatus}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const texts = ['Not Verified', 'Under Review', 'Verified', 'Partial'];
            const classes = ['bg-danger', 'bg-warning text-dark', 'bg-success', 'bg-purple'];
            badge.textContent = texts[newStatus];
            badge.className = `badge verify-badge me-2 ${classes[newStatus]}`;
            badge.dataset.current = newStatus;
        } else {
            alert('Error updating status');
            location.reload();
        }
    })
    .catch(() => {
        alert('Connection error');
        location.reload();
    });
}
</script>

</html>
