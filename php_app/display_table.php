<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$dbHost = getenv('DB_HOST');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
$dbName = getenv('DB_NAME');

$pdo = null;
$selectedTableName = null;
$columnsInfo = []; // Will store full column info including type
$rows = [];
$message = null;
$error_message = null;

// --- Get Selected Table Name ---
if (isset($_GET['table_name']) && !empty($_GET['table_name'])) {
    if (preg_match('/^[a-zA-Z0-9_]+$/', $_GET['table_name'])) {
         $selectedTableName = $_GET['table_name'];
    } else {
        $error_message = "Ungültiges Namensformat.";
    }
} else {
    $error_message = ">.< Baka?! Kein Table ausgewählt. Hier kannst du das Ändern UwU:<a href='index.php'>index page</a>.";
}

// --- Database Connection ---
if (!$error_message && (!$dbHost || !$dbUser || !$dbPass || !$dbName)) {
    $error_message = "Error: Missing database connection environment variables.";
} elseif (!$error_message) {
    try {
        $dsn = "mysql:host=" . $dbHost . ";dbname=" . $dbName . ";charset=utf8mb4";
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
        $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    } catch (PDOException $e) {
        $error_message = "Database connection error: " . htmlspecialchars($e->getMessage());
    }
}

// --- Fetch Available Tables for Validation ---
if ($pdo && $selectedTableName) {
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $availableTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array($selectedTableName, $availableTables)) {
            $error_message = "Table '" . htmlspecialchars($selectedTableName) . "' Die Geister, die du riefst, findest du nicht wieder";
            $selectedTableName = null; // Unset if invalid
        }
    } catch (PDOException $e) {
         $error_message = "Error validating table name: " . htmlspecialchars($e->getMessage());
         $selectedTableName = null;
    }
}

// --- Handle Add Entry Form Submission ---
if ($pdo && $selectedTableName && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_entry'])) {
    try {
        // Fetch column info again to be sure, especially for the INSERT
        $stmtDesc = $pdo->query("DESCRIBE `" . str_replace("`", "``", $selectedTableName) . "`");
        $currentColumnsMeta = $stmtDesc->fetchAll(PDO::FETCH_ASSOC);

        $insertColsSql = [];
        $insertValues = [];
        $insertPlaceholders = [];

        foreach ($currentColumnsMeta as $colMeta) {
            $colName = $colMeta['Field'];
            // Skip auto_increment columns for insert if no value is provided
            if (strpos(strtolower($colMeta['Extra']), 'auto_increment') !== false && empty($_POST['data'][$colName])) {
                continue;
            }

            if (array_key_exists($colName, $_POST['data'])) { // Check if data was submitted for this col
                $insertColsSql[] = "`" . str_replace("`", "``", $colName) . "`";
                $submittedValue = $_POST['data'][$colName];
                if ($submittedValue === '' && (stripos($colMeta['Type'], 'int') !== false || stripos($colMeta['Type'], 'decimal') !== false || stripos($colMeta['Type'], 'float') !== false || stripos($colMeta['Type'], 'double') !== false) && $colMeta['Null'] === 'NO') {
                } elseif (stripos($colMeta['Type'], 'boolean') !== false || stripos($colMeta['Type'], 'tinyint(1)') !== false) {
                    $insertValues[] = filter_var($submittedValue, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                }
                else {
                    $insertValues[] = ($submittedValue === '') ? null : $submittedValue;
                }
                $insertPlaceholders[] = '?';
            }
        }

        if (!empty($insertColsSql)) {
            $sql = "INSERT INTO `" . str_replace("`", "``", $selectedTableName) . "` (" . implode(', ', $insertColsSql) . ") VALUES (" . implode(', ', $insertPlaceholders) . ")";
            $stmtInsert = $pdo->prepare($sql);
            $stmtInsert->execute($insertValues);
            $message = "Erfolg! Daten hinzugefügt: '" . htmlspecialchars($selectedTableName) . "'.";
        } else {
            $error_message = "Nenn mich Google, denn ich brauche mehr Daten...";
        }
    } catch (PDOException $e) {
        $error_message = "Error: " . htmlspecialchars($e->getMessage());
    }
}

if ($pdo && $selectedTableName) {
    try {
        $stmtColumns = $pdo->query("DESCRIBE `" . str_replace("`", "``", $selectedTableName) . "`");
        $columnsInfo = $stmtColumns->fetchAll(PDO::FETCH_ASSOC); // Get full info

        $stmtData = $pdo->query("SELECT * FROM `" . str_replace("`", "``", $selectedTableName) . "`");
        $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "Error fetching table data: " . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Table: <?php echo htmlspecialchars($selectedTableName ?? 'Error'); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>Viewing Table: <?php echo htmlspecialchars($selectedTableName ?? 'Error'); ?> 🧐</h1>
    </header>
    
    <main class="container">
        <a href="index.php" class="back-link action-btn-secondary">&laquo; Back to Table List</a>

        <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>

        <?php if ($selectedTableName && !empty($columnsInfo)): ?>
            <section class="section data-table-section">
                <h2>Table Content 📜</h2>
                <?php if (!empty($rows)): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <?php foreach ($columnsInfo as $colInfo): ?>
                                    <th><?php echo htmlspecialchars($colInfo['Field']); ?><br><small>(<?php echo htmlspecialchars($colInfo['Type']); ?>)</small></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <?php foreach ($columnsInfo as $colInfo): ?>
                                        <td><?php echo htmlspecialchars(isset($row[$colInfo['Field']]) ? $row[$colInfo['Field']] : 'NULL'); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p>Dieses Table ist leerer als der Geldbeutel eines dualen Studenten am Ende des Monats :(</p>
                <?php endif; ?>
            </section>

            <section class="section add-entry-section">
                <h2>Add New Entry ➕</h2>
                <form action="display_table.php?table_name=<?php echo urlencode($selectedTableName); ?>" method="post" class="styled-form">
                    <input type="hidden" name="add_entry" value="1">
                    <?php foreach ($columnsInfo as $colInfo): ?>
                        <?php // Skip auto_increment columns for the add form if they are likely primary keys ?>
                        <?php if (strpos(strtolower($colInfo['Extra']), 'auto_increment') !== false): ?>
                            <div class="form-group">
                                <label for="data_<?php echo htmlspecialchars($colInfo['Field']); ?>">
                                    <?php echo htmlspecialchars($colInfo['Field']); ?> (<?php echo htmlspecialchars($colInfo['Type']); ?>):
                                </label>
                                <input type="text" id="data_<?php echo htmlspecialchars($colInfo['Field']); ?>" 
                                       name="data[<?php echo htmlspecialchars($colInfo['Field']); ?>]"
                                       placeholder="Auto-generated" disabled>
                                <small>This field is auto-incrementing.</small>       
                            </div>
                        <?php else: ?>
                            <div class="form-group">
                                <label for="data_<?php echo htmlspecialchars($colInfo['Field']); ?>">
                                    <?php echo htmlspecialchars($colInfo['Field']); ?> (<?php echo htmlspecialchars($colInfo['Type']); ?>):
                                </label>
                                <?php
                                $inputType = "text";
                                $colTypeLower = strtolower($colInfo['Type']);
                                if (strpos($colTypeLower, 'date') !== false && strpos($colTypeLower, 'datetime') === false) $inputType = "date";
                                if (strpos($colTypeLower, 'datetime') !== false || strpos($colTypeLower, 'timestamp') !== false) $inputType = "datetime-local";
                                if (strpos($colTypeLower, 'int') !== false || strpos($colTypeLower, 'decimal') !== false) $inputType = "number";
                                if (strpos($colTypeLower, 'boolean') !== false || strpos($colTypeLower, 'tinyint(1)') !== false) $inputType = "checkbox";
                                ?>
                                <?php if ($inputType === 'checkbox'): ?>
                                    <input type="<?php echo $inputType; ?>" 
                                           id="data_<?php echo htmlspecialchars($colInfo['Field']); ?>" 
                                           name="data[<?php echo htmlspecialchars($colInfo['Field']); ?>]"
                                           value="1">
                                <?php elseif ($inputType === 'number'): ?>
                                     <input type="<?php echo $inputType; ?>" 
                                           id="data_<?php echo htmlspecialchars($colInfo['Field']); ?>" 
                                           name="data[<?php echo htmlspecialchars($colInfo['Field']); ?>]"
                                           step="any" <?php // Allows decimals for DECIMAL, FLOAT etc. ?>
                                           placeholder="Enter <?php echo htmlspecialchars($colInfo['Type']); ?>">
                                <?php else: ?>
                                    <input type="<?php echo $inputType; ?>" 
                                           id="data_<?php echo htmlspecialchars($colInfo['Field']); ?>" 
                                           name="data[<?php echo htmlspecialchars($colInfo['Field']); ?>]"
                                           placeholder="Enter <?php echo htmlspecialchars($colInfo['Type']); ?>">
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <button type="submit" class="action-btn">Add Entry</button>
                </form>
            </section>
        <?php elseif (!$error_message): ?>
             <p>Table kann nicht angezeigt werden?</p>
        <?php endif; ?>
    </main>
</body>
</html>

