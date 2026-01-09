<?php
session_start();
include('../config/dbcon.php');
include('inc/header.php');
include('inc/navbar.php');
include('inc/sidebar.php');

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Invalid region.";
    header("Location: region_settings.php");
    exit();
}

$id = mysqli_real_escape_string($con, $_GET['id']);
$query = "SELECT id, country, qr_image FROM region_settings WHERE id='$id' LIMIT 1";
$result = mysqli_query($con, $query);

if (mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = "Region not found.";
    header("Location: region_settings.php");
    exit();
}

$data = mysqli_fetch_assoc($result);
?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Edit QR Code</h1>
        <p class="text-muted">Region: <strong><?= htmlspecialchars($data['country']) ?></strong></p>
    </div>

    <div class="card">
        <div class="card-body">

            <form action="codes/update_region_qr.php" method="POST" enctype="multipart/form-data">

                <input type="hidden" name="region_id" value="<?= $data['id'] ?>">

                <div class="mb-3">
                    <label class="form-label">Current QR / Image</label><br>
                    <?php if (!empty($data['qr_image']) && file_exists($data['qr_image'])): ?>
                        <img src="<?= htmlspecialchars($data['qr_image']) ?>" class="preview-img">
                    <?php else: ?>
                        <p class="text-muted">No QR uploaded yet.</p>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label">Upload New QR / Image</label>
                    <input type="file" name="qr_image" class="form-control" accept="image/*" required>
                </div>

                <button type="submit" name="update_qr" class="btn btn-secondary">
                    Save QR
                </button>
                <a href="region_settings.php" class="btn btn-light">Back</a>

            </form>

        </div>
    </div>

</main>

<?php include('inc/footer.php'); ?>
