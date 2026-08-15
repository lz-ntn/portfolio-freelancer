<?php
/**
 * WhatsAppService — lógica de negócio do módulo WhatsApp Business.
 *
 * Desenhado para ser testável:
 *   - Recebe PDO injectado (base de testes nos testes)
 *   - Recebe um HTTP client injectável (fake nos testes, Meta real em produção)
 *   - Modo simulação: token vazio => nunca chama a API real
 *
 * Fonte de verdade = base de dados. A BD regista PRIMEIRO (estado pending),
 * depois a API é chamada e o estado é actualizado (sent / failed).
 */

interface WhatsappHttpClient
{
    /**
     * Envia um payload para a API da Meta.
     * @return array{ok: bool, external_id: ?string, error: ?string}
     */
    public function send(array $payload): array;
}

/**
 * Client real da Meta Cloud API (produção).
 */
class MetaWhatsappClient implements WhatsappHttpClient
{
    private $token;
    private $phoneId;
    private $apiUrl;

    public function __construct(string $token, string $phoneId, string $apiUrl)
    {
        $this->token   = $token;
        $this->phoneId = $phoneId;
        $this->apiUrl  = $apiUrl;
    }

    public function send(array $payload): array
    {
        $url = rtrim($this->apiUrl, '/') . '/' . $this->phoneId . '/messages';
        $body = array_merge(['messaging_product' => 'whatsapp'], $payload);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error !== '' || $httpCode < 200 || $httpCode >= 300) {
            return [
                'ok'          => false,
                'external_id' => null,
                'error'       => $error !== '' ? $error : 'API WhatsApp respondeu HTTP ' . $httpCode,
            ];
        }

        $decoded = json_decode($response, true);
        $externalId = $decoded['messages'][0]['id'] ?? null;

        return [
            'ok'          => true,
            'external_id' => $externalId ?: ('wamid.' . uniqid('', true)),
            'error'       => null,
        ];
    }
}

/**
 * Client de simulação: nunca toca na rede.
 * Usado quando o token está vazio (demo) e nos testes.
 */
class SimulatedWhatsappClient implements WhatsappHttpClient
{
    public function send(array $payload): array
    {
        return [
            'ok'          => true,
            'external_id' => 'sim.' . $payload['to'] . '.' . uniqid('', true),
            'error'       => null,
        ];
    }
}

class WhatsappService
{
    /** @var PDO */
    private $db;

    /** @var WhatsappHttpClient */
    private $http;

    /** @var array */
    private $config;

    public function __construct(
        PDO $db,
        ?WhatsappHttpClient $http = null,
        array $config = [
            'token'        => WHATSAPP_TOKEN,
            'phone_id'     => WHATSAPP_PHONE_ID,
            'verify_token' => WHATSAPP_VERIFY_TOKEN,
            'api_url'      => WHATSAPP_API_URL,
        ]
    ) {
        $this->db = $db;
        $this->config = $config;
        $this->http = $http ?? $this->createDefaultClient();
    }

    private function createDefaultClient(): WhatsappHttpClient
    {
        if ($this->config['token'] === '' || $this->config['phone_id'] === '') {
            return new SimulatedWhatsappClient();
        }
        return new MetaWhatsappClient(
            $this->config['token'],
            $this->config['phone_id'],
            $this->config['api_url']
        );
    }

    /**
     * CA-9: normaliza qualquer formato para E.164 (ex.: +351912345678).
     * "+351 912 345 678", "351912345678", "+351912345678" -> "+351912345678"
     */
    public static function normalizarNumero(string $raw): string
    {
        $digits = preg_replace('/[^0-9]/', '', $raw);
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) >= 12 && substr($digits, 0, 2) === '00') {
            $digits = substr($digits, 2); // prefixo internacional 00XX -> XX
        }
        return '+' . $digits;
    }

    /**
     * CA-2: validação E.164 (8-15 dígitos com +).
     */
    public static function numeroValido(string $e164): bool
    {
        return (bool)preg_match('/^\+[0-9]{8,15}$/', $e164);
    }

    /**
     * Devolve a conversa de um cliente com o número normalizado (cria se faltar).
     */
    public function getOrCreateConversa(int $clienteId, string $numeroE164): int
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM conversas WHERE numero_e164 = ?'
        );
        $stmt->execute([$numeroE164]);
        $conversa = $stmt->fetch();
        if ($conversa) {
            return (int)$conversa['id'];
        }

        $stmt = $this->db->prepare(
            'SELECT id FROM conversas WHERE cliente_id = ? LIMIT 1'
        );
        $stmt->execute([$clienteId]);
        $conversa = $stmt->fetch();
        if ($conversa) {
            return (int)$conversa['id'];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO conversas (cliente_id, numero_e164) VALUES (?, ?)'
        );
        $stmt->execute([$clienteId, $numeroE164]);
        return (int)$this->db->lastInsertId();
    }

    public function findConversa(int $clienteId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM conversas WHERE cliente_id = ? LIMIT 1');
        $stmt->execute([$clienteId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * CA-4: histórico de uma conversa, por ordem cronológica.
     */
    public function listarMensagens(int $conversaId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, external_id, direction, text, is_template, status, created_at
             FROM whatsapp_messages
             WHERE conversa_id = ?
             ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute([$conversaId]);
        return $stmt->fetchAll();
    }

    public function listarTemplates(): array
    {
        $stmt = $this->db->query(
            'SELECT id, nome, language_code, exemplo FROM whatsapp_templates WHERE ativo = 1 ORDER BY nome'
        );
        return $stmt->fetchAll();
    }

    /**
     * Regra 3: texto livre só dentro da janela de 24h (última mensagem recebida).
     */
    public function janelaAberta(int $conversaId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT created_at FROM whatsapp_messages
             WHERE conversa_id = ? AND direction = \'in\'
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$conversaId]);
        $row = $stmt->fetch();

        if (!$row) {
            return false; // nunca recebeu -> obrigatório template
        }
        return (time() - strtotime($row['created_at'])) < 86400;
    }

    /**
     * CA-1/CA-2: envia texto livre (dentro da janela de 24h).
     *
     * @return array{ok: bool, error: ?string, message_id: ?int}
     */
    public function enviarTexto(int $clienteId, string $texto): array
    {
        $cliente = $this->findCliente($clienteId);
        if (!$cliente) {
            return ['ok' => false, 'error' => 'Cliente não encontrado.', 'message_id' => null];
        }

        $numero = self::normalizarNumero($cliente['telefone'] ?? '');
        if (!self::numeroValido($numero)) {
            return ['ok' => false, 'error' => 'Número de WhatsApp inválido.', 'message_id' => null];
        }

        $conversaId = $this->getOrCreateConversa($clienteId, $numero);

        if (!$this->janelaAberta($conversaId)) {
            return [
                'ok'          => false,
                'error'       => 'Fora da janela de 24h. Usa um template aprovado.',
                'message_id'  => null,
            ];
        }

        // Regra 7: regista PRIMEIRO (fonte de verdade = BD)
        $messageId = $this->guardarMensagem($conversaId, 'out', $texto, 0);

        try {
            $result = $this->http->send([
                'to'   => $numero,
                'type' => 'text',
                'text' => ['body' => $texto],
            ]);
        } catch (Throwable $e) {
            $result = ['ok' => false, 'external_id' => null, 'error' => 'Falha na ligação à API WhatsApp.'];
        }

        $this->actualizarEstadoEnvio($messageId, $result);

        return [
            'ok'         => $result['ok'],
            'error'      => $result['error'],
            'message_id' => $messageId,
        ];
    }

    /**
     * CA-7: envia template aprovado (funciona mesmo fora da janela de 24h).
     */
    public function enviarTemplate(int $clienteId, string $templateNome): array
    {
        $cliente = $this->findCliente($clienteId);
        if (!$cliente) {
            return ['ok' => false, 'error' => 'Cliente não encontrado.', 'message_id' => null];
        }

        $numero = self::normalizarNumero($cliente['telefone'] ?? '');
        if (!self::numeroValido($numero)) {
            return ['ok' => false, 'error' => 'Número de WhatsApp inválido.', 'message_id' => null];
        }

        $template = $this->findTemplate($templateNome);
        if (!$template) {
            return ['ok' => false, 'error' => 'Template não encontrado.', 'message_id' => null];
        }

        $conversaId = $this->getOrCreateConversa($clienteId, $numero);

        $messageId = $this->guardarMensagem($conversaId, 'out', $template['exemplo'], 1);

        try {
            $result = $this->http->send([
                'to'       => $numero,
                'type'     => 'template',
                'template' => [
                    'name'     => $template['nome'],
                    'language' => ['code' => $template['language_code']],
                ],
            ]);
        } catch (Throwable $e) {
            $result = ['ok' => false, 'external_id' => null, 'error' => 'Falha na ligação à API WhatsApp.'];
        }

        $this->actualizarEstadoEnvio($messageId, $result);

        return [
            'ok'         => $result['ok'],
            'error'      => $result['error'],
            'message_id' => $messageId,
        ];
    }

    /**
     * CA-5: processa uma mensagem recebida vinda da Meta (webhook POST).
     * Idempotente: external_id único => não duplica.
     */
    public function processarWebhook(array $payload): array
    {
        $processados = ['messages' => 0, 'statuses' => 0];

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                foreach ($value['messages'] ?? [] as $msg) {
                    $this->processarMensagemRecebida($msg);
                    $processados['messages']++;
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    $this->processarStatus($status);
                    $processados['statuses']++;
                }
            }
        }

        return $processados;
    }

    private function processarMensagemRecebida(array $msg): void
    {
        $externalId = (string)($msg['id'] ?? '');
        $numero     = self::normalizarNumero((string)($msg['from'] ?? ''));
        $texto      = (string)($msg['text']['body'] ?? '');

        if ($externalId === '' || !self::numeroValido($numero)) {
            return;
        }

        // CA-5: encontra o cliente pelo número; senão cria um lead
        $cliente = $this->findClientePorNumero($numero);
        if (!$cliente) {
            $nome = 'Lead ' . $numero;
            if (!empty($msg['contacts'][0]['profile']['name'])) {
                $nome = $msg['contacts'][0]['profile']['name'];
            }
            $clienteId = $this->criarLead($nome, $numero);
        } else {
            $clienteId = (int)$cliente['id'];
        }

        // Idempotência: já processada?
        $stmt = $this->db->prepare('SELECT id FROM whatsapp_messages WHERE external_id = ?');
        $stmt->execute([$externalId]);
        if ($stmt->fetch()) {
            return;
        }

        $conversaId = $this->getOrCreateConversa($clienteId, $numero);

        $this->db->prepare(
            'INSERT INTO whatsapp_messages (conversa_id, external_id, direction, text, status)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$conversaId, $externalId, 'in', $texto, 'received']);

        $this->db->prepare(
            'UPDATE conversas SET last_message_at = NOW() WHERE id = ?'
        )->execute([$conversaId]);
    }

    /**
     * CA-8: actualiza estado (delivered/read) sem duplicar e sem voltar atrás.
     */
    private function processarStatus(array $status): void
    {
        $externalId = (string)($status['id'] ?? '');
        $novoStatus = strtolower((string)($status['status'] ?? ''));

        if ($externalId === '' || !in_array($novoStatus, ['sent', 'delivered', 'read', 'failed'], true)) {
            return;
        }

        $stmt = $this->db->prepare(
            'SELECT id, status FROM whatsapp_messages WHERE external_id = ?'
        );
        $stmt->execute([$externalId]);
        $msg = $stmt->fetch();
        if (!$msg) {
            return;
        }

        if ($this->estadoPodeEvoluir($msg['status'], $novoStatus)) {
            $this->db->prepare(
                'UPDATE whatsapp_messages SET status = ? WHERE id = ?'
            )->execute([$novoStatus, $msg['id']]);
        }
    }

    /**
     * Regra 4/6: sent -> delivered -> read (e -> failed); nunca volta atrás.
     */
    private function estadoPodeEvoluir(string $atual, string $novo): bool
    {
        $ordem = ['pending' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 4];
        return ($ordem[$novo] ?? 0) >= ($ordem[$atual] ?? 0);
    }

    /**
     * CA-6: handshake do webhook com a Meta.
     * Recusa se o verify_token não estiver configurado (proteção em produção).
     */
    public function verificarWebhook(string $verifyToken): bool
    {
        $configurado = (string)$this->config['verify_token'];
        if ($configurado === '') {
            return false;
        }
        return hash_equals($configurado, $verifyToken);
    }

    // ---- helpers internos ----

    private function findCliente(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, nome, telefone FROM clientes WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function findClientePorNumero(string $numeroE164): ?array
    {
        $clientes = $this->db->query('SELECT id, telefone FROM clientes')->fetchAll();
        foreach ($clientes as $cliente) {
            if (self::normalizarNumero((string)$cliente['telefone']) === $numeroE164) {
                return ['id' => (int)$cliente['id']];
            }
        }
        return null;
    }

    private function criarLead(string $nome, string $numeroE164): int
    {
        $this->db->prepare(
            'INSERT INTO clientes (nome, telefone) VALUES (?, ?)'
        )->execute([$nome, $numeroE164]);
        return (int)$this->db->lastInsertId();
    }

    private function findTemplate(string $nome): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT nome, language_code, exemplo FROM whatsapp_templates WHERE nome = ? AND ativo = 1'
        );
        $stmt->execute([$nome]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function guardarMensagem(int $conversaId, string $direction, string $texto, int $isTemplate): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO whatsapp_messages (conversa_id, direction, text, is_template, status)
             VALUES (?, ?, ?, ?, \'pending\')'
        );
        $stmt->execute([$conversaId, $direction, $texto, $isTemplate]);
        return (int)$this->db->lastInsertId();
    }

    private function actualizarEstadoEnvio(int $messageId, array $result): void
    {
        $status  = $result['ok'] ? 'sent' : 'failed';
        $externalId = $result['external_id'] ?? null;

        if ($externalId !== null) {
            $this->db->prepare(
                'UPDATE whatsapp_messages SET status = ?, external_id = ? WHERE id = ?'
            )->execute([$status, $externalId, $messageId]);
        } else {
            $this->db->prepare(
                'UPDATE whatsapp_messages SET status = ? WHERE id = ?'
            )->execute([$status, $messageId]);
        }
    }
}
