<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token inválido. Tenta novamente.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $error = 'Preenche todos os campos.';
        } else {
            $stmt = getDB()->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];

                $redirect = $_SESSION['redirect_after'] ?? 'dashboard.php';
                unset($_SESSION['redirect_after']);
                redirect($redirect);
            } else {
                $error = 'Email ou password incorretos.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login · <?= APP_NAME ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-page">
    <main class="login-container">
        <h1><?= APP_NAME ?></h1>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <?php if ($error): ?>
                <p class="error"><?= escapeHtml($error) ?></p>
            <?php endif; ?>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autofocus>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Entrar</button>
        </form>
    </main>
</body>
</html>
