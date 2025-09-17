<?php
session_start();
header('Content-Type: application/json');

require_once('../config/db_connect.php');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$assignment_id = isset($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : 0;

if ($assignment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid assignment ID.']);
    exit();
}

// Verify that the assignment belongs to the logged-in user and is in a cancellable state ('pending')
$stmt = $conn->prepare("
    UPDATE delivery_assignments da
    JOIN subscriptions s ON da.subscription_id = s.subscription_id
    SET da.status = 'cancelled'
    WHERE da.assignment_id = ? 
      AND s.user_id = ? 
      AND da.status = 'pending'
");
$stmt->bind_param("ii", $assignment_id, $user_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Meal cancelled successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Could not cancel this meal. It may have already been processed or does not belong to you.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
}

$stmt->close();
$conn->close();