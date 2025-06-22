<?php
require_once 'db_config.php'; // Includes $pdo or dies

$tableName = $_GET['table_name'] ?? null;
$entryId = $_GET['id'] ?? null;
$entry = null;
$columnsInfo = [];
$message = null;
$error_message = null;

// Validate table name and entry ID
if (!is_valid_name($tableName) || !is_numeric($entryId)) {
    die("Ungültige Anfrage. <a href='index.php'>Zurück</a>.");
}

// --- Handle Update Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_entry'])) {
    try {
        $stmtDesc = $pdo->query("DESCRIBE `" . $tableName . "`");
        $columnsMeta = $stmtDesc->fetchAll(PDO::FETCH_ASSOC);

        $updateSetParts = [];
        $updateValues = [];

        foreach ($columnsMeta as $colMeta) {
            $colName = $colMeta['Field'];
            // Cannot update the primary key 'id'
            if ($colName === 'id') continue;

            if (isset($_POST['data'][$colName])) {
                $updateSetParts[] = "`" . $colName . "` = ?";
                $submittedValue = $_POST['data'][$colName];
                // Handle checkboxes (booleans)
                if (strpos(strtoupper($colMeta['Type']), 'BOOL') !== false || strpos(strtoupper($colMeta['Type']), 'TINYINT(1)') !== false) {
                    $updateValues[] = isset($_POST['data'][$colName]) ? 1 : 0;
                } else {
                    $updateValues[] = ($submittedValue === '') ? null : $submittedValue;
                }
            } else if (strpos(strtoupper($colMeta['Type']), 'BOOL') !== false || strpos(strtoupper($colMeta['Type']), 'TINYINT(1)') !== false) {
                 // if checkbox is not submitted, it means its value is 0
                $updateSetParts[] = "`" . $colName . "` = ?";
                $updateValues[] = 0;
            }
        }

        if (!empty($updateSetParts)) {
            $sql = "UPDATE `" . $tableName . "` SET " . implode(', ', $updateSetParts) . " WHERE `id` = ?";
            $updateValues[] = $entryId; // Add the ID for the WHERE clause
            $stmtUpdate = $pdo->prepare($sql);
            $stmtUpdate->execute($updateValues);
            $message = "Eintrag erfolgreich aktualisiert.";
        } else {
            $error_message = "Keine Daten zum Aktualisieren vorhanden.";
        }
    } catch (PDOException $e) {
        $error_message = "Fehler beim Aktualisieren des Eintrags: " . htmlspecialchars($e->getMessage());
    }
}


// --- Fetch Entry Data for the Form ---
try {
    // Get column info
    $stmtColumns = $pdo->query("DESCRIBE `" . $tableName . "`");
    $columnsInfo = $stmtColumns->fetchAll(PDO::FETCH_ASSOC);

    // Fetch the specific entry
    $stmtData = $pdo->prepare("SELECT * FROM `" . $tableName . "` WHERE id = ?");
    $stmtData->execute([$entryId]);
    $entry = $stmtData->fetch(PDO::FETCH_ASSOC);

    if (!$entry) {
        $error_message = "Eintrag nicht gefunden.";
    }
} catch (PDOException $e) {
    $error_message = "Fehler beim Abrufen des Eintrags: " . htmlspecialchars($e->getMessage());
    $entry = null; // Ensure entry is null on error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eintrag bearbeiten in '<?php echo htmlspecialchars($tableName); ?>'</title>
    <link rel="stylesheet" href="style.css" id="theme-link">
    <script src="theme.js" defer></script>
</head>
<body>
    <script src="./oneko.js"></script>
    <header>
        <h1>Eintrag bearbeiten ✍️</h1>
        <h2>Tabelle: <?php echo htmlspecialchars($tableName); ?></h2>
    </header>

    <main class="container">
        <a href="display_table.php?table_name=<?php echo urlencode($tableName); ?>" class="action-btn-secondary">&laquo; Zurück zur Tabelle</a>

        <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>

        <?php if ($entry): ?>
            <section class="section">
                <h2>Eintrag mit ID: <?php echo htmlspecialchars($entry['id']); ?></h2>
                <form action="edit_entry.php?table_name=<?php echo urlencode($tableName); ?>&id=<?php echo urlencode($entryId); ?>" method="post" class="styled-form">
                    <input type="hidden" name="update_entry" value="1">
                    <?php foreach ($columnsInfo as $colInfo):
                        $fieldName = htmlspecialchars($colInfo['Field']);
                        // The primary key 'id' should not be editable
                        if ($fieldName === 'id') continue;
                    ?>
                        <div class="form-group">
                            <label for="data_<?php echo $fieldName; ?>">
                                <?php echo $fieldName; ?> (<?php echo htmlspecialchars($colInfo['Type']); ?>):
                            </label>
                            <?php
                                $currentValue = $entry[$colInfo['Field']];
                                $inputType = 'text';
                                $inputAttrs = '';
                                if (strpos(strtoupper($colInfo['Type']), 'DATE') !== false) {
                                    $inputType = 'date';
                                    $currentValue = date('Y-m-d', strtotime($currentValue));
                                } elseif (strpos(strtoupper($colInfo['Type']), 'BOOL') !== false || strpos(strtoupper($colInfo['Type']), 'TINYINT(1)') !== false) {
                                    $inputType = 'checkbox';
                                    $inputAttrs = $currentValue ? 'checked' : '';
                                } elseif (strpos(strtoupper($colInfo['Type']), 'INT') !== false) {
                                    $inputType = 'number';
                                }
                            ?>
                             <?php if ($inputType === 'checkbox'): ?>
                                <input type="<?php echo $inputType; ?>"
                                       id="data_<?php echo $fieldName; ?>"
                                       name="data[<?php echo $fieldName; ?>]"
                                       value="1"
                                       <?php echo $inputAttrs; ?>>
                            <?php else: ?>
                                <input type="<?php echo $inputType; ?>"
                                       id="data_<?php echo $fieldName; ?>"
                                       name="data[<?php echo $fieldName; ?>]"
                                       value="<?php echo htmlspecialchars($currentValue); ?>"
                                       <?php echo $inputAttrs; ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" class="action-btn">Änderungen speichern</button>
                </form>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>