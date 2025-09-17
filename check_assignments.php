<?php
require_once __DIR__ . '/config/db_connect.php';

// Get all delivery assignments
$query = "SELECT da.*, u.name as partner_name, 
          CONCAT(s.start_date, ' to ', s.end_date) as subscription_period
          FROM delivery_assignments da
          JOIN subscriptions s ON da.subscription_id = s.subscription_id
          LEFT JOIN users u ON da.partner_id = u.user_id
          ORDER BY da.delivery_date, da.meal_type";

$result = $conn->query($query);

echo "<h2>Delivery Assignments</h2>";
echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
echo "<tr>
        <th>Assignment ID</th>
        <th>Subscription ID</th>
        <th>Period</th>
        <th>Delivery Date</th>
        <th>Meal Type</th>
        <th>Status</th>
        <th>Assigned To</th>
        <th>Assigned At</th>
      </tr>";

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['assignment_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['subscription_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['subscription_period']) . "</td>";
        echo "<td>" . htmlspecialchars($row['delivery_date']) . "</td>";
        echo "<td>" . ucfirst(htmlspecialchars($row['meal_type'])) . "</td>";
        echo "<td>" . ucfirst(htmlspecialchars($row['status'])) . "</td>";
        echo "<td>" . ($row['partner_name'] ? htmlspecialchars($row['partner_name']) : 'Not Assigned') . "</td>";
        echo "<td>" . htmlspecialchars($row['assigned_at']) . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='8'>No delivery assignments found.</td></tr>";
}

echo "</table>";

$conn->close();
?>
