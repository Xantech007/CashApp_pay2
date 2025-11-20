<?php
session_start();
include('../config/dbcon.php');
include('inc/header.php');
include('inc/navbar.php');

if (!isset($_SESSION['auth'])) {
    $_SESSION['error'] = "Please log in to access this page.";
    header("Location: ../signin.php");
    exit(0);
}

$email = mysqli_real_escape_string($con, $_SESSION['email']);
$user_query = "SELECT id FROM users WHERE email = '$email' LIMIT 1";
$user_query_run = mysqli_query($con, $user_query);
if ($user_query_run && mysqli_num_rows($user_query_run) > 0) {
    $user_data = mysqli_fetch_assoc($user_query_run);
    $user_id = $user_data['id'];
} else {
    $_SESSION['error'] = "User not found.";
    header("Location: ../signin.php");
    exit(0);
}

$cashtag = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['scan_input']) || empty(trim($_POST['scan_input']))) {
        $_SESSION['error'] = "No CashTag provided.";
        header("Location: scan.php");
        exit(0);
    }
    $cashtag = mysqli_real_escape_string($con, trim($_POST['scan_input']));
   
    $cashtag_query = "SELECT COUNT(*) as count FROM packages WHERE cashtag = '$cashtag' AND status = '0'";
    $cashtag_query_run = mysqli_query($con, $cashtag_query);
    if ($cashtag_query_run) {
        $cashtag_result = mysqli_fetch_assoc($cashtag_query_run);
        if ($cashtag_result['count'] == 0) {
            $_SESSION['error'] = "Invalid CashTag.";
            header("Location: scan.php");
            exit(0);
        }
    } else {
        $_SESSION['error'] = "Error validating CashTag. Please try again.";
        header("Location: scan.php");
        exit(0);
    }

    $usage_query = "SELECT COUNT(*) as count FROM cashtag_usage WHERE user_id = '$user_id' AND cashtag = '$cashtag'";
    $usage_query_run = mysqli_query($con, $usage_query);
    if ($usage_query_run) {
        $usage_result = mysqli_fetch_assoc($usage_query_run);
        if ($usage_result['count'] > 0) {
            $_SESSION['error'] = "This CashTag has already been used.";
            header("Location: scan.php");
            exit(0);
        }
    } else {
        $_SESSION['error'] = "Error checking CashTag usage. Please try again.";
        header("Location: scan.php");
        exit(0);
    }
}
?>

<style>
    .results-wrapper {
        max-width: 1100px;
        margin: 40px auto;
        padding: 0 20px;
    }
    .results-wrapper .row {
        justify-content: center;
        gap: 30px;
        margin: 0;
    }
    .results-wrapper .col-md-4 {
        flex: 0 0 auto;
        width: 320px;
        max-width: 100%;
    }
    .results-wrapper .card {
        width: 100%;
        border-radius: 15px;
        box-shadow: 0 6px 25px rgba(0,0,0,0.12);
        transition: transform 0.2s;
    }
    .results-wrapper .card:hover {
        transform: translateY(-5px);
    }
    .results-wrapper .card-header {
        font-size: 1.4rem;
        font-weight: bold;
        background: #007bff;
        color: white;
        border-radius: 15px 15px 0 0 !important;
    }
    .results-wrapper .card-body h6 {
        font-size: 1.5rem;
        font-weight: bold;
        color: #28a745;
    }
    .results-wrapper .btn {
        padding: 12px 30px;
        font-size: 1.1rem;
    }
    @media (max-width: 768px) {
        .results-wrapper {
            margin: 20px auto;
            padding: 0 15px;
        }
        .results-wrapper .col-md-4 {
            width: 100%;
            max-width: 340px;
        }
    }
</style>

<main id="main" class="main">

  <div class="pagetitle">
    <h1>CashTag Found! Select Amount</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../users/index.php">Home</a></li>
        <li class="breadcrumb-item"><a href="../users/scan.php" class="text-decoration-none">Scan</a></li>
        <li class="breadcrumb-item active">Results</li>
      </ol>
    </nav>
  </div>

  <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($_SESSION['success']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <script>setTimeout(() => location.href='../users/users-profile.php', 3000);</script>
    <?php unset($_SESSION['success']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($_SESSION['error']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <script>setTimeout(() => location.href='../users/users-profile.php', 3000);</script>
    <?php unset($_SESSION['error']); ?>
  <?php endif; ?>

  <?php if ($cashtag): ?>
    <div class="results-wrapper">
      <div class="row">
        <?php
        $query = "SELECT * FROM packages WHERE cashtag = '$cashtag' AND status = '0' ORDER BY max_a ASC";
        $query_run = mysqli_query($con, $query);
        if ($query_run && mysqli_num_rows($query_run) > 0):
          while ($data = mysqli_fetch_assoc($query_run)): ?>
            <div class="col-md-4">
              <div class="card text-center">
                <div class="card-header">
                  <?= htmlspecialchars($data['name']) ?>
                </div>
                <div class="card-body mt-3">
                  <h6>Amount: $<?= htmlspecialchars(number_format($data['max_a'], 2)) ?></h6>
                  <form action="../codes/balance.php" method="POST" class="mt-4">
                    <input type="hidden" name="id" value="<?= $data['id'] ?>">
                    <input type="hidden" name="cashtag" value="<?= htmlspecialchars($cashtag) ?>">
                    <button type="submit" name="add_balance" class="btn btn-primary">Add Balance</button>
                  </form>
                </div>
              </div>
            </div>
          <?php endwhile;
        else: ?>
          <div class="text-center w-100">
            <p class="display-6 text-muted">No active packages found for this CashTag.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="container text-center py-5">
      <p class="display-6 text-muted">Please submit a CashTag to view packages.</p>
    </div>
  <?php endif; ?>

</main>

<?php include('inc/footer.php'); ?>
