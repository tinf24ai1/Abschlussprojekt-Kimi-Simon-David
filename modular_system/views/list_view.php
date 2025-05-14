<?php
$pageTitle = htmlspecialchars($config['module_title']);
include __DIR__ . '/header.php';
?>

<h1><?php echo htmlspecialchars($config['module_title']); ?></h1>

<div class="actions-bar">
    <?php if (in_array('create', $config['list_actions'])): ?>
        <a href="index.php?module=<?php echo urlencode($moduleIdentifier); ?>&action=create" class="button add-new">Add New <?php echo htmlspecialchars($config['module_title_singular'] ?? ''); ?></a>
    <?php endif; ?>
    <form method="GET" action="index.php" class="search-form">
        <input type="hidden" name="module" value="<?php echo htmlspecialchars($moduleIdentifier); ?>">
        <input type="hidden" name="action" value="list">
        <input type="search" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
        <button type="submit">Search</button>
        <?php if(!empty($_GET['search'])): ?>
             <a href="index.php?module=<?php echo urlencode($moduleIdentifier); ?>&action=list" class="button">Clear Search</a>
        <?php endif; ?>
    </form>
</div>


<?php if (!empty($data)): ?>
    <table>
        <thead>
            <tr>
                <?php foreach ($config['fields'] as $fieldKey => $fieldConfig): ?>
                    <?php if ($fieldConfig['list_display']): ?>
                        <th><?php echo htmlspecialchars($fieldConfig['label']); ?></th>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (!empty($config['list_actions'])): ?>
                    <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data as $row): ?>
            <tr>
                <?php foreach ($config['fields'] as $fieldKey => $fieldConfig): ?>
                    <?php if ($fieldConfig['list_display']): ?>
                        <td>
                            <?php
                            $value = $row[$fieldKey] ?? '';
                            if ($fieldConfig['type'] === 'foreign_key' && isset($row[$fieldKey . '_display'])) {
                                echo htmlspecialchars($row[$fieldKey . '_display']);
                            } elseif ($fieldConfig['type'] === 'date' && !empty($value)) {
                                echo htmlspecialchars(date('M d, Y', strtotime($value)));
                            } elseif ($fieldConfig['type'] === 'currency' && is_numeric($value)) {
                                echo '$' . htmlspecialchars(number_format((float)$value, 2));
                            } elseif ($fieldConfig['type'] === 'select' && isset($fieldConfig['options'][$value])) {
                                echo htmlspecialchars($fieldConfig['options'][$value]);
                            } else {
                                echo nl2br(htmlspecialchars($value));
                            }
                            ?>
                        </td>
                    <?php endif; ?>
                <?php endforeach; ?>
                <td>
                    <?php if (in_array('edit', $config['list_actions'])): ?>
                        <a href="index.php?module=<?php echo urlencode($moduleIdentifier); ?>&action=edit&id=<?php echo urlencode($row[$config['primary_key']]); ?>" class="button edit">Edit</a>
                    <?php endif; ?>
                    <?php if (in_array('delete', $config['list_actions'])): ?>
                        <a href="index.php?module=<?php echo urlencode($moduleIdentifier); ?>&action=delete&id=<?php echo urlencode($row[$config['primary_key']]); ?>" class="button delete" onclick="return confirmDelete();">Delete</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No records found<?php echo !empty($_GET['search']) ? ' for your search term "' . htmlspecialchars($_GET['search']) . '"' : ''; ?>.</p>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
