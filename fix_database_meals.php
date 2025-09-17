<?php
// Database cleanup script to fix meal-related issues
include('config/db_connect.php');

echo "Starting database cleanup for meal-related issues...\n";

try {
    $conn->begin_transaction();
    
    // 1. Remove plan_meals entries that reference invalid meal_id (0 or non-existent meals)
    echo "1. Cleaning up invalid plan_meals entries...\n";
    $cleanup_plan_meals = "DELETE pm FROM plan_meals pm 
                          LEFT JOIN meals m ON pm.meal_id = m.meal_id 
                          WHERE pm.meal_id = 0 OR m.meal_id IS NULL OR m.is_active = 0";
    $result1 = $conn->query($cleanup_plan_meals);
    echo "   Removed " . $conn->affected_rows . " invalid plan_meals entries\n";
    
    // 2. Remove subscription_meals entries that reference meals that don't exist
    echo "2. Cleaning up invalid subscription_meals entries...\n";
    $cleanup_subscription_meals = "DELETE sm FROM subscription_meals sm 
                                  WHERE sm.meal_name = '' OR sm.meal_name IS NULL";
    $result2 = $conn->query($cleanup_subscription_meals);
    echo "   Removed " . $conn->affected_rows . " invalid subscription_meals entries\n";
    
    // 3. Remove meals with meal_id = 0 (invalid entries)
    echo "3. Cleaning up invalid meals entries...\n";
    $cleanup_meals = "DELETE FROM meals WHERE meal_id = 0 OR meal_name = '' OR meal_name IS NULL";
    $result3 = $conn->query($cleanup_meals);
    echo "   Removed " . $conn->affected_rows . " invalid meals entries\n";
    
    // 4. Update delivery_assignments that reference invalid meal_ids
    echo "4. Cleaning up invalid delivery_assignments...\n";
    $cleanup_assignments = "UPDATE delivery_assignments da 
                           LEFT JOIN meals m ON da.meal_id = m.meal_id 
                           SET da.meal_id = NULL 
                           WHERE da.meal_id = 0 OR (da.meal_id IS NOT NULL AND m.meal_id IS NULL)";
    $result4 = $conn->query($cleanup_assignments);
    echo "   Updated " . $conn->affected_rows . " delivery_assignments entries\n";
    
    // 5. Verify data integrity
    echo "5. Verifying data integrity...\n";
    
    // Check for orphaned plan_meals
    $check_plan_meals = "SELECT COUNT(*) as count FROM plan_meals pm 
                        LEFT JOIN meals m ON pm.meal_id = m.meal_id 
                        WHERE m.meal_id IS NULL OR m.is_active = 0";
    $result = $conn->query($check_plan_meals);
    $orphaned_plan_meals = $result->fetch_assoc()['count'];
    echo "   Orphaned plan_meals: $orphaned_plan_meals\n";
    
    // Check for empty subscription_meals
    $check_sub_meals = "SELECT COUNT(*) as count FROM subscription_meals 
                       WHERE meal_name = '' OR meal_name IS NULL";
    $result = $conn->query($check_sub_meals);
    $empty_sub_meals = $result->fetch_assoc()['count'];
    echo "   Empty subscription_meals: $empty_sub_meals\n";
    
    // Check for invalid meals
    $check_meals = "SELECT COUNT(*) as count FROM meals 
                   WHERE meal_id = 0 OR meal_name = '' OR meal_name IS NULL";
    $result = $conn->query($check_meals);
    $invalid_meals = $result->fetch_assoc()['count'];
    echo "   Invalid meals: $invalid_meals\n";
    
    if ($orphaned_plan_meals == 0 && $empty_sub_meals == 0 && $invalid_meals == 0) {
        $conn->commit();
        echo "\n✅ Database cleanup completed successfully!\n";
        echo "All meal-related data integrity issues have been resolved.\n";
    } else {
        throw new Exception("Data integrity issues still exist after cleanup");
    }
    
} catch (Exception $e) {
    $conn->rollback();
    echo "\n❌ Error during cleanup: " . $e->getMessage() . "\n";
    echo "Database changes have been rolled back.\n";
}

$conn->close();
?>