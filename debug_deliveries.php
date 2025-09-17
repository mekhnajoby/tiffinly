<?php
require_once __DIR__ . '/config/db_connect.php';

// Check if we're logged in as a delivery partner
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'delivery') {
    die("Please log in as a delivery partner first.");
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Debug query to see what's in delivery_assignments
$query = "SELECT 
    da.*, 
    s.status as subscription_status,
    s.payment_status,
    u.name as user_name
FROM delivery_assignments da
JOIN subscriptions s ON da.subscription_id = s.subscription_id
JOIN users u ON s.user_id = u.user_id
WHERE da.partner_id = ?
AND da.delivery_date >= ?
ORDER BY da.delivery_date, da.meal_type";

$stmt = $conn->prepare($query);
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$result = $stmt->get_result();

// Output the results
echo "<h2>Debug: Delivery Assignments for Partner ID: $user_id</h2>";
echo "<p>Today's date: $today</p>";

echo "<table border='1' cellpadding='8' style='border-collapse: collapse; margin-top: 20px;'>";
echo "<tr>
        <th>Assignment ID</th>
        <th>Subscription ID</th>
        <th>Customer</th>
        <th>Delivery Date</th>
        <th>Meal Type</th>
        <th>Status</th>
        <th>Sub Status</th>
        <th>Payment Status</th>
      </tr>";

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['assignment_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['subscription_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['user_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['delivery_date']) . "</td>";
        echo "<td>" . ucfirst(htmlspecialchars($row['meal_type'])) . "</td>";
        echo "<td>" . ucfirst(htmlspecialchars($row['status'])) . "</td>";
        echo "<td>" . ucfirst(htmlspecialchars($row['subscription_status'])) . "</td>";
        echo "<td>" . ucfirst(htmlspecialchars($row['payment_status'])) . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='8'>No delivery assignments found for this partner.</td></tr>";
}

echo "</table>";

// Check subscription status
$sub_query = "SELECT status, COUNT(*) as count FROM subscriptions 
              WHERE status IN ('active', 'pending', 'cancelled')
              GROUP BY status";
$sub_result = $conn->query($sub_query);

echo "<h3>Subscription Status Summary:</h3>";
echo "<ul>";
while ($row = $sub_result->fetch_assoc()) {
    echo "<li>" . ucfirst($row['status']) . ": " . $row['count'] . "</li>";
}
echo "</ul>";

$conn->close();
?>
