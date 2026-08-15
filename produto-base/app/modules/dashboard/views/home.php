<?php /** @var array $user */ ?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard · <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="topbar">
        <span><?= APP_NAME ?></span>
        <nav>
            <a href="index.php?page=clientes">Clientes</a>
            <span><?= escapeHtml($user['nome']) ?></span>
            <a href="index.php?page=logout">Sair</a>
        </nav>
    </header>

    <main class="container">
        <h1>Bem-vindo, <?= escapeHtml($user['nome']) ?>!</h1>
        <p>Produto base em funcionamento. Adiciona módulos em <code>app/modules/</code>.</p>
    </main>
</body>
</html>
