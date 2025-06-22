<?php
require_once './include/db_config.php';

// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$userPrefix = "u{$userId}_";

$selectedTableName = null;
$columnsInfo = [];
$rows = [];
$message = null;
$error_message = null;
$entryToEdit = null;
$isUsersTable = false;

// --- Get and Validate Table Name ---
if (isset($_GET['table_name']) && !empty($_GET['table_name'])) {
    $potentialTable = $_GET['table_name'];
    if (is_valid_name(str_replace($userPrefix, '', $potentialTable)) || $potentialTable === 'users') {
        $selectedTableName = $potentialTable;
        $isUsersTable = ($selectedTableName === 'users');

        // --- Authorization Check ---
        $hasAccess = false;
        if (strpos($selectedTableName, $userPrefix) === 0) { $hasAccess = true; }
        if ($userRole === 'admin' && $isUsersTable) { $hasAccess = true; }

        if (!$hasAccess) {
            $error_message = "Zugriff verweigert. Du hast keine Berechtigung, auf die Tabelle '" . htmlspecialchars($selectedTableName) . "' zuzugreifen.";
            $selectedTableName = null;
        }

    } else {
        $error_message = "Ungültiges Tabellenformat in der URL.";
    }
} else {
    $error_message = "Keine Tabelle ausgewählt. <a href='index.php'>Zurück</a>.";
}


// Further checks if table is valid and user has access
if ($pdo && $selectedTableName) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($selectedTableName));
        if ($stmt->rowCount() == 0) {
            $error_message = "Tabelle '" . htmlspecialchars($selectedTableName) . "' existiert nicht.";
            $selectedTableName = null;
        }
    } catch (PDOException $e) {
         $error_message = "Fehler beim Überprüfen der Tabelle: " . htmlspecialchars($e->getMessage());
         $selectedTableName = null;
    }
}


// --- Handle Delete Entry ---
if ($pdo && $selectedTableName && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_entry'])) {
    $entryId = $_POST['entry_id'] ?? null;
    if ($entryId) {
        if ($isUsersTable && $entryId == $userId) {
            $error_message = "Du kannst dich nicht selbst löschen.";
        } else {
            try {
                // --- NEW: CASCADING DELETE LOGIC ---
                // If we are deleting from the 'users' table, first delete that user's own tables.
                if ($isUsersTable) {
                    $userTablesPrefix = "u{$entryId}_";
                    // Find all tables belonging to the user being deleted
                    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
                    $stmt->execute([$userTablesPrefix . '%']);
                    $tablesToDelete = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    // Drop each of those tables
                    foreach ($tablesToDelete as $table) {
                        $pdo->exec("DROP TABLE `" . $table . "`");
                    }
                }
                // --- END OF NEW LOGIC ---

                // Now, delete the entry itself (either the user or a regular entry)
                $sql = "DELETE FROM `" . $selectedTableName . "` WHERE `id` = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$entryId]);
                $message = "Eintrag und alle zugehörigen Daten erfolgreich gelöscht.";

            } catch (PDOException $e) {
                $error_message = "Fehler beim Löschen des Eintrags: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// --- Shared logic to build SQL parts from POST data ---
function buildSqlParts($pdo, $tableName, $postData, $isUpdate = false) {
    $stmtDesc = $pdo->query("DESCRIBE `" . $tableName . "`");
    $currentColumnsMeta = $stmtDesc->fetchAll(PDO::FETCH_ASSOC);

    $sqlParts = ['columns' => [], 'values' => [], 'placeholders' => []];
    
    foreach ($currentColumnsMeta as $colMeta) {
        $colName = $colMeta['Field'];
        if (strtolower($colName) === 'id' || ($isUpdate && !array_key_exists($colName, $postData))) {
            continue;
        }

        if (array_key_exists($colName, $postData)) {
            $submittedValue = $postData[$colName];
            $finalValue = ($submittedValue === '') ? null : $submittedValue;

            if ($tableName === 'users' && $colName === 'password') {
                if (empty($finalValue) && $isUpdate) { continue; }
                $finalValue = password_hash($finalValue, PASSWORD_DEFAULT);
            }
            
            $sqlParts['columns'][] = "`" . $colName . "`";
            $sqlParts['values'][] = $finalValue;
            $sqlParts['placeholders'][] = '?';
        }
    }
    return $sqlParts;
}


// --- Handle Update or Add Entry ---
if ($pdo && $selectedTableName && $_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['update_entry']) || isset($_POST['add_entry']))) {
    $isUpdate = isset($_POST['update_entry']);
    $postData = $_POST['data'] ?? [];
    
    try {
        $sqlParts = buildSqlParts($pdo, $selectedTableName, $postData, $isUpdate);

        if ($isUpdate) {
            $entryId = $_POST['entry_id'] ?? null;
            if ($entryId && !empty($sqlParts['columns'])) {
                $updateSetSql = [];
                foreach($sqlParts['columns'] as $col) { $updateSetSql[] = $col . ' = ?'; }

                $sqlParts['values'][] = $entryId;
                $sql = "UPDATE `" . $selectedTableName . "` SET " . implode(', ', $updateSetSql) . " WHERE `id` = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($sqlParts['values']);
                $message = "Eintrag erfolgreich aktualisiert.";
            }
        } else {
            if (!empty($sqlParts['columns'])) {
                $sql = "INSERT INTO `" . $selectedTableName . "` (" . implode(', ', $sqlParts['columns']) . ") VALUES (" . implode(', ', $sqlParts['placeholders']) . ")";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($sqlParts['values']);
                $message = "Neuer Eintrag erfolgreich hinzugefügt.";
            }
        }
    } catch (PDOException $e) {
        $error_message = "Datenbankfehler: " . htmlspecialchars($e->getMessage());
    }
}


// --- Fetch data for the entry to be edited ---
if ($pdo && $selectedTableName && isset($_GET['edit_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM `" . $selectedTableName . "` WHERE `id` = ?");
        $stmt->execute([$_GET['edit_id']]);
        $entryToEdit = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "Fehler beim Abrufen des Eintrags zum Bearbeiten: " . htmlspecialchars($e->getMessage());
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
    <link rel="stylesheet" href="css/style.css" id="theme-link">
    <script src="js/theme.js" defer></script>
    <style>
        input[type="date"] { padding: 10px; border-radius: 6px; font-family: inherit; font-size: inherit; cursor: pointer; }
    </style>
</head>
<body>
    <script src="js/oneko/oneko.js"></script>
    <header>
        <h1>Tabelle anzeigen: <?php echo htmlspecialchars($selectedTableName ?? 'Fehler'); ?> 🧐</h1>
         <div class="header-actions">
            <div class="theme-selector">
                <select id="theme-select" class="theme-dropdown">
                    <option value="css/style.css">Standard-Theme</option>
                    <option value="css/light.css">Helles Theme</option>
                    <option value="css/dark.css">Dunkles Theme</option>
                </select>
            </div>
            <a href="logout.php" class="action-btn-secondary">Abmelden</a>
        </div>
    </header>
    
    <main class="container">
        <a href="index.php" class="back-link action-btn-secondary">&laquo; Zurück zur Tabellenliste</a>

        <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>

        <?php if ($isUsersTable && $userRole === 'admin'): ?>
            <div class="message info">Du bearbeitest die <code>users</code>-Tabelle. Passwörter werden automatisch verschlüsselt. Gib ein neues Passwort ein, um es zu ändern, oder lasse es leer, um es beizubehalten.</div>
        <?php endif; ?>

        <?php if ($selectedTableName && !empty($columnsInfo)): ?>
            <section class="section data-table-section">
                <h2>Tabelleninhalt 📜</h2>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <?php foreach ($columnsInfo as $colInfo): ?>
                                    <?php if ($isUsersTable && $colInfo['Field'] === 'password') continue; ?>
                                    <th><?php echo htmlspecialchars($colInfo['Field']); ?></th>
                                <?php endforeach; ?>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($rows)): ?>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <?php foreach ($columnsInfo as $colInfo): ?>
                                            <?php if ($isUsersTable && $colInfo['Field'] === 'password') continue; ?>
                                            <td><?php echo htmlspecialchars($row[$colInfo['Field']] ?? 'NULL'); ?></td>
                                        <?php endforeach; ?>
                                        <td>
                                            <a href="display_table.php?table_name=<?php echo urlencode($selectedTableName); ?>&edit_id=<?php echo htmlspecialchars($row['id']); ?>" class="action-btn-secondary">Bearbeiten</a>
                                            <form action="display_table.php?table_name=<?php echo urlencode($selectedTableName); ?>" method="post" style="display:inline;" onsubmit="return confirm('Diesen Eintrag wirklich löschen? ACHTUNG: Alle Tabellen dieses Benutzers werden ebenfalls gelöscht!');">
                                                <input type="hidden" name="delete_entry" value="1">
                                                <input type="hidden" name="entry_id" value="<?php echo htmlspecialchars($row['id']); ?>">
                                                <button type="submit" class="action-btn-danger">Löschen</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="<?php echo count($columnsInfo) + 1; ?>">Keine Daten gefunden.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            
            <?php
            $showFormFor = $entryToEdit ? 'edit' : 'add';
            if ($showFormFor === 'edit' || $showFormFor === 'add') {
                $formAction = "display_table.php?table_name=" . urlencode($selectedTableName);
                $formSubmitText = ($showFormFor === 'edit') ? 'Eintrag aktualisieren' : 'Eintrag hinzufügen';
                $entryData = ($showFormFor === 'edit') ? $entryToEdit : [];
            ?>
            <section class="section <?php echo $showFormFor; ?>-entry-section">
                <h2>
                    <?php if($showFormFor === 'edit'): ?>
                        Eintrag #<?php echo htmlspecialchars($entryData['id']); ?> bearbeiten ✏️
                    <?php else: ?>
                        Neuen Eintrag hinzufügen ➕
                    <?php endif; ?>
                </h2>
                <form action="<?php echo $formAction; ?>" method="post" class="styled-form">
                    <input type="hidden" name="<?php echo $showFormFor === 'edit' ? 'update_entry' : 'add_entry'; ?>" value="1">
                    <?php if ($showFormFor === 'edit'): ?>
                        <input type="hidden" name="entry_id" value="<?php echo htmlspecialchars($entryData['id']); ?>">
                    <?php endif; ?>

                    <?php foreach ($columnsInfo as $colInfo):
                        $colName = $colInfo['Field'];
                        if (strtolower($colName) === 'id' || strpos(strtolower($colInfo['Extra']), 'auto_increment') !== false && $showFormFor === 'add') continue;
                        if ($colName === 'created_at' && $showFormFor === 'add') continue;

                        if ($isUsersTable && $colName === 'password' && $showFormFor === 'edit') {
                            $inputType = 'password';
                            $placeholder = "Leer lassen, um nicht zu ändern";
                            $value = '';
                        } else {
                            $value = htmlspecialchars($entryData[$colName] ?? '');
                            $placeholder = "Gib " . htmlspecialchars($colInfo['Type']) . " ein";
                            $colTypeUpper = strtoupper($colInfo['Type']);
                            if ($colTypeUpper === 'DATE') $inputType = 'date';
                            elseif ($colTypeUpper === 'BOOLEAN' || $colTypeUpper === 'TINYINT(1)') $inputType = 'checkbox';
                            elseif (strpos($colTypeUpper, 'INT') !== false) $inputType = 'number';
                            elseif ($isUsersTable && $colName === 'role') $inputType = 'select';
                            else $inputType = 'text';
                        }
                    ?>
                        <div class="form-group">
                            <label for="data_<?php echo $colName; ?>"><?php echo htmlspecialchars($colName); ?>:</label>
                            <?php if ($inputType === 'select' && $colName === 'role'): ?>
                                <select name="data[role]" id="data_role">
                                    <option value="user" <?php if($value === 'user') echo 'selected'; ?>>user</option>
                                    <option value="admin" <?php if($value === 'admin') echo 'selected'; ?>>admin</option>
                                </select>
                            <?php elseif ($inputType === 'checkbox'): ?>
                                <input type="hidden" name="data[<?php echo $colName; ?>]" value="0">
                                <input type="checkbox" id="data_<?php echo $colName; ?>" name="data[<?php echo $colName; ?>]" value="1" <?php if (!empty($value)) echo 'checked'; ?>>
                            <?php else: ?>
                                <input type="<?php echo $inputType; ?>" id="data_<?php echo $colName; ?>" name="data[<?php echo htmlspecialchars($colName); ?>]" value="<?php echo $value; ?>" placeholder="<?php echo $placeholder; ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <button type="submit" class="action-btn"><?php echo $formSubmitText; ?></button>
                    <?php if ($showFormFor === 'edit'): ?>
                        <a href="display_table.php?table_name=<?php echo urlencode($selectedTableName); ?>" class="action-btn-secondary">Abbrechen</a>
                    <?php endif; ?>
                </form>
            </section>
            <?php } ?>
        <?php elseif (!$error_message): ?>
             <p>Tabellendaten können nicht angezeigt werden.</p>
        <?php endif; ?>
    </main>
</body>
</html>