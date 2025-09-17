<?php
require_once 'config/db_connect.php';

// Check all subscriptions and their meals
echo "<h2>All Subscriptions with Meal Details:</h2>";
$sql = "SELECT 
    s.subscription_id,
    u.name as user_name,
    p.plan_name,
    s.start_date,
    s.end_date,
    s.schedule,
    s.dietary_preference,
    s.status,
    s.payment_status,
    GROUP_CONCAT(CONCAT(sm.day_of_week, ' (', sm.meal_type, ')') SEPARATOR ', ') as meal_schedule
FROM subscriptions s
JOIN users u ON s.user_id = u.user_id
JOIN meal_plans p ON s.plan_id = p.plan_id
LEFT JOIN subscription_meals sm ON s.subscription_id = sm.subscription_id
GROUP BY s.subscription_id
ORDER BY s.status, s.start_date";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr>
        <th>Subscription ID</th>
        <th>User</th>
        <th>Plan</th>
        <th>Start Date</th>
        <th>End Date</th>
        <th>Schedule</th>
        <th>Dietary</th>
        <th>Status</th>
        <th>Payment</th>
        <th>Meal Schedule</th>
    </tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['subscription_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['user_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['plan_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['start_date']) . "</td>";
        echo "<td>" . htmlspecialchars($row['end_date']) . "</td>";
        echo "<td>" . htmlspecialchars($row['schedule']) . "</td>";
        echo "<td>" . htmlspecialchars($row['dietary_preference']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['payment_status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['meal_schedule'] ?? 'No meals scheduled') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No subscriptions found in the system.";
}

// Show delivery assignments
echo "<h2>Current Delivery Assignments:</h2>";
$sql = "SELECT 
    da.assignment_id,
    da.subscription_id,
    u.name as user_name,
    da.delivery_date,
    da.meal_type,
    da.status,
    up.name as partner_name
FROM delivery_assignments da
JOIN subscriptions s ON da.subscription_id = s.subscription_id
JOIN users u ON s.user_id = u.user_id
LEFT JOIN users up ON da.partner_id = up.user_id
ORDER BY da.delivery_date, da.meal_type";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr>
        <th>Assignment ID</th>
        <th>Subscription ID</th>
        <th>Customer</th>
        <th>Delivery Date</th>
        <th>Meal Type</th>
        <th>Status</th>
        <th>Delivery Partner</th>
    </tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['assignment_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['subscription_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['user_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['delivery_date']) . "</td>";
        echo "<td>" . ucfirst(htmlspecialchars($row['meal_type'])) . "</td>";
        echo "<td>" . ucfirst(htmlspecialchars($row['status'])) . "</td>";
        echo "<td>" . htmlspecialchars($row['partner_name'] ?? 'Unassigned') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No delivery assignments found.";
}

$conn->close();
?>
