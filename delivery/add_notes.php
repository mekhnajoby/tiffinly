<?php
session_start();
include('../config/db_connect.php');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'delivery') {
    header('Location: ../login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['note'])) {
    $note = $_POST['note'];
    $stmt = $conn->prepare('INSERT INTO delivery_notes (partner_id, note, created_at) VALUES (?, ?, NOW())');
    $stmt->bind_param('is', $user_id, $note);
    $stmt->execute();
    $success = true;
}
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>Add Notes - Tiffinly Partner</title>
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
                <h1>Add Notes / Issues</h1>
                <p class='subtitle'>Log any delivery issues or notes for admin review.</p>
            </div>
        </div>
        <div class='dashboard-section animate-fade-in'>
            <?php if(isset($_SESSION['note_success'])): ?>
                <div class='success-msg'>Note added successfully!</div>
                <?php unset($_SESSION['note_success']); ?>
            <?php endif; ?>
            <form method='POST' class='notes-form'>
                <textarea name='note' rows='5' placeholder='Describe your issue or note...' required></textarea>
                <button type='submit' class='confirm-btn'><i class='fas fa-plus'></i> Add Note</button>
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
