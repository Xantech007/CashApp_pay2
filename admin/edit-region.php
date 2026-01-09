<?php
session_start();
include('../config/dbcon.php');
include('inc/header.php');
include('inc/navbar.php');
include('inc/sidebar.php');

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Invalid region selected.";
    header("Location: region_settings.php");
    exit();
}

$id = mysqli_real_escape_string($con, $_GET['id']);
$query = "SELECT * FROM region_settings WHERE id='$id' LIMIT 1";
$query_run = mysqli_query($con, $query);

if (mysqli_num_rows($query_run) == 0) {
    $_SESSION['error'] = "Region not found.";
    header("Location: region_settings.php");
    exit();
}

$data = mysqli_fetch_assoc($query_run);
?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Edit Region</h1>
    </div>

    <div class="card">
        <div class="card-body">

            <form action="codes/region_settings.php" method="POST">

                <input type="hidden" name="id" value="<?= $data['id'] ?>">

                <!-- Country / Currency -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Country</label>
                        <input type="text" name="country" class="form-control" value="<?= htmlspecialchars($data['country']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label>Currency</label>
                        <input type="text" name="currency" class="form-control" value="<?= htmlspecialchars($data['currency']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label>Alt Currency</label>
                        <input type="text" name="alt_currency" class="form-control" value="<?= htmlspecialchars($data['alt_currency']) ?>">
                    </div>
                </div>

                <!-- Crypto / Channel -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Crypto Payment</label>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="crypto" value="1" <?= $data['crypto'] == 1 ? 'checked' : '' ?>>
                            <label class="form-check-label">Enable Crypto Deposit/Transfer</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label>Channel</label>
                        <input type="text" name="Channel" class="form-control" value="<?= htmlspecialchars($data['Channel']) ?>" required>
                    </div>
                </div>

                <!-- Channel Details -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Alt Channel</label>
                        <input type="text" name="alt_channel" class="form-control" value="<?= htmlspecialchars($data['alt_channel']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label>Channel Name</label>
                        <input type="text" name="Channel_name" class="form-control" value="<?= htmlspecialchars($data['Channel_name']) ?>" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Alt Channel Name</label>
                        <input type="text" name="alt_ch_name" class="form-control" value="<?= htmlspecialchars($data['alt_ch_name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label>Channel Number</label>
                        <input type="text" name="Channel_number" class="form-control" value="<?= htmlspecialchars($data['Channel_number']) ?>" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Alt Channel Number</label>
                        <input type="text" name="alt_ch_number" class="form-control" value="<?= htmlspecialchars($data['alt_ch_number']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label>Channel Value</label>
                        <input type="text" name="chnl_value" class="form-control" value="<?= htmlspecialchars($data['chnl_value']) ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Channel Name Value</label>
                        <input type="text" name="chnl_name_value" class="form-control" value="<?= htmlspecialchars($data['chnl_name_value']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label>Channel Number Value</label>
                        <input type="text" name="chnl_number_value" class="form-control" value="<?= htmlspecialchars($data['chnl_number_value']) ?>">
                    </div>
                </div>

                <!-- Amount / Rate -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Payment Amount</label>
                        <input type="number" step="0.01" name="payment_amount" class="form-control" value="<?= htmlspecialchars($data['payment_amount']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label>Rate</label>
                        <input type="number" step="0.01" name="rate" class="form-control" value="<?= htmlspecialchars($data['rate']) ?>" required>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <label>Alt Rate</label>
                        <input type="text" name="alt_rate" class="form-control" value="<?= htmlspecialchars($data['alt_rate']) ?>">
                    </div>
                </div>

                <button type="submit" name="update_region" class="btn btn-secondary">Update Region</button>
                <a href="region_settings.php" class="btn btn-light">Cancel</a>

            </form>

        </div>
    </div>

</main>

<?php include('inc/footer.php'); ?>
