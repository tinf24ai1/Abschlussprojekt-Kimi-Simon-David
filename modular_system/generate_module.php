<?php
// --- DEBUGGING: Force display of errors ---
ini_set('display_errors', 1);
ini_set('log_errors', 1); // Ensure errors are also logged
error_reporting(E_ALL);

// --- Start Session ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

error_log("--- generate_module.php execution started (v2 with auto DB create) ---");
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);

// --- Configuration ---
$configDir = __DIR__ . '/config/';
error_log("Config directory: " . $configDir);

// Include database connection for potential DDL execution
// Suppress errors if it's already included or fails, handle $pdo check later
@include_once __DIR__ . '/lib/database.php'; // $pdo should be defined here

// --- Helper Functions (same as before) ---
function sanitize_filename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '', $filename);
    $filename = preg_replace('/\.{2,}/', '', $filename);
    return strtolower($filename);
}

function generate_sql_data_type($fieldType, $fieldName) {
    switch ($fieldType) {
        case 'textarea': return 'TEXT';
        case 'number': return 'INT';
        case 'currency': return 'DECIMAL(10, 2)';
        case 'date': return 'DATE';
        case 'datetime': return 'DATETIME';
        case 'select': return 'VARCHAR(255)';
        case 'foreign_key': return 'INT';
        case 'text': default: return 'VARCHAR(255)';
    }
}

// --- Script Logic ---
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

    // --- More Validation (same as before) ---
    $errors = [];
    if (empty($moduleNameDisplay)) $errors[] = "Module Display Name is required.";
    if (empty($moduleIdentifier)) $errors[] = "Module Identifier is required and must be valid for filenames.";
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
            if ($field['type'] === 'select' && (empty($field['options_key']) || !is_array($field['options_key']) || empty(array_filter($field['options_key'], 'trim')) )) {
                 $errors[] = "Field #".($index+1)." ('".htmlspecialchars($field['label'])."'): At least one non-empty option (key and value) is required for select type.";
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
    // --- Construct Config Array (same as before) ---
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
        if ($fieldConfig['type'] === 'select') {
            $fieldConfig['options'] = [];
            if (isset($field['options_key']) && is_array($field['options_key'])) {
                foreach ($field['options_key'] as $i => $key) {
                    $value = $field['options_value'][$i] ?? $key;
                    if (!empty(trim($key))) { $fieldConfig['options'][trim($key)] = trim($value); }
                }
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

    // --- Generate SQL CREATE TABLE Statement (split into main and alter for FKs) ---
    $sqlStatements = [];
    $mainCreateTableSQL = "CREATE TABLE IF NOT EXISTS `{$tableName}` (\n";
    $mainCreateTableSQL .= "  `{$primaryKey}` INT AUTO_INCREMENT PRIMARY KEY,\n";
    foreach ($newConfig['fields'] as $fieldName => $fieldConf) {
        if ($fieldName === $primaryKey) continue;
        $sqlDataType = generate_sql_data_type($fieldConf['type'], $fieldName);
        $sqlNull = empty($fieldConf['required']) ? 'NULL' : 'NOT NULL';
        $mainCreateTableSQL .= "  `{$fieldName}` {$sqlDataType} {$sqlNull},\n";
    }
    $mainCreateTableSQL .= "  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
    $mainCreateTableSQL .= "  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n";
    $mainCreateTableSQL .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $sqlStatements[] = $mainCreateTableSQL;

    $alterTableSQLs = [];
    foreach ($newConfig['fields'] as $fieldName => $fieldConf) {
        if ($fieldConf['type'] === 'foreign_key' && !empty($fieldConf['lookup_table']) && !empty($fieldConf['lookup_id_column'])) {
            $constraintName = "fk_{$tableName}_{$fieldName}_" . substr(md5($fieldConf['lookup_table'].$fieldConf['lookup_id_column']),0,10);
            if (strlen($constraintName) > 64) { $constraintName = substr($constraintName, 0, 50) . '_' . substr(md5($constraintName),0,10); }
            $fkSql = "ALTER TABLE `{$tableName}`\n";
            $fkSql .= "  ADD CONSTRAINT `{$constraintName}` FOREIGN KEY (`{$fieldName}`)\n";
            $fkSql .= "  REFERENCES `{$fieldConf['lookup_table']}`(`{$fieldConf['lookup_id_column']}`)\n";
            $fkSql .= "  ON DELETE SET NULL ON UPDATE CASCADE;"; // Or restrict, no action, etc.
            $alterTableSQLs[] = $fkSql;
        }
    }
    $fullGeneratedSQL = $mainCreateTableSQL . "\n\n" . implode("\n\n", $alterTableSQLs);
    error_log("Generated SQL:\n" . $fullGeneratedSQL);
    $_SESSION['generated_sql'] = $fullGeneratedSQL; // Store for display regardless of execution

    // --- Save Config File ---
    $fileWritten = false;
    if (!is_dir($configDir)) {
        error_log("Config directory '{$configDir}' does NOT exist.");
        $_SESSION['generate_module_errors'] = ["Error: Configuration directory '{$configDir}' does not exist. Please create it."];
    } elseif (!is_writable($configDir)) {
        error_log("Config directory '{$configDir}' is NOT writable.");
        $_SESSION['generate_module_errors'] = ["Error: Configuration directory '{$configDir}' is not writable. Check server permissions."];
    } else {
        error_log("Config directory '{$configDir}' exists and is writable. Attempting to write file...");
        if (file_put_contents($configFilePath, $phpConfigFileContent) !== false) {
            $fileWritten = true;
            error_log("Successfully wrote config file to '{$configFilePath}'.");
        } else {
            error_log("Failed to write config file to '{$configFilePath}'. file_put_contents returned false.");
            $_SESSION['generate_module_errors'] = ["Error: Could not write configuration file to '{$configFilePath}'. Unknown error during write operation."];
        }
    }

    if (!$fileWritten) {
        // Errors already set in $_SESSION['generate_module_errors']
        if (empty($_SESSION['generate_module_errors'])) {
             $_SESSION['generate_module_errors'] = ["An unspecified error occurred while trying to save the configuration file."];
        }
        $_SESSION['generate_module_old_input'] = $_POST;
        $_SESSION['generated_php_config'] = $phpConfigFileContent; // So user can copy
        // $_SESSION['generated_sql'] is already set for display
        error_log("File write failed or directory issue. Error messages: " . print_r($_SESSION['generate_module_errors'], true));
        header('Location: create_module_form.php');
        exit;
    }

    // --- Attempt to Auto-Create Database Table if Checked and $pdo is available ---
    if ($autoCreateTable) {
        if (!isset($pdo) || !$pdo) {
            $_SESSION['sql_execution_error'] = "Database connection (PDO) is not available. Table not created automatically.";
            error_log("Auto create table checked, but PDO object is not available.");
        } else {
            error_log("Auto create table checked. Attempting to execute SQL...");
            try {
                $pdo->beginTransaction();
                error_log("Executing: " . $mainCreateTableSQL);
                $pdo->exec($mainCreateTableSQL);

                foreach($alterTableSQLs as $fkSql) {
                    error_log("Executing: " . $fkSql);
                    $pdo->exec($fkSql);
                }
                $pdo->commit();
                $_SESSION['sql_execution_status'] = 'success';
                error_log("SQL statements executed successfully and transaction committed.");
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $sqlError = "Database error during automatic table creation: " . $e->getMessage();
                $_SESSION['sql_execution_error'] = $sqlError;
                error_log($sqlError);
                // Add to main errors if you want it at the top of the form too
                // $_SESSION['generate_module_errors'][] = $sqlError;
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
    // Redirect to the form page if accessed directly via GET.
    // The form page (create_module_form.php) will handle displaying any session messages.
    error_log("Accessed generate_module.php via GET. Redirecting to create_module_form.php.");
    header('Location: create_module_form.php');
    exit;
}
?>

