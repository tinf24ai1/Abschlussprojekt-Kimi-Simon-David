<?php
require_once './include/db_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$userPrefix = "u{$userId}_";

$availableTables = [];
$message = null;
$error_message = null;
$valid_column_types = ['TEXT', 'INT', 'DATE', 'BOOLEAN'];

// --- Handle Delete Table Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_table'])) {
    $tableToDelete = trim($_POST['table_to_delete'] ?? '');
    $canDelete = false;
    if (strpos($tableToDelete, $userPrefix) === 0) { $canDelete = true; }

    if ($canDelete) {
        try {
            $pdo->exec("DROP TABLE `" . $tableToDelete . "`");
            $message = "Tabelle '" . htmlspecialchars($tableToDelete) . "' wurde erfolgreich gelöscht.";
        } catch (PDOException $e) {
            $error_message = "Fehler beim Löschen der Tabelle: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error_message = "Keine Berechtigung zum Löschen dieser Tabelle.";
    }
}

// --- Handle Create Table Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_table'])) {
    $newTableName = trim($_POST['table_name'] ?? '');
    $columns_input = $_POST['columns'] ?? [];
    $prefixedTableName = $userPrefix . $newTableName;

    if (!is_valid_name($newTableName)) {
        $error_message = "Ungültiger Tabellenname.";
    } else {
        $columnDefs = ["`id` INT PRIMARY KEY AUTO_INCREMENT"];
        foreach ($columns_input as $col) {
            $colName = trim($col['name'] ?? '');
            $colType = $col['type'] ?? '';
            if ($colName !== '' && $colType !== '' && strtolower($colName) !== 'id' && in_array($colType, $valid_column_types) && is_valid_name($colName)) {
                $columnDefs[] = "`" . $colName . "` " . $colType;
            }
        }

        if (count($columnDefs) > 1) {
            try {
                $sql = "CREATE TABLE `" . $prefixedTableName . "` (" . implode(', ', $columnDefs) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
                $pdo->exec($sql);
                $message = "Tabelle '" . htmlspecialchars($prefixedTableName) . "' wurde erfolgreich erstellt.";
            } catch (PDOException $e) { $error_message = "Fehler beim Erstellen der Tabelle: " . htmlspecialchars($e->getMessage()); }
        } else { $error_message = "Definiere mindestens eine gültige Spalte."; }
    }
}

// --- Fetch Available Tables ---
try {
    $stmt = $pdo->query("SHOW TABLES");
    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $availableTables = [];
    foreach ($allTables as $tableName) {
        if (strpos($tableName, $userPrefix) === 0) { $availableTables[] = $tableName; }
        if ($userRole === 'admin' && $tableName === 'users') { $availableTables[] = $tableName; }
    }
    $availableTables = array_unique($availableTables);
    sort($availableTables);
} catch (PDOException $e) {
    $error_message = "Fehler beim Abrufen der Tabellen: " . htmlspecialchars($e->getMessage());
}

// --- Page Setup ---
$pageTitle = "DB Manager";
require_once './include/header.php';
?>

<?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
<?php if ($error_message): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>

<div class="section tables-section">
    <h2>Deine verfügbaren Tabellen ✨</h2>
    <?php if ($userRole === 'admin'): ?>
        <p>Als Admin kannst du zusätzlich die <code>users</code>-Tabelle zur Benutzerverwaltung sehen.</p>
    <?php endif; ?>
    
    <?php if (!empty($availableTables)): ?>
        <ul class="table-list">
            <?php foreach ($availableTables as $tableName): ?>
                <li>
                    <a href="display_table.php?table_name=<?php echo urlencode($tableName); ?>" class="table-link">
                        <?php echo htmlspecialchars($tableName); ?>
                        <?php if ($tableName === 'users' && $userRole === 'admin') echo " (Benutzerverwaltung)"; ?>
                    </a>
                    <?php if ($tableName !== 'users'): ?>
                    <form action="index.php" method="post" onsubmit="return confirm('Möchten Sie diese Tabelle wirklich löschen?');" style="display: inline; margin-left: 10px;">
                        <input type="hidden" name="delete_table" value="1">
                        <input type="hidden" name="table_to_delete" value="<?php echo htmlspecialchars($tableName); ?>">
                        <button type="submit" class="action-btn-danger">Löschen</button>
                    </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Keine Tabellen für dich gefunden. Erstelle eine neue!</p>
    <?php endif; ?>
</div>

<div class="section create-table-section">
    <h2>Neue Tabelle erstellen 🛠️</h2>
     <form action="index.php" method="post" class="styled-form">
        <input type="hidden" name="create_table" value="1">
        <div class="form-group">
            <label for="table_name">Tabellenname:</label>
            <div class="input-with-prefix">
                <span><?php echo $userPrefix; ?></span>
                <input type="text" id="table_name" name="table_name" required pattern="[a-zA-Z0-9_]{1,64}" placeholder="meine_tabelle">
            </div>
        </div>
        <hr>
        <p><strong>Spalten definieren (bis zu 5):</strong></p>
        <?php for ($i = 0; $i < 5; $i++): ?>
        <div class="form-group column-definition">
            <label>Spalte <?php echo $i + 1; ?>:</label>
            <input type="text" name="columns[<?php echo $i; ?>][name]" placeholder="Spaltenname" pattern="[a-zA-Z0-9_]{1,64}">
            <select name="columns[<?php echo $i; ?>][type]">
                <option value="">-- Typ auswählen --</option>
                <?php foreach ($valid_column_types as $type): ?>
                <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endfor; ?>
        <button type="submit" class="action-btn">Tabelle erstellen</button>
    </form>
</div>

<?php
require_once './include/footer.php';
?>