<?php
require_once 'db_config.php'; // Includes $pdo or dies

$selectedTableName = null;
$columnsInfo = []; // Will store full column info
$rows = [];
$message = null;
$error_message = null;

// --- Get Selected Table Name ---
if (isset($_GET['table_name']) && !empty($_GET['table_name'])) {
    if (is_valid_name($_GET['table_name'])) { // Use helper function
         $selectedTableName = $_GET['table_name'];
    } else {
        $error_message = "Invalid table name format in URL.";
    }
} else {
    $error_message = "No table selected. <a href='index.php'>Go back</a>.";
}

// --- Validate Table Name against actual tables ---
if ($pdo && $selectedTableName) {
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $availableTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array($selectedTableName, $availableTables)) {
            $error_message = "Table '" . htmlspecialchars($selectedTableName) . "' does not exist.";
            $selectedTableName = null; // Unset if invalid
        }
    } catch (PDOException $e) {
         $error_message = "Error validating table: " . htmlspecialchars($e->getMessage());
         $selectedTableName = null;
    }
}

// --- Handle Add Entry Form Submission ---
if ($pdo && $selectedTableName && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_entry'])) {
    try {
        $stmtDesc = $pdo->query("DESCRIBE `" . $selectedTableName . "`");
        $currentColumnsMeta = $stmtDesc->fetchAll(PDO::FETCH_ASSOC);

        $insertColsSql = [];
        $insertValues = [];
        $insertPlaceholders = [];

        foreach ($currentColumnsMeta as $colMeta) {
            $colName = $colMeta['Field'];
            // Skip auto_increment columns for insert
            if (strpos(strtolower($colMeta['Extra']), 'auto_increment') !== false) {
                continue;
            }
            // Include all other columns submitted
            if (array_key_exists($colName, $_POST['data'])) {
                $insertColsSql[] = "`" . $colName . "`";
                $submittedValue = $_POST['data'][$colName];
                // Store empty strings as NULL for better DB compatibility
                $insertValues[] = ($submittedValue === '') ? null : $submittedValue;
                $insertPlaceholders[] = '?';
            }
        }

        if (!empty($insertColsSql)) {
            $sql = "INSERT INTO `" . $selectedTableName . "` (" . implode(', ', $insertColsSql) . ") VALUES (" . implode(', ', $insertPlaceholders) . ")";
            $stmtInsert = $pdo->prepare($sql);
            $stmtInsert->execute($insertValues);
            $message = "New entry added successfully.";
        } else {
            $error_message = "No data provided to insert.";
        }
    } catch (PDOException $e) {
        $error_message = "Error adding entry: " . htmlspecialchars($e->getMessage());
    }
}

// --- Fetch Table Columns and Data for Display ---
if ($pdo && $selectedTableName) {
    try {
        $stmtColumns = $pdo->query("DESCRIBE `" . $selectedTableName . "`");
        $columnsInfo = $stmtColumns->fetchAll(PDO::FETCH_ASSOC);

        $stmtData = $pdo->query("SELECT * FROM `" . $selectedTableName . "`");
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
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <?php foreach ($columnsInfo as $colInfo): ?>
                                    <th><?php echo htmlspecialchars($colInfo['Field']); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($rows)): ?>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <?php foreach ($columnsInfo as $colInfo): ?>
                                            <td><?php echo htmlspecialchars($row[$colInfo['Field']] ?? 'NULL'); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo count($columnsInfo); ?>">No data found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="section add-entry-section">
                <h2>Add New Entry ➕</h2>
                <form action="display_table.php?table_name=<?php echo urlencode($selectedTableName); ?>" method="post" class="styled-form">
                    <input type="hidden" name="add_entry" value="1">
                    <?php foreach ($columnsInfo as $colInfo): ?>
                        <?php // Skip auto_increment columns for the add form ?>
                        <?php if (strpos(strtolower($colInfo['Extra']), 'auto_increment') === false): ?>
                            <div class="form-group">
                                <label for="data_<?php echo htmlspecialchars($colInfo['Field']); ?>">
                                    <?php echo htmlspecialchars($colInfo['Field']); ?>:
                                </label>
                                <input type="text" 
                                       id="data_<?php echo htmlspecialchars($colInfo['Field']); ?>" 
                                       name="data[<?php echo htmlspecialchars($colInfo['Field']); ?>]"
                                       placeholder="Enter value (<?php echo htmlspecialchars($colInfo['Type']); ?>)">
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <button type="submit" class="action-btn">Add Entry</button>
                </form>
            </section>
        <?php elseif (!$error_message): ?>
             <p>Table data cannot be displayed.</p>
        <?php endif; ?>
    </main>
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Basic DB Solutions.</p>
    </footer>
</body>
</html>

