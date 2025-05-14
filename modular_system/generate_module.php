<?php
session_start();

// --- Configuration ---
$configDir = __DIR__ . '/config/'; // Path to your config directory

// --- Helper Functions ---
function sanitize_filename($filename) {
    // Remove anything which isn't a word, whitespace, number
    // or any of the following caracters -_~,;[]().
    // If you don't need to handle multi-byte characters
    // you can use preg_replace /[^A-Za-z0-9\.\-_]/', '', $filename);
    $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '', $filename);
    // Remove any runs of periods
    $filename = preg_replace('/\.{2,}/', '', $filename);
    return strtolower($filename);
}

function generate_sql_data_type($fieldType, $fieldName) {
    switch ($fieldType) {
        case 'textarea':
            return 'TEXT';
        case 'number':
            return 'INT'; // Or DECIMAL(10,2) if decimals are expected
        case 'currency':
            return 'DECIMAL(10, 2)';
        case 'date':
            return 'DATE';
        case 'datetime':
            return 'DATETIME';
        case 'select': // Select stores a value, often VARCHAR or INT
            return 'VARCHAR(255)'; // Adjust as needed
        case 'foreign_key':
            return 'INT'; // Assuming FKs are INTs
        case 'text':
        default:
            return 'VARCHAR(255)';
    }
}

// --- Script Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Basic Validation & Data Extraction ---
    $moduleNameDisplay = trim($_POST['module_name_display'] ?? '');
    $moduleIdentifier = sanitize_filename(trim($_POST['module_identifier'] ?? ''));
    $tableName = sanitize_filename(trim($_POST['table_name'] ?? '')); // Also SQL identifier rules
    $primaryKey = sanitize_filename(trim($_POST['primary_key'] ?? 'id'));

    $fieldsData = $_POST['fields'] ?? [];

    $defaultSortColumn = sanitize_filename(trim($_POST['default_sort_column'] ?? ''));
    $defaultSortDirection = in_array($_POST['default_sort_direction'] ?? 'DESC', ['ASC', 'DESC']) ? $_POST['default_sort_direction'] : 'DESC';
    $recordsPerPage = filter_var($_POST['records_per_page'] ?? 10, FILTER_VALIDATE_INT, ['options' => ['default' => 10, 'min_range' => 1]]);


    // --- More Validation ---
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

            if ($field['type'] === 'select' && (!isset($field['options_key']) || !is_array($field['options_key']))) {
                 $errors[] = "Field #".($index+1)." ('".htmlspecialchars($field['label'])."'): Options are required for select type.";
            }
            if ($field['type'] === 'foreign_key') {
                if (empty($field['fk_lookup_table'])) $errors[] = "Field #".($index+1)." ('".htmlspecialchars($field['label'])."'): Lookup Table Name is required for foreign key.";
                if (empty($field['fk_lookup_id_column'])) $errors[] = "Field #".($index+1)." ('".htmlspecialchars($field['label'])."'): Lookup ID Column is required for foreign key.";
                if (empty($field['fk_lookup_value_column'])) $errors[] = "Field #".($index+1)." ('".htmlspecialchars($field['label'])."'): Lookup Value Column is required for foreign key.";
            }
        }
    }
     if (!empty($defaultSortColumn) && !in_array($defaultSortColumn, array_column($fieldsData, 'name'))) {
        if ($defaultSortColumn !== $primaryKey && $defaultSortColumn !== 'created_at' && $defaultSortColumn !== 'updated_at') { // Common implicit fields
            $errors[] = "Default Sort Column ('".htmlspecialchars($defaultSortColumn)."') must be one of the defined field names, the primary key, 'created_at', or 'updated_at'.";
        }
    }


    if (!empty($errors)) {
        $_SESSION['generate_module_errors'] = $errors;
        $_SESSION['generate_module_old_input'] = $_POST; // Preserve input
        header('Location: create_module_form.php'); // Redirect back to form
        exit;
    }

    // --- Construct Config Array ---
    $newConfig = [
        'module_title' => $moduleNameDisplay,
        'module_title_singular' => rtrim($moduleNameDisplay, 's'), // Basic singularization
        'table_name' => $tableName,
        'primary_key' => $primaryKey,
        'fields' => [],
        'list_actions' => ['create', 'edit', 'delete'], // Default actions
        'default_sort_column' => !empty($defaultSortColumn) ? $defaultSortColumn : $primaryKey,
        'default_sort_direction' => $defaultSortDirection,
        'records_per_page' => (int)$recordsPerPage,
    ];

    // Add primary key as a non-displayed, non-form field by default
    $newConfig['fields'][$primaryKey] = [
        'label' => 'ID',
        'type' => 'id', // Special type for primary key
        'list_display' => false,
        'form_display' => false,
    ];

    foreach ($fieldsData as $field) {
        $fieldName = sanitize_filename($field['name']);
        $fieldConfig = [
            'label' => trim($field['label']),
            'type' => trim($field['type']),
            'list_display' => isset($field['list_display']),
            'form_display' => isset($field['form_display']),
            'required' => isset($field['required']),
            'searchable' => isset($field['searchable']),
        ];

        if ($fieldConfig['type'] === 'select') {
            $fieldConfig['options'] = [];
            if (isset($field['options_key']) && is_array($field['options_key'])) {
                foreach ($field['options_key'] as $i => $key) {
                    $value = $field['options_value'][$i] ?? $key;
                    if (!empty(trim($key))) { // Ensure key is not empty
                        $fieldConfig['options'][trim($key)] = trim($value);
                    }
                }
            }
            if (!empty($field['select_placeholder'])) {
                $fieldConfig['placeholder'] = trim($field['select_placeholder']);
            }
        } elseif ($fieldConfig['type'] === 'foreign_key') {
            $fieldConfig['lookup_table'] = sanitize_filename(trim($field['fk_lookup_table'] ?? ''));
            $fieldConfig['lookup_id_column'] = sanitize_filename(trim($field['fk_lookup_id_column'] ?? ''));
            $fieldConfig['lookup_value_column'] = sanitize_filename(trim($field['fk_lookup_value_column'] ?? ''));
             if (!empty($field['fk_placeholder'])) {
                $fieldConfig['placeholder'] = trim($field['fk_placeholder']);
            }
        }
        // Add min/max for number/currency if provided in form (not in this basic form example)
        // Add step for currency/number if provided

        $newConfig['fields'][$fieldName] = $fieldConfig;
    }


    // --- Generate PHP Config File Content ---
    $phpConfigFileContent = "<?php\n\nreturn " . var_export($newConfig, true) . ";\n";
    $configFilePath = $configDir . 'config_' . $moduleIdentifier . '.php';

    // --- Generate SQL CREATE TABLE Statement ---
    $sqlCreateTable = "CREATE TABLE IF NOT EXISTS `{$tableName}` (\n";
    $sqlCreateTable .= "  `{$primaryKey}` INT AUTO_INCREMENT PRIMARY KEY,\n";

    foreach ($newConfig['fields'] as $fieldName => $fieldConf) {
        if ($fieldName === $primaryKey) continue; // Already defined

        $sqlDataType = generate_sql_data_type($fieldConf['type'], $fieldName);
        $sqlNull = empty($fieldConf['required']) ? 'NULL' : 'NOT NULL';
        $sqlCreateTable .= "  `{$fieldName}` {$sqlDataType} {$sqlNull},\n";
    }

    // Add standard timestamp fields
    $sqlCreateTable .= "  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
    $sqlCreateTable .= "  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n";
    $sqlCreateTable .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

    // Add foreign key constraints if any (after table definition)
    foreach ($newConfig['fields'] as $fieldName => $fieldConf) {
        if ($fieldConf['type'] === 'foreign_key' && !empty($fieldConf['lookup_table']) && !empty($fieldConf['lookup_id_column'])) {
            $constraintName = "fk_{$tableName}_{$fieldName}_{$fieldConf['lookup_table']}"; // Generate a unique constraint name
             // Ensure constraint name is not too long for MySQL
            if (strlen($constraintName) > 64) {
                $constraintName = substr($constraintName, 0, 50) . '_' . substr(md5($constraintName),0,10);
            }

            $sqlCreateTable .= "ALTER TABLE `{$tableName}`\n";
            $sqlCreateTable .= "  ADD CONSTRAINT `{$constraintName}` FOREIGN KEY (`{$fieldName}`)\n";
            $sqlCreateTable .= "  REFERENCES `{$fieldConf['lookup_table']}`(`{$fieldConf['lookup_id_column']}`)\n";
            $sqlCreateTable .= "  ON DELETE SET NULL ON UPDATE CASCADE;\n\n"; // Or restrict, no action, etc.
        }
    }


    // --- Save Config File & Prepare Output ---
    $fileWritten = false;
    if (is_writable($configDir)) {
        if (file_put_contents($configFilePath, $phpConfigFileContent) !== false) {
            $fileWritten = true;
        } else {
            $outputMessage = "Error: Could not write configuration file to '{$configFilePath}'. Check directory permissions.";
            $outputMessageType = "error";
        }
    } else {
        $outputMessage = "Error: Configuration directory '{$configDir}' is not writable.";
        $outputMessageType = "error";
    }

    if ($fileWritten) {
        $_SESSION['generate_module_success'] = "Module '{$moduleNameDisplay}' configuration file generated successfully at:<br><code>{$configFilePath}</code>";
        $_SESSION['generated_sql'] = $sqlCreateTable;
    } else {
         $_SESSION['generate_module_errors'] = [$outputMessage];
         $_SESSION['generated_php_config'] = $phpConfigFileContent; // So user can copy if write failed
         $_SESSION['generated_sql_on_error'] = $sqlCreateTable;
    }

    header('Location: create_module_form.php'); // Redirect back to form to show messages
    exit;

} else {
    // --- Display Form & Messages (if redirected) ---
    $pageTitle = "Create New Module";
    include __DIR__ . '/views/header.php'; // Assuming you have a header

    if (isset($_SESSION['generate_module_errors'])) {
        echo '<div class="flash-message error"><strong>Errors:</strong><ul>';
        foreach ($_SESSION['generate_module_errors'] as $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul></div>';
        if(isset($_SESSION['generated_php_config'])) {
            echo '<h3>Generated PHP Config (Copy if file write failed):</h3>';
            echo '<pre style="background:#f0f0f0; padding:10px; border:1px solid #ccc; white-space:pre-wrap; word-wrap:break-word;"><code>' . htmlspecialchars($_SESSION['generated_php_config']) . '</code></pre>';
        }
         if(isset($_SESSION['generated_sql_on_error'])) {
            echo '<h3>Generated SQL (Copy if file write failed or for manual execution):</h3>';
            echo '<pre style="background:#f0f0f0; padding:10px; border:1px solid #ccc; white-space:pre-wrap; word-wrap:break-word;"><code>' . htmlspecialchars($_SESSION['generated_sql_on_error']) . '</code></pre>';
        }
        unset($_SESSION['generate_module_errors'], $_SESSION['generated_php_config'], $_SESSION['generated_sql_on_error']);
    }

    if (isset($_SESSION['generate_module_success'])) {
        echo '<div class="flash-message success">' . $_SESSION['generate_module_success'] . '</div>';
        if (isset($_SESSION['generated_sql'])) {
            echo '<h3>Generated SQL `CREATE TABLE` Statement:</h3>';
            echo '<p>Please review and run this SQL in your database management tool (e.g., phpMyAdmin) to create the necessary table for your new module:</p>';
            echo '<pre style="background:#f0f0f0; padding:10px; border:1px solid #ccc; white-space:pre-wrap; word-wrap:break-word;"><code>' . htmlspecialchars($_SESSION['generated_sql']) . '</code></pre>';
            unset($_SESSION['generated_sql']);
        }
        unset($_SESSION['generate_module_success']);
    }

    // The actual form HTML should be in `create_module_form.php`
    // This script is primarily for processing.
    // For simplicity if accessed directly via GET, it could include the form.
    // However, it's better to keep form display and processing separate.
    // The redirect above handles showing the form again with messages.
    // If you want to access `generate_module.php` directly to see the form,
    // you would include the form HTML here. But the current flow is POST to this, then redirect.

    echo "<p><a href='create_module_form.php' class='button'>Go to Module Creation Form</a></p>";


    include __DIR__ . '/views/footer.php'; // Assuming you have a footer
}
?>

