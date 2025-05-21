<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "mugna";

try {
    // Set up the PDO connection
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    
    // Set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Optionally, set the charset to utf8mb4 for better character support
    $conn->exec("SET NAMES utf8mb4");

    // You can use this for debugging if needed:
    // echo "Connected successfully";
} catch (PDOException $e) {
    // Handle connection failure
    die("Connection failed: " . $e->getMessage());
}
?>
