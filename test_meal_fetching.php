<?php
// Test script to verify meal fetching functionality
include('config/db_connect.php');

echo "Testing meal fetching functionality after fixes...\n\n";

// Test 1: Check if basic plan meals are fetched correctly
echo "=== Test 1: Basic Plan Meal Fetching ===\n";
$basic_plan_id = 1; // Basic plan ID
$meal_sql = "SELECT pm.day_of_week, pm.meal_type, m.meal_name, mc.option_type 
            FROM plan_meals pm 
            JOIN meals m ON pm.meal_id = m.meal_id 
            JOIN meal_categories mc ON m.category_id = mc.category_id 
            WHERE pm.plan_id = ? AND m.is_active = 1 AND pm.meal_id > 0
            ORDER BY FIELD(pm.day_of_week, 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'), 
                    FIELD(pm.meal_type, 'Breakfast', 'Lunch', 'Dinner')";

$stmt = $conn->prepare($meal_sql);
$stmt->bind_param("i", $basic_plan_id);
$stmt->execute();
$result = $stmt->get_result();

$basic_meals = [];
$meal_count = 0;
while ($row = $result->fetch_assoc()) {
    if (!empty($row['meal_name'])) {
        $basic_meals[$row['day_of_week']][$row['meal_type']][] = [
            'name' => $row['meal_name'], 
            'type' => $row['option_type']
        ];
        $meal_count++;
    }
}
$stmt->close();

echo "Basic plan meals found: $meal_count\n";
foreach (['MONDAY', 'TUESDAY', 'WEDNESDAY'] as $day) {
    if (isset($basic_meals[$day])) {
        echo "$day:\n";
        foreach (['Breakfast', 'Lunch', 'Dinner'] as $slot) {
            if (isset($basic_meals[$day][$slot])) {
                echo "  $slot: " . count($basic_meals[$day][$slot]) . " meals\n";
                foreach ($basic_meals[$day][$slot] as $meal) {
                    echo "    - {$meal['name']} ({$meal['type']})\n";
                }
            }
        }
    }
}

// Test 2: Check if premium plan meals are fetched correctly
echo "\n=== Test 2: Premium Plan Meal Fetching ===\n";
$premium_plan_id = 2; // Premium plan ID
$stmt = $conn->prepare($meal_sql);
$stmt->bind_param("i", $premium_plan_id);
$stmt->execute();
$result = $stmt->get_result();

$premium_meals = [];
$premium_count = 0;
while ($row = $result->fetch_assoc()) {
    if (!empty($row['meal_name'])) {
        $premium_meals[$row['day_of_week']][$row['meal_type']][] = [
            'name' => $row['meal_name'], 
            'type' => $row['option_type']
        ];
        $premium_count++;
    }
}
$stmt->close();

echo "Premium plan meals found: $premium_count\n";
foreach (['MONDAY', 'TUESDAY', 'WEDNESDAY'] as $day) {
    if (isset($premium_meals[$day])) {
        echo "$day:\n";
        foreach (['Breakfast', 'Lunch', 'Dinner'] as $slot) {
            if (isset($premium_meals[$day][$slot])) {
                echo "  $slot: " . count($premium_meals[$day][$slot]) . " meals\n";
            }
        }
    }
}

// Test 3: Check subscription meals fetching
echo "\n=== Test 3: Subscription Meals Fetching ===\n";
$subscription_sql = "SELECT subscription_id, plan_type FROM subscriptions s 
                    JOIN meal_plans mp ON s.plan_id = mp.plan_id 
                    WHERE s.status != 'cancelled' 
                    ORDER BY s.created_at DESC LIMIT 3";
$result = $conn->query($subscription_sql);

if ($result->num_rows > 0) {
    while ($sub = $result->fetch_assoc()) {
        echo "Subscription ID: {$sub['subscription_id']} (Plan: {$sub['plan_type']})\n";
        
        if ($sub['plan_type'] === 'premium') {
            $meal_sql = "SELECT day_of_week, meal_type, meal_name 
                        FROM subscription_meals 
                        WHERE subscription_id = ?";
            $stmt = $conn->prepare($meal_sql);
            $stmt->bind_param("i", $sub['subscription_id']);
            $stmt->execute();
            $meal_result = $stmt->get_result();
            
            $sub_meal_count = 0;
            while ($meal_row = $meal_result->fetch_assoc()) {
                if (!empty($meal_row['meal_name'])) {
                    $sub_meal_count++;
                }
            }
            echo "  Custom meals: $sub_meal_count\n";
            $stmt->close();
        }
    }
} else {
    echo "No active subscriptions found\n";
}

// Test 4: Check for data integrity issues
echo "\n=== Test 4: Data Integrity Check ===\n";

// Check for orphaned plan_meals
$check_sql = "SELECT COUNT(*) as count FROM plan_meals pm 
              LEFT JOIN meals m ON pm.meal_id = m.meal_id 
              WHERE pm.meal_id = 0 OR m.meal_id IS NULL OR m.is_active = 0";
$result = $conn->query($check_sql);
$orphaned = $result->fetch_assoc()['count'];
echo "Orphaned plan_meals: $orphaned\n";

// Check for empty subscription_meals
$check_sql = "SELECT COUNT(*) as count FROM subscription_meals 
              WHERE meal_name = '' OR meal_name IS NULL";
$result = $conn->query($check_sql);
$empty = $result->fetch_assoc()['count'];
echo "Empty subscription_meals: $empty\n";

// Check for invalid meals
$check_sql = "SELECT COUNT(*) as count FROM meals 
              WHERE meal_id = 0 OR meal_name = '' OR meal_name IS NULL";
$result = $conn->query($check_sql);
$invalid = $result->fetch_assoc()['count'];
echo "Invalid meals: $invalid\n";

if ($orphaned == 0 && $empty == 0 && $invalid == 0) {
    echo "\n✅ All data integrity checks passed!\n";
} else {
    echo "\n❌ Data integrity issues found!\n";
}

echo "\n=== Test Summary ===\n";
echo "Basic plan meals: $meal_count\n";
echo "Premium plan meals: $premium_count\n";
echo "Data integrity: " . ($orphaned == 0 && $empty == 0 && $invalid == 0 ? "PASS" : "FAIL") . "\n";

if ($meal_count > 0 && $premium_count > 0 && $orphaned == 0 && $empty == 0 && $invalid == 0) {
    echo "\n🎉 All meal fetching tests PASSED! The fixes are working correctly.\n";
} else {
    echo "\n⚠️  Some tests failed. Please review the issues above.\n";
}

$conn->close();
?>