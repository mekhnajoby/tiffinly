<?php
require_once 'config/db_connect.php';

// Check delivery_assignments table structure
$result = $conn->query("DESCRIBE delivery_assignments");
if ($result) {
    echo "<h3>delivery_assignments table structure:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error describing table: " . $conn->error;
}

// Show current data
$result = $conn->query("SELECT * FROM delivery_assignments");
if ($result) {
    echo "<h3>Current delivery_assignments data:</h3>";
    if ($result->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        // Header row
        $fields = [];
        $header = $result->fetch_fields();
        echo "<tr>";
        foreach ($header as $h) {
            echo "<th>" . htmlspecialchars($h->name) . "</th>";
            $fields[] = $h->name;
        }
        echo "</tr>";
        
        // Data rows
        $result->data_seek(0); // Reset pointer
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            foreach ($fields as $field) {
                echo "<td>" . htmlspecialchars($row[$field] ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No records found in delivery_assignments table.";
    }
} else {
    echo "Error querying table: " . $conn->error;
}

$conn->close();
?>
