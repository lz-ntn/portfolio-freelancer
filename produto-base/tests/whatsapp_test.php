<?php
/**
 * Testes de integração do módulo WhatsApp Business.
 * Cobre os critérios de aceitação CA-1..CA-9 da spec whatsapp-business.md.
 * $pdo já aponta para a base de testes (ver tests/run.php).
 */

// HTTP client fake: regista chamadas, não toca na rede
class FakeWhatsappHttpClient implements WhatsappHttpClient
{
    public $calls = [];
    public $failNext = false;
    private $n = 0;

    public function send(array $payload): array
    {
        $this->calls[] = $payload;
        $this->n++;
        if ($this->failNext) {
            $this->failNext = false;
            return ['ok' => false, 'external_id' => null, 'error' => 'API em baixo'];
        }
        return [
            'ok'          => true,
            'external_id' => 'wamid.fake.' . $this->n,
            'error'       => null,
        ];
    }
}

$http = new FakeWhatsappHttpClient();
$svc = new WhatsappService($pdo, $http, [
    'token'        => 'TOKEN_TESTE',
    'phone_id'     => '123',
    'verify_token' => 'verify-123',
    'api_url'      => 'https://graph.facebook.com/v19.0',
]);

// Helper: cria cliente com telefone
function criaClienteTeste(PDO $pdo, string $telefone, string $nome = 'Maria Silva'): int
{
    $pdo->prepare('INSERT INTO clientes (nome, telefone) VALUES (?, ?)')
        ->execute([$nome, $telefone]);
    return (int)$pdo->lastInsertId();
}

// Helper: insere mensagem recebida (abre janela de 24h)
function simulaRespostaRecebida(PDO $pdo, int $conversaId, string $externalId, int $minutosAtras = 5): void
{
    $created = date('Y-m-d H:i:s', time() - $minutosAtras * 60);
    $pdo->prepare(
        'INSERT INTO whatsapp_messages (conversa_id, external_id, direction, text, status, created_at)
         VALUES (?, ?, \'in\', ?, \'received\', ?)'
    )->execute([$conversaId, $externalId, 'Olá!', $created]);
}

echo "  --- CA-9: normalização de números (unit) ---\n";
assertEquals('+351912345678', WhatsappService::normalizarNumero('+351 912 345 678'), 'CA-9: +351 912 345 678 -> E.164');
assertEquals('+351912345678', WhatsappService::normalizarNumero('351912345678'), 'CA-9: 351912345678 -> E.164');
assertEquals('+351912345678', WhatsappService::normalizarNumero('+351912345678'), 'CA-9: +351912345678 -> E.164');
assertEquals('+351912345678', WhatsappService::normalizarNumero('(+351) 912-345-678'), 'CA-9: com parênteses/traços');
assertEquals('+123', WhatsappService::normalizarNumero('abc123'), 'CA-9: "abc123" -> "+123" (não valida)');
assertTrue(WhatsappService::numeroValido('+351912345678'), 'CA-9: número válido aceite');
assertTrue(!WhatsappService::numeroValido('+123'), 'CA-9: número curto rejeitado');

echo "  --- CA-1: envio de mensagem ---\n";
$clienteId = criaClienteTeste($pdo, '+351 912 345 678');
$conversaId = $svc->getOrCreateConversa($clienteId, '+351912345678');
simulaRespostaRecebida($pdo, $conversaId, 'wamid.in.1');
$http->calls = [];

$result = $svc->enviarTexto($clienteId, 'Olá Maria, segue o teu orçamento.');
assertTrue($result['ok'], 'CA-1: envio devolve ok=true');
assertEquals(1, count($http->calls), 'CA-1: API chamada 1 vez');
assertEquals('+351912345678', $http->calls[0]['to'], 'CA-1: número correcto enviado à API');
assertEquals('text', $http->calls[0]['type'], 'CA-1: tipo text enviado');

$msgs = $svc->listarMensagens($conversaId);
$last = end($msgs);
assertEquals('out', $last['direction'], 'CA-1: registo direction=out');
assertEquals('sent', $last['status'], 'CA-1: registo status=sent');
assertEquals('Olá Maria, segue o teu orçamento.', $last['text'], 'CA-1: texto registado');

echo "  --- CA-2: número inválido ---\n";
$clienteInvalido = criaClienteTeste($pdo, 'abc123');
$http->calls = [];
$result = $svc->enviarTexto($clienteInvalido, 'Oi');
assertTrue(!$result['ok'], 'CA-2: envio falha');
assertEquals('Número de WhatsApp inválido.', $result['error'], 'CA-2: erro explícito');
assertEquals(0, count($http->calls), 'CA-2: API NÃO é chamada');
$conversaInvalida = $svc->findConversa($clienteInvalido);
assertEquals(null, $conversaInvalida, 'CA-2: NÃO cria conversa');
$total = $pdo->query('SELECT COUNT(*) FROM whatsapp_messages WHERE text = \'Oi\'')->fetchColumn();
assertEquals(0, (int)$total, 'CA-2: NÃO cria registo em whatsapp_messages');

echo "  --- CA-3: permissão ---\n";
assertTrue(can(['role' => ROLE_ADMIN], 'whatsapp.send'), 'CA-3: admin pode enviar');
assertTrue(can(['role' => ROLE_GESTOR], 'whatsapp.send'), 'CA-3: gestor pode enviar');
assertTrue(!can(['role' => ROLE_COLABORADOR], 'whatsapp.send'), 'CA-3: colaborador NÃO pode enviar');
assertTrue(can(['role' => ROLE_COLABORADOR], 'whatsapp.view'), 'CA-3: colaborador pode ver histórico');

echo "  --- CA-4: histórico ordenado ---\n";
$cliente2 = criaClienteTeste($pdo, '+351 933 111 222', 'João Costa');
$conv2 = $svc->getOrCreateConversa($cliente2, '+351933111222');
$pdo->prepare(
    'INSERT INTO whatsapp_messages (conversa_id, direction, text, status, created_at) VALUES
     (?, \'out\', \'Primeira (há 3 dias)\', \'sent\', ?),
     (?, \'in\', \'Resposta (há 2 dias)\', \'received\', ?),
     (?, \'out\', \'Última (hoje)\', \'sent\', ?)'
)->execute([
    $conv2, date('Y-m-d H:i:s', time() - 3 * 86400),
    $conv2, date('Y-m-d H:i:s', time() - 2 * 86400),
    $conv2, date('Y-m-d H:i:s', time() - 3600),
]);
$historico = $svc->listarMensagens($conv2);
assertEquals(3, count($historico), 'CA-4: 3 mensagens listadas');
assertEquals('Primeira (há 3 dias)', $historico[0]['text'], 'CA-4: mais antiga primeiro');
assertEquals('Última (hoje)', $historico[2]['text'], 'CA-4: mais recente por último');
assertTrue(isset($historico[0]['direction']) && isset($historico[0]['status']), 'CA-4: mostra sentido e estado');

echo "  --- CA-5: recepção via webhook ---\n";
$http->calls = [];
$webhook = [
    'entry' => [[
        'changes' => [[
            'value' => [
                'messages' => [[
                    'id'     => 'wamid.in.99',
                    'from'   => '351912345678',
                    'text'   => ['body' => 'Sim, quero avançar'],
                ]],
            ],
        ]],
    ]],
];
$processados = $svc->processarWebhook($webhook);
assertEquals(1, $processados['messages'], 'CA-5: 1 mensagem processada');
$msgs = $svc->listarMensagens($conversaId);
$last = end($msgs);
assertEquals('in', $last['direction'], 'CA-5: direção in');
assertEquals('received', $last['status'], 'CA-5: estado received');
assertEquals('Sim, quero avançar', $last['text'], 'CA-5: texto da resposta');
assertEquals('wamid.in.99', $last['external_id'], 'CA-5: external_id da Meta guardado');

echo "  --- CA-5b: webhook cria lead se cliente não existe ---\n";
$webhookLead = [
    'entry' => [[
        'changes' => [[
            'value' => [
                'contacts' => [['profile' => ['name' => 'Nova Cliente']]],
                'messages' => [[
                    'id'   => 'wamid.in.lead1',
                    'from' => '351 944 000 111',
                    'text' => ['body' => 'Olá, quero saber mais'],
                ]],
            ],
        ]],
    ]],
];
$svc->processarWebhook($webhookLead);
$stmt = $pdo->prepare('SELECT id FROM clientes WHERE telefone = ?');
$stmt->execute(['+351944000111']);
$novoCliente = $stmt->fetch();
assertTrue($novoCliente !== false, 'CA-5b: lead criado com o número');

echo "  --- CA-6: verificação do webhook (handshake) ---\n";
assertTrue($svc->verificarWebhook('verify-123'), 'CA-6: token correcto aceite');
assertTrue(!$svc->verificarWebhook('token-errado'), 'CA-6: token errado rejeitado');

echo "  --- CA-7: envio de template ---\n";
$cliente3 = criaClienteTeste($pdo, '+351 955 555 444', 'Lead Novo'); // sem resposta -> fora da janela
$http->calls = [];
$result = $svc->enviarTemplate($cliente3, 'follow_up');
assertTrue($result['ok'], 'CA-7: template envia mesmo fora da janela');
assertEquals('template', $http->calls[0]['type'], 'CA-7: tipo template enviado');
assertEquals('follow_up', $http->calls[0]['template']['name'], 'CA-7: nome do template');
$conv3 = $svc->findConversa($cliente3);
$msgs3 = $svc->listarMensagens($conv3['id']);
$last = end($msgs3);
assertEquals(1, (int)$last['is_template'], 'CA-7: is_template=1');

// texto livre fora da janela deve falhar
$result = $svc->enviarTexto($cliente3, 'Oi de novo');
assertTrue(!$result['ok'], 'Regra 3: texto livre fora da janela NÃO envia');
assertEquals('Fora da janela de 24h. Usa um template aprovado.', $result['error'], 'Regra 3: mensagem clara');

echo "  --- CA-8: actualização de estado (idempotente) ---\n";
$cliente4 = criaClienteTeste($pdo, '+351 966 777 888', 'Cliente Estado');
$conv4 = $svc->getOrCreateConversa($cliente4, '+351966777888');
simulaRespostaRecebida($pdo, $conv4, 'wamid.in.4');
$http->calls = [];
$result = $svc->enviarTexto($cliente4, 'Mensagem com rastreio');
assertTrue($result['ok'], 'CA-8: envio inicial ok');
$msgId = $result['message_id'];
$external = $http->calls[0]['to']; // não interessa
$stmt = $pdo->prepare('SELECT external_id FROM whatsapp_messages WHERE id = ?');
$stmt->execute([$msgId]);
$externalId = $stmt->fetchColumn();

$statusDelivered = ['entry' => [['changes' => [['value' => [
    'statuses' => [['id' => $externalId, 'status' => 'delivered']],
]]]]]];
$svc->processarWebhook($statusDelivered);

$statusRead = ['entry' => [['changes' => [['value' => [
    'statuses' => [['id' => $externalId, 'status' => 'read']],
]]]]]];
$svc->processarWebhook($statusRead);

// entregue outra vez depois de "read" -> não volta atrás
$svc->processarWebhook($statusDelivered);

$stmt = $pdo->prepare('SELECT status FROM whatsapp_messages WHERE id = ?');
$stmt->execute([$msgId]);
assertEquals('read', $stmt->fetchColumn(), 'CA-8: estado evolui sent->delivered->read');
assertEquals(1, $pdo->query("SELECT COUNT(*) FROM whatsapp_messages WHERE external_id = '$externalId'")->fetchColumn(), 'CA-8: sem duplicar registos');
