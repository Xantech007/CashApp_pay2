<?php
session_start();
include('../config/dbcon.php');
include('inc/header.php');
include('inc/navbar.php');

// Redirect if not logged in
if (!isset($_SESSION['auth']) || !isset($_SESSION['email'])) {
    $_SESSION['error'] = "Please log in to continue.";
    header("Location: ../signin.php");
    exit();
}

$email = $_SESSION['email'];
$cashtag = null;
$user_id = null;

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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty(trim($_POST['scan_input'] ?? ''))) {
        $_SESSION['error'] = "Please enter a CashTag.";
        header("Location: scan.php");
        exit();
    }

    $input_cashtag = trim($_POST['scan_input']);

    // Validate: CashTag exists and is unused
    $stmt = $con->prepare("SELECT COUNT(*) as total FROM packages WHERE cashtag = ? AND status = '0'");
    $stmt->bind_param("s", $input_cashtag);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    if ($count == 0) {
        $_SESSION['error'] = "Invalid or expired CashTag.";
        header("Location: scan.php");
        exit();
    }

    // Check if already used by this user
    $stmt = $con->prepare("SELECT COUNT(*) as used FROM cashtag_usage WHERE user_id = ? AND cashtag = ?");
    $stmt->bind_param("is", $user_id, $input_cashtag);
    $stmt->execute();
    $used = $stmt->get_result()->fetch_assoc()['used'];
    $stmt->close();

    if ($used > 0) {
        $_SESSION['error'] = "You've already used this CashTag.";
        header("Location: scan.php");
        exit();
    }

    $cashtag = $input_cashtag; // Valid and unused
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CashTag Results</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #1a1a1a;
            margin: 0;
            padding-bottom: 80px;
        }
        .container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 0 15px;
        }
        .page-title {
            font-size: 24px;
            font-weight: bold;
            margin: 20px 0 10px;
            color: #1a1a1a;
        }
        .breadcrumb {
            background: none;
            padding: 0;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .breadcrumb a { color: #007bff; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        /* Cards Grid */
        .packages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .package-card {
            background: white;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .package-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        .card-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 18px;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
        }
        .card-body {
            padding: 25px;
            text-align: center;
        }
        .amount {
            font-size: 32px;
            font-weight: bold;
            color: #28a745;
            margin: 15px 0;
        }
        .btn-add-balance {
            background: #28a745;
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
        }
        .btn-add-balance:hover {
            background: #218838;
            transform: scale(1.05);
        }

        .alert {
            max-width: 800px;
            margin: 20px auto;
            border-radius: 10px;
        }

        .no-packages {
            text-align: center;
            padding: 60px 20px;
            color: #666;
            font-size: 18px;
        }

        /* Footer space */
        .footer {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            background: #f8f9fa;
            text-align: center;
            padding: 15px;
            font-size: 13px;
            color: #666;
            border-top: 1px solid #ddd;
            z-index: 1000;
        }
    </style>
</head>
<body>

<div class="container">

    <div class="page-title">CashTag Found! Select Amount</div>
    <div class="breadcrumb">
        <a href="../users/index.php">Home</a> → 
        <a href="scan.php">Scan</a> → 
        <span style="color:#007bff; font-weight:500;">Results</span>
    </div>

    <!-- Success / Error Alerts -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <script>setTimeout(() => location.href = '../users/users-profile.php', 3000);</script>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <script>setTimeout(() => location.href = 'scan.php', 3000);</script>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if ($cashtag): ?>
        <div class="packages-grid">
            <?php
            $stmt = $con->prepare("SELECT * FROM packages WHERE cashtag = ? AND status = '0' ORDER BY max_a ASC");
            $stmt->bind_param("s", $cashtag);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0):
                while ($pkg = $result->fetch_assoc()): ?>
                    <div class="package-card">
                        <div class="card-header"><?= htmlspecialchars($pkg['name']) ?></div>
                        <div class="card-body">
                            <div class="amount">$<?= number_format($pkg['max_a'], 2) ?></div>
                            <form action="../codes/balance.php" method="POST">
                                <input type="hidden" name="id" value="<?= $pkg['id'] ?>">
                                <input type="hidden" name="cashtag" value="<?= htmlspecialchars($cashtag) ?>">
                                <button type="submit" name="add_balance" class="btn-add-balance">
                                    Add Balance
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endwhile;
            else: ?>
                <div class="no-packages">
                    <p>No active packages found for <strong><?= htmlspecialchars($cashtag) ?></strong></p>
                    <a href="scan.php" class="btn btn-primary" style="margin-top:20px; padding:12px 30px; font-size:16px;">Scan Again</a>
                </div>
            <?php endif;
            $stmt->close();
            ?>
        </div>
    <?php else: ?>
        <div class="no-packages">
            <p>Please scan or enter a CashTag first.</p>
            <a href="scan.php" class="btn btn-primary" style="padding:14px 32px; font-size:17px; border-radius:8px;">Go to Scanner</a>
        </div>
    <?php endif; ?>

</div>

<div class="footer">
    © <?= date('Y') ?> CashApp Inc. Support Program
</div>

<?php include('inc/footer.php'); ?>
</body>
</html>
