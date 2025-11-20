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
        :root {
            --primary: #007bff;
            --success: #28a745;
            --secondary: #6c757d;
            --light: #f8f9fa;
            --dark: #1a1a1a;
            --gray: #757575;
        }

        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: var(--dark);
        }

        body {
            display: flex;
            flex-direction: column;
            padding-bottom: 70px; /* space for fixed footer */
        }

        .container {
            width: 100%;
            max-width: 800px;           /* comfortable on desktop */
            margin: 20px auto;
            padding: 0 15px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .card-title {
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-amount {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
        }

        .greeting {
            margin-top: 10px;
            font-size: 15px;
            color: var(--gray);
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin: 20px 0;
        }

        .btn {
            padding: 14px 20px;
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            color: white;
            transition: all 0.2s;
        }

        .btn-add { background: var(--primary); }
        .btn-withdraw { background: var(--secondary); }
        .btn-used-cashtags { background: var(--success); grid-column: 1 / -1; }

        .btn:hover { opacity: 0.9; transform: translateY(-1px); }

        .cashtag-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .cashtag-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .cashtag-item:last-child { border-bottom: none; }

        .copy-btn {
            background: #f1f3f5;
            color: #012970;
            border: none;
            border-radius: 6px;
            padding: 6px 10px;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.2s;
        }

        .copy-btn:hover { background: #e0e0e0; }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: var(--light);
            text-align: center;
            padding: 12px 0;
            font-size: 13px;
            color: var(--gray);
            border-top: 1px solid #dee2e6;
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
            padding: 16px 20px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            z-index: 9999;
            font-size: 14px;
            text-align: center;
        }

        .mgm a { color: #f2d516; font-weight: bold; }

        /* Responsive adjustments */
        @media (min-width: 768px) {
            .card-amount { font-size: 32px; }
            .action-buttons { grid-template-columns: repeat(3, 1fr); }
            .btn-used-cashtags { grid-column: auto; }
        }
    </style>
</head>
<body>

<div class="container">

    <!-- Cash Balance Card -->
    <div class="card">
        <div class="card-title">Cash Balance</div>
        <div class="card-amount">$<?php echo htmlspecialchars($formatted_balance); ?></div>
        <div class="greeting">Hello <?php echo htmlspecialchars($name); ?>, scan CashTags to add funds</div>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons">
        <a href="scan.php" class="btn btn-add">Scan</a>
        <a href="withdrawals.php" class="btn btn-withdraw">Withdraw</a>
        <a href="used-cashtag.php" class="btn btn-used-cashtags">View Used CashTags</a>
    </div>

    <!-- Available CashTags -->
    <div class="card">
        <div class="card-title">Available CashTag(s)</div>
        <?php if (!empty($cashtags)): ?>
            <div class="cashtag-list">
                <?php foreach ($cashtags as $index => $cashtag): ?>
                    <div class="cashtag-item">
                        <div class="card-amount"><?php echo htmlspecialchars($cashtag); ?></div>
                        <button class="copy-btn" data-cashtag="<?php echo htmlspecialchars($cashtag); ?>">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color:#999; margin:10px 0;">No CashTags available at the moment</p>
        <?php endif; ?>
    </div>

    <!-- Explore Card (placeholder) -->
    <div class="card">
        <div class="card-title">Explore</div>
        <p style="color:#888;">More features coming soon...</p>
    </div>

</div>

<!-- Fake notification (kept exactly as you had it) -->
<div class="mgm">
    <div class="txt"></div>
</div>

<div class="footer">
    © <?php echo date('Y'); ?> CashApp Inc. Support Program. All rights reserved.
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Copy to clipboard functionality
    document.querySelectorAll('.copy-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const text = this.getAttribute('data-cashtag');
            navigator.clipboard.writeText(text).then(() => {
                const original = this.innerHTML;
                this.innerHTML = '<i class="bi bi-check"></i> Copied!';
                this.style.background = '#28a745';
                this.style.color = 'white';
                setTimeout(() => this.innerHTML = original, 2000);
            }).catch(() => alert('Copy failed'));
        });
    });

    // Fake live withdrawal notifications (your original script – unchanged)
    var listNames = ['James','Mary','John','Patricia','Robert','Jennifer','Michael','Linda','William','Elizabeth','David','Barbara','Richard','Susan','Joseph','Nancy','Thomas','Karen','Charles','Lisa'];
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
