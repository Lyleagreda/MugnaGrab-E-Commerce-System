<?php
require 'db.php';

try {
    // Add last_login column to users table
    $conn->exec("ALTER TABLE users ADD COLUMN last_login DATETIME NULL DEFAULT NULL");
    echo "Successfully added last_login column to users table.<br>";
    
    // Add last_login column to admin table
    $conn->exec("ALTER TABLE admin ADD COLUMN last_login DATETIME NULL DEFAULT NULL");
    echo "Successfully added last_login column to admin table.<br>";
    
    echo "All updates completed successfully!";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 