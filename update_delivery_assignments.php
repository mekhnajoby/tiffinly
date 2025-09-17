<?php
require_once __DIR__ . '/config/db_connect.php';

// Get all active subscriptions with their meal preferences
$query = "SELECT s.subscription_id, s.start_date, s.end_date, s.schedule, s.dietary_preference,
          GROUP_CONCAT(CONCAT(sm.day_of_week, '|', sm.meal_type) SEPARATOR ',') as meal_schedule
          FROM subscriptions s
          LEFT JOIN subscription_meals sm ON s.subscription_id = sm.subscription_id
          WHERE s.status = 'active' AND s.payment_status = 'paid'
          GROUP BY s.subscription_id, s.start_date, s.end_date, s.schedule, s.dietary_preference";

$result = $conn->query($query);

if ($result->num_rows > 0) {
    while ($subscription = $result->fetch_assoc()) {
        $start = new DateTime($subscription['start_date']);
        $end = new DateTime($subscription['end_date']);
        $interval = new DateInterval('P1D'); // 1 day interval
        $period = new DatePeriod($start, $interval, $end->modify('+1 day')); // Include end date

        foreach ($period as $date) {
            $delivery_date = $date->format('Y-m-d');
            
            // Parse meal schedule
            $meals = [];
            $schedule = explode(',', $subscription['meal_schedule']);
            $day_name = strtoupper($date->format('l'));
            
            // Skip weekends if schedule is Weekdays
            if ($subscription['schedule'] === 'Weekdays' && in_array($day_name, ['SATURDAY', 'SUNDAY'])) {
                continue;
            }
            
            // Get meals for this day
            foreach ($schedule as $meal) {
                $meal_parts = explode('|', $meal, 2);
                if (count($meal_parts) === 2) {
                    list($day, $meal_type) = $meal_parts;
                    if (strtoupper(trim($day)) === $day_name) {
                        $meals[] = strtolower(trim($meal_type));
                    }
                }
            }
            
            foreach ($meals as $meal_type) {
                // Check if assignment already exists
                $check_sql = "SELECT assignment_id FROM delivery_assignments 
                             WHERE subscription_id = ? AND delivery_date = ? AND meal_type = ?";
                $stmt = $conn->prepare($check_sql);
                $stmt->bind_param("iss", $subscription['subscription_id'], $delivery_date, $meal_type);
                $stmt->execute();
                $exists = $stmt->get_result()->num_rows > 0;
                $stmt->close();
                
                if (!$exists) {
                    // Get a random delivery partner (assuming delivery partners have role 'delivery' in users table)
                    $partner_sql = "SELECT user_id FROM users WHERE role = 'delivery' ORDER BY RAND() LIMIT 1";
                    $partner_result = $conn->query($partner_sql);
                    $partner_id = $partner_result ? $partner_result->fetch_assoc()['user_id'] : 1; // Fallback to admin if no delivery partners
                    
                    // Insert new assignment
                    $insert_sql = "INSERT INTO delivery_assignments 
                                  (subscription_id, delivery_date, partner_id, assigned_at, status, meal_type) 
                                  VALUES (?, ?, ?, NOW(), 'pending', ?)";
                    $stmt = $conn->prepare($insert_sql);
                    $stmt->bind_param("isis", 
                        $subscription['subscription_id'], 
                        $delivery_date, 
                        $partner_id,
                        $meal_type
                    );
                    
                    if ($stmt->execute()) {
                        echo "Created assignment for subscription #{$subscription['subscription_id']} - " . 
                             "{$meal_type} on {$delivery_date}\n";
                    } else {
                        echo "Error creating assignment: " . $conn->error . "\n";
                    }
                    $stmt->close();
                }
            }
        }
    }
    
    echo "All delivery assignments have been updated successfully!\n";
} else {
    echo "No subscriptions found.\n";
}

$conn->close();
?>
