<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

error_log("--- generate_module.php execution started (v5 - auto DB create, simplified options/FK SQL) ---");
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);

// --- Configuration ---
$configDir = __DIR__ . '/config/';
error_log("Config directory: " . $configDir);

// Attempt to include database connection for DDL execution
// Suppress errors if it's already included or fails, handle $pdo check later
@include_once __DIR__ . '/lib/database.php'; // $pdo should be defined here

// --- Helper Functions ---
function sanitize_filename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '', $filename);
    $filename = preg_replace('/\.{2,}/', '', $filename);
    return strtolower($filename);
}

function generate_sql_data_type($fieldType) {
    switch ($fieldType) {
        case 'textarea': return 'TEXT';
        case 'number': return 'INT';
        case 'currency': return 'DECIMAL(10, 2)';
        case 'date': return 'DATE';
        case 'datetime': return 'DATETIME';
        case 'select': return 'VARCHAR(255)';
        case 'foreign_key': return 'INT'; // Column type for storing the foreign key ID
        case 'text':
        default:
            return 'VARCHAR(255)';
    }
}

// --- Script Logic --- KI-Generiert, Finger Weg!
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST request received. Processing form data...");
    error_log("Raw POST data: " . print_r($_POST, true));

    // --- Basic Validation & Data Extraction ---
    $moduleNameDisplay = trim($_POST['module_name_display'] ?? '');
    $moduleIdentifier = sanitize_filename(trim($_POST['module_identifier'] ?? ''));
    $tableName = sanitize_filename(trim($_POST['table_name'] ?? ''));
    $primaryKey = sanitize_filename(trim($_POST['primary_key'] ?? 'id'));
    $fieldsData = $_POST['fields'] ?? [];

    $defaultSortColumn = sanitize_filename(trim($_POST['default_sort_column'] ?? ''));
    $defaultSortDirection = in_array($_POST['default_sort_direction'] ?? 'DESC', ['ASC', 'DESC']) ? $_POST['default_sort_direction'] : 'DESC';
    $recordsPerPage = filter_var($_POST['records_per_page'] ?? 10, FILTER_VALIDATE_INT, ['options' => ['default' => 10, 'min_range' => 1]]);
    $autoCreateTable = isset($_POST['auto_create_table']) && $_POST['auto_create_table'] == '1';


    // --- More Validation ---
    $errors = [];
    if (empty($moduleNameDisplay)) $errors[] = "Module Display Name is required.";
    if (empty($moduleIdentifier)) $errors[] = "Module Identifier is required.";
    if (!preg_match('/^[a-z0-9_]+$/', $moduleIdentifier)) $errors[] = "Module Identifier can only contain lowercase letters, numbers, and underscores.";
    if (empty($tableName)) $errors[] = "Database Table Name is required.";
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) $errors[] = "Database Table Name can only contain letters, numbers, and underscores.";
    if (empty($primaryKey)) $errors[] = "Primary Key Field Name is required.";
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $primaryKey)) $errors[] = "Primary Key can only contain letters, numbers, and underscores.";

    if (empty($fieldsData)) {
        $errors[] = "You must define at least one field for the module.";
    } else {
        foreach ($fieldsData as $index => $field) {
            if (empty($field['name'])) $errors[] = "Field #".($index+1).": Name is required.";
            elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $field['name'])) $errors[] = "Field #".($index+1).": Name ('".htmlspecialchars($field['name'])."') can only contain letters, numbers, and underscores.";
            if (empty($field['label'])) $errors[] = "Field #".($index+1).": Label is required.";
            if (empty($field['type'])) $errors[] = "Field #".($index+1).": Type is required.";
            
            if ($field['type'] === 'select' && empty(trim($field['select_options_string'] ?? ''))) {
                 $errors[] = "Field #".($index+1)." ('".htmlspecialchars($field['label'])."'): Options string is required for select type.";
            }
            if ($field['type'] === 'foreign_key') {
                if (empty($field['fk_lookup_table'])) $errors[] = "Field #".($index+1)." ('".htmlspecialchars($field['label'])."'): Lookup Table Name is required for foreign key.";
                if (empty($field['fk_lookup_id_column'])) $errors[] = "Field #".($index+1)." ('".htmlspecialchars($field['label'])."'): Lookup ID Column is required for foreign key.";
                if (empty($field['fk_lookup_value_column'])) $errors[] = "Field #".($index+1)." ('".htmlspecialchars($field['label'])."'): Lookup Value Column is required for foreign key.";
            }
        }
    }
    if (!empty($defaultSortColumn) && !in_array($defaultSortColumn, array_column($fieldsData, 'name'))) {
        if ($defaultSortColumn !== $primaryKey && $defaultSortColumn !== 'created_at' && $defaultSortColumn !== 'updated_at') {
            $errors[] = "Default Sort Column ('".htmlspecialchars($defaultSortColumn)."') must be one of the defined field names, the primary key, 'created_at', or 'updated_at'.";
        }
    }


    if (!empty($errors)) {
        error_log("Validation errors: " . print_r($errors, true));
        $_SESSION['generate_module_errors'] = $errors;
        $_SESSION['generate_module_old_input'] = $_POST;
        error_log("Redirecting to create_module_form.php due to validation errors.");
        header('Location: create_module_form.php');
        exit;
    }

    error_log("Validation passed. Constructing config array...");
    // --- Construct Config Array ---
    $newConfig = [
        'module_title' => $moduleNameDisplay,
        'module_title_singular' => rtrim($moduleNameDisplay, 's'),
        'table_name' => $tableName,
        'primary_key' => $primaryKey,
        'fields' => [],
        'list_actions' => ['create', 'edit', 'delete'],
        'default_sort_column' => !empty($defaultSortColumn) ? $defaultSortColumn : $primaryKey,
        'default_sort_direction' => $defaultSortDirection,
        'records_per_page' => (int)$recordsPerPage,
    ];
    $newConfig['fields'][$primaryKey] = [
        'label' => 'ID', 'type' => 'id', 'list_display' => false, 'form_display' => false,
    ];
    foreach ($fieldsData as $field) {
        $fieldName = sanitize_filename($field['name']);
        $fieldConfig = [
            'label' => trim($field['label']), 'type' => trim($field['type']),
            'list_display' => isset($field['list_display']), 'form_display' => isset($field['form_display']),
            'required' => isset($field['required']), 'searchable' => isset($field['searchable']),
        ];
        if ($fieldConfig['type'] === 'select' && !empty($field['select_options_string'])) {
            $fieldConfig['options'] = [];
            $optionsPairs = explode(',', trim($field['select_options_string']));
            foreach ($optionsPairs as $pair) {
                $parts = explode(':', trim($pair), 2);
                $key = trim($parts[0]);
                $value = isset($parts[1]) ? trim($parts[1]) : $key;
                if (!empty($key)) { $fieldConfig['options'][$key] = $value; }
            }
            if (!empty($field['select_placeholder'])) { $fieldConfig['placeholder'] = trim($field['select_placeholder']);}
        } elseif ($fieldConfig['type'] === 'foreign_key') {
            $fieldConfig['lookup_table'] = sanitize_filename(trim($field['fk_lookup_table'] ?? ''));
            $fieldConfig['lookup_id_column'] = sanitize_filename(trim($field['fk_lookup_id_column'] ?? ''));
            $fieldConfig['lookup_value_column'] = sanitize_filename(trim($field['fk_lookup_value_column'] ?? ''));
            if (!empty($field['fk_placeholder'])) { $fieldConfig['placeholder'] = trim($field['fk_placeholder']);}
        }
        $newConfig['fields'][$fieldName] = $fieldConfig;
    }

    // --- Generate PHP Config File Content ---
    $phpConfigFileContent = "<?php\n\nreturn " . var_export($newConfig, true) . ";\n";
    $configFilePath = $configDir . 'config_' . $moduleIdentifier . '.php';
    error_log("Generated PHP Config Content:\n" . $phpConfigFileContent);
    error_log("Target config file path: " . $configFilePath);

    // --- Generate SQL CREATE TABLE Statement ---
    $sqlCreateTable = "CREATE TABLE IF NOT EXISTS `{$tableName}` (\n";
    $sqlCreateTable .= "  `{$primaryKey}` INT AUTO_INCREMENT PRIMARY KEY,\n";
    foreach ($newConfig['fields'] as $fieldName => $fieldConf) {
        if ($fieldName === $primaryKey) continue;
        $sqlDataType = generate_sql_data_type($fieldConf['type']);
        $sqlNull = empty($fieldConf['required']) ? 'NULL' : 'NOT NULL';
        $sqlCreateTable .= "  `{$fieldName}` {$sqlDataType} {$sqlNull},\n";
    }
    $sqlCreateTable .= "  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
    $sqlCreateTable .= "  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n";
    $sqlCreateTable .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    error_log("Generated SQL:\n" . $sqlCreateTable);
    $_SESSION['generated_sql'] = $sqlCreateTable; // Store for display regardless of execution

    // --- Save Config File ---
    $fileWritten = false;
    if (!is_dir($configDir)) {
        $_SESSION['generate_module_errors'] = ["Error: Configuration directory '{$configDir}' does not exist."];
    } elseif (!is_writable($configDir)) {
        $_SESSION['generate_module_errors'] = ["Error: Configuration directory '{$configDir}' is not writable."];
    } else {
        if (file_put_contents($configFilePath, $phpConfigFileContent) !== false) {
            $fileWritten = true;
        } else {
            $_SESSION['generate_module_errors'] = ["Error: Could not write config file to '{$configFilePath}'."];
        }
    }

    if (!$fileWritten) {
        if (empty($_SESSION['generate_module_errors'])) {
             $_SESSION['generate_module_errors'] = ["Failed to save configuration file."];
        }
        $_SESSION['generate_module_old_input'] = $_POST;
        $_SESSION['generated_php_config'] = $phpConfigFileContent;
        $_SESSION['generated_sql_on_error'] = $sqlCreateTable; // Use a different key for SQL if write failed
        error_log("Config file write failed. Errors: " . print_r($_SESSION['generate_module_errors'], true));
        header('Location: create_module_form.php');
        exit;
    }

    // --- Attempt to Auto-Create Database Table if Checked and $pdo is available ---
    if ($autoCreateTable) {
        if (!isset($pdo) || !$pdo) {
            $_SESSION['sql_execution_error'] = "Database connection (PDO) is not available. Table not created automatically.";
            error_log("Auto create table checked, but PDO object is not available.");
        } else {
            error_log("Auto create table checked. Attempting to execute SQL: " . $sqlCreateTable);
            try {
                // For CREATE TABLE, a transaction isn't strictly necessary as it's usually a single atomic DDL.
                // However, if we were doing multiple related DDLs, it would be.
                $pdo->exec($sqlCreateTable);
                $_SESSION['sql_execution_status'] = 'success';
                error_log("SQL statement executed successfully.");
            } catch (PDOException $e) {
                $sqlError = "Database error during automatic table creation: " . $e->getMessage();
                $_SESSION['sql_execution_error'] = $sqlError;
                error_log($sqlError);
            }
        }
    } else {
        $_SESSION['sql_execution_status'] = 'skipped';
        error_log("Auto create table NOT checked. Skipping SQL execution.");
    }

    $_SESSION['generate_module_success'] = "Module '{$moduleNameDisplay}' configuration file generated successfully at:<br><code>{$configFilePath}</code>";
    error_log("Process completed. Redirecting to create_module_form.php. Session data: " . print_r($_SESSION, true));
    header('Location: create_module_form.php');
    exit;

} else {
    error_log("Accessed generate_module.php via GET. Redirecting to create_module_form.php.");
    header('Location: create_module_form.php');
    exit;
}
?>

