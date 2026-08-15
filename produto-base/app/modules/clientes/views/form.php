<?php /** @var array $cliente Registro existente (vazio para criação) */ ?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= !empty($cliente) ? 'Editar' : 'Novo' ?> Cliente · <?= APP_NAME ?></title>
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
        <h1><?= !empty($cliente) ? 'Editar Cliente' : 'Novo Cliente' ?></h1>

        <?php if ($msg = flash('error')): ?>
            <p class="alert alert-error"><?= escapeHtml($msg) ?></p>
        <?php endif; ?>

        <form method="POST" action="index.php?page=clientes&action=save">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <?php if (!empty($cliente)): ?>
                <input type="hidden" name="id" value="<?= (int)$cliente['id'] ?>">
            <?php endif; ?>

            <label for="nome">Nome *</label>
            <input type="text" id="nome" name="nome" required
                   value="<?= escapeHtml($cliente['nome'] ?? '') ?>">

            <label for="email">Email</label>
            <input type="email" id="email" name="email"
                   value="<?= escapeHtml($cliente['email'] ?? '') ?>">

            <label for="telefone">Telefone</label>
            <input type="text" id="telefone" name="telefone"
                   value="<?= escapeHtml($cliente['telefone'] ?? '') ?>">

            <label for="cidade">Cidade</label>
            <input type="text" id="cidade" name="cidade"
                   value="<?= escapeHtml($cliente['cidade'] ?? '') ?>">

            <label for="observacoes">Observações</label>
            <textarea id="observacoes" name="observacoes" rows="4"><?= escapeHtml($cliente['observacoes'] ?? '') ?></textarea>

            <button type="submit">Guardar</button>
            <a href="index.php?page=clientes">Cancelar</a>
        </form>
    </main>
</body>
</html>
