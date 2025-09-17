<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'delivery') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once('../config/db_connect.php');
$partner_id = $_SESSION['user_id'];
$subscription_id = isset($_GET['subscription_id']) ? (int)$_GET['subscription_id'] : 0;

if ($subscription_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Subscription ID.']);
    exit();
}

$assignments = [];
$stmt = $conn->prepare("
    SELECT 
        assignment_id, 
        meal_type, 
        delivery_date, 
        status 
    FROM delivery_assignments 
    WHERE partner_id = ? AND subscription_id = ? 
    ORDER BY delivery_date DESC, FIELD(meal_type, 'Breakfast', 'Lunch', 'Dinner')
");
$stmt->bind_param("ii", $partner_id, $subscription_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $row['meal_type'] = ucfirst($row['meal_type']);
    $assignments[] = $row;
}

$stmt->close();

echo json_encode(['success' => true, 'assignments' => $assignments]);