<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireAuth();

$user = currentUser();
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard · <?= APP_NAME ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="topbar">
        <span><?= APP_NAME ?></span>
        <div>
            <span><?= escapeHtml($user['nome']) ?></span>
            <a href="logout.php">Sair</a>
        </div>
    </header>

    <main class="container">
        <h1>Dashboard</h1>
        <p>Bem-vindo, <?= escapeHtml($user['nome']) ?>!</p>

        <!-- Conteúdo do projeto aqui -->
    </main>
</body>
</html>
