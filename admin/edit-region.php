<?php
session_start();
include('../config/dbcon.php');
include('inc/header.php');
include('inc/navbar.php');
include('inc/sidebar.php');

$id = $_GET['id'];
$query = "SELECT * FROM region_settings WHERE id='$id'";
$result = mysqli_query($con, $query);
$data = mysqli_fetch_assoc($result);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Edit Region</h1>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="codes/region_settings.php" method="POST" enctype="multipart/form-data">

                <input type="hidden" name="id" value="<?= $data['id'] ?>">

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Country</label>
                        <input type="text" name="country" class="form-control" value="<?= $data['country'] ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label>Currency</label>
                        <input type="text" name="currency" class="form-control" value="<?= $data['currency'] ?>" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Channel</label>
                        <input type="text" name="Channel" class="form-control" value="<?= $data['Channel'] ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label>Channel Number</label>
                        <input type="text" name="Channel_number" class="form-control" value="<?= $data['Channel_number'] ?>" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Payment Amount</label>
                        <input type="number" step="0.01" name="payment_amount" class="form-control" value="<?= $data['payment_amount'] ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label>Rate</label>
                        <input type="number" step="0.01" name="rate" class="form-control" value="<?= $data['rate'] ?>" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>QR / Image</label>
                        <input type="file" name="qr_image" class="form-control">
                        <?php if (!empty($data['qr_image'])): ?>
                            <img src="<?= $data['qr_image'] ?>" class="preview-img mt-2">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label>
                            <input type="checkbox" name="crypto" <?= $data['crypto'] == 1 ? 'checked' : '' ?>>
                            Enable Crypto
                        </label>
                    </div>
                </div>

                <button type="submit" name="update_region" class="btn btn-secondary">Update Region</button>
                <a href="region-settings.php" class="btn btn-light">Cancel</a>

            </form>
        </div>
    </div>
</main>

<?php include('inc/footer.php'); ?>
