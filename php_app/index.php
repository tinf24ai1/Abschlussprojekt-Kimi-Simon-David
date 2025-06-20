<?php
require_once 'db_config.php'; // Includes $pdo or dies

$availableTables = [];
$message = null;
$error_message = null;
$valid_column_types = ['TEXT', 'INT', 'DATE', 'BOOLEAN'];

// --- Handle Create Table Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_table'])) {
    $newTableName = trim($_POST['table_name'] ?? '');
    $columns_input = $_POST['columns'] ?? [];

    if (!is_valid_name($newTableName)) {
        $error_message = "Ungültiger Tabellenname. Verwende nur Buchstaben, Zahlen und Unterstriche (max. 64 Zeichen).";
    } else {
        $columnDefs = ["`id` INT PRIMARY KEY AUTO_INCREMENT"];  // Always add ID column first
        $has_pk_id = true;
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
                if (strtolower($colName) !== 'id') {  // Skip if user tries to add another ID column
                    $columnDefs[] = "`" . $colName . "` " . $colType;
                }
            }
        }

        if (empty($columnDefs) && !$error_message) {
            $error_message = "Keine gültigen Spalten definiert.";
        } elseif (!empty($columnDefs)) {
            try {
                $sql = "CREATE TABLE `" . $newTableName . "` (" . implode(', ', $columnDefs) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
                $pdo->exec($sql);
                $message = "Tabelle '" . htmlspecialchars($newTableName) . "' wurde erfolgreich erstellt.";
                 if ($has_pk_id) $message .= " 'id' ist PK/AI.";
            } catch (PDOException $e) {
                $error_message = "Error creating table: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// --- Fetch Available Tables ---
try {
    $stmt = $pdo->query("SHOW TABLES");
    $availableTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $error_message = ($error_message ? $error_message . "<br>" : "") . "Error fetching tables: " . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DB Manager Basic</title>
    <link rel="stylesheet" href="style.css" id="theme-link">
    <script src="theme.js" defer></script>
</head>
<body>
    <script src="./oneko.js"></script>
    <header>
        <h1>DB Manager Basic ⚙️</h1>
        <div class="theme-selector">
            <select id="theme-select" class="theme-dropdown">
                <option value="style.css">Standard-Theme</option>
                <option value="light.css">Helles Theme</option>
                <option value="dark.css">Dunkles Theme</option>
            </select>
        </div>
    </header>

    <main class="container">
        <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>

        <div class="section tables-section">
            <h2>Verfügbare Tabellen ✨</h2>
            <?php if (!empty($availableTables)): ?>
                <ul class="table-list">
                    <?php foreach ($availableTables as $tableName): ?>
                        <li>
                            <a href="display_table.php?table_name=<?php echo urlencode($tableName); ?>" class="table-link">
                                <?php echo htmlspecialchars($tableName); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>Keine Tabellen in '<?php echo htmlspecialchars(getenv('DB_NAME')); ?>' gefunden.</p>
            <?php endif; ?>
        </div>

        <div class="section create-table-section">
            <h2>Neue Tabelle erstellen 🛠️</h2>
            <form action="index.php" method="post" class="styled-form">
                <input type="hidden" name="create_table" value="1">
                <div class="form-group">
                    <label for="table_name">Tabellenname:</label>
                    <input type="text" id="table_name" name="table_name" required pattern="[a-zA-Z0-9_]{1,64}" placeholder="z.B. meine_tabelle">
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

