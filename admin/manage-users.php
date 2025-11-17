<?php
session_start();
include('inc/header.php');
include('inc/navbar.php');
include('inc/sidebar.php');
?>

<!-- Custom CSS for Purple Badge -->
<style>
    .bg-purple {
        background-color: #6f42c1 !important; /* Bootstrap-like purple */
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
    </div><!-- End Page Title -->

    <div class="card">
        <div class="card-body">
            <!-- Search Bar -->
            <div class="mb-3 mt-4">
                <input type="text" id="searchInput" class="form-control" placeholder="Search by name or email..." style="max-width: 400px;">
            </div>

            <!-- Bordered Table -->
            <div class="table-responsive">
                <table class="table table-borderless" id="usersTable">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Referred By</th>
                            <th scope="col">Profile Picture</th>
                            <th scope="col">Verification Status</th>
                            <th scope="col">Edit</th>
                            <th scope="col">Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT * FROM users";
                        $query_run = mysqli_query($con, $query);

                        if (mysqli_num_rows($query_run) > 0) {
                            foreach ($query_run as $data) {
                                // Determine status text
                                $verify_status = match ((int)$data['verify']) {
                                    0 => 'Not Verified',
                                    1 => 'Under Review',
                                    2 => 'Verified',
                                    3 => 'Partial',
                                    default => 'Not Verified'
                                };

                                // Determine badge color
                                $verify_badge_class = match ((int)$data['verify']) {
                                    0, null => 'bg-danger',
                                    1 => 'bg-warning text-dark',
                                    2 => 'bg-success',
                                    3 => 'bg-purple',           // Purple for Partial
                                    default => 'bg-danger'
                                };
                        ?>
                                <tr>
                                    <td><?= htmlspecialchars($data['id']) ?></td>
                                    <td class="user-name"><?= htmlspecialchars($data['name']) ?></td>
                                    <td class="user-email"><?= htmlspecialchars($data['email']) ?></td>
                                    <td><?= htmlspecialchars($data['refered_by'] ?? '-') ?></td>
                                    <td>
                                        <img src="../Uploads/profile-picture/<?= htmlspecialchars($data['image'] ?? 'default.png') ?>" 
                                             style="width:50px;height:50px;border-radius:50%;object-fit:cover;" 
                                             alt="Profile" class="img-thumbnail">
                                    </td>
                                    <td>
                                        <span class="badge <?= $verify_badge_class ?>">
                                            <?= $verify_status ?>
                                        </span>
                                        <button type="button" class="btn btn-outline-primary btn-sm mt-1"
                                                data-bs-toggle="modal"
                                                data-bs-target="#verifyModal<?= $data['id'] ?>">
                                            Change
                                        </button>

                                        <!-- Verification Modal -->
                                        <div class="modal fade" id="verifyModal<?= $data['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">
                                                            Change Verification Status for <?= htmlspecialchars($data['name']) ?>
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <form action="codes/users.php" method="POST">
                                                            <input type="hidden" name="user_id" value="<?= $data['id'] ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label">Verification Status</label>
                                                                <select name="verify_status" class="form-select" required>
                                                                    <option value="0" <?= ($data['verify'] == 0 || $data['verify'] === null) ? 'selected' : '' ?>>Not Verified</option>
                                                                    <option value="1" <?= $data['verify'] == 1 ? 'selected' : '' ?>>Under Review</option>
                                                                    <option value="3" <?= $data['verify'] == 3 ? 'selected' : '' ?>>Partial</option>
                                                                    <option value="2" <?= $data['verify'] == 2 ? 'selected' : '' ?>>Verified</option>
                                                                </select>
                                                            </div>
                                                            <button type="submit" name="update_verify_status" class="btn btn-primary">
                                                                Save Changes
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="edit-user?id=<?= $data['id'] ?>" class="btn btn-light btn-sm">Edit</a>
                                    </td>
                                    <td>
                                        <form action="codes/users.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="profile_pic" value="<?= htmlspecialchars($data['image']) ?>">
                                            <button type="submit" name="delete_user" value="<?= $data['id'] ?>" 
                                                    class="btn btn-outline-danger btn-sm"
                                                    onclick="return confirm('Are you sure you want to delete this user?')">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                        <?php
                            }
                        } else {
                            echo '<tr><td colspan="8" class="text-center">No users found.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include('inc/footer.php'); ?>

<!-- Real-time Search Script -->
<script>
    document.getElementById('searchInput').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('#usersTable tbody tr');

        rows.forEach(row => {
            const name = row.querySelector('.user-name')?.textContent.toLowerCase() || '';
            const email = row.querySelector('.user-email')?.textContent.toLowerCase() || '';

            if (name.includes(searchTerm) || email.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
</script>
