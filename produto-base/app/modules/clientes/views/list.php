<?php /** @var array $clientes */ ?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes · <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="topbar">
        <span><?= APP_NAME ?></span>
        <nav>
            <a href="index.php?page=clientes">Clientes</a>
            <span><?= escapeHtml(currentUser()['nome']) ?></span>
            <a href="index.php?page=logout">Sair</a>
        </nav>
    </header>

    <main class="container">
        <h1>Clientes</h1>

        <?php if ($msg = flash('success')): ?>
            <p class="alert alert-success"><?= escapeHtml($msg) ?></p>
        <?php endif; ?>
        <?php if ($msg = flash('error')): ?>
            <p class="alert alert-error"><?= escapeHtml($msg) ?></p>
        <?php endif; ?>

        <form method="GET" class="search">
            <input type="hidden" name="page" value="clientes">
            <input type="text" name="q" value="<?= escapeHtml($_GET['q'] ?? '') ?>" placeholder="Pesquisar...">
            <button type="submit">Pesquisar</button>
        </form>

        <p><a class="btn" href="index.php?page=clientes&action=form">+ Novo cliente</a></p>

        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Telefone</th>
                    <th>Cidade</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clientes)): ?>
                    <tr><td colspan="5">Sem clientes.</td></tr>
                <?php endif; ?>
                <?php foreach ($clientes as $c): ?>
                    <tr>
                        <td><?= escapeHtml($c['nome']) ?></td>
                        <td><?= escapeHtml($c['email']) ?></td>
                        <td><?= escapeHtml($c['telefone']) ?></td>
                        <td><?= escapeHtml($c['cidade']) ?></td>
                        <td>
                            <a href="index.php?page=whatsapp&action=conversa&cliente_id=<?= (int)$c['id'] ?>">💬 WhatsApp</a>
                            <a href="index.php?page=clientes&action=form&id=<?= (int)$c['id'] ?>">Editar</a>
                            <a href="index.php?page=clientes&action=delete&id=<?= (int)$c['id'] ?>"
                               onclick="return confirm('Remover este cliente?')">Remover</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</body>
</html>
