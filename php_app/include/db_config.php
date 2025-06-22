<?php
// Start the session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database credentials from environment variables
$host = getenv('DB_HOST');
$dbname = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$charset = 'utf8mb4';

// Data Source Name
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// PDO options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Establish PDO connection
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Stop script execution on connection failure
    die("Database connection failed: " . $e->getMessage());
}

// Helper function for validating table/column names
function is_valid_name(string $name): bool {
    // Allows letters, numbers, and underscores. Max 64 chars. Cannot start with a number.
    return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/', $name);
}

// --- User System Initialization ---
try {
    // Check if the users table exists by trying to query it.
    $pdo->query("SELECT 1 FROM `users` LIMIT 1");
} catch (PDOException $e) {
    // --- THIS IS THE KEY CHANGE ---
    // If the error code is '42S02', it means the table doesn't exist.
    if ($e->getCode() === '42S02') {
        try {
            // SQL to create the users table
            $createUsersTableSql = "
            CREATE TABLE `users` (
              `id` INT PRIMARY KEY AUTO_INCREMENT,
              `username` VARCHAR(50) NOT NULL UNIQUE,
              `password` VARCHAR(255) NOT NULL,
              `role` ENUM('user', 'admin') NOT NULL DEFAULT 'user',
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $pdo->exec($createUsersTableSql);

            // Create the default admin user
            $adminUsername = 'admin';
            $adminPassword = 'admin1'; // As requested
            $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
            $adminRole = 'admin';

            $insertAdminSql = "INSERT INTO `users` (username, password, role) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($insertAdminSql);
            $stmt->execute([$adminUsername, $hashedPassword, $adminRole]);

        } catch (PDOException $init_e) {
            die("Failed to initialize user system: " . $init_e->getMessage());
        }
    } else {
        // If it's a different error, re-throw it.
        throw $e;
    }
}