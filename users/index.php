<?php
session_start();
include('inc/header.php');
include('inc/navbar.php');
if (!isset($_SESSION['auth'])) {
    $_SESSION['error'] = "Login to access dashboard!";
    header("Location: ../signin");
    exit(0);
}
$email = $_SESSION['email'] ?? null;
$name = 'Guest';
$balance = 0.00;
if ($email) {
    $user_query = "SELECT name, balance FROM users WHERE email = ?";
    $stmt = $con->prepare($user_query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user_result = $stmt->get_result();
    if ($user_result && $user_result->num_rows > 0) {
        $user_data = $user_result->fetch_assoc();
        $name = $user_data['name'];
        $balance = $user_data['balance'] ?? 0.00;
    }
    $stmt->close();
}
$cashtag_query = "SELECT cashtag FROM packages WHERE dashboard = 'enabled' ORDER BY cashtag";
$cashtag_result = mysqli_query($con, $cashtag_query);
$cashtags = [];
if ($cashtag_result && mysqli_num_rows($cashtag_result) > 0) {
    while ($row = mysqli_fetch_assoc($cashtag_result)) {
        $cashtags[] = $row['cashtag'];
    }
}
$formatted_balance = number_format($balance, 2, '.', $balance >= 1000 ? ',' : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #1a1a1a;
        }
        body { padding-bottom: 80px; }
        .container {
            width: 100%;
            max-width: 800px;
            margin: 20px auto;
            padding: 0 15px;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .card-title {
            font-size: 14px;
            color: #757575;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .card-amount {
            font-size: 32px;
            font-weight: bold;
            margin: 8px 0;
        }
        .greeting {
            font-size: 15px;
            color: #555;
        }

        /* === NEW VERTICAL & WIDER BUTTON LAYOUT === */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 18px;
            margin: 30px 0;
        }
        .btn {
            display: block;
            width: 100%;                    /* Full width on mobile */
            max-width: 500px;               /* Limit on large screens */
            margin: 0 auto;                 /* Center the button block */
            padding: 18px 20px;
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
            color: white !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.25s ease;
        }
        .btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        .btn-add       { background: #007bff; } /* Scan - Blue   */
        .btn-withdraw  { background: #6c757d; } /* Withdraw - Gray */
        .btn-used-cashtags { background: #28a745; } /* View Used - Green */

        /* Optional: slightly less wide on very large desktops */
        @media (min-width: 992px) {
            .btn {
                max-width: 420px;
            }
        }

        .cashtag-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        .cashtag-item:last-child { border-bottom: none; }
        .copy-btn {
            background: #f8f9fa;
            color: #012970;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 13px;
            cursor: pointer;
        }
        .copy-btn:hover { background: #e9ecef; }

        .footer {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            background: #f8f9fa;
            text-align: center;
            padding: 14px;
            font-size: 13px;
            font-weight: 500;
            color: #666;
            border-top: 1px solid #ddd;
            z-index: 1000;
        }

        /* Fake notification popup */
        .mgm {
            display: none;
            position: fixed;
            top: 15%;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 420px;
            background: #fff;
            padding: 18px 22px;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.25);
            z-index: 9999;
            text-align: center;
            font-size: 14.5px;
            font-weight: 500;
        }
        .mgm a { color: #f2d516; font-weight: bold; }

        @media (min-width: 768px) {
            .card-amount { font-size: 36px; }
        }
    </style>
</head>
<body>
<div class="container">

    <!-- Balance Card -->
    <div class="card">
        <div class="card-title">Cash Balance</div>
        <div class="card-amount">$<?php echo htmlspecialchars($formatted_balance); ?></div>
        <div class="greeting">Hello <?php echo htmlspecialchars($name); ?>, Scan CashTags to Add Funds into Your Account</div>
    </div>

    <!-- Action Buttons - Now Vertical & Wider -->
    <div class="action-buttons">
        <a href="scan.php" class="btn btn-add">Scan</a>
        <a href="withdrawals.php" class="btn btn-withdraw">Withdraw</a>
        <a href="used-cashtag.php" class="btn btn-used-cashtags">View Used CashTags</a>
    </div>

    <!-- Available CashTags -->
    <div class="card">
        <div class="card-title">Available CashTag(s):</div>
        <?php if (!empty($cashtags)): ?>
            <?php foreach ($cashtags as $index => $cashtag): ?>
                <div class="cashtag-item">
                    <div style="font-weight:bold; font-size:18px;"><?php echo htmlspecialchars($cashtag); ?></div>
                    <button class="copy-btn" data-cashtag="<?php echo htmlspecialchars($cashtag); ?>">
                        <i class="bi bi-clipboard"></i> Copy
                    </button>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#999; text-align:center; padding:20px 0;">No CashTags available</p>
        <?php endif; ?>
    </div>
</div>

<!-- Fake Notification -->
<div class="mgm"><div class="txt"></div></div>

<div class="footer">
    © <?php echo date('Y'); ?> CashApp Inc. Support Program
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Copy buttons
    document.querySelectorAll('.copy-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const text = this.getAttribute('data-cashtag');
            navigator.clipboard.writeText(text).then(() => {
                const original = this.innerHTML;
                this.innerHTML = '<i class="bi bi-check-lg"></i> Copied!';
                this.style.background = '#28a745';
                this.style.color = 'white';
                setTimeout(() => {
                    this.innerHTML = original;
                    this.style.background = '#f8f9fa';
                    this.style.color = '#012970';
                }, 2000);
            });
        });
    });

    // Fake live notifications
    var listNames = ['James','Mary','John','Patricia','Robert','Jennifer','Michael','Linda','William','Elizabeth','David','Barbara','Richard','Susan','Joseph','Nancy','Thomas','Karen','Charles','Lisa','Christopher','Sarah','Daniel','Betty','Matthew'];
    function getRandomAmount(){return Math.floor(Math.random()*(10000-500+1))+500;}
    var interval = Math.floor(Math.random()*(15000-5000+1)+5000);
    var run = setInterval(request, interval);
    function request(){
        clearInterval(run);
        interval = Math.floor(Math.random()*(15000-5000+1)+5000);
        var name = listNames[Math.floor(Math.random()*listNames.length)];
        var amount = getRandomAmount();
        var msg = '<b>'+name+'</b> just withdrew <a href="javascript:void(0);">$'+amount+'</a> from CASHAPP INC. SUPPORT PROGRAM now';
        $(".mgm .txt").html(msg);
        $(".mgm").stop(true).fadeIn(300);
        setTimeout(() => $(".mgm").stop(true).fadeOut(300), 6000);
        run = setInterval(request, interval);
    }
</script>

<?php include('inc/footer.php'); ?>
</body>
</html>
