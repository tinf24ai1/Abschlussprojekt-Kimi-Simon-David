<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/lib/database.php'; 
require_once __DIR__ . '/lib/functions.php';

$moduleIdentifier = getModuleIdentifier();
$config = loadModuleConfig($moduleIdentifier);

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$searchTerm = $_GET['search'] ?? null;


try {
    switch ($action) {
        case 'list':
            $data = fetchData($pdo, $config, $searchTerm);
            include __DIR__ . '/views/list_view.php';
            break;

        case 'create':
            $recordData = null; // For consistency in the form view
            include __DIR__ . '/views/form_view.php';
            break;

        case 'edit':
            if (!$id) die("No ID provided for editing.");
            $recordData = fetchSingleRecord($pdo, $config, $id);
            if (!$recordData) die("Record not found.");
            include __DIR__ . '/views/form_view.php';
            break;

        case 'save':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') die("Invalid request method for save.");
            // Add CSRF token check here for security:
            // if (!verifyCsrfToken($_POST['csrf_token'])) die("CSRF token validation failed.");

            $recordIdToSave = $_POST[$config['primary_key']] ?? null;
            $savedId = saveRecord($pdo, $config, $_POST, $recordIdToSave);

            if ($savedId) {
                $_SESSION['flash_message'] = htmlspecialchars($config['module_title_singular'] ?? 'Item') . ($recordIdToSave ? ' updated' : ' created') . " successfully.";
                $_SESSION['flash_message_type'] = 'success';
                header("Location: index.php?module={$moduleIdentifier}&action=list");
                exit;
            } else {
                // Errors are in $_SESSION['form_errors'], data in $_SESSION['form_data']
                $redirectAction = $recordIdToSave ? 'edit' : 'create';
                $redirectIdParam = $recordIdToSave ? '&id=' . urlencode($recordIdToSave) : '';
                $_SESSION['flash_message'] = "Failed to save. Please correct the errors below.";
                $_SESSION['flash_message_type'] = 'error';
                header("Location: index.php?module={$moduleIdentifier}&action={$redirectAction}{$redirectIdParam}");
                exit;
            }
            break;

        case 'delete':
            if (!$id) die("No ID provided for deletion.");
            if (deleteRecord($pdo, $config, $id)) {
                 $_SESSION['flash_message'] = htmlspecialchars($config['module_title_singular'] ?? 'Item') . " deleted successfully.";
                 $_SESSION['flash_message_type'] = 'success';
            } else {
                 $_SESSION['flash_message'] = "Failed to delete " . htmlspecialchars($config['module_title_singular'] ?? 'Item') . ".";
                 $_SESSION['flash_message_type'] = 'error';
            }
            header("Location: index.php?module={$moduleIdentifier}&action=list");
            exit;

        default:
            die("Was tun Sie da, Mann?: " . htmlspecialchars($action)); // https://www.php.net/manual/en/function.htmlspecialchars.php
    }
} catch (PDOException $e) {
    error_log("Database Error in index.php: " . $e->getMessage());
    // User-friendly message
    die("Fehler!: " . $e->getMessage());
} catch (Exception $e) {
    // Log error
    error_log("General Error in index.php: " . $e->getMessage());
    die("Fehler!: " . $e->getMessage());
}

?>
