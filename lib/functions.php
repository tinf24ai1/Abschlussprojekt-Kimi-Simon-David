<?php

function getModuleIdentifier() {
    $moduleIdentifier = $_GET['module'] ?? 'my_books'; // Default module
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $moduleIdentifier)) {
        die("Invalid module identifier.");
    }
    return $moduleIdentifier;
}

function loadModuleConfig($moduleIdentifier) {
    $configFile = __DIR__ . "/../config/config_{$moduleIdentifier}.php";
    if (!file_exists($configFile)) {
        die("Configuration for module '{$moduleIdentifier}' not found at {$configFile}.");
    }
    return require $configFile;
}

function fetchData($pdo, $config, $searchTerm = null) {
    $selectFields = [];
    $joinClauses = "";
    $fieldAliasCounter = 0;

    foreach ($config['fields'] as $fieldKey => $fieldConfig) {
        if ($fieldConfig['type'] === 'foreign_key' && !empty($fieldConfig['lookup_table']) && !empty($fieldConfig['lookup_id_column']) && !empty($fieldConfig['lookup_value_column'])) {
            $alias = "fk_alias_" . ($fieldAliasCounter++);
            $selectFields[] = "{$alias}.{$fieldConfig['lookup_value_column']} AS {$fieldKey}_display";
            $selectFields[] = "{$config['table_name']}.{$fieldKey}"; // Also select the actual ID
            $joinClauses .= " LEFT JOIN {$fieldConfig['lookup_table']} AS {$alias} ON {$config['table_name']}.{$fieldKey} = {$alias}.{$fieldConfig['lookup_id_column']}";
        } else {
            $selectFields[] = "{$config['table_name']}.{$fieldKey}";
        }
    }
    if (!in_array("{$config['table_name']}.".$config['primary_key'], $selectFields)) {
         $selectFields[] = "{$config['table_name']}.".$config['primary_key'];
    }


    $sql = "SELECT DISTINCT " . implode(", ", array_unique($selectFields)) . " FROM " . $config['table_name'] . $joinClauses;

    $whereClauses = [];
    $bindings = [];

    if ($searchTerm) {
        $searchableFieldsConfig = array_filter($config['fields'], fn($fc) => !empty($fc['searchable']));
        if (!empty($searchableFieldsConfig)) {
            $searchParts = [];
            foreach (array_keys($searchableFieldsConfig) as $sFieldKey) {
                 $searchParts[] = "{$config['table_name']}.{$sFieldKey} LIKE :searchTerm";
            }
            if (!empty($searchParts)) {
                $whereClauses[] = "(" . implode(" OR ", $searchParts) . ")";
                $bindings[':searchTerm'] = "%" . $searchTerm . "%";
            }
        }
    }

    if (!empty($whereClauses)) {
        $sql .= " WHERE " . implode(" AND ", $whereClauses);
    }

    $orderBy = $config['default_sort_column'] ?? $config['primary_key'];
    $orderDir = $config['default_sort_direction'] ?? 'ASC';
    $sql .= " ORDER BY {$config['table_name']}.{$orderBy} {$orderDir}";

    // Basic pagination (can be expanded)
    // $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    // $recordsPerPage = $config['records_per_page'] ?? 10;
    // $offset = ($page - 1) * $recordsPerPage;
    // $sql .= " LIMIT {$recordsPerPage} OFFSET {$offset}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindings);
    return $stmt->fetchAll();
}


function fetchSingleRecord($pdo, $config, $id) {
    $selectFields = [];
     foreach ($config['fields'] as $fieldKey => $fieldConfig) {
        $selectFields[] = $fieldKey;
    }
    if(!in_array($config['primary_key'], $selectFields)){
        $selectFields[] = $config['primary_key'];
    }

    $sql = "SELECT " . implode(", ", $selectFields) . " FROM " . $config['table_name'] . " WHERE " . $config['primary_key'] . " = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    return $stmt->fetch();
}

function saveRecord($pdo, $config, $data, $id = null) {
    $fieldValues = [];
    $errors = [];

    // CSRF token validation should happen here if implemented

    foreach ($config['fields'] as $fieldKey => $fieldConf) {
        if (!$fieldConf['form_display'] && $fieldConf['type'] !== 'id') continue;

        $value = $data[$fieldKey] ?? null;

        if (!empty($fieldConf['required']) && ($value === null || $value === '')) {
            $errors[$fieldKey] = htmlspecialchars($fieldConf['label']) . " is required.";
            continue;
        }

        if ($value !== null && $value !== '') {
            switch ($fieldConf['type']) {
                case 'number':
                case 'currency':
                    if (!is_numeric($value)) {
                        $errors[$fieldKey] = htmlspecialchars($fieldConf['label']) . " must be a number.";
                    } else {
                        if (isset($fieldConf['min']) && $value < $fieldConf['min']) {
                            $errors[$fieldKey] = htmlspecialchars($fieldConf['label']) . " must be at least " . $fieldConf['min'] . ".";
                        }
                        if (isset($fieldConf['max']) && $value > $fieldConf['max']) {
                            $errors[$fieldKey] = htmlspecialchars($fieldConf['label']) . " must be no more than " . $fieldConf['max'] . ".";
                        }
                    }
                    break;
                case 'date':
                    // Basic date validation, can be more robust
                    if (DateTime::createFromFormat('Y-m-d', $value) === false && !empty($value)) {
                        $errors[$fieldKey] = htmlspecialchars($fieldConf['label']) . " is not a valid date (YYYY-MM-DD).";
                    }
                    break;
            }
        }
        // Sanitize if no error yet for this field
        if (!isset($errors[$fieldKey])) {
             $fieldValues[$fieldKey] = ($value === '' && $fieldConf['type'] !== 'text' && $fieldConf['type'] !== 'textarea') ? null : $value; // Allow empty strings for text, otherwise nullify for DB
        }
    }

    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = $data; // Preserve submitted data
        return false;
    }


    if ($id) { // Update
        $setClauses = [];
        $updateBindings = [];
        foreach ($fieldValues as $key => $val) {
            if ($key === $config['primary_key']) continue; // Don't try to update PK itself
            $setClauses[] = "{$key} = :{$key}";
            $updateBindings[":{$key}"] = $val;
        }
        if (empty($setClauses)) { // Nothing to update (e.g. only PK was in $fieldValues)
            return $id;
        }
        $sql = "UPDATE " . $config['table_name'] . " SET " . implode(", ", $setClauses) . " WHERE " . $config['primary_key'] . " = :primary_key_val";
        $updateBindings[":primary_key_val"] = $id;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateBindings);
    } else { // Insert
        // Filter out empty values that are not explicitly allowed to be empty strings
        // This helps with auto-increment PKs and fields that allow NULL but not empty strings
        $insertData = array_filter($fieldValues, function($v) { return $v !== null; });

        if (empty($insertData)) {
             $_SESSION['form_errors'] = ['general' => 'No data to insert.'];
             return false;
        }

        $columns = implode(", ", array_keys($insertData));
        $placeholders = ":" . implode(", :", array_keys($insertData));
        $sql = "INSERT INTO " . $config['table_name'] . " ({$columns}) VALUES ({$placeholders})";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($insertData);
        $id = $pdo->lastInsertId();
    }
    return $id;
}

function deleteRecord($pdo, $config, $id) {
    // CSRF token validation should happen here if implemented
    $sql = "DELETE FROM " . $config['table_name'] . " WHERE " . $config['primary_key'] . " = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    return $stmt->rowCount() > 0;
}

function fetchLookupOptions($pdo, $fieldConfig) {
    if ($fieldConfig['type'] !== 'foreign_key' || empty($fieldConfig['lookup_table']) || empty($fieldConfig['lookup_id_column']) || empty($fieldConfig['lookup_value_column'])) {
        return [];
    }
    try {
        $sql = "SELECT {$fieldConfig['lookup_id_column']}, {$fieldConfig['lookup_value_column']} FROM {$fieldConfig['lookup_table']} ORDER BY {$fieldConfig['lookup_value_column']} ASC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Fetches into [id => value] array
    } catch (PDOException $e) {
        // Log error, return empty or throw
        error_log("Error fetching lookup options for {$fieldConfig['label']}: " . $e->getMessage());
        return [];
    }
}
?>
