<?php /** @var string $error */ ?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login · <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
    <main class="login-container">
        <h1><?= APP_NAME ?></h1>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <?php if ($error): ?>
                <p class="alert alert-error"><?= escapeHtml($error) ?></p>
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
