<?php

// Zugangsdaten für die Datenbank 
$db_host = getenv('DB_HOST') ?: 'db'; // aus der docker-compose.yml 
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
    error_log("Database Connection Error: " . $e->getMessage());
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

?>
