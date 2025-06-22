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

$availableTables = [];
$message = null;
$error_message = null;
$valid_column_types = ['TEXT', 'INT', 'DATE', 'BOOLEAN'];

// --- Handle Delete Table Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_table'])) {
    $tableToDelete = trim($_POST['table_to_delete'] ?? '');
    $canDelete = false;

    // Security check: A user (including admin) can only delete tables that have their prefix.
    if (strpos($tableToDelete, $userPrefix) === 0) {
        $canDelete = true;
    }

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
    
    // Prefix table name with user ID
    $prefixedTableName = $userPrefix . $newTableName;

    if (!is_valid_name($newTableName)) {
        $error_message = "Ungültiger Tabellenname. Verwende nur Buchstaben, Zahlen und Unterstriche (max. 64 Zeichen).";
    } else {
        $columnDefs = ["`id` INT PRIMARY KEY AUTO_INCREMENT"];
        foreach ($columns_input as $col) {
            $colName = trim($col['name'] ?? '');
            $colType = $col['type'] ?? '';
            if ($colName !== '' && $colType !== '') {
                if (!is_valid_name($colName)) {
                    $error_message = "Ungültiger Spaltenname: " . htmlspecialchars($colName) . ".";
                    $columnDefs = []; break;
                }
                if (!in_array($colType, $valid_column_types)) {
                    $error_message = "Ungültiger Spaltentyp: " . htmlspecialchars($colType) . ".";
                    $columnDefs = []; break;
                }
                 if (strtolower($colName) !== 'id') {
                    $columnDefs[] = "`" . $colName . "` " . $colType;
                }
            }
        }

        if (count($columnDefs) <= 1 && !$error_message) {
            $error_message = "Definiere mindestens eine Spalte.";
        } elseif (!empty($columnDefs)) {
            try {
                $sql = "CREATE TABLE `" . $prefixedTableName . "` (" . implode(', ', $columnDefs) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
                $pdo->exec($sql);
                $message = "Tabelle '" . htmlspecialchars($prefixedTableName) . "' wurde erfolgreich erstellt.";
            } catch (PDOException $e) {
                $error_message = "Fehler beim Erstellen der Tabelle: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// --- Fetch Available Tables ---
try {
    $stmt = $pdo->query("SHOW TABLES");
    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $availableTables = []; // Start with an empty list

    // New Logic: Iterate through all tables and decide which ones to show.
    foreach ($allTables as $tableName) {
        // Rule 1: All users (including admin) see their own prefixed tables.
        if (strpos($tableName, $userPrefix) === 0) {
            $availableTables[] = $tableName;
        }
        // Rule 2: The admin ALSO sees the 'users' table for management.
        if ($userRole === 'admin' && $tableName === 'users') {
            $availableTables[] = $tableName;
        }
    }
    // Ensure the 'users' table isn't listed twice and sort the list for consistency.
    $availableTables = array_unique($availableTables);
    sort($availableTables);

} catch (PDOException $e) {
    $error_message = "Fehler beim Abrufen der Tabellen: " . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DB Manager Basic</title>
    <link rel="stylesheet" href="css/style.css" id="theme-link">
    <script src="js/theme.js" defer></script>
</head>
<body>
    <script src="js/oneko/oneko.js"></script>
    <header>
        <h1>DB Manager ⚙️ <span style="font-size: 0.6em; color: #ccc;">(Angemeldet als: <?php echo htmlspecialchars($_SESSION['username']); ?>)</span></h1>
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
                            <?php if ($tableName !== 'users'): // Prevent deleting the users table from UI ?>
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
    </main>
</body>
</html>