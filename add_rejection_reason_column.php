<?php
// add_rejection_reason_column.php - Migration script to add rejection_reason column
require_once __DIR__ . "/classes/Database.php";

$db = new Database();
$conn = $db->getConnection();

// Check if rejection_reason column exists
$check_column = $conn->query("SHOW COLUMNS FROM lawyers LIKE 'rejection_reason'");

if ($check_column->num_rows == 0) {
    // Add rejection_reason column
    $sql = "ALTER TABLE lawyers ADD COLUMN rejection_reason TEXT NULL AFTER approved";
    
    if ($conn->query($sql) === TRUE) {
        echo "SUCCESS: rejection_reason column added to lawyers table\n";
    } else {
        echo "ERROR: " . $conn->error . "\n";
    }
} else {
    echo "INFO: rejection_reason column already exists\n";
}

$conn->close();
?>