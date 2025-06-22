<?php
// This file assumes a $pageTitle variable is set before it's included.
// It also checks for an active session to conditionally display elements.
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'KauansAllesDatenbank'); ?></title>
    <link rel="stylesheet" href="css/style.css" id="theme-link">
    <script src="js/theme.js" defer></script>
    <?php if(isset($_SESSION['user_id'])): // Only include these scripts for logged-in users ?>
        <script src="js/oneko/oneko.js"></script> 
    <?php endif; ?>
    <style>
        input[type="date"] { padding: 10px; border-radius: 6px; font-family: inherit; font-size: inherit; cursor: pointer; }
    </style>
</head>
<body>
    <header>
        <h1><?php echo htmlspecialchars($pageTitle ?? 'KauansAllesDatenbank'); ?> ⚙️</h1>
        <?php if(isset($_SESSION['user_id'])): ?>
            <div class="header-actions">
                <span class="user-info">(Angemeldet als: <?php echo htmlspecialchars($_SESSION['username']); ?>)</span>
                <div class="theme-selector">
                    <select id="theme-select" class="theme-dropdown">
                        <option value="css/style.css">Standard-Theme</option>
                        <option value="css/light.css">Helles Theme</option>
                        <option value="css/dark.css">Dunkles Theme</option>
                    </select>
                </div>
                <a href="logout.php" class="action-btn-secondary">Abmelden</a>
            </div>
        <?php endif; ?>
    </header>
    <main class="container">