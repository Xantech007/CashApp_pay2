<?php
session_start();
include('inc/header.php');
include('inc/navbar.php');
include('inc/sidebar.php');
?>

  <!-- ======= Main Section ======= -->
  <main id="main" class="main">

    <div class="pagetitle">
      <h1>Database Management</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item">Settings</li>
          <li class="breadcrumb-item active">Database Backup</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
      <div class="row">

        <!-- Info Summary Card -->
        <div class="col-xxl-4 col-md-6">
          <div class="card info-card sales-card">
            <div class="card-body">
              <h5 class="card-title">Total Database Tables</h5>
              <div class="d-flex align-items-center">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                  <i class="bi bi-database"></i>
                </div>
                <div class="ps-3">
                  <?php
                  // Count total tables in database
                  $tables_query = mysqli_query($con, "SHOW TABLES");
                  $total_tables = mysqli_num_rows($tables_query);
                  ?>
                  <h6><?= $total_tables ?></h6>
                  <span class="text-muted small pt-2 ps-1">Tables configured</span>
                </div>
              </div>
            </div>
          </div>
        </div><!-- End Info Summary Card -->

        <!-- Main Backup Action Card -->
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title"><i class="bi bi-download me-2"></i>Export & Download Backup</h5>
              <p class="card-text">
                Clicking the button below will generate a full SQL dump file containing both your database structure (schema) and all table data.
              </p>

              <div class="alert alert-info d-flex align-items-center" role="alert">
                <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                <div>
                  <strong>Note:</strong> Depending on the size of your database, this process may take a few seconds. Do not refresh or close the page while the download starts.
                </div>
              </div>

              <!-- Download Form / Button -->
              <form action="export_backup.php" method="POST" class="mt-4">
                <button type="submit" name="download_backup" class="btn btn-primary btn-lg">
                  <i class="bi bi-cloud-arrow-down-fill me-2"></i> Download Full SQL Backup
                </button>
              </form>

            </div>
          </div>
        </div><!-- End Action Card -->

      </div>
    </section>

  </main><!-- End #main -->

<?php include('inc/footer.php'); ?>
