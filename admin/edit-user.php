<?php
session_start();
include('../config/dbcon.php');
include('inc/header.php');
include('inc/navbar.php');
include('inc/sidebar.php');
?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Edit User Details</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard">Home</a></li>
                <li class="breadcrumb-item">Users</li>
                <li class="breadcrumb-item active">Edit User</li>
            </ol>
        </nav>
    </div>

    <style>
        .add-btn {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin: 15px 0;
        }
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .password-toggle {
            display: flex;
            align-items: center;
            margin-top: 5px;
        }
    </style>

    <div class="container">
        <div class="row">
            <div class="card" style="padding:10px">

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <form action="codes/users.php" method="POST">
                    <?php
                    if (isset($_GET['id'])) {
                        $id = mysqli_real_escape_string($con, $_GET['id']);

                        $query = "SELECT id, name, balance, email, refered_by, country,
                                         referal_bonus, message, payment_amount, created_at
                                  FROM users
                                  WHERE id='$id'
                                  LIMIT 1";
                        $query_run = mysqli_query($con, $query);

                        if ($query_run && mysqli_num_rows($query_run) > 0) {
                            $row = mysqli_fetch_array($query_run);

                            $name = $row['name'];
                            $id = $row['id'];
                            $balance = $row['balance'];
                            $email = $row['email'];
                            $referral = $row['refered_by'];
                            $country = $row['country'];
                            $bonus = $row['referal_bonus'];
                            $message = $row['message'] ?? '';
                            $payment_amount = $row['payment_amount'] ?? '';
                            $created_at = $row['created_at'];
                        } else {
                            echo '<div class="alert alert-danger">User not found.</div>';
                            exit;
                        }
                    } else {
                        echo '<div class="alert alert-danger">No user ID provided.</div>';
                        exit;
                    }
                    ?>

                    <div class="row">

                        <div class="col-md-6 form-group mb-3">
                            <label class="mb-2">Name</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($name) ?>" readonly>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label class="mb-2">Email</label>
                            <input name="email" type="email" class="form-control" required value="<?= htmlspecialchars($email) ?>">
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label class="mb-2">Country</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($country) ?>" readonly>
                        </div>

                        <!-- ACCOUNT CREATED DATE -->
                        <div class="col-md-6 form-group mb-3">
                            <label class="mb-2">Account Created On</label>
                            <input type="text"
                                   class="form-control"
                                   value="<?= date('F j, Y h:i A', strtotime($created_at)) ?>"
                                   readonly>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label class="mb-2">Balance</label>
                            <input name="balance" type="number" class="form-control" required value="<?= htmlspecialchars($balance) ?>">
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label class="mb-2">Referral Bonus</label>
                            <input name="referal_bonus" type="number" class="form-control" required value="<?= htmlspecialchars($bonus) ?>">
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label class="mb-2">Payment Amount</label>
                            <input name="payment_amount" type="number" step="0.01" class="form-control"
                                   value="<?= htmlspecialchars($payment_amount) ?>">
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label class="mb-2">New Password</label>
                            <input name="password" type="password" id="password" class="form-control">
                            <div class="password-toggle">
                                <input type="checkbox" id="showPassword" class="me-2">
                                <label for="showPassword">Show Password</label>
                            </div>
                        </div>

                        <div class="col-md-12 form-group mb-3">
                            <label class="mb-2">Notification Message</label>
                            <textarea name="message" class="form-control" rows="4"><?= htmlspecialchars($message) ?></textarea>
                        </div>
                    </div>

                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($id) ?>">
                    <button type="submit" class="btn btn-secondary" name="update_user">Update</button>
                </form>
            </div>
        </div>

        <div class="add-btn">
            <a href="manage-users" class="btn btn-secondary">Back</a>
        </div>
    </div>
</main>

<?php include('inc/footer.php'); ?>

<script>
document.getElementById('showPassword').addEventListener('change', function () {
    document.getElementById('password').type = this.checked ? 'text' : 'password';
});
</script>
</html>
