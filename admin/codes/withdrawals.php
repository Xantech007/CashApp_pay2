<?php
session_start();
include('../config/dbcon.php');

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {  // ← use your real admin session check
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);

if ($id <= 0 || !in_array($action, ['approve','reject','complete'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$con->begin_transaction();

try {
    $new_status = match($action) {
        'approve'   => 1,     // Processing
        'complete'  => 2,     // Completed
        'reject'    => 3      // Rejected
    };

    // Get current status + amount + email
    $stmt = $con->prepare("SELECT status, amount, email FROM withdrawals WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $wd = $result->fetch_assoc();
    $stmt->close();

    if (!$wd) {
        throw new Exception("Withdrawal not found");
    }

    if ($wd['status'] != 0 && $action === 'approve') {
        throw new Exception("Withdrawal already processed");
    }

    // Update status
    $stmt = $con->prepare("UPDATE withdrawals SET status = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_status, $id);
    $stmt->execute();
    $stmt->close();

    // Optional: if reject → maybe add balance back
    if ($action === 'reject' && $wd['status'] == 0) {
        $stmt = $con->prepare("UPDATE users SET balance = balance + ? WHERE email = ?");
        $stmt->bind_param("ds", $wd['amount'], $wd['email']);
        $stmt->execute();
        $stmt->close();
    }

    $con->commit();

    echo json_encode([
        'success' => true,
        'message' => ucfirst($action) . ' successful'
    ]);

} catch (Exception $e) {
    $con->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
