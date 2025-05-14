<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Module</title>
    <link rel="stylesheet" href="css/style.css"> <style>
        .module-creator-form {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .module-creator-form h1, .module-creator-form h2 {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .form-section {
            margin-bottom: 25px;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
        }
        .field-definition {
            padding: 15px;
            border: 1px dashed #ccc;
            margin-bottom: 15px;
            border-radius: 4px;
            background-color: #f9f9f9;
        }
        .field-definition label {
            font-weight: normal; /* Override general bold for sub-labels */
        }
        .field-options, .fk-options {
            margin-left: 20px;
            padding: 10px;
            background-color: #eef;
            border-radius: 4px;
            display: none; /* Hidden by default */
        }
        .field-options .option-pair {
            display: flex;
            gap: 10px;
            margin-bottom: 5px;
        }
        .field-options .option-pair input {
            flex: 1;
        }
        .remove-field, .remove-option {
            background-color: #d9534f;
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            border-radius: 3px;
            font-size: 0.9em;
        }
         .add-button {
            background-color: #337ab7;
            color: white;
            padding: 8px 12px;
        }
        .warning-notice {
            background-color: #fff3cd;
            color: #856404;
            padding: 10px;
            border: 1px solid #ffeeba;
            border-radius: 4px;
            margin-top: 5px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <a href="index.php">Back to System</a>
        </nav>
    </header>
    <main class="container">
        <div class="module-creator-form">
            <h1>Create New Module/Tracker</h1>
            <p>Define the structure for your new module. This will generate a configuration file and a SQL `CREATE TABLE` statement.</p>

            <?php
            // Display errors or success messages from generate_module.php
            if (session_status() == PHP_SESSION_NONE) { // Ensure session is started
                session_start();
            }
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
                unset($_SESSION['generate_module_errors'], $_SESSION['generated_php_config'], $_SESSION['generated_sql_on_error'], $_SESSION['generate_module_old_input']);
            }

            if (isset($_SESSION['generate_module_success'])) {
                echo '<div class="flash-message success">' . $_SESSION['generate_module_success'] . '</div>';
                if (isset($_SESSION['generated_sql'])) {
                    echo '<h3>Generated SQL `CREATE TABLE` Statement:</h3>';
                    if (isset($_SESSION['sql_execution_status']) && $_SESSION['sql_execution_status'] === 'success') {
                        echo '<p style="color:green; font-weight:bold;">The following SQL was also executed successfully against the database.</p>';
                    } elseif (isset($_SESSION['sql_execution_status']) && $_SESSION['sql_execution_status'] === 'skipped') {
                         echo '<p style="color:orange; font-weight:bold;">Automatic SQL execution was skipped (option not checked).</p>';
                    } elseif (isset($_SESSION['sql_execution_error'])) {
                        echo '<p style="color:red; font-weight:bold;">An error occurred trying to execute the SQL automatically: ' . htmlspecialchars($_SESSION['sql_execution_error']) . '</p>';
                        echo '<p>Please review and run this SQL in your database management tool (e.g., phpMyAdmin) to create the necessary table for your new module:</p>';
                    } else {
                         echo '<p>Please review and run this SQL in your database management tool (e.g., phpMyAdmin) to create the necessary table for your new module:</p>';
                    }
                    echo '<pre style="background:#f0f0f0; padding:10px; border:1px solid #ccc; white-space:pre-wrap; word-wrap:break-word;"><code>' . htmlspecialchars($_SESSION['generated_sql']) . '</code></pre>';
                    unset($_SESSION['generated_sql'], $_SESSION['sql_execution_status'], $_SESSION['sql_execution_error']);
                }
                unset($_SESSION['generate_module_success']);
            }
            $oldInput = $_SESSION['generate_module_old_input'] ?? [];
            unset($_SESSION['generate_module_old_input']); // Clear after use
            ?>

            <form action="generate_module.php" method="POST" id="createModuleForm">

                <div class="form-section">
                    <h2>Module Details</h2>
                    <div class="form-group">
                        <label for="module_name_display">Module Display Name <span class="required">*</span></label>
                        <input type="text" id="module_name_display" name="module_name_display" placeholder="e.g., My Project Tasks" value="<?php echo htmlspecialchars($oldInput['module_name_display'] ?? ''); ?>" required>
                        <small>User-friendly name that will appear in titles.</small>
                    </div>
                    <div class="form-group">
                        <label for="module_identifier">Module Identifier <span class="required">*</span></label>
                        <input type="text" id="module_identifier" name="module_identifier" placeholder="e.g., my_project_tasks" value="<?php echo htmlspecialchars($oldInput['module_identifier'] ?? ''); ?>" required pattern="[a-z0-9_]+" title="Lowercase letters, numbers, and underscores only.">
                        <small>Used for filenames (config_MODULE_IDENTIFIER.php) and internal references. Lowercase letters, numbers, and underscores only.</small>
                    </div>
                    <div class="form-group">
                        <label for="table_name">Database Table Name <span class="required">*</span></label>
                        <input type="text" id="table_name" name="table_name" placeholder="e.g., project_tasks" value="<?php echo htmlspecialchars($oldInput['table_name'] ?? ''); ?>" required pattern="[a-zA-Z0-9_]+" title="Letters, numbers, and underscores only.">
                        <small>The actual name of the SQL table that will be created/used.</small>
                    </div>
                    <div class="form-group">
                        <label for="primary_key">Primary Key Field Name <span class="required">*</span></label>
                        <input type="text" id="primary_key" name="primary_key" value="<?php echo htmlspecialchars($oldInput['primary_key'] ?? 'id'); ?>" required pattern="[a-zA-Z0-9_]+" title="Letters, numbers, and underscores only.">
                        <small>The name of the auto-incrementing primary key column (e.g., id, task_id).</small>
                    </div>
                </div>

                <div class="form-section">
                    <h2>Define Fields</h2>
                    <div id="fields-container">
                        <?php
                        // Repopulate fields if there was an error and old input exists
                        if (!empty($oldInput['fields']) && is_array($oldInput['fields'])) {
                            foreach ($oldInput['fields'] as $index => $fieldData) {
                                // This part is complex to re-render dynamically with PHP here.
                                // The JS will handle adding initial fields.
                                // For a full server-side re-population of dynamic fields,
                                // you'd need to pass this data to JS or have a more complex PHP render loop.
                                // For now, the JS `addField()` will run on load.
                            }
                        }
                        ?>
                    </div>
                    <button type="button" id="addFieldBtn" class="button add-button">Add Field</button>
                </div>

                <div class="form-section">
                    <h2>Module Settings (Optional)</h2>
                    <div class="form-group">
                        <label for="default_sort_column">Default Sort Column</label>
                        <input type="text" id="default_sort_column" name="default_sort_column" value="<?php echo htmlspecialchars($oldInput['default_sort_column'] ?? ''); ?>" placeholder="e.g., created_at (must be one of your defined fields)">
                    </div>
                    <div class="form-group">
                        <label for="default_sort_direction">Default Sort Direction</label>
                        <select id="default_sort_direction" name="default_sort_direction">
                            <option value="ASC" <?php echo (($oldInput['default_sort_direction'] ?? 'DESC') === 'ASC' ? 'selected' : ''); ?>>Ascending (ASC)</option>
                            <option value="DESC" <?php echo (($oldInput['default_sort_direction'] ?? 'DESC') === 'DESC' ? 'selected' : ''); ?>>Descending (DESC)</option>
                        </select>
                    </div>
                     <div class="form-group">
                        <label for="records_per_page">Records Per Page</label>
                        <input type="number" id="records_per_page" name="records_per_page" value="<?php echo htmlspecialchars($oldInput['records_per_page'] ?? 10); ?>" min="1">
                    </div>
                </div>

                <div class="form-section">
                    <h2>Database Options</h2>
                    <div class="form-group">
                        <input type="checkbox" name="auto_create_table" id="auto_create_table" value="1" <?php echo !empty($oldInput['auto_create_table']) ? 'checked' : ''; ?>>
                        <label for="auto_create_table" class="inline-label">Attempt to create database table automatically?</label>
                        <div class="warning-notice">
                            <strong>Warning:</strong> Enabling this will attempt to execute `CREATE TABLE` and `ALTER TABLE` SQL commands directly on your database.
                            Ensure the database user has sufficient permissions (CREATE, ALTER). This is powerful and could lead to errors or overwrite existing structures if not used carefully.
                            It is recommended to back up your database before proceeding if unsure.
                        </div>
                    </div>
                </div>


                <div class="form-actions">
                    <button type="submit" class="button save">Generate Module Files</button>
                </div>
            </form>
        </div>
    </main>

    <template id="fieldTemplate">
        <div class="field-definition">
            <button type="button" class="remove-field" onclick="this.parentElement.remove()">Remove this Field</button>
            <div class="form-group">
                <label>Field Name (SQL Column) <span class="required">*</span></label>
                <input type="text" name="fields[INDEX][name]" placeholder="e.g., task_title" required pattern="[a-zA-Z0-9_]+" title="Letters, numbers, and underscores only.">
            </div>
            <div class="form-group">
                <label>Field Label (Display) <span class="required">*</span></label>
                <input type="text" name="fields[INDEX][label]" placeholder="e.g., Task Title" required>
            </div>
            <div class="form-group">
                <label>Field Type <span class="required">*</span></label>
                <select name="fields[INDEX][type]" class="field-type-select" required>
                    <option value="text">Text (VARCHAR)</option>
                    <option value="textarea">Textarea (TEXT)</option>
                    <option value="number">Number (INT or DECIMAL)</option>
                    <option value="currency">Currency (DECIMAL)</option>
                    <option value="date">Date (DATE)</option>
                    <option value="datetime">Date & Time (DATETIME)</option>
                    <option value="select">Select Dropdown</option>
                    <option value="foreign_key">Foreign Key (Relational Select)</option>
                </select>
            </div>

            <div class="field-options" style="display:none;">
                <h4>Select Options</h4>
                <div class="options-list">
                </div>
                <button type="button" class="add-option-btn button add-button" style="font-size:0.9em; padding: 5px 8px;">Add Option</button>
                <div class="form-group" style="margin-top:10px;">
                     <label>Placeholder Text (Optional)</label>
                     <input type="text" name="fields[INDEX][select_placeholder]" placeholder="e.g., -- Select Status --">
                </div>
            </div>

            <div class="fk-options" style="display:none;">
                <h4>Foreign Key Setup</h4>
                <div class="form-group">
                    <label>Lookup Table Name <span class="required">*</span></label>
                    <input type="text" name="fields[INDEX][fk_lookup_table]" placeholder="e.g., users">
                </div>
                <div class="form-group">
                    <label>Lookup ID Column (in lookup table) <span class="required">*</span></label>
                    <input type="text" name="fields[INDEX][fk_lookup_id_column]" placeholder="e.g., user_id">
                </div>
                <div class="form-group">
                    <label>Lookup Value Column (to display) <span class="required">*</span></label>
                    <input type="text" name="fields[INDEX][fk_lookup_value_column]" placeholder="e.g., username">
                </div>
                 <div class="form-group" style="margin-top:10px;">
                     <label>Placeholder Text (Optional)</label>
                     <input type="text" name="fields[INDEX][fk_placeholder]" placeholder="e.g., -- Select User --">
                </div>
            </div>

            <div class="form-group">
                <input type="checkbox" name="fields[INDEX][list_display]" id="list_display_INDEX"> <label for="list_display_INDEX" class="inline-label">Show in List View</label>
            </div>
            <div class="form-group">
                <input type="checkbox" name="fields[INDEX][form_display]" id="form_display_INDEX" checked> <label for="form_display_INDEX" class="inline-label">Show in Form</label>
            </div>
            <div class="form-group">
                <input type="checkbox" name="fields[INDEX][required]" id="required_INDEX"> <label for="required_INDEX" class="inline-label">Required Field</label>
            </div>
            <div class="form-group">
                <input type="checkbox" name="fields[INDEX][searchable]" id="searchable_INDEX"> <label for="searchable_INDEX" class="inline-label">Searchable</label>
            </div>
             <hr style="margin-top:15px;">
        </div>
    </template>

    <template id="selectOptionTemplate">
        <div class="option-pair">
            <input type="text" name="fields[FIELD_INDEX][options_key][]" placeholder="Value (e.g., option_1)" required>
            <input type="text" name="fields[FIELD_INDEX][options_value][]" placeholder="Label (e.g., Option 1)" required>
            <button type="button" class="remove-option" onclick="this.parentElement.remove()">X</button>
        </div>
    </template>

    <script>
        // JavaScript remains the same as your previous version for field adding/handling
        // (The JS for adding fields, options, handling field type changes, and auto-populating identifiers)
        document.addEventListener('DOMContentLoaded', function () {
            const fieldsContainer = document.getElementById('fields-container');
            const addFieldBtn = document.getElementById('addFieldBtn');
            const fieldTemplate = document.getElementById('fieldTemplate');
            const selectOptionTemplate = document.getElementById('selectOptionTemplate');
            let fieldIndex = 0;
            const initialFieldsData = <?php echo json_encode($oldInput['fields'] ?? []); ?>;


            function createFieldElement(fieldData = {}, existingIndex = null) {
                const newField = fieldTemplate.content.cloneNode(true);
                const currentIndex = existingIndex !== null ? existingIndex : fieldIndex;

                newField.querySelectorAll('[name*="[INDEX]"]').forEach(el => {
                    el.name = el.name.replace('[INDEX]', `[${currentIndex}]`);
                });
                newField.querySelectorAll('[id*="_INDEX"]').forEach(el => {
                    const newId = el.id.replace('_INDEX', `_${currentIndex}`);
                    el.id = newId;
                    if (el.nextElementSibling && el.nextElementSibling.tagName === 'LABEL') {
                         el.nextElementSibling.setAttribute('for', newId);
                    }
                });

                const fieldNameInput = newField.querySelector('input[name*="[name]"]');
                const fieldLabelInput = newField.querySelector('input[name*="[label]"]');
                const fieldTypeSelect = newField.querySelector('select[name*="[type]"]');
                const listDisplayCheckbox = newField.querySelector('input[name*="[list_display]"]');
                const formDisplayCheckbox = newField.querySelector('input[name*="[form_display]"]');
                const requiredCheckbox = newField.querySelector('input[name*="[required]"]');
                const searchableCheckbox = newField.querySelector('input[name*="[searchable]"]');

                const fieldOptionsDiv = newField.querySelector('.field-options');
                const fkOptionsDiv = newField.querySelector('.fk-options');
                const addOptionBtn = newField.querySelector('.add-option-btn');
                const optionsListDiv = fieldOptionsDiv.querySelector('.options-list');

                // Populate with existing data if provided (for re-rendering on error)
                if (fieldData.name) fieldNameInput.value = fieldData.name;
                if (fieldData.label) fieldLabelInput.value = fieldData.label;
                if (fieldData.type) fieldTypeSelect.value = fieldData.type;
                if (fieldData.list_display) listDisplayCheckbox.checked = true;
                // form_display is checked by default in template, uncheck if explicitly false
                if (fieldData.hasOwnProperty('form_display') && !fieldData.form_display) formDisplayCheckbox.checked = false; else if (!fieldData.hasOwnProperty('form_display')) formDisplayCheckbox.checked = true; // Default from template
                if (fieldData.required) requiredCheckbox.checked = true;
                if (fieldData.searchable) searchableCheckbox.checked = true;


                function toggleOptionVisibility() {
                    fieldOptionsDiv.style.display = (fieldTypeSelect.value === 'select') ? 'block' : 'none';
                    fkOptionsDiv.style.display = (fieldTypeSelect.value === 'foreign_key') ? 'block' : 'none';
                    fkOptionsDiv.querySelectorAll('input').forEach(input => {
                        input.required = (fieldTypeSelect.value === 'foreign_key');
                    });
                }
                fieldTypeSelect.addEventListener('change', toggleOptionVisibility);
                toggleOptionVisibility(); // Initial check

                if (fieldData.type === 'select') {
                    if (fieldData.select_placeholder) newField.querySelector('input[name*="[select_placeholder]"]').value = fieldData.select_placeholder;
                    if (fieldData.options_key && fieldData.options_key.length) {
                        for (let i = 0; i < fieldData.options_key.length; i++) {
                            addOptionPairElement(optionsListDiv, currentIndex, fieldData.options_key[i], fieldData.options_value[i]);
                        }
                    }
                } else if (fieldData.type === 'foreign_key') {
                    if (fieldData.fk_lookup_table) newField.querySelector('input[name*="[fk_lookup_table]"]').value = fieldData.fk_lookup_table;
                    if (fieldData.fk_lookup_id_column) newField.querySelector('input[name*="[fk_lookup_id_column]"]').value = fieldData.fk_lookup_id_column;
                    if (fieldData.fk_lookup_value_column) newField.querySelector('input[name*="[fk_lookup_value_column]"]').value = fieldData.fk_lookup_value_column;
                    if (fieldData.fk_placeholder) newField.querySelector('input[name*="[fk_placeholder]"]').value = fieldData.fk_placeholder;
                }


                addOptionBtn.addEventListener('click', function () {
                    addOptionPairElement(optionsListDiv, currentIndex);
                });

                fieldsContainer.appendChild(newField);
                if (existingIndex === null) {
                    fieldIndex++;
                }
            }

            function addOptionPairElement(parentDiv, currentFieldIdx, key = '', val = '') {
                const newOptionPair = selectOptionTemplate.content.cloneNode(true);
                newOptionPair.querySelectorAll('[name*="[FIELD_INDEX]"]').forEach(el => {
                     el.name = el.name.replace('[FIELD_INDEX]', `[${currentFieldIdx}]`);
                });
                newOptionPair.querySelector('input[name*="[options_key]"]').value = key;
                newOptionPair.querySelector('input[name*="[options_value]"]').value = val;
                parentDiv.appendChild(newOptionPair);
            }


            // Repopulate fields from old input if available
            if (initialFieldsData && initialFieldsData.length > 0) {
                initialFieldsData.forEach((field, idx) => {
                    createFieldElement(field, idx);
                });
                fieldIndex = initialFieldsData.length; // Ensure new fields get correct index
            } else {
                 // Add one field by default if no old input
                createFieldElement();
            }


            addFieldBtn.addEventListener('click', () => createFieldElement());

            const moduleNameDisplayInput = document.getElementById('module_name_display');
            const moduleIdentifierInput = document.getElementById('module_identifier');
            const tableNameInput = document.getElementById('table_name');

            moduleNameDisplayInput.addEventListener('input', function() {
                let baseName = this.value.toLowerCase()
                                      .replace(/\s+/g, '_')
                                      .replace(/[^a-z0-9_]/g, '');
                if (!moduleIdentifierInput.value || moduleIdentifierInput.dataset.autoPopulated !== 'false') {
                    moduleIdentifierInput.value = baseName;
                    moduleIdentifierInput.dataset.autoPopulated = 'true';
                }
                if (!tableNameInput.value || tableNameInput.dataset.autoPopulated !== 'false') {
                    tableNameInput.value = baseName;
                    tableNameInput.dataset.autoPopulated = 'true';
                }
            });
            moduleIdentifierInput.addEventListener('input', () => { moduleIdentifierInput.dataset.autoPopulated = 'false'; });
            tableNameInput.addEventListener('input', () => { tableNameInput.dataset.autoPopulated = 'false'; });
        });
    </script>
     <footer>
        <p>&copy; <?php echo date('Y'); ?> Modular System</p>
    </footer>
</body>
</html>


