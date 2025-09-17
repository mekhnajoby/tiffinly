<?php
session_start();
header('Content-Type: application/json');

include('../config/db_connect.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'delivery') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$partner_id = $_SESSION['user_id'];
$assignment_id = $_POST['assignment_id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$assignment_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit();
}

// Validate status (must match schema enum: 'pending','out_for_delivery','delivered','cancelled')
// Typically partners will set to 'out_for_delivery', 'delivered', or 'cancelled'.
$allowed_statuses = ['out_for_delivery', 'delivered', 'cancelled'];
if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
    exit();
}

// Prepare and execute the update statement
// Also ensures that a partner can only update their own assignments
$stmt = $conn->prepare("UPDATE delivery_assignments SET status = ? WHERE assignment_id = ? AND partner_id = ?");
$stmt->bind_param('sii', $status, $assignment_id, $partner_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Status updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Could not find the assignment or no change was made.']);
    }
} else {
    error_log('DB Error: ' . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}

$stmt->close();
$conn->close();
