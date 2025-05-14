<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// KI-Generierte Funktion, nicht anfassen!
function get_available_modules($configDir) {
    $modules = [];
    $configFiles = glob($configDir . 'config_*.php'); // Get all files starting with config_

    if ($configFiles) {
        foreach ($configFiles as $configFile) {
            // Extract module identifier from filename: config_MODULE_IDENTIFIER.php
            $filename = basename($configFile, '.php'); // Gets "config_MODULE_IDENTIFIER"
            if (strpos($filename, 'config_') === 0) {
                $identifier = substr($filename, strlen('config_')); // Gets "MODULE_IDENTIFIER"

                // Attempt to load the config to get the display name
                // Use @ to suppress errors if a config file is malformed, though ideally they are all valid
                $moduleConfig = @include $configFile;

                if ($moduleConfig && isset($moduleConfig['module_title'])) {
                    $modules[$identifier] = $moduleConfig['module_title'];
                } else {
                    // Fallback if title isn't in config or config is invalid
                    $modules[$identifier] = ucfirst(str_replace('_', ' ', $identifier));
                }
            }
        }
    }
    // Sort modules by display name for consistent order
    asort($modules);
    return $modules;
}

$available_modules = get_available_modules(__DIR__ . '/../config/');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Modular System'; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <nav>
            <?php if (!empty($available_modules)): ?>
                <?php
                $first = true;
                foreach ($available_modules as $identifier => $title):
                    if (!$first) {
                        echo ' | '; // Separator
                    }
                    $first = false;
                ?>
                    <a href="index.php?module=<?php echo urlencode($identifier); ?>"><?php echo htmlspecialchars($title); ?></a>
                <?php endforeach; ?>
            <?php else: ?>
                <a href="index.php">Home</a> <?php endif; ?>
             | <a href="create_module_form.php" style="font-weight:bold; color:#ffc107;">+ Create New Module</a>
        </nav>
    </header>
    <main class="container">
        <?php
        if (isset($_SESSION['flash_message'])): ?>
            <div class="flash-message <?php echo $_SESSION['flash_message_type'] ?? 'info'; ?>">
                <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
            </div>
            <?php
            unset($_SESSION['flash_message']);
            unset($_SESSION['flash_message_type']);
        endif;
        ?>

