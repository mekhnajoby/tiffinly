<?php
session_start();
include('../config/db_connect.php');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'delivery') {
    header('Location: ../login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$subscription_id = $_GET['subscription_id'] ?? null;
if (!$subscription_id) {
    echo 'No subscription selected.';
    exit();
}
// Fetch current status
$stmt = $conn->prepare('SELECT status FROM delivery_assignments WHERE subscription_id = ? AND partner_id = ?');
$stmt->bind_param('ii', $subscription_id, $user_id);
$stmt->execute();
$status = $stmt->get_result()->fetch_assoc()['status'] ?? 'pending';
// Update status if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_status'])) {
    $new_status = $_POST['new_status'];
    $up_stmt = $conn->prepare('UPDATE delivery_assignments SET status = ? WHERE subscription_id = ? AND partner_id = ?');
    $up_stmt->bind_param('sii', $new_status, $subscription_id, $user_id);
    $up_stmt->execute();
    header('Location: my_deliveries.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>Update Delivery Status - Tiffinly Partner</title>
    <link rel='stylesheet' href='../user/user_css/user_dashboard_style.css'>
    <link rel='stylesheet' href='partner_dashboard_style.css'>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
</head>
<body>
<div class='dashboard-container'>
    <div class='sidebar'>
        <?php include 'partner_sidebar.php'; ?>
    </div>
    <div class='main-content'>
        <div class='header'>
            <div class='welcome-message'>
                <h1>Update Delivery Status</h1>
                <p class='subtitle'>Change the status for this delivery.</p>
            </div>
        </div>
        <div class='dashboard-section animate-fade-in'>
            <form method='POST' class='status-form'>
                <label for='new_status'>Select New Status:</label>
                <select name='new_status' id='new_status' required>
                    <option value='pending' <?php if($status==='pending') echo 'selected'; ?>>Pending</option>
                    <option value='out_for_delivery' <?php if($status==='out_for_delivery') echo 'selected'; ?>>Out for Delivery</option>
                    <option value='delivered' <?php if($status==='delivered') echo 'selected'; ?>>Delivered</option>
                </select>
                <button type='submit' class='confirm-btn'><i class='fas fa-save'></i> Update Status</button>
            </form>
        </div>
        <footer class='dashboard-footer'>
            <div class='footer-content'>
                <span>&copy; 2025 Tiffinly. All rights reserved.</span>
                <span>Partner Portal</span>
            </div>
        </footer>
    </div>
</div>
</body>
</html>
