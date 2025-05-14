<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Module</title>
    <link rel="stylesheet" href="css/style.css">
    </head>
<body>
    <header>
        <nav>
            <a href="index.php">Back to System</a>
             | <a href="create_module_form.php" style="font-weight:bold; color:#ffc107;">+ Create New Module</a>
        </nav>
    </header>
    <main class="container">
        <div class="module-creator-form">
            <h1>Create New Module/Tracker</h1>
            <p>Define the structure for your new module. This will generate a configuration file and a SQL `CREATE TABLE` statement.</p>

	// KI-Generiert, nicht anfassen!
            <?php
            if (session_status() == PHP_SESSION_NONE) { session_start(); }
            if (isset($_SESSION['generate_module_errors'])) {
                echo '<div class="flash-message error"><strong>Errors:</strong><ul>';
                foreach ($_SESSION['generate_module_errors'] as $error) { echo '<li>' . htmlspecialchars($error) . '</li>'; }
                echo '</ul></div>';
                if(isset($_SESSION['generated_php_config'])) {
                    echo '<h3>Generated PHP Config (Copy if file write failed):</h3>';
                    echo '<pre style="background:#f0f0f0; padding:10px; border:1px solid #ccc; white-space:pre-wrap; word-wrap:break-word;"><code>' . htmlspecialchars($_SESSION['generated_php_config']) . '</code></pre>';
                }
                if(isset($_SESSION['generated_sql_on_error'])) { // Changed from generated_sql to avoid conflict
                    echo '<h3>Generated SQL (Copy if file write failed or for manual execution):</h3>';
                    echo '<pre style="background:#f0f0f0; padding:10px; border:1px solid #ccc; white-space:pre-wrap; word-wrap:break-word;"><code>' . htmlspecialchars($_SESSION['generated_sql_on_error']) . '</code></pre>';
                }
                unset($_SESSION['generate_module_errors'], $_SESSION['generated_php_config'], $_SESSION['generated_sql_on_error']);
            }
            if (isset($_SESSION['generate_module_success'])) {
                echo '<div class="flash-message success">' . $_SESSION['generate_module_success'] . '</div>';
                if (isset($_SESSION['generated_sql'])) {
                    echo '<h3>Generated SQL `CREATE TABLE` Statement:</h3>';
                    if (isset($_SESSION['sql_execution_status']) && $_SESSION['sql_execution_status'] === 'success') {
                        echo '<p style="color:green; font-weight:bold;">The SQL was also executed successfully against the database.</p>';
                    } elseif (isset($_SESSION['sql_execution_status']) && $_SESSION['sql_execution_status'] === 'skipped') {
                         echo '<p style="color:orange; font-weight:bold;">Automatic SQL execution was skipped.</p>';
                    } elseif (isset($_SESSION['sql_execution_error'])) {
                        echo '<p style="color:red; font-weight:bold;">Error executing SQL automatically: ' . htmlspecialchars($_SESSION['sql_execution_error']) . '</p>';
                        echo '<p>Please review and run this SQL manually:</p>';
                    } else {
                         echo '<p>Please review and run this SQL manually:</p>';
                    }
                    echo '<pre style="background:#f0f0f0; padding:10px; border:1px solid #ccc; white-space:pre-wrap; word-wrap:break-word;"><code>' . htmlspecialchars($_SESSION['generated_sql']) . '</code></pre>';
                    unset($_SESSION['generated_sql'], $_SESSION['sql_execution_status'], $_SESSION['sql_execution_error']);
                }
                unset($_SESSION['generate_module_success']);
            }
            $oldInput = $_SESSION['generate_module_old_input'] ?? [];
            unset($_SESSION['generate_module_old_input']);
?> 

<!-- ab hier wieder anfassbar-->

            <form action="generate_module.php" method="POST" id="createModuleForm">
                <div class="form-section">
                    <h2>Module Details</h2>
                    <div class="form-group">
                        <label for="module_name_display">Module Display Name <span class="required">*</span></label>
                        <input type="text" id="module_name_display" name="module_name_display" placeholder="e.g., My Project Tasks" value="<?php echo htmlspecialchars($oldInput['module_name_display'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="module_identifier">Module Identifier <span class="required">*</span></label>
                        <input type="text" id="module_identifier" name="module_identifier" placeholder="e.g., my_project_tasks" value="<?php echo htmlspecialchars($oldInput['module_identifier'] ?? ''); ?>" required pattern="[a-z0-9_]+" title="0-9a-z_.">
                    </div>
                    <div class="form-group">
                        <label for="table_name">Database Table Name <span class="required">*</span></label>
                        <input type="text" id="table_name" name="table_name" placeholder="e.g., project_tasks" value="<?php echo htmlspecialchars($oldInput['table_name'] ?? ''); ?>" required pattern="[a-zA-Z0-9_]+" title="[a-zA-Z0-9_]">
                    </div>
                    <div class="form-group">
                        <label for="primary_key">Primary Key Field Name <span class="required">*</span></label>
                        <input type="text" id="primary_key" name="primary_key" value="<?php echo htmlspecialchars($oldInput['primary_key'] ?? 'id'); ?>" required pattern="[a-zA-Z0-9_]+" title="Zugelassen: [a-zA-Z0-9_]">
                    </div>
                </div>

                <div class="form-section">
                    <h2>Define Fields</h2>
                    <div id="fields-container"></div>
                    <button type="button" id="addFieldBtn" class="button add-button">Add Field</button>
                </div>

                <div class="form-section">
                    <h2>Module Settings (Optional)</h2>
                    <div class="form-group">
                        <label for="default_sort_column">Default Sort Column</label>
                        <input type="text" id="default_sort_column" name="default_sort_column" value="<?php echo htmlspecialchars($oldInput['default_sort_column'] ?? ''); ?>" placeholder="e.g., created_at">
                    </div>
                    <div class="form-group">
                        <label for="default_sort_direction">Default Sort Direction</label>
                        <select id="default_sort_direction" name="default_sort_direction">
                            <option value="ASC" <?php echo (($oldInput['default_sort_direction'] ?? 'DESC') === 'ASC' ? 'selected' : ''); ?>>Ascending</option>
                            <option value="DESC" <?php echo (($oldInput['default_sort_direction'] ?? 'DESC') === 'DESC' ? 'selected' : ''); ?>>Descending</option>
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
                            <strong>Warning:</strong> Enabling this attempts to execute `CREATE TABLE` SQL. Ensure DB user has permissions. Recommended for development. Backup if unsure.
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
            <button type="button" class="remove-field" onclick="this.parentElement.remove()">Remove Field</button>
            <div class="form-group">
                <label>Field Name (SQL Column) <span class="required">*</span></label>
                <input type="text" name="fields[INDEX][name]" placeholder="e.g., task_title" required pattern="[a-zA-Z0-9_]+">
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
                    <option value="number">Number (INT)</option>
                    <option value="currency">Currency (DECIMAL)</option>
                    <option value="date">Date (DATE)</option>
                    <option value="datetime">Date & Time (DATETIME)</option>
                    <option value="select">Select Dropdown</option>
                    <option value="foreign_key">Foreign Key (INT)</option>
                </select>
            </div>

            <div class="field-options-group" style="display:none;"> <h4>Select Options</h4>
                <div class="form-group">
                    <label>Options (comma-separated)</label>
                    <input type="text" name="fields[INDEX][select_options_string]" placeholder="key1:Label1,key2:Label2 OR Val1,Val2">
                    <small>E.g., <code>pending:Pending,in_progress:In Progress</code> or just <code>Low,Medium,High</code>.</small>
                </div>
                <div class="form-group">
                     <label>Placeholder Text (Optional)</label>
                     <input type="text" name="fields[INDEX][select_placeholder]" placeholder="e.g., -- Select Status --">
                </div>
            </div>

            <div class="fk-options-group" style="display:none;"> <h4>Foreign Key Setup (for UI - constraints are manual)</h4>
                <div class="form-group">
                    <label>Lookup Table Name <span class="required">*</span></label>
                    <input type="text" name="fields[INDEX][fk_lookup_table]" placeholder="e.g., users">
                </div>
                <div class="form-group">
                    <label>Lookup ID Column <span class="required">*</span></label>
                    <input type="text" name="fields[INDEX][fk_lookup_id_column]" placeholder="e.g., user_id">
                </div>
                <div class="form-group">
                    <label>Lookup Value Column <span class="required">*</span></label>
                    <input type="text" name="fields[INDEX][fk_lookup_value_column]" placeholder="e.g., username">
                </div>
                 <div class="form-group">
                     <label>Placeholder Text (Optional)</label>
                     <input type="text" name="fields[INDEX][fk_placeholder]" placeholder="e.g., -- Select User --">
                </div>
            </div>

            <div class="form-group">
                <input type="checkbox" name="fields[INDEX][list_display]" id="list_display_INDEX"> <label for="list_display_INDEX" class="inline-label">Show in List</label>
            </div>
            <div class="form-group">
                <input type="checkbox" name="fields[INDEX][form_display]" id="form_display_INDEX" checked> <label for="form_display_INDEX" class="inline-label">Show in Form</label>
            </div>
            <div class="form-group">
                <input type="checkbox" name="fields[INDEX][required]" id="required_INDEX"> <label for="required_INDEX" class="inline-label">Required</label>
            </div>
            <div class="form-group">
                <input type="checkbox" name="fields[INDEX][searchable]" id="searchable_INDEX"> <label for="searchable_INDEX" class="inline-label">Searchable</label>
            </div>
             <hr style="margin-top:15px; clear:both;">
        </div>
    </template>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const fieldsContainer = document.getElementById('fields-container');
        const addFieldBtn = document.getElementById('addFieldBtn');
        const fieldTemplate = document.getElementById('fieldTemplate');
        let fieldIndex = 0;
        const initialFieldsData = <?php echo json_encode($oldInput['fields'] ?? []); ?>;

        function createFieldElement(fieldData = {}, existingIndex = null) {
            const newFieldFragment = fieldTemplate.content.cloneNode(true);
            const newField = newFieldFragment.firstElementChild; // Get the actual div
            const currentIndex = existingIndex !== null ? existingIndex : fieldIndex;

            // Update IDs and names for uniqueness
            newField.innerHTML = newField.innerHTML.replace(/INDEX/g, currentIndex);

            const fieldTypeSelect = newField.querySelector('.field-type-select');
            const fieldOptionsGroup = newField.querySelector('.field-options-group');
            const fkOptionsGroup = newField.querySelector('.fk-options-group');

            // Populate with existing data if provided (for re-rendering on error/edit)
            if (fieldData.name) newField.querySelector(`input[name="fields[${currentIndex}][name]"]`).value = fieldData.name;
            if (fieldData.label) newField.querySelector(`input[name="fields[${currentIndex}][label]"]`).value = fieldData.label;
            if (fieldData.type) fieldTypeSelect.value = fieldData.type;

            if (fieldData.type === 'select') {
                if (fieldData.select_options_string) newField.querySelector(`input[name="fields[${currentIndex}][select_options_string]"]`).value = fieldData.select_options_string;
                if (fieldData.select_placeholder) newField.querySelector(`input[name="fields[${currentIndex}][select_placeholder]"]`).value = fieldData.select_placeholder;
            } else if (fieldData.type === 'foreign_key') {
                if (fieldData.fk_lookup_table) newField.querySelector(`input[name="fields[${currentIndex}][fk_lookup_table]"]`).value = fieldData.fk_lookup_table;
                if (fieldData.fk_lookup_id_column) newField.querySelector(`input[name="fields[${currentIndex}][fk_lookup_id_column]"]`).value = fieldData.fk_lookup_id_column;
                if (fieldData.fk_lookup_value_column) newField.querySelector(`input[name="fields[${currentIndex}][fk_lookup_value_column]"]`).value = fieldData.fk_lookup_value_column;
                if (fieldData.fk_placeholder) newField.querySelector(`input[name="fields[${currentIndex}][fk_placeholder]"]`).value = fieldData.fk_placeholder;
            }

            if (fieldData.list_display) newField.querySelector(`input[name="fields[${currentIndex}][list_display]"]`).checked = true;
            newField.querySelector(`input[name="fields[${currentIndex}][form_display]"]`).checked = fieldData.hasOwnProperty('form_display') ? !!fieldData.form_display : true; // Default true
            if (fieldData.required) newField.querySelector(`input[name="fields[${currentIndex}][required]"]`).checked = true;
            if (fieldData.searchable) newField.querySelector(`input[name="fields[${currentIndex}][searchable]"]`).checked = true;


            function toggleOptionVisibility() {
                fieldOptionsGroup.style.display = (fieldTypeSelect.value === 'select') ? 'block' : 'none';
                fkOptionsGroup.style.display = (fieldTypeSelect.value === 'foreign_key') ? 'block' : 'none';
                // Set required for fk fields if visible
                fkOptionsGroup.querySelectorAll('input[name*="[fk_"]').forEach(input => {
                    input.required = (fieldTypeSelect.value === 'foreign_key');
                });
                 // Set required for select_options_string if select type is chosen
                const selectOptsInput = fieldOptionsGroup.querySelector('input[name*="[select_options_string]"]');
                if (selectOptsInput) {
                    selectOptsInput.required = (fieldTypeSelect.value === 'select');
                }
            }

            fieldTypeSelect.addEventListener('change', toggleOptionVisibility);
            toggleOptionVisibility(); // Call once on creation

            fieldsContainer.appendChild(newField);
            if (existingIndex === null) {
                fieldIndex++;
            }
        }

        // Repopulate fields from old input if available
        if (initialFieldsData && initialFieldsData.length > 0) {
            initialFieldsData.forEach((field, idx) => {
                createFieldElement(field, idx);
            });
            fieldIndex = initialFieldsData.length; // Ensure new fields get correct index
        } else {
            createFieldElement(); // Add one field by default if no old input
        }

        addFieldBtn.addEventListener('click', () => createFieldElement());

        // Auto-populate identifier and table name
        const moduleNameDisplayInput = document.getElementById('module_name_display');
        const moduleIdentifierInput = document.getElementById('module_identifier');
        const tableNameInput = document.getElementById('table_name');

        moduleNameDisplayInput.addEventListener('input', function() {
            let baseName = this.value.toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '');
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

