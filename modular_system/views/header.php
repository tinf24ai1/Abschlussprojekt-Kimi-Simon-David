<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
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
            <a href="index.php?module=my_books">Book Tracker</a> |
	    <a href="index.php?module=my_expenses">Expense Tracker</a>
<a href="create_module_form.php">Create New Module</a> |
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
