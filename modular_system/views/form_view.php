<?php
$pageTitle = htmlspecialchars(($action === 'create' ? 'Create New' : 'Edit') . ' ' . ($config['module_title_singular'] ?? 'Item'));
include __DIR__ . '/header.php';

$formErrors = $_SESSION['form_errors'] ?? [];
$formData = $_SESSION['form_data'] ?? $recordData ?? []; // Use session data if available (after failed submission)
unset($_SESSION['form_errors'], $_SESSION['form_data']);
?>

<h1><?php echo htmlspecialchars(($action === 'create' ? 'Create New' : 'Edit') . ' ' . ($config['module_title_singular'] ?? 'Item')); ?></h1>

<?php if (!empty($formErrors['general'])): ?>
    <div class="form-error general-error"><?php echo htmlspecialchars($formErrors['general']); ?></div>
<?php endif; ?>

<form method="POST" action="index.php?module=<?php echo urlencode($moduleIdentifier); ?>&action=save">
    <?php // IMPORTANT: Add CSRF token here in a real application ?>
    <?php if ($action === 'edit' && isset($formData[$config['primary_key']])): ?>
        <input type="hidden" name="<?php echo htmlspecialchars($config['primary_key']); ?>" value="<?php echo htmlspecialchars($formData[$config['primary_key']]); ?>">
    <?php endif; ?>

    <?php foreach ($config['fields'] as $fieldKey => $fieldConfig): ?>
        <?php if ($fieldConfig['form_display']): ?>
            <div class="form-group">
                <label for="<?php echo htmlspecialchars($fieldKey); ?>">
                    <?php echo htmlspecialchars($fieldConfig['label']); ?>
                    <?php if (!empty($fieldConfig['required'])): ?> <span class="required">*</span><?php endif; ?>
                </label>
                <?php
                $currentValue = $formData[$fieldKey] ?? $fieldConfig['default_value'] ?? '';
                $attributes = [];
                if (!empty($fieldConfig['required'])) $attributes[] = 'required';
                if (isset($fieldConfig['min'])) $attributes[] = 'min="' . htmlspecialchars($fieldConfig['min']) . '"';
                if (isset($fieldConfig['max'])) $attributes[] = 'max="' . htmlspecialchars($fieldConfig['max']) . '"';
                if (isset($fieldConfig['step'])) $attributes[] = 'step="' . htmlspecialchars($fieldConfig['step']) . '"';
                if (isset($fieldConfig['pattern'])) $attributes[] = 'pattern="' . htmlspecialchars($fieldConfig['pattern']) . '"';
                $attrString = implode(' ', $attributes);

                switch ($fieldConfig['type']) {
                    case 'text':
                        echo "<input type=\"text\" name=\"{$fieldKey}\" id=\"{$fieldKey}\" value=\"" . htmlspecialchars($currentValue) . "\" {$attrString}>";
                        break;
                    case 'number':
                        echo "<input type=\"number\" name=\"{$fieldKey}\" id=\"{$fieldKey}\" value=\"" . htmlspecialchars($currentValue) . "\" {$attrString}>";
                        break;
                    case 'currency': // Often handled as text with pattern or number with step
                        echo "<input type=\"number\" name=\"{$fieldKey}\" id=\"{$fieldKey}\" value=\"" . htmlspecialchars($currentValue) . "\" {$attrString} step=\"0.01\">";
                        break;
                    case 'date':
                        echo "<input type=\"date\" name=\"{$fieldKey}\" id=\"{$fieldKey}\" value=\"" . htmlspecialchars($currentValue) . "\" {$attrString}>";
                        break;
                    case 'textarea':
                        echo "<textarea name=\"{$fieldKey}\" id=\"{$fieldKey}\" {$attrString}>" . htmlspecialchars($currentValue) . "</textarea>";
                        break;
                    case 'select':
                        echo "<select name=\"{$fieldKey}\" id=\"{$fieldKey}\" {$attrString}>";
                        if (isset($fieldConfig['placeholder'])) {
                            echo "<option value=\"\">" . htmlspecialchars($fieldConfig['placeholder']) . "</option>";
                        }
                        foreach ($fieldConfig['options'] as $optVal => $optLabel) {
                            $selected = ($currentValue == $optVal && $currentValue !== '') ? ' selected' : '';
                            echo "<option value=\"" . htmlspecialchars($optVal) . "\"{$selected}>" . htmlspecialchars($optLabel) . "</option>";
                        }
                        echo "</select>";
                        break;
                    case 'foreign_key':
                        $lookupOptions = fetchLookupOptions($pdo, $fieldConfig);
                        echo "<select name=\"{$fieldKey}\" id=\"{$fieldKey}\" {$attrString}>";
                        if (isset($fieldConfig['placeholder'])) {
                            echo "<option value=\"\">" . htmlspecialchars($fieldConfig['placeholder']) . "</option>";
                        }
                        foreach ($lookupOptions as $optId => $optName) {
                            $selected = ($currentValue == $optId && $currentValue !== '') ? ' selected' : '';
                            echo "<option value=\"" . htmlspecialchars($optId) . "\"{$selected}>" . htmlspecialchars($optName) . "</option>";
                        }
                        echo "</select>";
                        break;
                    default:
                        echo "Unsupported field type: " . htmlspecialchars($fieldConfig['type']);
                }
                if (isset($formErrors[$fieldKey])): ?>
                    <div class="form-error"><?php echo htmlspecialchars($formErrors[$fieldKey]); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <div class="form-actions">
        <button type="submit" class="button save">Save <?php echo htmlspecialchars($config['module_title_singular'] ?? 'Item'); ?></button>
        <a href="index.php?module=<?php echo urlencode($moduleIdentifier); ?>&action=list" class="button cancel">Cancel</a>
    </div>
</form>

<?php include __DIR__ . '/footer.php'; ?>
