<?php
session_start();
include('../config/dbcon.php');
include('inc/header.php');
include('inc/navbar.php');

// Redirect if not logged in
if (!isset($_SESSION['auth']) || !isset($_SESSION['email'])) {
    $_SESSION['error'] = "Please log in to access this page.";
    header("Location: ../signin.php");
    exit();
}

$email = $_SESSION['email'];
$user_id = null;
$cashtag = null;

// Get user ID securely
$stmt = $con->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $user_id = $row['id'];
} else {
    $_SESSION['error'] = "User not found.";
    header("Location: ../signin.php");
    exit();
}
$stmt->close();

// Handle CashTag submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty(trim($_POST['scan_input'] ?? ''))) {
    $input_cashtag = trim($_POST['scan_input']);

    // Check if CashTag exists and is active
    $stmt = $con->prepare("SELECT COUNT(*) as total FROM packages WHERE cashtag = ? AND status = '0'");
    $stmt->bind_param("s", $input_cashtag);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    if ($count == 0) {
        $_SESSION['error'] = "Invalid or already used CashTag.";
        header("Location: scan.php");
        exit();
    }

    // Check if user already used this CashTag
    $stmt = $con->prepare("SELECT COUNT(*) as used FROM cashtag_usage WHERE user_id = ? AND cashtag = ?");
    $stmt->bind_param("is", $user_id, $input_cashtag);
    $stmt->execute();
    $used = $stmt->get_result()->fetch_assoc()['used'];
    $stmt->close();

    if ($used > 0) {
        $_SESSION['error'] = "You have already claimed this CashTag.";
        header("Location: scan.php");
        exit();
    }

    $cashtag = $input_cashtag;
}
?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>CashTag Found! Select Amount</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../users/index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="scan.php">Scan</a></li>
                <li class="breadcrumb-item active">Results</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Success Message -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-1"></i>
            <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <script>
            setTimeout(() => window.location.href = '../users/users-profile.php', 3000);
        </script>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- Error Message -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-octagon me-1"></i>
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <script>
            setTimeout(() => window.location.href = 'scan.php', 4000);
        </script>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <section class="section">
        <div class="row">

            <?php if ($cashtag): ?>
                <?php
                $stmt = $con->prepare("SELECT * FROM packages WHERE cashtag = ? AND status = '0' ORDER BY max_a ASC");
                $stmt->bind_param("s", $cashtag);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0):
                    while ($pkg = $result->fetch_assoc()): ?>
                        <div class="col-lg-4 col-md-6 col-sm-12 mb-4">
                            <div class="card info-card sales-card">
                                <div class="card-body text-center">
                                    <h5 class="card-title"><?= htmlspecialchars($pkg['name']) ?></h5>
                                    <div class="d-flex align-items-center justify-content-center">
                                        <div class="ps-3">
                                            <h4 class="text-success fw-bold">$<?= number_format($pkg['max_a'], 2) ?></h4>
                                            <span class="text-muted small">Available Balance</span>
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <form action="../codes/balance.php" method="POST">
                                            <input type="hidden" name="id" value="<?= $pkg['id'] ?>">
                                            <input type="hidden" name="cashtag" value="<?= htmlspecialchars($cashtag) ?>">
                                            <button type="submit" name="add_balance" class="btn btn-success w-100">
                                                <i class="bi bi-plus-circle"></i> Add Balance
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile;
                else: ?>
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <h5>No active packages found for this CashTag.</h5>
                                <a href="scan.php" class="btn btn-primary mt-3">Scan Another</a>
                            </div>
                        </div>
                    </div>
                <?php endif;
                $stmt->close();
                ?>

            <?php else: ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <h4>Please scan a valid CashTag first.</h4>
                            <a href="scan.php" class="btn btn-primary btn-lg mt-3">
                                <i class="bi bi-upc-scan"></i> Go to Scanner
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </section>

</main><!-- End #main -->

<?php include('inc/footer.php'); ?>
