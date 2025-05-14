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

            <form action="generate_module.php" method="POST" id="createModuleForm">

                <div class="form-section">
                    <h2>Module Details</h2>
                    <div class="form-group">
                        <label for="module_name_display">Module Display Name <span class="required">*</span></label>
                        <input type="text" id="module_name_display" name="module_name_display" placeholder="e.g., My Project Tasks" required>
                        <small>User-friendly name that will appear in titles.</small>
                    </div>
                    <div class="form-group">
                        <label for="module_identifier">Module Identifier <span class="required">*</span></label>
                        <input type="text" id="module_identifier" name="module_identifier" placeholder="e.g., my_project_tasks" required pattern="[a-z0-9_]+" title="Lowercase letters, numbers, and underscores only.">
                        <small>Used for filenames (config_MODULE_IDENTIFIER.php) and internal references. Lowercase letters, numbers, and underscores only.</small>
                    </div>
                    <div class="form-group">
                        <label for="table_name">Database Table Name <span class="required">*</span></label>
                        <input type="text" id="table_name" name="table_name" placeholder="e.g., project_tasks" required pattern="[a-zA-Z0-9_]+" title="Letters, numbers, and underscores only.">
                        <small>The actual name of the SQL table that will be created/used.</small>
                    </div>
                    <div class="form-group">
                        <label for="primary_key">Primary Key Field Name <span class="required">*</span></label>
                        <input type="text" id="primary_key" name="primary_key" value="id" required pattern="[a-zA-Z0-9_]+" title="Letters, numbers, and underscores only.">
                        <small>The name of the auto-incrementing primary key column (e.g., id, task_id).</small>
                    </div>
                </div>

                <div class="form-section">
                    <h2>Define Fields</h2>
                    <div id="fields-container">
                        </div>
                    <button type="button" id="addFieldBtn" class="button add-button">Add Field</button>
                </div>

                <div class="form-section">
                    <h2>Module Settings (Optional)</h2>
                    <div class="form-group">
                        <label for="default_sort_column">Default Sort Column</label>
                        <input type="text" id="default_sort_column" name="default_sort_column" placeholder="e.g., created_at (must be one of your defined fields)">
                    </div>
                    <div class="form-group">
                        <label for="default_sort_direction">Default Sort Direction</label>
                        <select id="default_sort_direction" name="default_sort_direction">
                            <option value="ASC">Ascending (ASC)</option>
                            <option value="DESC" selected>Descending (DESC)</option>
                        </select>
                    </div>
                     <div class="form-group">
                        <label for="records_per_page">Records Per Page</label>
                        <input type="number" id="records_per_page" name="records_per_page" value="10" min="1">
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
        document.addEventListener('DOMContentLoaded', function () {
            const fieldsContainer = document.getElementById('fields-container');
            const addFieldBtn = document.getElementById('addFieldBtn');
            const fieldTemplate = document.getElementById('fieldTemplate');
            const selectOptionTemplate = document.getElementById('selectOptionTemplate');
            let fieldIndex = 0;

            function addField() {
                const newField = fieldTemplate.content.cloneNode(true);
                
                // Update INDEX placeholders in names and ids
                newField.querySelectorAll('[name*="[INDEX]"]').forEach(el => {
                    el.name = el.name.replace('[INDEX]', `[${fieldIndex}]`);
                });
                newField.querySelectorAll('[id*="_INDEX"]').forEach(el => {
                    el.id = el.id.replace('_INDEX', `_${fieldIndex}`);
                    if (el.nextElementSibling && el.nextElementSibling.tagName === 'LABEL') {
                         el.nextElementSibling.setAttribute('for', el.id);
                    }
                });
                
                const fieldTypeSelect = newField.querySelector('.field-type-select');
                const fieldOptionsDiv = newField.querySelector('.field-options');
                const fkOptionsDiv = newField.querySelector('.fk-options');
                const addOptionBtn = newField.querySelector('.add-option-btn');

                fieldTypeSelect.addEventListener('change', function () {
                    fieldOptionsDiv.style.display = (this.value === 'select') ? 'block' : 'none';
                    fkOptionsDiv.style.display = (this.value === 'foreign_key') ? 'block' : 'none';
                    
                    // Set required for fk fields if visible
                    fkOptionsDiv.querySelectorAll('input').forEach(input => {
                        input.required = (this.value === 'foreign_key');
                    });
                });

                let currentFieldIndex = fieldIndex; // Closure for option template
                addOptionBtn.addEventListener('click', function () {
                    const newOptionPair = selectOptionTemplate.content.cloneNode(true);
                    newOptionPair.querySelectorAll('[name*="[FIELD_INDEX]"]').forEach(el => {
                         el.name = el.name.replace('[FIELD_INDEX]', `[${currentFieldIndex}]`);
                    });
                    fieldOptionsDiv.querySelector('.options-list').appendChild(newOptionPair);
                });

                fieldsContainer.appendChild(newField);
                fieldIndex++;
            }

            addFieldBtn.addEventListener('click', addField);

            // Add one field by default
            addField(); 
            
            // Auto-generate module_identifier and table_name from module_name_display
            const moduleNameDisplayInput = document.getElementById('module_name_display');
            const moduleIdentifierInput = document.getElementById('module_identifier');
            const tableNameInput = document.getElementById('table_name');

            moduleNameDisplayInput.addEventListener('input', function() {
                let baseName = this.value.toLowerCase()
                                      .replace(/\s+/g, '_') // Replace spaces with underscores
                                      .replace(/[^a-z0-9_]/g, ''); // Remove invalid chars
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

