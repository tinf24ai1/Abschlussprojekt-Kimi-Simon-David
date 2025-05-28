<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$dbHost = getenv('DB_HOST');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
$dbName = getenv('DB_NAME');

$pdo = null;
$availableTables = [];
$message = null;
$error_message = null;
$valid_column_types = ['VARCHAR(255)', 'INT', 'TEXT', 'DATE', 'TIMESTAMP', 'DECIMAL(10,2)', 'BOOLEAN'];

// --- Database Connection ---
if (!$dbHost || !$dbUser || !$dbPass || !$dbName) {
    $error_message = "Error: Missing database connection environment variables.";
} else {
    try {
        $dsn = "mysql:host=" . $dbHost . ";dbname=" . $dbName . ";charset=utf8mb4";
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    } catch (PDOException $e) {
        $error_message = "Database connection error: " . htmlspecialchars($e->getMessage());
    }
}

// --- Function to Validate Names ---
function is_valid_name($name) {
    return preg_match('/^[a-zA-Z0-9_]+$/', $name) && strlen($name) <= 64;
}

// --- Handle Create Table Form Submission ---
if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_table'])) {
    $newTableName = trim($_POST['table_name'] ?? '');
    $columns_input = $_POST['columns'] ?? [];

    if (!is_valid_name($newTableName)) {
        $error_message = "Invalid table name. Use only letters, numbers, and underscores (max 64 chars).";
    } else {
        $columnDefs = [];
        $has_pk_id = false;
        foreach ($columns_input as $col) {
            $colName = trim($col['name'] ?? '');
            $colType = $col['type'] ?? '';
            if ($colName !== '' && $colType !== '') {
                if (!is_valid_name($colName)) {
                    $error_message = "Invalid column name: " . htmlspecialchars($colName) . ". Use only letters, numbers, and underscores.";
                    $columnDefs = []; // Clear to prevent creation
                    break;
                }
                if (!in_array($colType, $valid_column_types)) {
                    $error_message = "Invalid column type: " . htmlspecialchars($colType) . ".";
                    $columnDefs = []; // Clear
                    break;
                }

                // Auto PRIMARY KEY AUTO_INCREMENT for 'id' INT column
                if (strtolower($colName) === 'id' && $colType === 'INT') {
                    $columnDefs[] = "`" . str_replace("`", "``", $colName) . "` INT PRIMARY KEY AUTO_INCREMENT";
                    $has_pk_id = true;
                } else {
                    $columnDefs[] = "`" . str_replace("`", "``", $colName) . "` " . $colType;
                }
            }
        }

        if (empty($columnDefs) && !$error_message) {
            $error_message = "No valid columns defined for the new table.";
        } elseif (!empty($columnDefs)) {
            try {
                $sql = "CREATE TABLE `" . str_replace("`", "``", $newTableName) . "` (" . implode(', ', $columnDefs) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
                $pdo->exec($sql);
                $message = "Table '" . htmlspecialchars($newTableName) . "' created successfully.";
                if ($has_pk_id) {
                    $message .= " Column 'id' was made PRIMARY KEY AUTO_INCREMENT.";
                }
            } catch (PDOException $e) {
                $error_message = "Error creating table: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// --- Fetch Available Tables ---
if ($pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $availableTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $error_message = "Error fetching tables: " . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DB Manager Deluxe</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>DB Manager Deluxe 🚀</h1>
    </header>

    <main class="container">
        <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>

        <div class="section tables-section">
            <h2>Available Tables ✨</h2>
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
            <?php elseif(!$error_message): ?>
                <p>No tables found in '<?php echo htmlspecialchars($dbName); ?>'. Time to create some!</p>
            <?php endif; ?>
        </div>

        <div class="section create-table-section">
            <button id="toggleCreateTableBtn" class="action-btn">Toggle Create New Table Form</button>
            <div id="createTableFormContainer" style="display: none;">
                <h2>Create New Table 🛠️</h2>
                <form action="index.php" method="post" class="styled-form">
                    <input type="hidden" name="create_table" value="1">
                    <div class="form-group">
                        <label for="table_name">Table Name:</label>
                        <input type="text" id="table_name" name="table_name" required pattern="[a-zA-Z0-9_]{1,64}" placeholder="e.g., my_awesome_table">
                    </div>
                    <hr>
                    <p><strong>Define Columns (at least one):</strong><br>
                       <small>If you name a column 'id' (case-insensitive) and set its type to 'INT', it will automatically become a Primary Key with Auto Increment.</small>
                    </p>
                    <div id="columns-container">
                        <?php for ($i = 0; $i < 3; $i++): // Start with 3 column fields ?>
                        <div class="form-group column-definition">
                            <label>Column <?php echo $i + 1; ?>:</label>
                            <input type="text" name="columns[<?php echo $i; ?>][name]" placeholder="Column Name (e.g., id, name, email)" pattern="[a-zA-Z0-9_]{1,64}">
                            <select name="columns[<?php echo $i; ?>][type]">
                                <option value="">-- Select Type --</option>
                                <?php foreach ($valid_column_types as $type): ?>
                                <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <button type="button" id="addColumnBtn" class="action-btn-secondary">Add Another Column</button>
                    <button type="submit" class="action-btn">Create Table</button>
                </form>
            </div>
        </div>
    </main>

    <script>
        document.getElementById('toggleCreateTableBtn').addEventListener('click', function() {
            var formContainer = document.getElementById('createTableFormContainer');
            if (formContainer.style.display === 'none' || formContainer.style.display === '') {
                formContainer.style.display = 'block';
                this.textContent = 'Hide Create New Table Form';
            } else {
                formContainer.style.display = 'none';
                this.textContent = 'Toggle Create New Table Form';
            }
        });

        document.getElementById('addColumnBtn').addEventListener('click', function() {
            var columnsContainer = document.getElementById('columns-container');
            var newIndex = columnsContainer.getElementsByClassName('column-definition').length;
            var newColumnDiv = document.createElement('div');
            newColumnDiv.classList.add('form-group', 'column-definition');
            
            var label = document.createElement('label');
            label.textContent = 'Column ' + (newIndex + 1) + ':';
            newColumnDiv.appendChild(label);

            var nameInput = document.createElement('input');
            nameInput.type = 'text';
            nameInput.name = 'columns[' + newIndex + '][name]';
            nameInput.placeholder = 'Column Name';
            nameInput.pattern = '[a-zA-Z0-9_]{1,64}';
            newColumnDiv.appendChild(nameInput);

            var typeSelect = document.createElement('select');
            typeSelect.name = 'columns[' + newIndex + '][type]';
            var defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = '-- Select Type --';
            typeSelect.appendChild(defaultOption);
            <?php foreach ($valid_column_types as $type): ?>
            var option = document.createElement('option');
            option.value = '<?php echo $type; ?>';
            option.textContent = '<?php echo $type; ?>';
            typeSelect.appendChild(option);
            <?php endforeach; ?>
            newColumnDiv.appendChild(typeSelect);
            
            columnsContainer.appendChild(newColumnDiv);
        });
    </script>
</body>
</html>

