<?php
require_once 'config/db_connect.php';

echo "<h2>Fixing Meal Assignments...</h2>";

// --- Step 1: Add meal_id column to delivery_assignments if it doesn't exist ---
$check_column_sql = "SHOW COLUMNS FROM `delivery_assignments` LIKE 'meal_id'";
$result = $conn->query($check_column_sql);
if ($result->num_rows == 0) {
    $alter_sql = "ALTER TABLE `delivery_assignments` ADD COLUMN `meal_id` INT(11) NULL DEFAULT NULL AFTER `meal_type`, ADD INDEX `idx_meal_id` (`meal_id`)";
    if ($conn->query($alter_sql)) {
        echo "<p style='color:green;'>Successfully added 'meal_id' column to delivery_assignments table.</p>";
    } else {
        die("<p style='color:red;'>Error adding column: " . $conn->error . "</p>");
    }
} else {
    echo "<p style='color:blue;'>'meal_id' column already exists.</p>";
}

// --- Step 2: Populate meal_id for existing assignments where it is NULL ---

// Fetch all assignments that need a meal_id
$assignments_sql = "
    SELECT 
        da.assignment_id, 
        da.subscription_id, 
        da.delivery_date, 
        da.meal_type,
        s.plan_id,
        s.dietary_preference
    FROM delivery_assignments da
    JOIN subscriptions s ON da.subscription_id = s.subscription_id
    WHERE da.meal_id IS NULL
";

$assignments_result = $conn->query($assignments_sql);

if ($assignments_result->num_rows > 0) {
    $update_stmt = $conn->prepare("UPDATE delivery_assignments SET meal_id = ? WHERE assignment_id = ?");
    $updated_count = 0;
    $not_found_count = 0;

    while ($assignment = $assignments_result->fetch_assoc()) {
        $day_of_week = strtoupper(date('l', strtotime($assignment['delivery_date'])));
        $meal_type = $assignment['meal_type'];
        $plan_id = $assignment['plan_id'];
        $diet_pref = strtolower($assignment['dietary_preference']);

        // Find the correct meal_id from plan_meals based on plan, day, and meal type
        // This logic must handle dietary preferences correctly.
        $meal_sql = "
            SELECT 
                pm.meal_id, mc.option_type
            FROM plan_meals pm
            JOIN meals m ON pm.meal_id = m.meal_id
            JOIN meal_categories mc ON m.category_id = mc.category_id
            WHERE pm.plan_id = ? 
              AND pm.day_of_week = ? 
              AND pm.meal_type = ?
              AND m.is_active = 1
        ";
        
        $meal_stmt = $conn->prepare($meal_sql);
        $meal_stmt->bind_param("iss", $plan_id, $day_of_week, $meal_type);
        $meal_stmt->execute();
        $meals_result = $meal_stmt->get_result();

        $potential_meals = [];
        while($row = $meals_result->fetch_assoc()){
            $potential_meals[] = $row;
        }
        $meal_stmt->close();

        $final_meal_id = null;
        if (!empty($potential_meals)) {
            // Simple preference logic: if non-veg, find non-veg meal, else find veg.
            if ($diet_pref === 'non-veg' || $diet_pref === 'nonveg') {
                foreach ($potential_meals as $meal) {
                    if (strtolower($meal['option_type']) !== 'veg') {
                        $final_meal_id = $meal['meal_id'];
                        break;
                    }
                }
            }
            // If still no meal, or pref is veg, take the first available (which might be veg)
            if ($final_meal_id === null) {
                $final_meal_id = $potential_meals[0]['meal_id'];
            }
        }

        if ($final_meal_id) {
            $update_stmt->bind_param("ii", $final_meal_id, $assignment['assignment_id']);
            $update_stmt->execute();
            $updated_count++;
        } else {
            $not_found_count++;
            echo "<p style='color:orange;'>Could not find a meal for assignment ID: {$assignment['assignment_id']} (Date: {$assignment['delivery_date']}, Type: {$meal_type})</p>";
        }
    }
    echo "<p style='color:green;'>Successfully updated {$updated_count} assignments.</p>";
    if ($not_found_count > 0) {
        echo "<p style='color:red;'>Failed to find a meal for {$not_found_count} assignments.</p>";
    }
    $update_stmt->close();
} else {
    echo "<p style='color:blue;'>All delivery assignments already have a meal_id. No updates needed.</p>";
}

echo "<h2>Fix complete.</h2>";
$conn->close();
?>