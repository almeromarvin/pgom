<?php
// Database configuration for deployment
// Update these values with your hosting provider credentials

$host = 'your-mysql-host.infinityfree.com'; // Replace with your MySQL host
$dbname = 'your-username_pgom_facilities'; // Replace with your database name
$username = 'your-username'; // Replace with your database username
$password = 'your-password'; // Replace with your database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Connection failed: " . $e->getMessage());
}
?>