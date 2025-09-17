<?php
require_once 'config/db_connect.php';

// 1. Fix existing delivery assignments by adding delivery_date
try {
    // Add delivery_date column if it doesn't exist
    $conn->query("ALTER TABLE `delivery_assignments` 
                 ADD COLUMN IF NOT EXISTS `delivery_date` DATE NOT NULL DEFAULT '2025-09-01'
                 AFTER `subscription_id`");
    
    // Update existing records with delivery_date
    $conn->query("UPDATE `delivery_assignments` SET `delivery_date` = '2025-09-01' 
                 WHERE `delivery_date` = '0000-00-00' OR `delivery_date` IS NULL");
    
    echo "<p>Updated existing delivery assignments with delivery dates.</p>";
} catch (Exception $e) {
    echo "<p>Error updating delivery assignments: " . $e->getMessage() . "</p>";
}

// 2. Create future delivery assignments for the active subscription
$subscription = $conn->query("SELECT * FROM subscriptions WHERE status = 'active' AND subscription_id = 64")->fetch_assoc();

if ($subscription) {
    $start_date = new DateTime($subscription['start_date']);
    $end_date = new DateTime('2025-09-26');
    $end_date->modify('+1 day'); // Add one day to include the end date
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start_date, $interval, $end_date);
    
    $assigned_dates = [];
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    
    foreach ($period as $date) {
        $day_name = $days[$date->format('N') - 1];
        
        // Skip weekends for weekday subscriptions
        if ($subscription['schedule'] === 'Weekdays' && in_array($day_name, ['Saturday', 'Sunday'])) {
            continue;
        }
        
        $date_str = $date->format('Y-m-d');
        
        // Check if assignments already exist for this date
        $check = $conn->prepare("SELECT COUNT(*) as count FROM delivery_assignments 
                               WHERE subscription_id = ? AND delivery_date = ?");
        $check->bind_param('is', $subscription['subscription_id'], $date_str);
        $check->execute();
        $result = $check->get_result()->fetch_assoc();
        
        if ($result['count'] == 0) {
            // Get a random delivery partner
            $partner = $conn->query("SELECT user_id FROM users WHERE role = 'delivery' ORDER BY RAND() LIMIT 1")->fetch_assoc();
            $partner_id = $partner ? $partner['user_id'] : 1; // Fallback to admin if no delivery partners
            
            // Create assignments for each meal type
            $meal_types = ['breakfast', 'lunch', 'dinner'];
            foreach ($meal_types as $meal_type) {
                $stmt = $conn->prepare("INSERT INTO delivery_assignments 
                    (subscription_id, delivery_date, partner_id, assigned_at, status, meal_type)
                    VALUES (?, ?, ?, NOW(), 'pending', ?)");
                $stmt->bind_param('isis', 
                    $subscription['subscription_id'],
                    $date_str,
                    $partner_id,
                    $meal_type
                );
                $stmt->execute();
            }
            
            $assigned_dates[] = $date_str;
        }
    }
    
    if (!empty($assigned_dates)) {
        echo "<p>Created delivery assignments for dates: " . implode(', ', $assigned_dates) . "</p>";
    } else {
        echo "<p>No new delivery assignments were needed (they may already exist).</p>";
    }
    
    // 3. Update the delivery partner's page to show today's deliveries
    $today = date('Y-m-d');
    $deliveries = $conn->query("SELECT COUNT(*) as count FROM delivery_assignments 
                              WHERE delivery_date = '$today' AND status = 'pending'")->fetch_assoc();
    
    echo "<p>There are " . $deliveries['count'] . " pending deliveries for today ($today).</p>";
    
} else {
    echo "<p>No active subscription found with ID 64.</p>";
}

// 4. Show current delivery assignments
echo "<h3>Current Delivery Assignments</h3>";
$result = $conn->query("SELECT da.*, u.name as partner_name 
                       FROM delivery_assignments da
                       LEFT JOIN users u ON da.partner_id = u.user_id
                       ORDER BY da.delivery_date, da.meal_type");

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr>
        <th>ID</th>
        <th>Subscription ID</th>
        <th>Date</th>
        <th>Meal Type</th>
        <th>Status</th>
        <th>Partner</th>
    </tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['assignment_id'] . "</td>";
        echo "<td>" . $row['subscription_id'] . "</td>";
        echo "<td>" . $row['delivery_date'] . "</td>";
        echo "<td>" . ucfirst($row['meal_type']) . "</td>";
        echo "<td>" . ucfirst($row['status']) . "</td>";
        echo "<td>" . ($row['partner_name'] ?? 'Unassigned') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No delivery assignments found.</p>";
}

$conn->close();

echo "<p><a href='/mini/tiffinlysept1night/delivery/my_deliveries.php'>Go to Delivery Partner Page</a></p>";
?>
