<?php
include('./config/db_connect.php');

$sql = "SELECT da.assignment_id, da.subscription_id, da.meal_type, s.plan_id, s.dietary_preference, DAYNAME(da.assigned_at) as day_of_week
        FROM delivery_assignments da
        JOIN subscriptions s ON da.subscription_id = s.subscription_id
        WHERE da.meal_id IS NULL";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $assignment_id = $row['assignment_id'];
        $subscription_id = $row['subscription_id'];
        $meal_type = $row['meal_type'];
        $plan_id = $row['plan_id'];
        $dietary_preference = $row['dietary_preference'];
        $day_of_week = $row['day_of_week'];

        $meal_sql = "SELECT meal_id FROM subscription_meals WHERE subscription_id = ? AND meal_type = ? AND day_of_week = ?";
        $stmt = $conn->prepare($meal_sql);
        $stmt->bind_param("iss", $subscription_id, $meal_type, $day_of_week);
        $stmt->execute();
        $meal_result = $stmt->get_result();

        if ($meal_result->num_rows > 0) {
            $meal_row = $meal_result->fetch_assoc();
            $meal_id = $meal_row['meal_id'];

            $update_sql = "UPDATE delivery_assignments SET meal_id = ? WHERE assignment_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ii", $meal_id, $assignment_id);
            $update_stmt->execute();
        } else {
            // Fallback for basic plans if subscription_meals is not populated
            $plan_meal_sql = "SELECT pm.meal_id 
                              FROM plan_meals pm
                              JOIN meals m ON pm.meal_id = m.meal_id
                              JOIN meal_categories mc ON m.category_id = mc.category_id
                              WHERE pm.plan_id = ? AND pm.day_of_week = ? AND pm.meal_type = ? AND mc.option_type = ?
                              LIMIT 1";
            $plan_meal_stmt = $conn->prepare($plan_meal_sql);
            $plan_meal_stmt->bind_param("isss", $plan_id, $day_of_week, $meal_type, $dietary_preference);
            $plan_meal_stmt->execute();
            $plan_meal_result = $plan_meal_stmt->get_result();
            if($plan_meal_result->num_rows > 0) {
                $plan_meal_row = $plan_meal_result->fetch_assoc();
                $meal_id = $plan_meal_row['meal_id'];

                $update_sql = "UPDATE delivery_assignments SET meal_id = ? WHERE assignment_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ii", $meal_id, $assignment_id);
                $update_stmt->execute();
            }
        }
    }
    echo "Updated meal_id for existing delivery assignments.";
} else {
    echo "No delivery assignments with NULL meal_id found.";
}

$conn->close();
?>