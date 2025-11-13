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

    <div class="card">
        <div class="card-body">
            <!-- Filters Row -->
            <div class="row mb-3 mt-4 align-items-end">
                <div class="col-md-4">
                    <input type="text" id="searchInput" class="form-control" placeholder="Search by name or email...">
                </div>
                <div class="col-md-3">
                    <select id="dateFilter" class="form-select">
                        <option value="">All Dates</option>
                        <!-- Options will be populated by JS -->
                    </select>
                </div>
            </div>

            <!-- Table -->
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
                        $query = "SELECT d.id, d.amount, d.currency, d.name, d.email, d.image, d.approval_status, d.created_at, 
                                         d.payment_plan, d.installment_number, u.id AS user_id 
                                  FROM deposits d 
                                  LEFT JOIN users u ON d.email = u.email 
                                  ORDER BY d.created_at DESC";
                        $query_run = mysqli_query($con, $query);

                        if (!$query_run) {
                            echo "<tr><td colspan='9'>Error: " . mysqli_error($con) . "</td></tr>";
                            exit;
                        }

                        $grouped = [];
                        $today = (new DateTime('now', new DateTimeZone('UTC')))->modify('+5 hours')->format('d-M-Y');

                        while ($data = mysqli_fetch_assoc($query_run)) {
                            $dateTime = new DateTime($data['created_at']);
                            $dateTime->modify('+5 hours');
                            $dateKey = $dateTime->format('d-M-Y');
                            $time = $dateTime->format('H:i:s');

                            $data['formatted_date'] = $dateKey;
                            $data['formatted_time'] = $time;
                            $grouped[$dateKey][] = $data;
                        }

                        $hasToday = isset($grouped[$today]);
                        $displayed = false;

                        foreach ($grouped as $date => $deposits) {
                            $isToday = ($date === $today);
                            $rowStyle = (!$displayed && !$isToday) ? 'style="display: none;"' : '';
                            $groupStyle = $isToday ? '' : 'style="display: none;" data-date-group="' . $date . '"';

                            echo '<tr class="date-group-header" ' . $groupStyle . '><th colspan="9" class="bg-light text-dark p-2"><strong>' . $date . '</strong></th></tr>';

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
                                <tr class="deposit-row" data-date="<?= $date ?>" <?= $rowStyle ?>>
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
                                            <img src="../Uploads/<?= $image ?>" style="width:50px;height:50px" alt="Proof">
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
                            if ($isToday) $displayed = true;
                        }

                        if (empty($grouped)) {
                            echo '<tr><td colspan="9">No deposits found.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modals (unchanged) -->
    <!-- Status Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Deposit Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <select id="newStatusSelect" class="form-select">
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <input type="hidden" id="depositId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveStatusButton">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Installment Modal -->
    <div class="modal fade" id="installmentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Installment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Total Installments</label>
                        <input type="number" id="paymentPlanInput" class="form-control" min="1" value="1">
                    </div>
                    <div class="mb-3">
                        <label>Current Installment</label>
                        <input type="number" id="installmentNumberInput" class="form-control" min="1" value="1">
                    </div>
                    <input type="hidden" id="installmentDepositId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveInstallmentButton">Save</button>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include('inc/footer.php'); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const dateFilter = document.getElementById('dateFilter');
    const depositRows = document.querySelectorAll('.deposit-row');
    const dateHeaders = document.querySelectorAll('.date-group-header');
    const today = new Date();
    today.setHours(today.getHours() + 5);
    const todayStr = today.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).replace(/ /g, '-');

    // Populate date filter
    const dates = new Set();
    depositRows.forEach(row => {
        const date = row.getAttribute('data-date');
        dates.add(date);
    });
    const sortedDates = Array.from(dates).sort((a, b) => new Date(b.split('-').reverse().join('-')) - new Date(a.split('-').reverse().join('-')));
    
    sortedDates.forEach(date => {
        const option = document.createElement('option');
        option.value = date;
        option.textContent = date + (date === todayStr ? ' (Today)' : '');
        if (date === todayStr) option.selected = Celebrity;
        dateFilter.appendChild(option);
    });

    // Filter function
    function applyFilters() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const selectedDate = dateFilter.value;

        let visibleCount = 0;

        dateHeaders.forEach(header => {
            const date = header.nextElementSibling?.getAttribute('data-date') || '';
            const shouldShowHeader = selectedDate === '' || selectedDate === date;
            header.style.display = shouldShowHeader ? '' : 'none';
        });

        depositRows.forEach(row => {
            const date = row.getAttribute('data-date');
            const name = row.querySelector('.deposit-name').textContent.toLowerCase();
            const email = row.querySelector('.deposit-email').textContent.toLowerCase();

            const matchesSearch = name.includes(searchTerm) || email.includes(searchTerm);
            const matchesDate = selectedDate === '' || selectedDate === date;

            if (matchesSearch && matchesDate) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Show "No results" if nothing visible
        let noResultRow = document.getElementById('noResultRow');
        if (!noResultRow && visibleCount === 0) {
            const tbody = document.getElementById('depositsBody');
            noResultRow = document.createElement('tr');
            noResultRow.id = 'noResultRow';
            noResultRow.innerHTML = '<td colspan="9" class="text-center text-muted">No deposits found matching your filters.</td>';
            tbody.appendChild(noResultRow);
        } else if (noResultRow && visibleCount > 0) {
            noResultRow.remove();
        }
    }

    // Event Listeners
    searchInput.addEventListener('input', applyFilters);
    dateFilter.addEventListener('change', applyFilters);

    // Initial filter: show only today by default
    if (dateFilter.querySelector(`option[value="${todayStr}"]`)) {
        dateFilter.value = todayStr;
    }
    applyFilters();

    // Modal Functions (unchanged)
    window.openStatusModal = function(depositId, currentStatus) {
        const modal = new bootstrap.Modal(document.getElementById('statusModal'));
        document.getElementById('newStatusSelect').value = currentStatus;
        document.getElementById('depositId').value = depositId;
        modal.show();
    };

    window.openInstallmentModal = function(depositId, paymentPlan, installmentNumber) {
        const modal = new bootstrap.Modal(document.getElementById('installmentModal'));
        document.getElementById('paymentPlanInput').value = paymentPlan;
        document.getElementById('installmentNumberInput').value = installmentNumber;
        document.getElementById('installmentDepositId').value = depositId;
        modal.show();
    };

    // Save Status
    document.getElementById('saveStatusButton').addEventListener('click', function() {
        const depositId = document.getElementById('depositId').value;
        const newStatus = document.getElementById('newStatusSelect').value;
        const badge = document.querySelector(`.status-badge[data-deposit-id="${depositId}"]`);
        const currentStatus = badge.getAttribute('data-current-status');

        if (newStatus === currentStatus) {
            bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
            return;
        }

        fetch('update-deposit-status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `deposit_id=${depositId}&approval_status=${newStatus}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                badge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                badge.className = `badge status-badge ${newStatus === 'pending' ? 'bg-warning text-light' : newStatus === 'approved' ? 'bg-success text-light' : 'bg-danger text-light'}`;
                badge.setAttribute('data-current-status', newStatus);
            } else {
                alert('Error: ' + data.message);
            }
            bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
        });
    });

    // Save Installment
    document.getElementById('saveInstallmentButton').addEventListener('click', function() {
        const depositId = document.getElementById('installmentDepositId').value;
        const paymentPlan = parseInt(document.getElementById('paymentPlanInput').value);
        const installmentNumber = parseInt(document.getElementById('installmentNumberInput').value);
        const badge = document.querySelector(`.installment-badge[data-deposit-id="${depositId}"]`);

        if (installmentNumber > paymentPlan || paymentPlan < 1 || installmentNumber < 1) {
            alert('Invalid installment values.');
            return;
        }

        fetch('update-deposit-installment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `deposit_id=${depositId}&payment_plan=${paymentPlan}&installment_number=${installmentNumber}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                badge.textContent = paymentPlan > 1 ? `${installmentNumber}/${paymentPlan}` : 'One-Time';
                badge.setAttribute('data-payment-plan', paymentPlan);
                badge.setAttribute('data-installment-number', installmentNumber);
            } else {
                alert('Error: ' + data.message);
            }
            bootstrap.Modal.getInstance(document.getElementById('installmentModal')).hide();
        });
    });
});
</script>
