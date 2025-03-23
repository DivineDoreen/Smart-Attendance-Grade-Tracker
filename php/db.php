<?php
// Database configuration
$host = 'localhost'; // Host name
$dbname = 'smart_attendance'; // Database name
$username = 'root'; // Default username for XAMPP
$password = ''; // Default password for XAMPP (empty)

// Create a connection to the database
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    // Set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // If connection fails, display an error message
    echo "Connection failed: " . $e->getMessage();
}
?>