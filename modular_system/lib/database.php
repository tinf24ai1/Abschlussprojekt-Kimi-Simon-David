<?php
// lib/database.php

// Get database credentials from environment variables
$db_host = getenv('DB_HOST') ?: 'db'; // 'db' is the service name in docker-compose.yml
$db_name = getenv('DB_DATABASE') ?: 'my_app_db';
$db_user = getenv('DB_USER') ?: 'my_app_user';
$db_pass = getenv('DB_PASSWORD') ?: 'my_app_password';
$db_charset = 'utf8mb4';

$dsn = "mysql:host={$db_host};dbname={$db_name};charset={$db_charset}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
    // In a real app, log this error and show a user-friendly message
    error_log("Database Connection Error: " . $e->getMessage());
    // You might want to throw the exception or handle it more gracefully
    // For debugging during setup, you can print it:
    // echo "Database Connection Error: " . $e->getMessage() . "<br/>";
    // echo "Host: " . $db_host . "<br/>";
    // echo "DB Name: " . $db_name . "<br/>";
    // echo "User: " . $db_user . "<br/>";
    // Make sure these values are being picked up correctly.
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// You can then use $pdo in other parts of your application
// For example:
// function getModules() {
//     global $pdo;
//     $stmt = $pdo->query("SELECT * FROM modules"); // Assuming you have a 'modules' table
//     return $stmt->fetchAll();
// }
?>
