<?php
require_once './include/db_config.php';
require_once './include/functions.php'; // Include the new functions file

// --- Authentication & Authorization ---
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

// --- Get, Validate, and Authorize Table Name ---
if (isset($_GET['table_name']) && !empty($_GET['table_name'])) {
    $selectedTableName = $_GET['table_name'];
    $isUsersTable = ($selectedTableName === 'users');

    $hasAccess = false;
    if (strpos($selectedTableName, $userPrefix) === 0) { $hasAccess = true; }
    if ($userRole === 'admin' && $isUsersTable) { $hasAccess = true; }

    if (!$hasAccess) {
        $error_message = "Zugriff verweigert.";
        $selectedTableName = null;
    }
} else {
    $error_message = "Keine Tabelle ausgewählt.";
}

// --- Handle Delete Entry ---
if ($pdo && $selectedTableName && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_entry'])) {
    $entryId = $_POST['entry_id'] ?? null;
    if ($entryId) {
        if ($isUsersTable && $entryId == $userId) {
            $error_message = "Du kannst dich nicht selbst löschen.";
        } else {
            try {
                if ($isUsersTable) {
                    // --- THIS BLOCK IS CORRECTED ---
                    $userTablesPrefix = "u{$entryId}_";
                    $likePattern = $userTablesPrefix . '%';
                    
                    // Use pdo->quote() to safely build the query string, as placeholders are not allowed in SHOW TABLES.
                    $sqlShowTables = "SHOW TABLES LIKE " . $pdo->quote($likePattern);
                    $stmt = $pdo->query($sqlShowTables);
                    $tablesToDelete = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    // --- END OF CORRECTION ---

                    foreach ($tablesToDelete as $table) {
                        $pdo->exec("DROP TABLE `" . $table . "`");
                    }
                }
                
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
    } catch (PDOException $e) { $error_message = "Datenbankfehler: " . htmlspecialchars($e->getMessage()); }
}


// --- Fetch data for display and editing ---
if ($pdo && $selectedTableName) {
    try {
        $stmtColumns = $pdo->query("DESCRIBE `" . $selectedTableName . "`");
        $columnsInfo = $stmtColumns->fetchAll(PDO::FETCH_ASSOC);
        $stmtData = $pdo->query("SELECT * FROM `" . $selectedTableName . "`");
        $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);
        if (isset($_GET['edit_id'])) {
            $stmt = $pdo->prepare("SELECT * FROM `" . $selectedTableName . "` WHERE `id` = ?");
            $stmt->execute([$_GET['edit_id']]);
            $entryToEdit = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) { $error_message = "Fehler beim Abrufen der Tabellendaten: " . htmlspecialchars($e->getMessage()); }
}


// --- Page Setup ---
$pageTitle = "Tabelle: " . htmlspecialchars($selectedTableName ?? 'Fehler');
require_once './include/header.php';
?>

<a href="index.php" class="back-link action-btn-secondary">&laquo; Zurück zur Tabellenliste</a>

<?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
<?php if ($error_message): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>

<?php if ($isUsersTable && $userRole === 'admin'): ?>
    <div class="message info">Du bearbeitest die <code>users</code>-Tabelle. Passwörter werden automatisch verschlüsselt.</div>
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
    $formType = $entryToEdit ? 'edit' : 'add';
    ?>
    <section class="section <?php echo $formType; ?>-entry-section">
        <h2>
            <?php if($formType === 'edit'): ?>
                Eintrag #<?php echo htmlspecialchars($entryToEdit['id']); ?> bearbeiten ✏️
            <?php else: ?>
                Neuen Eintrag hinzufügen ➕
            <?php endif; ?>
        </h2>
        <form action="display_table.php?table_name=<?php echo urlencode($selectedTableName); ?>" method="post" class="styled-form">
            <input type="hidden" name="<?php echo $formType === 'edit' ? 'update_entry' : 'add_entry'; ?>" value="1">
            <?php if ($formType === 'edit'): ?>
                <input type="hidden" name="entry_id" value="<?php echo htmlspecialchars($entryToEdit['id']); ?>">
            <?php endif; ?>

            <?php
            render_form_fields($columnsInfo, $isUsersTable, $entryToEdit ?? [], $formType);
            ?>
            
            <button type="submit" class="action-btn">
                <?php echo $formType === 'edit' ? 'Eintrag aktualisieren' : 'Eintrag hinzufügen'; ?>
            </button>
            <?php if ($formType === 'edit'): ?>
                <a href="display_table.php?table_name=<?php echo urlencode($selectedTableName); ?>" class="action-btn-secondary">Abbrechen</a>
            <?php endif; ?>
        </form>
    </section>

<?php endif; ?>

<?php
require_once './include/footer.php';
?>