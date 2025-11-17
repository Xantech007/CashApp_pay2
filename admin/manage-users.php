<?php
session_start();
include('inc/header.php');
include('inc/navbar.php');
include('inc/sidebar.php');
?>

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
                    <!-- Rest of the table code remains unchanged -->
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
                                $verify_status = match ((int)$data['verify']) {
                                    0 => 'Not Verified',
                                    1 => 'Under Review',
                                    2 => 'Verified',
                                    default => 'Not Verified'
                                };
                                $verify_badge_class = match ((int)$data['verify']) {
                                    0, null => 'bg-danger',
                                    1 => 'bg-warning',
                                    2 => 'bg-success',
                                    default => 'bg-danger'
                                };
                        ?>
                                <tr>                                       
                                    <td><?= htmlspecialchars($data['id']) ?></td>                   
                                    <td class="user-name"><?= htmlspecialchars($data['name']) ?></td>                   
                                    <td class="user-email"><?= htmlspecialchars($data['email']) ?></td>                   
                                    <td><?= htmlspecialchars($data['refered_by']) ?></td>                   
                                    <td>
                                        <img src="../Uploads/profile-picture/<?= htmlspecialchars($data['image']) ?>" style="width:50px;height:50px" alt="Profile" class="">
                                    </td>                   
                                    <td>
                                        <span class="badge <?= $verify_badge_class ?>">
                                            <?= htmlspecialchars($verify_status) ?>
                                        </span>
                                        <button type="button" class="btn btn-outline-primary btn-sm mt-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#verifyModal<?= $data['id'] ?>">
                                            Change
                                        </button>
                                        <!-- Verification Status Modal -->
                                        <div class="modal fade" id="verifyModal<?= $data['id'] ?>" tabindex="-1" aria-labelledby="verifyModalLabel<?= $data['id'] ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="verifyModalLabel<?= $data['id'] ?>">Change Verification Status for <?= htmlspecialchars($data['name']) ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <form action="codes/users.php" method="POST">
                                                            <input type="hidden" name="user_id" value="<?= $data['id'] ?>">
                                                            <div class="form-group mb-3">
                                                                <label for="verify_status_<?= $data['id'] ?>" class="mb-2">Verification Status</label>
                                                                <select name="verify_status" class="form-control" id="verify_status_<?= $data['id'] ?>" required>
                                                                    <option value="0" <?= ((int)$data['verify'] === 0 || is_null($data['verify'])) ? 'selected' : '' ?>>Not Verified</option>
                                                                    <option value="1" <?= (int)$data['verify'] === 1 ? 'selected' : '' ?>>Under Review</option>
                                                                    <option value="2" <?= (int)$data['verify'] === 2 ? 'selected' : '' ?>>Verified</option>
                                                                </select>
                                                            </div>
                                                            <button type="submit" class="btn btn-secondary" name="update_verify_status">Save Changes</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>                   
                                    <td>
                                        <a href="edit-user?id=<?= $data['id'] ?>" class="btn btn-light">Edit</a> 
                                    </td>                   
                                    <td>
                                        <form action="codes/users.php" method="POST">  
                                            <input type="hidden" value="<?= htmlspecialchars($data['image']) ?>" name="profile_pic">                         
                                            <button class="btn btn-outline-danger" name="delete_user" value="<?= $data['id'] ?>">Delete</button>
                                        </form> 
                                    </td>                   
                                </tr>
                        <?php
                            }
                        } else {
                            echo '<tr><td colspan="8">No users found.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <!-- End Bordered Table -->
        </div>
    </div>
</main><!-- End #main -->

<?php include('inc/footer.php'); ?>

<!-- JavaScript for real-time search -->
<script>
document.getElementById('searchInput').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#usersTable tbody tr');

    rows.forEach(row => {
        const name = row.querySelector('.user-name').textContent.toLowerCase();
        const email = row.querySelector('.user-email').textContent.toLowerCase();
        
        if (name.includes(searchTerm) || email.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});
</script>
</html>
