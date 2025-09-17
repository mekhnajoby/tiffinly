<?php
/**
 * Debug Track Order Page
 * 
 * This script helps debug why no meals are showing up in track_order.php
 */

// Start session and include database connection
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Please log in first.");
}

include('../config/db_connect.php');
$user_id = $_SESSION['user_id'];

// Get today's date
$today = date('Y-m-d');

echo "<h1>Track Order Debug</h1>";
echo "<p>Today's date: $today</p>";

// 1. Check active subscription
$subscription_sql = "
    SELECT s.*, p.plan_name 
    FROM subscriptions s
    LEFT JOIN meal_plans p ON s.plan_id = p.plan_id
    WHERE s.user_id = ? 
    AND UPPER(s.status) IN ('ACTIVE','ACTIVATED')
    AND UPPER(s.payment_status) IN ('PAID','COMPLETED','SUCCESS')
    ORDER BY s.start_date DESC
    LIMIT 1
";

$stmt = $conn->prepare($subscription_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$subscription = $stmt->get_result()->fetch_assoc();

echo "<h2>1. Subscription Status</h2>";
if ($subscription) {
    echo "<pre>" . print_r($subscription, true) . "</pre>";
    
    // 2. Check delivery assignments for today
    $delivery_sql = "
        SELECT da.*, u.name as partner_name
        FROM delivery_assignments da
        LEFT JOIN users u ON da.partner_id = u.user_id
        WHERE da.subscription_id = ?
        AND DATE(da.delivery_date) = ?
        ORDER BY da.meal_type
    ";
    
    $stmt = $conn->prepare($delivery_sql);
    $stmt->bind_param("is", $subscription['subscription_id'], $today);
    $stmt->execute();
    $deliveries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "<h2>2. Today's Deliveries (" . count($deliveries) . ")</h2>";
    if (!empty($deliveries)) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Meal Type</th><th>Status</th><th>Partner</th><th>Delivery Date</th></tr>";
        $allDelivered = true;
        foreach ($deliveries as $delivery) {
            if (strtolower($delivery['status']) !== 'delivered') {
                $allDelivered = false;
            }
            echo "<tr>";
            echo "<td>" . htmlspecialchars($delivery['meal_type'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($delivery['status'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($delivery['partner_name'] ?? 'Not assigned') . "</td>";
            echo "<td>" . htmlspecialchars($delivery['delivery_date'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        if ($allDelivered) {
            echo "<div style='color: green; font-weight: bold;'>Today's deliveries completed</div>";
        }
    } else {
        echo "<p>No delivery assignments found for today.</p>";
    }

    // Find next upcoming delivery date and meals
    $upcoming_sql = "
        SELECT da.delivery_date, da.meal_type, sm.meal_name
        FROM delivery_assignments da
        LEFT JOIN subscription_meals sm ON da.subscription_id = sm.subscription_id AND da.meal_type = sm.meal_type AND DAYOFWEEK(da.delivery_date) = sm.day_of_week
        WHERE da.subscription_id = ?
        AND DATE(da.delivery_date) > ?
        AND (da.status IS NULL OR LOWER(da.status) != 'delivered')
        ORDER BY da.delivery_date ASC
        LIMIT 10
    ";
    $stmt = $conn->prepare($upcoming_sql);
    $stmt->bind_param("is", $subscription['subscription_id'], $today);
    $stmt->execute();
    $upcoming = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    if (!empty($upcoming)) {
        $next_date = $upcoming[0]['delivery_date'];
        $next_date_fmt = date('F j, Y', strtotime($next_date));
        echo "<h3 style='margin-top:2em;'>Upcoming Delivery: $next_date_fmt</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Meal Type</th><th>Meal Name</th></tr>";
        foreach ($upcoming as $row) {
            if ($row['delivery_date'] == $next_date) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['meal_type'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($row['meal_name'] ?? 'N/A') . "</td>";
                echo "</tr>";
            }
        }
        echo "</table>";
    }
    
    // 3. Check subscription meals
    $meals_sql = "
        SELECT * FROM subscription_meals 
        WHERE subscription_id = ?
        ORDER BY day_of_week, meal_type
    ";
    
    $stmt = $conn->prepare($meals_sql);
    $stmt->bind_param("i", $subscription['subscription_id']);
    $stmt->execute();
    $meals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "<h2>3. Subscription Meals (" . count($meals) . ")</h2>";
    if (!empty($meals)) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Day</th><th>Meal Type</th><th>Meal Name</th></tr>";
        foreach ($meals as $meal) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($meal['day_of_week'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($meal['meal_type'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($meal['meal_name'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No meals found for this subscription.</p>";
    }
    
    // 4. Check delivery assignments in date range
    $range_sql = "
        SELECT DATE(delivery_date) as delivery_date, 
               COUNT(*) as meal_count,
               GROUP_CONCAT(meal_type) as meal_types
        FROM delivery_assignments
        WHERE subscription_id = ?
        AND delivery_date BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY)
        GROUP BY DATE(delivery_date)
        ORDER BY delivery_date
    ";
    
    $stmt = $conn->prepare($range_sql);
    $stmt->bind_param("iss", $subscription['subscription_id'], $today, $today);
    $stmt->execute();
    $delivery_dates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "<h2>4. Upcoming Deliveries (Next 7 Days)</h2>";
    if (!empty($delivery_dates)) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Date</th><th>Meal Count</th><th>Meal Types</th></tr>";
        foreach ($delivery_dates as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['delivery_date']) . "</td>";
            echo "<td>" . htmlspecialchars($row['meal_count']) . "</td>";
            echo "<td>" . htmlspecialchars($row['meal_types']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No delivery assignments found in the next 7 days.</p>";
    }
    
} else {
    echo "<p>No active subscription found for user ID: $user_id</p>";
}

// Close connection
$conn->close();
?>

<style>
table { border-collapse: collapse; margin: 10px 0; }
th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
th { background-color: #f2f2f2; }
</style>
