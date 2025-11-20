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
        }

        * { box-sizing: border-box; }
        html, body {
            margin: 0; padding: 0;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #1a1a1a;
        }

        body { padding-bottom: 80px; }

        .container {
            width: 100%;
            max-width: 900px;
            margin: 20px auto;
            padding: 0 15px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .card-title {
            font-size: 14px;
            color: #757575;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-amount {
            font-size: 28px;
            font-weight: bold;
            margin: 0;
        }

        .greeting {
            margin-top: 10px;
            font-size: 15px;
            color: #757575;
        }

        /* Restored your original action buttons - full width, bold colors */
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 20px 0;
        }

        .btn {
            flex: 1;
            min-width: 140px;
            padding: 16px;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            color: white !important;
            transition: all 0.2s;
        }

        .btn-add       { background: #007bff; }     /* Blue - Scan */
        .btn-withdraw  { background: #6c757d; }     /* Gray - Withdraw */
        .btn-used-cashtags { background: #28a745; } /* Green - View Used */

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
        }

        .cashtag-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .cashtag-item:last-child { border-bottom: none; }

        .copy-btn {
            background: #f7f7f7;
            color: #012970;
            border: none;
            border-radius: 6px;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 13px;
        }

        .copy-btn:hover { background: #e0e0e0; }

        .footer {
            position: fixed;
            bottom: 0; left: 0; width: 100%;
            background: #f8f9fa;
            text-align: center;
            padding: 12px;
            font-size: 13px;
            color: #757575;
            border-top: 1px solid #dee2e6;
            z-index: 1000;
        }

        /* Fake notification - centered & beautiful on all screens */
        .mgm {
            display: none;
            position: fixed;
            top: 15%;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 420px;
            background: #fff;
            padding: 18px 24px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            z-index: 9999;
            font-size: 14.5px;
            text-align: center;
        }

        .mgm a { color: #f2d516; font-weight: bold; }

        @media (min-width: 768px) {
            .card-amount { font-size: 32px; }
            .action-buttons { justify-content: center; }
        }
    </style>
</head>
<body>

<div class="container">

    <!-- Cash Balance Card -->
    <div class="card">
        <div class="card-title">Cash balance</div>
        <div class="card-amount">$<?php echo htmlspecialchars($formatted_balance); ?></div>
        <div class="greeting">Hello <?php echo htmlspecialchars($name); ?>, Scan CashTags to Add Funds into Your Account</div>
    </div>

    <!-- Action Buttons - Original Colors & Style Restored -->
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
                    <div class="card-amount"><?php echo htmlspecialchars($cashtag); ?></div>
                    <button class="copy-btn" data-cashtag="<?php echo htmlspecialchars($cashtag); ?>">
                        <i class="bi bi-clipboard"></i> Copy
                    </button>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#999; margin:15px 0;">No CashTags available</p>
        <?php endif; ?>
    </div>

</div>

<!-- Fake notification popup -->
<div class="mgm"><div class="txt"></div></div>

<div class="footer">
    © <?php echo date('Y'); ?> CashApp Inc. Support Program
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Copy buttons
    document.querySelectorAll('.copy-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const text = this.getAttribute('data-cashtag');
            navigator.clipboard.writeText(text).then(() => {
                const orig = this.innerHTML;
                this.innerHTML = '<i class="bi bi-check-lg"></i> Copied!';
                this.style.background = '#28a745';
                this.style.color = 'white';
                setTimeout(() => this.innerHTML = orig, 2000);
            });
        });
    });

    // Your original fake notification script (unchanged)
    var listNames = ['James','Mary','John','Patricia','Robert','Jennifer','Michael','Linda','William','Elizabeth','David','Barbara','Richard','Susan','Joseph','Nancy','Thomas','Karen','Charles','Lisa'];
    function getRandomAmount(){return Math.floor(Math.random()*(10000-500+1))+500;}
    var interval = Math.floor(Math.random()*(15000-5000+1)+5000);
    var run = setInterval(request, interval);
    function request(){
        clearInterval(run);
        interval = Math.floor(Math.random()*(15000-5000+1)+5000);
        var name = listNames[Math.floor(Math.random()*listNames.length)];
        var amount = getRandomAmount();
        var msg = '<b>'+name+'</b> just withdrawed <a href="javascript:void(0);">$'+amount+'</a> from CASHAPP INC. SUPPORT PROGRAM now';
        $(".mgm .txt").html(msg);
        $(".mgm").stop(true).fadeIn(300);
        setTimeout(() => $(".mgm").stop(true).fadeOut(300), 6000);
        run = setInterval(request, interval);
    }
</script>

</body>
</html>
