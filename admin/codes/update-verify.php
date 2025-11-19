<?php
session_start();
include('../../config/dbcon.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['verify'])) {
    $user_id = (int)$_POST['user_id'];
    $verify = (int)$_POST['verify'];

    if ($verify >= 0 && $verify <= 3) {
        $stmt = mysqli_prepare($con, "UPDATE users SET verify = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $verify, $user_id);
        $success = mysqli_stmt_execute($stmt);
        echo json_encode(['success' => $success]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}
?>
