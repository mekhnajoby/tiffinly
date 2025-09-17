<?php
require_once 'config/db_connect.php';

// Check subscription_meals table
echo "<h2>Subscription Meals Configuration:</h2>";
$result = $conn->query("SELECT * FROM subscription_meals");
if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    $fields = $result->fetch_fields();
    echo "<tr>";
    foreach ($fields as $field) {
        echo "<th>" . htmlspecialchars($field->name) . "</th>";
    }
    echo "</tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($fields as $field) {
            echo "<td>" . htmlspecialchars($row[$field->name] ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No subscription meals configured.";
}

// Show all subscriptions regardless of status
echo "<h2>All Subscriptions:</h2>";
$sql = "SELECT 
    s.subscription_id,
    s.user_id,
    s.plan_id,
    s.start_date,
    s.end_date,
    s.schedule,
    s.dietary_preference,
    s.payment_status,
    s.status,
    p.plan_name,
    u.name as user_name,
    u.phone
FROM subscriptions s
JOIN meal_plans p ON s.plan_id = p.plan_id
JOIN users u ON s.user_id = u.user_id
ORDER BY s.status, s.start_date";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr>
        <th>Subscription ID</th>
        <th>User</th>
        <th>Plan</th>
        <th>Schedule</th>
        <th>Dietary</th>
        <th>Start Date</th>
        <th>End Date</th>
        <th>Status</th>
        <th>Payment</th>
    </tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['subscription_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['user_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['plan_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['schedule']) . "</td>";
        echo "<td>" . htmlspecialchars($row['dietary_preference']) . "</td>";
        echo "<td>" . htmlspecialchars($row['start_date']) . "</td>";
        echo "<td>" . htmlspecialchars($row['end_date']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['payment_status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No subscriptions found in the system.";
}

$conn->close();
?>
