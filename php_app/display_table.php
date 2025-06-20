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
        $error_message = "Ungültiges Tabellenformat in der URL.";
    }
} else {
    $error_message = "Keine Tabelle ausgewählt. <a href='index.php'>Zurück</a>.";
}

// --- Validate Table Name against actual tables ---
if ($pdo && $selectedTableName) {
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $availableTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array($selectedTableName, $availableTables)) {
            $error_message = "Tabelle '" . htmlspecialchars($selectedTableName) . "' existiert nicht.";
            $selectedTableName = null; // Unset if invalid
        }
    } catch (PDOException $e) {
         $error_message = "Fehler beim Überprüfen der Tabelle: " . htmlspecialchars($e->getMessage());
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
            $message = "Neuer Eintrag erfolgreich hinzugefügt.";
        } else {
            $error_message = "Keine Daten zum Einfügen vorhanden.";
        }
    } catch (PDOException $e) {
        $error_message = "Fehler beim Hinzufügen des Eintrags: " . htmlspecialchars($e->getMessage());
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
        $error_message = "Fehler beim Abrufen der Tabellendaten: " . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tabelle anzeigen: <?php echo htmlspecialchars($selectedTableName ?? 'Fehler'); ?></title>
    <link rel="stylesheet" href="style.css" id="theme-link">
    <script src="theme.js" defer></script>
    <script src="table-features.js" defer></script>
    <style>
        /* Custom styles for date inputs */
        input[type="date"] {
            padding: 10px;
            border-radius: 6px;
            font-family: inherit;
            font-size: inherit;
            cursor: pointer;
        }
        
        /* Dark theme support for date inputs */
        @media (prefers-color-scheme: dark) {
            input[type="date"] {
                background-color: #252525;
                border: 1px solid #374151;
                color: #ffffff;
            }
            input[type="date"]::-webkit-calendar-picker-indicator {
                filter: invert(1);
            }
        }
    </style>
</head>
<body>
    <script src="./oneko.js"></script>
    <header>
        <h1>Tabelle anzeigen: <?php echo htmlspecialchars($selectedTableName ?? 'Fehler'); ?> 🧐</h1>
        <div class="theme-selector">
            <select id="theme-select" class="theme-dropdown">
                <option value="style.css">Standard-Theme</option>
                <option value="light.css">Helles Theme</option>
                <option value="dark.css">Dunkles Theme</option>
            </select>
        </div>
    </header>
    
    <main class="container">
        <a href="index.php" class="back-link action-btn-secondary">&laquo; Zurück zur Tabellenliste</a>

        <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>

        <?php if ($selectedTableName && !empty($columnsInfo)): ?>
            <section class="section data-table-section">
                <h2>Tabelleninhalt 📜</h2>
                <div class="table-responsive">
                    <table class="data-table">
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
                                    <td colspan="<?php echo count($columnsInfo); ?>">Keine Daten gefunden.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="section add-entry-section">
                <h2>Neuen Eintrag hinzufügen ➕</h2>
                <form action="display_table.php?table_name=<?php echo urlencode($selectedTableName); ?>" method="post" class="styled-form">
                    <input type="hidden" name="add_entry" value="1">
                    <?php foreach ($columnsInfo as $colInfo): ?>
                        <?php // Skip auto_increment columns for the add form ?>
                        <?php if (strpos(strtolower($colInfo['Extra']), 'auto_increment') === false): ?>
                            <div class="form-group">
                                <label for="data_<?php echo htmlspecialchars($colInfo['Field']); ?>">
                                    <?php echo htmlspecialchars($colInfo['Field']); ?>:
                                </label>
                                <?php
                                    $inputType = 'text';
                                    $inputAttrs = '';
                                    
                                    // Determine input type based on column type
                                    if (strtoupper($colInfo['Type']) === 'DATE') {
                                        $inputType = 'date';
                                    } elseif (strtoupper($colInfo['Type']) === 'BOOLEAN' || strtoupper($colInfo['Type']) === 'TINYINT(1)') {
                                        $inputType = 'checkbox';
                                        $inputAttrs = 'value="1"';
                                    } elseif (strpos(strtoupper($colInfo['Type']), 'INT') !== false) {
                                        $inputType = 'number';
                                        $inputAttrs = 'step="1"';
                                    }
                                ?>
                                <input type="<?php echo $inputType; ?>" 
                                       id="data_<?php echo htmlspecialchars($colInfo['Field']); ?>" 
                                       name="data[<?php echo htmlspecialchars($colInfo['Field']); ?>]"
                                       <?php echo $inputAttrs; ?>
                                       <?php if ($inputType !== 'checkbox'): ?>
                                       placeholder="Gib <?php echo htmlspecialchars($colInfo['Type']); ?> ein"
                                       <?php endif; ?>>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <button type="submit" class="action-btn">Eintrag hinzufügen</button>
                </form>
            </section>
        <?php elseif (!$error_message): ?>
             <p>Tabellendaten können nicht angezeigt werden.</p>
        <?php endif; ?>
    </main>
</body>
</html>

