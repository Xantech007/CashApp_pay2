<?php
session_start();
include('inc/header.php');
include('inc/navbar.php');
include('inc/sidebar.php');
include('../config/dbcon.php');
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Manage Deposits</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard">Home</a></li>
                <li class="breadcrumb-item">Users</li>
                <li class="breadcrumb-item active">Manage Deposits</li>
            </ol>
        </nav>
    </div>

    <!-- ==================== SEARCH BAR ==================== -->
    <div class="card mb-3">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-auto flex-grow-1">
                    <input type="text" name="q" class="form-control" placeholder="Search by name or email..." 
                           value="<?= isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>" id="searchInput">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
                <?php if (!empty($_GET['q'])): ?>
                <div class="col-auto">
                    <a href="?" class="btn btn-outline-secondary">Clear</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <!-- ==================================================== -->

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-borderless" id="depositsTable">
                    <thead>
                        <tr>
                            <th>Amount</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Installment</th>
                            <th>Payment Proof</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="depositsBody">
                        <?php
                        // === PAGINATION & SEARCH SETUP ===
                        $limit = 25;
                        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                        $offset = ($page - 1) * $limit;
                        $search = trim($_GET['q'] ?? '');

                        // Build WHERE
                        $where = '';
                        $params = [];
                        $types = '';
                        if ($search !== '') {
                            $where = "WHERE d.name LIKE ? OR d.email LIKE ?";
                            $like = "%{$search}%";
                            $params = [$like, $like];
                            $types = 'ss';
                        }

                        // Count total
                        $count_sql = "SELECT COUNT(*) AS total FROM deposits d $where";
                        $stmt = $con->prepare($count_sql);
                        if ($params) $stmt->bind_param($types, ...$params);
                        $stmt->execute();
                        $total_deposits = $stmt->get_result()->fetch_assoc()['total'];
                        $total_pages = max(1, ceil($total_deposits / $limit));

                        // Fetch with JOIN
                        $query = "SELECT d.id, d.amount, d.currency, d.name, d.email, d.image, d.approval_status, d.created_at, 
                                         d.payment_plan, d.installment_number, u.id AS user_id 
                                  FROM deposits d 
                                  LEFT JOIN users u ON d.email = u.email 
                                  $where
                                  ORDER BY d.created_at DESC 
                                  LIMIT ? OFFSET ?";
                        $stmt = $con->prepare($query);
                        if ($params) {
                            $stmt->bind_param($types . 'ii', ...$params, $limit, $offset);
                        } else {
                            $stmt->bind_param('ii', $limit, $offset);
                        }
                        $stmt->execute();
                        $query_run = $stmt->get_result();

                        if ($query_run->num_rows == 0) {
                            echo '<tr><td colspan="9" class="text-center text-muted p-4">No deposits found.</td></tr>';
                        } else {
                            $grouped = [];
                            $today = (new DateTime('now', new DateTimeZone('UTC')))->modify('+5 hours')->format('d-M-Y');
                            $firstDate = null;

                            while ($data = $query_run->fetch_assoc()) {
                                $dateTime = new DateTime($data['created_at']);
                                $dateTime->modify('+5 hours');
                                $dateKey = $dateTime->format('d-M-Y');
                                $time = $dateTime->format('H:i:s');

                                if (!$firstDate) $firstDate = $dateKey;

                                $data['formatted_date'] = $dateKey;
                                $data['formatted_time'] = $time;
                                $grouped[$dateKey][] = $data;
                            }

                            $defaultDate = $grouped[$today] ?? $firstDate;

                            foreach ($grouped as $date => $deposits) {
                                $isVisible = ($date === $defaultDate) ? '' : 'style="display: none;"';
                                $groupClass = $date === $defaultDate ? 'active-date-group' : '';

                                echo "<tr class='date-group-header $groupClass' data-date='$date' $isVisible>
                                        <th colspan='9' class='bg-light text-dark p-3 border-bottom'>
                                            <strong>$date</strong> 
                                            " . ($date === $today ? '<span class="badge bg-primary ms-2">Today</span>' : '') . "
                                        </th>
                                      </tr>";

                                foreach ($deposits as $data) {
                                    $deposit_id = htmlspecialchars($data['id']);
                                    $amount = htmlspecialchars($data['amount']);
                                    $currency = htmlspecialchars($data['currency'] ?? '$');
                                    $name = htmlspecialchars($data['name']);
                                    $email = htmlspecialchars($data['email'] ?? 'No Email');
                                    $image = htmlspecialchars($data['image']);
                                    $approval_status = htmlspecialchars($data['approval_status']);
                                    $payment_plan = (int)($data['payment_plan'] ?? 1);
                                    $installment_number = (int)($data['installment_number'] ?? 1);
                                    $user_id = htmlspecialchars($data['user_id'] ?? '');

                                    $display_status = ucfirst($approval_status);
                                    $installment_display = $payment_plan > 1 ? "$installment_number/$payment_plan" : "One-Time";
                                    ?>
                                    <tr class="deposit-row" data-date="<?= $date ?>" <?= $isVisible ?>>
                                        <td><?= $currency ?> <?= number_format($amount, 2) ?></td>
                                        <td class="deposit-name"><?= $name ?></td>
                                        <td class="deposit-email"><?= $email ?></td>
                                        <td>
                                            <span class="badge bg-info text-light installment-badge" 
                                                  data-deposit-id="<?= $deposit_id ?>" 
                                                  data-payment-plan="<?= $payment_plan ?>" 
                                                  data-installment-number="<?= $installment_number ?>" 
                                                  style="cursor: pointer;" 
                                                  onclick="openInstallmentModal(<?= $deposit_id ?>, <?= $payment_plan ?>, <?= $installment_number ?>)">
                                                <?= $installment_display ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($image) { ?>
                                                <img src="../Uploads/<?= $image ?>" style="width:50px;height:50px" alt="Proof" class="img-thumbnail">
                                            <?php } else { echo 'No Image'; } ?>
                                        </td>
                                        <td>
                                            <span class="badge 
                                                <?= $approval_status === 'pending' ? 'bg-warning text-light' : 
                                                   ($approval_status === 'approved' ? 'bg-success text-light' : 'bg-danger text-light') ?> 
                                                status-badge" 
                                                data-deposit-id="<?= $deposit_id ?>" 
                                                data-current-status="<?= $approval_status ?>" 
                                                style="cursor: pointer;" 
                                                onclick="openStatusModal(<?= $deposit_id ?>, '<?= $approval_status ?>')">
                                                <?= $display_status ?>
                                            </span>
                                        </td>
                                        <td><?= $data['formatted_date'] ?></td>
                                        <td><?= $data['formatted_time'] ?></td>
                                        <td>
                                            <?php if ($image) { ?>
                                                <a href="../Uploads/<?= $image ?>" download class="btn btn-light btn-sm me-1">Download</a>
                                            <?php } ?>
                                            <?php if ($user_id) { ?>
                                                <a href="edit-user.php?id=<?= urlencode($user_id) ?>" class="btn btn-light btn-sm">Edit</a>
                                            <?php } else { ?>
                                                <span class="text-muted">No User</span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            }
                        }
                        $stmt->close();
                        ?>
                    </tbody>
                </table>

                <!-- === PAGINATION === -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mt-4">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= buildUrl($page - 1, $search) ?>">Previous</a>
                        </li>
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= buildUrl($i, $search) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= buildUrl($page + 1, $search) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modals (unchanged) -->
    <div class="modal fade" id="statusModal" tabindex="-1"> ... </div>
    <div class="modal fade" id="installmentModal" tabindex="-1"> ... </div>
</main>

<?php
function buildUrl($page, $search) {
    $params = ['page' => $page];
    if ($search !== '') $params['q'] = $search;
    return '?' . http_build_query($params);
}
?>

<!-- JavaScript (Keep your original) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const rows = document.querySelectorAll('.deposit-row');
    const headers = document.querySelectorAll('.date-group-header');

    searchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase().trim();
        let anyVisible = false;

        headers.forEach(header => {
            const date = header.getAttribute('data-date');
            let hasMatch = false;

            document.querySelectorAll(`.deposit-row[data-date="${date}"]`).forEach(row => {
                const name = row.querySelector('.deposit-name').textContent.toLowerCase();
                const email = row.querySelector('.deposit-email').textContent.toLowerCase();
                const matches = name.includes(term) || email.includes(term);

                row.style.display = matches ? '' : 'none';
                if (matches) hasMatch = true;
            });

            header.style.display = hasMatch ? '' : 'none';
            if (hasMatch) anyVisible = true;
        });

        let noResult = document.getElementById('no-result-row');
        if (!anyVisible && !noResult) {
            const tbody = document.getElementById('depositsBody');
            const tr = document.createElement('tr');
            tr.id = 'no-result-row';
            tr.innerHTML = '<td colspan="9" class="text-center text-muted p-4">No deposits match your search.</td>';
            tbody.appendChild(tr);
        } else if (noResult && anyVisible) {
            noResult.remove();
        }
    });

    // Keep all your modal JS exactly as-is
    window.openStatusModal = function(depositId, currentStatus) { /* ... */ };
    window.openInstallmentModal = function(depositId, paymentPlan, installmentNumber) { /* ... */ };
    // ... rest of your JS
});
</script>

<?php include('inc/footer.php'); ?>
</html>
