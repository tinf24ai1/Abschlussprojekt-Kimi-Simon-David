<?php
require_once './include/db_config.php';

$error_message = null;

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = "Benutzername und Passwort sind erforderlich.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                header('Location: index.php');
                exit;
            } else {
                $error_message = "Ungültiger Benutzername oder Passwort.";
            }
        } catch (PDOException $e) {
            $error_message = "Datenbankfehler: " . htmlspecialchars($e->getMessage());
        }
    }
}

// --- Page Setup ---
$pageTitle = "Login";
require_once './include/header.php';
?>

<div class="section login-section" style="max-width: 500px; margin-top: 2rem;">
    <h2>Bitte anmelden</h2>
    <?php if ($error_message): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>
    <form action="login.php" method="post" class="styled-form">
        <input type="hidden" name="login" value="1">
        <div class="form-group">
            <label for="username">Benutzername:</label>
            <input type="text" id="username" name="username" required>
        </div>
        <div class="form-group">
            <label for="password">Passwort:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="action-btn">Anmelden</button>
    </form>
</div>

<?php
require_once './include/footer.php';
?>