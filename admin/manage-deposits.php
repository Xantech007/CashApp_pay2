<?php
session_start();
include('inc/header.php');
include('inc/navbar.php');
include('inc/sidebar.php');
include('../config/dbcon.php'); // Include database connection
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
    </div><!-- End Page Title -->

    <div class="card">
        <div class="card-body">
            <!-- Search Bar -->
            <div class="mb-3 mt-4">
                <input type="text" id="searchInput" class="form-control" placeholder="Search by name or email..." style="max-width: 400px;">
            </div>

            <!-- Bordered Table -->
            <div class="table-responsive">
                <table class="table table-borderless" id="depositsTable">
                    <thead>
                        <tr>
                            <th scope="col">Amount</th>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Installment</th>
                            <th scope="col">Payment Proof</th>
                            <th scope="col">Status</th>
                            <th scope="col">Date</th>
                            <th scope="col">Time</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Fetch all deposits, including those without email, in descending order by created_at
                        $query = "SELECT d.id, d.amount, d.currency, d.name, d.email, d.image, d.approval_status, d.created_at, d.payment_plan, d.installment_number, u.id AS user_id 
                                  FROM deposits d 
                                  LEFT JOIN users u ON d.email = u.email 
                                  ORDER BY d.created_at DESC";
                        $query_run = mysqli_query($con, $query);
                        if ($query_run === false) {
                            echo "<tr><td colspan='9'>Error fetching deposits: " . mysqli_error($con) . "</td></tr>";
                        } elseif (mysqli_num_rows($query_run) > 0) {
                            foreach ($query_run as $data) {
                                // Sanitize data to prevent XSS
                                $deposit_id = htmlspecialchars($data['id']);
                                $amount = htmlspecialchars($data['amount']);
                                $currency = htmlspecialchars($data['currency'] ?? '$');
                                $name = htmlspecialchars($data['name']);
                                $email = htmlspecialchars($data['email'] ?? 'No Email');
                                $image = htmlspecialchars($data['image']);
                                $approval_status = htmlspecialchars($data['approval_status']);
                                $payment_plan = (int)($data['payment_plan'] ?? 1);
                                $installment_number = (int)($data['installment_number'] ?? 1);
                                
                                // Capitalize status for display
                                $display_status = ucfirst($approval_status);
                                
                                // Format installment display
                                $installment_display = $payment_plan > 1 ? "$installment_number/$payment_plan" : "One-Time";
                                
                                // Add 5 hours to the created_at timestamp
                                $dateTime = new DateTime($data['created_at']);
                                $dateTime->modify('+5 hours');
                                $created_at = $dateTime->format('d-M-Y');
                                $time = $dateTime->format('H:i:s');
                                
                                $user_id = htmlspecialchars($data['user_id'] ?? '');
                        ?>
                                <tr>
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
                                            <img src="../Uploads/<?= $image ?>" style="width:50px;height:50px" alt="Payment Proof" class="">
                                        <?php } else { ?>
                                            No Image
                                        <?php } ?>
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
                                    <td><?= $created_at ?></td>
                                    <td><?= $time ?></td>
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
                        } else { ?>
                            <tr>
                                <td colspan="9">No deposits found.</td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <!-- End Bordered Table -->
        </div>
    </div>

    <!-- Status Change Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="statusModalLabel">Change Deposit Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <select id="newStatusSelect" class="form-select">
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <input type="hidden" id="depositId" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveStatusButton">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Installment Change Modal -->
    <div class="modal fade" id="installmentModal" tabindex="-1" aria-labelledby="installmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="installmentModalLabel">Change Installment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="paymentPlanInput" class="form-label">Total Installments (Payment Plan)</label>
                        <input type="number" id="paymentPlanInput" class="form-control" min="1" value="1">
                    </div>
                    <div class="mb-3">
                        <label for="installmentNumberInput" class="form-label">Current Installment Number</label>
                        <input type="number" id="installmentNumberInput" class="form-control" min="1" value="1">
                    </div>
                    <input type="hidden" id="installmentDepositId" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveInstallmentButton">Save</button>
                </div>
            </div>
        </div>
    </div>
</main><!-- End #main -->

<?php include('inc/footer.php'); ?>

<!-- JavaScript for real-time search and updates -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Real-time search
    document.getElementById('searchInput').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('#depositsTable tbody tr');

        rows.forEach(row => {
            const name = row.querySelector('.deposit-name').textContent.toLowerCase();
            const email = row.querySelector('.deposit-email').textContent.toLowerCase();
            
            if (name.includes(searchTerm) || email.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    // Function to open the status modal
    window.openStatusModal = function(depositId, currentStatus) {
        const modal = new bootstrap.Modal(document.getElementById('statusModal'));
        const select = document.getElementById('newStatusSelect');
        const depositIdInput = document.getElementById('depositId');
        
        select.value = currentStatus;
        depositIdInput.value = depositId;
        
        modal.show();
    };

    // Function to open the installment modal
    window.openInstallmentModal = function(depositId, paymentPlan, installmentNumber) {
        const modal = new bootstrap.Modal(document.getElementById('installmentModal'));
        const paymentPlanInput = document.getElementById('paymentPlanInput');
        const installmentNumberInput = document.getElementById('installmentNumberInput');
        const depositIdInput = document.getElementById('installmentDepositId');
        
        paymentPlanInput.value = paymentPlan;
        installmentNumberInput.value = installmentNumber;
        depositIdInput.value = depositId;
        
        modal.show();
    };

    // Handle save button click for status
    document.getElementById('saveStatusButton').addEventListener('click', function() {
        const depositId = document.getElementById('depositId').value;
        const newStatus = document.getElementById('newStatusSelect').value;
        const currentStatus = document.querySelector(`.status-badge[data-deposit-id="${depositId}"]`).getAttribute('data-current-status');

        if (newStatus === currentStatus) {
            bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
            return;
        }

        fetch('update-deposit-status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `deposit_id=${depositId}&approval_status=${newStatus}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Status updated successfully.');
                const badge = document.querySelector(`.status-badge[data-deposit-id="${depositId}"]`);
                badge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                badge.className = `badge status-badge ${
                    newStatus === 'pending' ? 'bg-warning text-light' :
                    newStatus === 'approved' ? 'bg-success text-light' :
                    'bg-danger text-light'
                }`;
                badge.setAttribute('data-current-status', newStatus);
                bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
            } else {
                alert('Error updating status: ' + data.message);
                bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
            }
        })
        .catch(error => {
            alert('Error updating status: ' + error.message);
            bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
        });
    });

    // Handle save button click for installment
    document.getElementById('saveInstallmentButton').addEventListener('click', function() {
        const depositId = document.getElementById('installmentDepositId').value;
        const paymentPlan = parseInt(document.getElementById('paymentPlanInput').value);
        const installmentNumber = parseInt(document.getElementById('installmentNumberInput').value);
        const currentPaymentPlan = parseInt(document.querySelector(`.installment-badge[data-deposit-id="${depositId}"]`).getAttribute('data-payment-plan'));
        const currentInstallmentNumber = parseInt(document.querySelector(`.installment-badge[data-deposit-id="${depositId}"]`).getAttribute('data-installment-number'));

        // Validate inputs
        if (paymentPlan < 1 || installmentNumber < 1) {
            alert('Payment plan and installment number must be at least 1.');
            return;
        }
        if (installmentNumber > paymentPlan) {
            alert('Current installment number cannot exceed total payment plan.');
            return;
        }
        if (paymentPlan === currentPaymentPlan && installmentNumber === currentInstallmentNumber) {
            bootstrap.Modal.getInstance(document.getElementById('installmentModal')).hide();
            return;
        }

        fetch('update-deposit-installment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `deposit_id=${depositId}&payment_plan=${paymentPlan}&installment_number=${installmentNumber}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Installment details updated successfully.');
                const badge = document.querySelector(`.installment-badge[data-deposit-id="${depositId}"]`);
                badge.textContent = paymentPlan > 1 ? `${installmentNumber}/${paymentPlan}` : 'One-Time';
                badge.setAttribute('data-payment-plan', paymentPlan);
                badge.setAttribute('data-installment-number', installmentNumber);
                bootstrap.Modal.getInstance(document.getElementById('installmentModal')).hide();
            } else {
                alert('Error updating installment details: ' + data.message);
                bootstrap.Modal.getInstance(document.getElementById('installmentModal')).hide();
            }
        })
        .catch(error => {
            alert('Error updating installment details: ' + error.message);
            bootstrap.Modal.getInstance(document.getElementById('installmentModal')).hide();
        });
    });
});
</script>
</html>
