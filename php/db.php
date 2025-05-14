<?php
$host = 'localhost';
$dbname = 'smart_attendance';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password, [
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true, // Prevents unbuffered errors
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Will throw exceptions on errors
        PDO::ATTR_PERSISTENT => false, // Avoids connection exhaustion
        PDO::ATTR_EMULATE_PREPARES => false // True security
    ]);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
