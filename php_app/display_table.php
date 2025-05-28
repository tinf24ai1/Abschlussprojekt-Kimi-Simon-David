<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$dbHost = getenv('DB_HOST');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
$dbName = getenv('DB_NAME');

// Check for essential DB connection variables from .env
if (!$dbHost || !$dbUser || !$dbPass || !$dbName) {
    http_response_code(500);
    echo "Error: Missing one or more database connection environment variables (DB_HOST, DB_USER, DB_PASS, DB_NAME).";
    exit;
}

$selectedTableName = null;
$availableTables = [];
$columns = [];
$rows = [];
$error_message = null;
$pdo = null;

try {
    $dsn = "mysql:host=" . $dbHost . ";dbname=" . $dbName . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);

    // Fetch all table names from the current database
    $stmt = $pdo->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?");
    $stmt->execute([$dbName]);
    $fetchedTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($fetchedTables) {
        $availableTables = $fetchedTables;
    }

    // Check if a specific table is requested via GET parameter
    if (isset($_GET['table_to_display']) && !empty($_GET['table_to_display'])) {
        $requestedTable = $_GET['table_to_display'];

        // Validate if the requested table is in the list of available tables (security measure)
        if (in_array($requestedTable, $availableTables)) {
            $selectedTableName = $requestedTable;

            // Fetch column names for the selected table
            $stmtColumns = $pdo->query("DESCRIBE `" . str_replace("`", "``", $selectedTableName) . "`");
            $columnsInfo = $stmtColumns->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($columnsInfo)) {
                $columns = array_map(function($colInfo) { return $colInfo['Field']; }, $columnsInfo);
            } else {
                $error_message = "Table '" . htmlspecialchars($selectedTableName) . "' exists but has no columns or schema could not be read.";
                $selectedTableName = null; // Prevent trying to display data
            }

            // Fetch data for the selected table (only if columns were found)
            if ($selectedTableName && !empty($columns)) {
                $stmtData = $pdo->query("SELECT * FROM `" . str_replace("`", "``", $selectedTableName) . "`");
                $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            $error_message = "Error: Table '" . htmlspecialchars($requestedTable) . "' is not a valid or accessible table.";
        }
    }

} catch (PDOException $e) {
    http_response_code(500);
    $error_message = "Database error: " . htmlspecialchars($e->getMessage());
    // Prevent further processing if DB connection fails critically
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Table Viewer</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .table-list { list-style-type: none; padding: 0; }
        .table-list li { margin-bottom: 5px; }
        .table-list a { text-decoration: none; color: #007bff; }
        .table-list a:hover { text-decoration: underline; }
        .current-table { font-weight: bold; color: #28a745 !important; }
    </style>
</head>
<body>
    <h1>Database Table Viewer</h1>

    <?php if ($error_message): ?>
        <p style="color: red;"><?php echo $error_message; ?></p>
    <?php endif; ?>

    <h2>Available Tables in '<?php echo htmlspecialchars($dbName); ?>':</h2>
    <?php if (!empty($availableTables)): ?>
        <ul class="table-list">
            <?php foreach ($availableTables as $tableName): ?>
                <li>
                    <a href="?table_to_display=<?php echo urlencode($tableName); ?>"
                       class="<?php echo ($tableName === $selectedTableName) ? 'current-table' : ''; ?>">
                        <?php echo htmlspecialchars($tableName); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php elseif (!$error_message): // Don't show "no tables" if there was a critical DB error ?>
        <p>No tables found in the database '<?php echo htmlspecialchars($dbName); ?>'. Use the Python script to create tables.</p>
    <?php endif; ?>

    <hr>

    <?php if ($selectedTableName && !empty($columns)): ?>
        <h2>Displaying Table: <?php echo htmlspecialchars($selectedTableName); ?></h2>
        <table>
            <thead>
                <tr>
                    <?php foreach ($columns as $columnName): ?>
                        <th><?php echo htmlspecialchars($columnName); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($rows)): ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($columns as $columnName): ?>
                                <td><?php echo htmlspecialchars(isset($row[$columnName]) ? $row[$columnName] : ''); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo count($columns); ?>">No data found in table '<?php echo htmlspecialchars($selectedTableName); ?>'.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php elseif (isset($_GET['table_to_display']) && !$error_message && empty($columns)): ?>
        <p>Table '<?php echo htmlspecialchars($_GET['table_to_display']); ?>' selected, but no columns could be determined or it's empty.</p>
    <?php elseif (!isset($_GET['table_to_display']) && empty($error_message)): ?>
        <p>Select a table from the list above to display its contents.</p>
    <?php endif; ?>

</body>
</html>
