<?php
/**
 * Controller do módulo WhatsApp Business.
 * Acções: conversa (ver histórico + enviar), webhook (Meta API)
 */

function whatsapp_conversa(): void
{
    requireAuth();

    if (!can(currentUser() ?? [], 'whatsapp.view')) {
        flash('error', 'Sem permissão para ver conversas.');
        redirect('index.php?page=clientes');
    }

    $clienteId = (int)($_GET['cliente_id'] ?? 0);
    $svc = new WhatsappService(getDB());

    require_once APP_ROOT . '/app/modules/clientes/service.php';
    $clienteSvc = new ClienteService(getDB());
    $cliente = $clienteSvc->find($clienteId);

    if (!$cliente) {
        flash('error', 'Cliente não encontrado.');
        redirect('index.php?page=clientes');
    }

    $numero = WhatsappService::normalizarNumero($cliente['telefone'] ?? '');
    $conversa = $svc->findConversa($clienteId);

    if ($conversa) {
        $mensagens = $svc->listarMensagens((int)$conversa['id']);
        $janelaAberta = $svc->janelaAberta((int)$conversa['id']);
    } else {
        $mensagens = [];
        $janelaAberta = false;
    }

    $templates = $svc->listarTemplates();
    $modoSimulacao = WHATSAPP_TOKEN === '' || WHATSAPP_PHONE_ID === '';

    require APP_ROOT . '/app/modules/whatsapp/views/conversa.php';
}

function whatsapp_enviar(): void
{
    requireAuth();

    if (!can(currentUser() ?? [], 'whatsapp.send')) {
        flash('error', 'Sem permissão para enviar mensagens.');
        redirect('index.php?page=clientes');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('index.php?page=clientes');
    }
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token inválido. Tenta novamente.');
        redirect('index.php?page=clientes');
    }

    $clienteId = (int)($_POST['cliente_id'] ?? 0);
    $template  = sanitize($_POST['template'] ?? '');

    $svc = new WhatsappService(getDB());

    if ($template !== '') {
        $result = $svc->enviarTemplate($clienteId, $template);
    } else {
        $texto = trim($_POST['texto'] ?? '');
        if ($texto === '') {
            flash('error', 'Escreve uma mensagem ou escolhe um template.');
            redirect('index.php?page=whatsapp&action=conversa&cliente_id=' . $clienteId);
        }
        $result = $svc->enviarTexto($clienteId, $texto);
    }

    if ($result['ok']) {
        flash('success', 'Mensagem enviada.');
    } else {
        flash('error', $result['error']);
    }

    redirect('index.php?page=whatsapp&action=conversa&cliente_id=' . $clienteId);
}

/**
 * Endpoint público chamado pela Meta Cloud API.
 * GET  = verificação (handshake) — CA-6
 * POST = webhook com mensagens/estados — CA-5, CA-8
 * Não exige autenticação (a Meta não faz login).
 */
function whatsapp_webhook(): void
{
    $svc = new WhatsappService(getDB());

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $mode = $_GET['hub_mode'] ?? '';
        $token = $_GET['hub_verify_token'] ?? '';
        $challenge = $_GET['hub_challenge'] ?? '';

        if ($mode === 'subscribe' && $svc->verificarWebhook($token)) {
            header('Content-Type: text/plain');
            echo $challenge; // a Meta exige a devolução exata do desafio
            return;
        }

        http_response_code(403);
        echo 'Verificação falhou';
        return;
    }

    // POST — corpo JSON enviado pela Meta
    $payload = json_decode(file_get_contents('php://input'), true);
    $svc->processarWebhook($payload ?: []);

    header('Content-Type: application/json');
    echo '{"status":"ok"}'; // resposta rápida evita retries da Meta
}
