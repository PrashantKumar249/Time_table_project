<?php
// Migration script to add is_coordinator column to faculty table
include '../include/db.php';

try {
    // Check if column already exists
    $check_query = "SHOW COLUMNS FROM faculty LIKE 'is_coordinator'";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) == 0) {
        // Column doesn't exist, so add it
        $alter_query = "ALTER TABLE faculty ADD COLUMN is_coordinator TINYINT(1) DEFAULT 0 AFTER password";
        
        if (mysqli_query($conn, $alter_query)) {
            echo "✓ Successfully added 'is_coordinator' column to faculty table";
            exit(0);
        } else {
            echo "✗ Error adding column: " . mysqli_error($conn);
            exit(1);
        }
    } else {
        echo "✓ Column 'is_coordinator' already exists";
        exit(0);
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage();
    exit(1);
}
?>
