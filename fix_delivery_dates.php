<?php
require_once 'config/db_connect.php';

// First, show current assignments
echo "<h2>Current Delivery Assignments:</h2>";
$result = $conn->query("SELECT * FROM delivery_assignments ORDER BY delivery_date, meal_type");
if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'><tr>";
    $fields = $result->fetch_fields();
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
    echo "No delivery assignments found.<br><br>";
}

// Update existing records to have today's date if they're not set
$update_sql = "UPDATE delivery_assignments SET delivery_date = '2025-09-02' WHERE delivery_date = '0000-00-00' OR delivery_date IS NULL";
if ($conn->query($update_sql) === TRUE) {
    $updated = $conn->affected_rows;
    echo "<p>Updated $updated delivery assignments to have today's date.</p>";
    
    // Now create new delivery assignments
    echo "<h2>Creating Future Deliveries...</h2>";
    ob_flush();
    flush();
    
    // Include the update script directly instead of requiring it
    include 'update_delivery_assignments.php';
    
    echo "<p>Future delivery assignments have been created. <a href='/mini/tiffinlysept1night/delivery/my_deliveries.php'>View Deliveries</a></p>";
} else {
    echo "<p>Error updating records: " . htmlspecialchars($conn->error) . "</p>";
}

// Show the updated assignments
$result = $conn->query("SELECT * FROM delivery_assignments ORDER BY delivery_date, meal_type");
if ($result->num_rows > 0) {
    echo "<h2>Updated Delivery Assignments:</h2>";
    echo "<table border='1' cellpadding='5'><tr>";
    $fields = $result->fetch_fields();
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
}

$conn->close();
?>
