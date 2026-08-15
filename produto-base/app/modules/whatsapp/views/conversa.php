<?php
/**
 * View: conversa WhatsApp de um cliente.
 * Variáveis disponíveis: $cliente, $conversa, $mensagens, $templates, $janelaAberta, $modoSimulacao
 */
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp · <?= escapeHtml($cliente['nome']) ?> · <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .chat-box { border:1px solid var(--border,#E2E8F0); border-radius:12px; padding:1.5rem; background:#fff; display:flex; flex-direction:column; gap:0.6rem; max-height:480px; overflow-y:auto; }
        .msg { max-width:75%; padding:0.55rem 0.9rem; border-radius:12px; font-size:0.92rem; word-wrap:break-word; }
        .msg.out { align-self:flex-end; background:#dcf8c6; border-top-right-radius:2px; }
        .msg.in { align-self:flex-start; background:#f1f3f4; border-top-left-radius:2px; }
        .msg .meta { display:block; font-size:0.7rem; color:#5f6368; margin-top:0.25rem; }
        .msg .badge { font-weight:600; }
        .badge { padding:0.15rem 0.5rem; border-radius:10px; font-size:0.7rem; }
        .badge-sent { background:#e2e8f0; color:#475569; }
        .badge-delivered { background:#dbeafe; color:#1e40af; }
        .badge-read { background:#bbf7d0; color:#166534; }
        .badge-failed { background:#fee2e2; color:#b91c1c; }
        .badge-pending { background:#fef3c7; color:#92400e; }
        .badge-received { background:#ede9fe; color:#6d28d9; }
        .sim-note { background:#fef3c7; border:1px solid #f59e0b; color:#92400e; border-radius:8px; padding:0.7rem 1rem; font-size:0.85rem; margin-bottom:1rem; }
        .form-grid { display:grid; grid-template-columns:1fr auto; gap:0.75rem; margin-top:1rem; }
        .form-grid textarea { min-height:70px; }
        .template-row { display:flex; gap:0.75rem; align-items:center; margin-top:0.75rem; }
        .muted { color:#5f6368; font-size:0.85rem; }
        .whatsapp-icon { color:#25D366; font-weight:700; }
    </style>
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
        <p><a href="index.php?page=clientes">&larr; Voltar aos clientes</a></p>
        <h1><span class="whatsapp-icon">💬</span> <?= escapeHtml($cliente['nome']) ?></h1>
        <p class="muted">
            <?= escapeHtml($cliente['telefone']) ?>
            (normalizado: <?= escapeHtml(WhatsappService::normalizarNumero($cliente['telefone'] ?? '')) ?>)
        </p>

        <?php if ($modoSimulacao): ?>
            <div class="sim-note">
                <strong>Modo simulação:</strong> o token/phone_id da Meta não estão configurados.
                As mensagens são registadas no histórico mas NÃO são enviadas de verdade.
                Configura <code>WHATSAPP_TOKEN</code> e <code>WHATSAPP_PHONE_ID</code> em <code>app/core/config.php</code> para produção.
            </div>
        <?php endif; ?>

        <?php if ($msg = flash('success')): ?>
            <p class="alert alert-success"><?= escapeHtml($msg) ?></p>
        <?php endif; ?>
        <?php if ($msg = flash('error')): ?>
            <p class="alert alert-error"><?= escapeHtml($msg) ?></p>
        <?php endif; ?>

        <div class="chat-box">
            <?php if (empty($mensagens)): ?>
                <p class="muted">Sem mensagens ainda. Envia a primeira para iniciar a conversa (a Meta exige template se o cliente nunca respondeu).</p>
            <?php endif; ?>

            <?php foreach ($mensagens as $m): ?>
                <div class="msg <?= $m['direction'] === 'out' ? 'out' : 'in' ?>">
                    <?php if ($m['is_template']): ?><span class="badge badge-sent">📋 template</span><?php endif; ?>
                    <?= escapeHtml($m['text']) ?>
                    <span class="meta">
                        <?= date('d/m/Y H:i', strtotime($m['created_at'])) ?>
                        ·
                        <?= $m['direction'] === 'out' ? 'enviada' : 'recebida' ?>
                        ·
                        <span class="badge badge-<?= escapeHtml($m['status']) ?>"><?= escapeHtml($m['status']) ?></span>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (can(currentUser() ?? [], 'whatsapp.send')): ?>
            <form method="POST" action="index.php?page=whatsapp&action=enviar">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="cliente_id" value="<?= (int)$cliente['id'] ?>">

                <div class="form-grid">
                    <textarea name="texto" placeholder="Escreve a mensagem..."
                        <?= !$janelaAberta && !empty($conversa) ? 'disabled' : '' ?>
                    ></textarea>
                    <button type="submit" class="btn"
                        <?= !$janelaAberta && !empty($conversa) ? 'disabled' : '' ?>
                    >Enviar</button>
                </div>

                <?php if (!$janelaAberta && !empty($conversa)): ?>
                    <p class="muted">⚠️ O cliente não respondeu nas últimas 24h — texto livre está bloqueado. Usa um template abaixo.</p>
                <?php endif; ?>

                <?php if (!empty($templates)): ?>
                    <div class="template-row">
                        <label>Ou envia template:</label>
                        <select name="template">
                            <option value="">— escolher —</option>
                            <?php foreach ($templates as $t): ?>
                                <option value="<?= escapeHtml($t['nome']) ?>">
                                    <?= escapeHtml($t['nome']) ?> (<?= escapeHtml($t['exemplo']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn">Enviar template</button>
                    </div>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <p class="muted">A tua permissão não permite enviar mensagens (apenas ver histórico).</p>
        <?php endif; ?>
    </main>
</body>
</html>
