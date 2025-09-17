<?php
// Debug script to check today's deliveries
require_once 'config/db_connect.php';

// Get today's date in YYYY-MM-DD format
$today = date('Y-m-d');

// Query to check today's deliveries
$sql = "
    SELECT 
        da.assignment_id,
        da.subscription_id,
        da.meal_type,
        da.delivery_date,
        da.status,
        u.name as partner_name,
        s.plan_id,
        p.plan_name
    FROM delivery_assignments da
    LEFT JOIN users u ON da.partner_id = u.user_id
    LEFT JOIN subscriptions s ON da.subscription_id = s.subscription_id
    LEFT JOIN meal_plans p ON s.plan_id = p.plan_id
    WHERE DATE(da.delivery_date) = ?
    ORDER BY da.delivery_date, da.meal_type
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

// Display results
echo "<h1>Today's Deliveries ($today)</h1>";
if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr>
            <th>Assignment ID</th>
            <th>Subscription ID</th>
            <th>Meal Type</th>
            <th>Delivery Time</th>
            <th>Status</th>
            <th>Delivery Partner</th>
            <th>Plan</th>
          </tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['assignment_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['subscription_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['meal_type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['delivery_date']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['partner_name'] ?? 'Not assigned') . "</td>";
        echo "<td>" . htmlspecialchars($row['plan_name'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No deliveries found for today ($today).</p>";
}

// Check if there are any subscriptions that should have deliveries today
$subscriptions_sql = "
    SELECT 
        s.subscription_id,
        s.user_id,
        s.plan_id,
        p.plan_name,
        s.start_date,
        s.end_date,
        s.schedule,
        s.dietary_preference
    FROM subscriptions s
    LEFT JOIN meal_plans p ON s.plan_id = p.plan_id
    WHERE s.status IN ('ACTIVE', 'ACTIVATED')
    AND s.payment_status IN ('PAID', 'COMPLETED', 'SUCCESS')
    AND ? BETWEEN s.start_date AND s.end_date
";

$stmt = $conn->prepare($subscriptions_sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$subscriptions = $stmt->get_result();

echo "<h2>Active Subscriptions (Should Have Deliveries Today)</h2>";
if ($subscriptions->num_rows > 0) {
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%; margin-top: 20px;'>";
    echo "<tr>
            <th>Subscription ID</th>
            <th>User ID</th>
            <th>Plan</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Schedule</th>
            <th>Dietary Pref</th>
          </tr>";
    
    while ($sub = $subscriptions->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($sub['subscription_id']) . "</td>";
        echo "<td>" . htmlspecialchars($sub['user_id']) . "</td>";
        echo "<td>" . htmlspecialchars($sub['plan_name'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($sub['start_date']) . "</td>";
        echo "<td>" . htmlspecialchars($sub['end_date']) . "</td>";
        echo "<td>" . htmlspecialchars($sub['schedule']) . "</td>";
        echo "<td>" . htmlspecialchars($sub['dietary_preference']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No active subscriptions found that should have deliveries today.</p>";
}

// Check if delivery_assignments table has any data
$check_table = "SHOW TABLES LIKE 'delivery_assignments'";
$table_exists = $conn->query($check_table)->num_rows > 0;

echo "<h2>Database Status</h2>";
echo "<p>delivery_assignments table exists: " . ($table_exists ? 'Yes' : 'No') . "</p>";

if ($table_exists) {
    $count = $conn->query("SELECT COUNT(*) as total FROM delivery_assignments")->fetch_assoc();
    echo "<p>Total delivery assignments in database: " . $count['total'] . "</p>";
}

$conn->close();
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
    h1 { color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px; }
    h2 { color: #444; margin-top: 30px; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    tr:hover { background-color: #f1f1f1; }
</style>
