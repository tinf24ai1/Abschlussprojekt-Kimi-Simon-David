<?php
// Database credentials - REPLACE WITH YOUR ACTUAL CREDENTIALS
define('DB_HOST', 'localhost');
define('DB_NAME', 'modular_db'); // The database name you created
define('DB_USER', 'root');    // Your MySQL username
define('DB_PASS', '');        // Your MySQL password

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    // In a production environment, log this error and show a user-friendly message.
    die("Database connection failed: " . $e->getMessage() . "<br><br>Please check your database credentials in /lib/database.php and ensure the database '" . DB_NAME . "' exists and the user has permissions.");
}
?>
