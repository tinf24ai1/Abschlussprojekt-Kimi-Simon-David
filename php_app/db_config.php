<?php
// Centralized Database Configuration and Connection

error_reporting(E_ALL);
ini_set('display_errors', 1);

$dbHost = getenv('DB_HOST');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
$dbName = getenv('DB_NAME');

// Ensure all environment variables are set.
if (!$dbHost || !$dbUser || !$dbPass || !$dbName) {
    die("FATAL ERROR: Missing one or more database connection environment variables (DB_HOST, DB_USER, DB_PASS, DB_NAME). Check your .env file and Docker Compose configuration.");
}

$pdo = null; // Initialize $pdo to null

try {
    // Data Source Name (DSN) for MySQL
    $dsn = "mysql:host=" . $dbHost . ";dbname=" . $dbName . ";charset=utf8mb4";
    
    // PDO Connection Options
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // Fetch results as associative arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                // Use native prepared statements
    ];

    // Create the PDO instance
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);

} catch (PDOException $e) {
    // If connection fails, stop everything and show the error.
    die("DATABASE CONNECTION FAILED: " . htmlspecialchars($e->getMessage()));
}

// Function to validate names (tables, columns)
function is_valid_name($name) {
    // Allows letters, numbers, and underscores. Max 64 chars. Must not be empty.
    return $name !== '' && preg_match('/^[a-zA-Z0-9_]+$/', $name) && strlen($name) <= 64;
}

?>

