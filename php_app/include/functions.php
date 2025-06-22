<?php
/**
 * Builds the components for an SQL INSERT or UPDATE statement from POST data.
 * This function was previously inside display_table.php and is now centralized here.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param string $tableName The name of the table to operate on.
 * @param array $postData The raw data from the $_POST array.
 * @param bool $isUpdate Flag to indicate if this is for an UPDATE operation.
 * @return array An array containing columns, values, and placeholders for the SQL query.
 */
function buildSqlParts(PDO $pdo, string $tableName, array $postData, bool $isUpdate = false): array {
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

            // Special handling for passwords in the 'users' table
            if ($tableName === 'users' && $colName === 'password') {
                if (empty($finalValue) && $isUpdate) { // Don't update password if empty on edit
                    continue; 
                }
                $finalValue = password_hash($finalValue, PASSWORD_DEFAULT);
            }
            
            $sqlParts['columns'][] = "`" . $colName . "`";
            $sqlParts['values'][] = $finalValue;
            $sqlParts['placeholders'][] = '?';
        }
    }
    return $sqlParts;
}


/**
 * Renders the HTML form fields for adding or editing an entry.
 *
 * @param array $columnsInfo The metadata of the table columns.
 * @param bool $isUsersTable A flag to indicate if we are dealing with the 'users' table.
 * @param array $entryData The existing data for an entry being edited.
 * @param string $formType The type of form, either 'add' or 'edit'.
 */
function render_form_fields(array $columnsInfo, bool $isUsersTable, array $entryData, string $formType) {
    foreach ($columnsInfo as $colInfo) {
        $colName = $colInfo['Field'];

        // --- Skip fields that should not be in the form ---
        if (strtolower($colName) === 'id' || 
           (strpos(strtolower($colInfo['Extra']), 'auto_increment') !== false && $formType === 'add') ||
           ($colName === 'created_at' && $formType === 'add')) {
            continue;
        }

        // --- Determine input type, value, and placeholder ---
        $value = htmlspecialchars($entryData[$colName] ?? '');
        $placeholder = "Gib " . htmlspecialchars($colInfo['Type']) . " ein";
        $inputType = 'text';
        $colTypeUpper = strtoupper($colInfo['Type']);
        
        if ($isUsersTable && $colName === 'password' && $formType === 'edit') {
            $inputType = 'password';
            $placeholder = "Leer lassen, um nicht zu ändern";
            $value = ''; // Never show existing password hash
        } elseif ($colTypeUpper === 'DATE') {
            $inputType = 'date';
        } elseif ($colTypeUpper === 'BOOLEAN' || $colTypeUpper === 'TINYINT(1)') {
            $inputType = 'checkbox';
        } elseif (strpos($colTypeUpper, 'INT') !== false) {
            $inputType = 'number';
        } elseif ($isUsersTable && $colName === 'role') {
            $inputType = 'select';
        }

        // --- Render the HTML for the form group ---
        echo '<div class="form-group">';
        echo '<label for="data_' . $colName . '">' . htmlspecialchars($colName) . ':</label>';

        if ($inputType === 'select' && $colName === 'role') {
            echo '<select name="data[role]" id="data_role">';
            echo '<option value="user"' . ($value === 'user' ? ' selected' : '') . '>user</option>';
            echo '<option value="admin"' . ($value === 'admin' ? ' selected' : '') . '>admin</option>';
            echo '</select>';
        } elseif ($inputType === 'checkbox') {
            // Add a hidden field to ensure a value (0) is sent when unchecked.
            echo '<input type="hidden" name="data[' . $colName . ']" value="0">';
            echo '<input type="checkbox" id="data_' . $colName . '" name="data[' . $colName . ']" value="1"' . (!empty($value) ? ' checked' : '') . '>';
        } else {
            echo '<input type="' . $inputType . '" id="data_' . $colName . '" name="data[' . htmlspecialchars($colName) . ']" value="' . $value . '" placeholder="' . $placeholder . '">';
        }

        echo '</div>';
    }
}