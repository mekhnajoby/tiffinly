<?php
session_start();
include('../config/db_connect.php');
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'delivery') {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit();
}
$user_id = $_SESSION['user_id'];
$subscription_id = $_POST['subscription_id'] ?? null;
$meal_types_str = $_POST['meal_types'] ?? null;

if (!$subscription_id || !$meal_types_str) {
    echo json_encode(['success' => false, 'message' => 'Missing subscription ID or meal types.']);
    exit();
}

$meal_types = explode(',', $meal_types_str);

$conn->begin_transaction();

try {
    $already_assigned = false;
    foreach ($meal_types as $meal_type) {
        $stmt = $conn->prepare("SELECT assignment_id FROM delivery_assignments WHERE subscription_id = ? AND meal_type = ? FOR UPDATE");
        $stmt->bind_param('is', $subscription_id, $meal_type);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $already_assigned = true;
            break;
        }
    }

    if ($already_assigned) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'This order has just been assigned to another partner.']);
        exit();
    }

    $insert_stmt = $conn->prepare('INSERT INTO delivery_assignments (subscription_id, partner_id, meal_type, status) VALUES (?, ?, ?, "pending")');
    foreach ($meal_types as $meal_type) {
        $insert_stmt->bind_param('iis', $subscription_id, $user_id, $meal_type);
        if (!$insert_stmt->execute()) {
            throw new Exception($conn->error);
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Order accepted successfully!']);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Order acceptance failed: " . $e->getMessage()); // Log error for debugging
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again.']);
}
