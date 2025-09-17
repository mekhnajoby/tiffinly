<?php
session_start();
require_once('../../config/db_connect.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_GET['order_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$order_id = intval($_GET['order_id']);
$user_id = $_SESSION['user_id'];

// Get latest subscription status
$stmt = $conn->prepare("
    SELECT s.*, d.status as delivery_status
    FROM subscriptions s
    LEFT JOIN delivery_assignments d ON s.subscription_id = d.subscription_id
    WHERE s.subscription_id = ? AND s.user_id = ?
      AND UPPER(s.status) IN ('ACTIVE','ACTIVATED')
      AND UPPER(s.payment_status) IN ('PAID','COMPLETED','SUCCESS')
      AND CURDATE() BETWEEN s.start_date AND s.end_date
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$subscription = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$subscription) {
    echo json_encode(['status' => 'error', 'message' => 'Subscription not found']);
    exit();
}

// Get delivery partner's current location if assigned
$delivery_partner = null;
$stmt = $conn->prepare("
    SELECT u.user_id as partner_id, u.name as partner_name, u.phone as partner_phone, dpd.vehicle_type, dpd.vehicle_number
    FROM delivery_assignments da
    JOIN users u ON da.partner_id = u.user_id
    LEFT JOIN delivery_partner_details dpd ON da.partner_id = dpd.partner_id
    WHERE da.subscription_id = ? AND da.partner_id IS NOT NULL AND da.status <> 'delivered'
    ORDER BY da.assigned_at DESC
    LIMIT 1
");
$stmt->bind_param("i", $subscription['subscription_id']);
$stmt->execute();
$delivery_partner = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
    'status' => 'success',
    'data' => [
        'status' => $subscription['delivery_status'] ?? 'pending',
        'delivery_partner' => $delivery_partner
    ]
]);