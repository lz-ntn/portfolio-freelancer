# Spec — Módulo WhatsApp Business

> Documento de requisitos (spec-first) · Produto Base
> Objetivo: definir EXATAMENTE o que o módulo deve fazer, para que a IA (ou tu)
> possa implementá-lo sem ambiguidades e os testes possam validá-lo.

---

## 0. Antes de começar — explicação didática

### O que este documento é (e o que não é)

Uma spec é **o contrato entre ti e o código**. Em vibe coding, ela é o que separa um pedido vago ("faz WhatsApp") de um pedido que a IA consegue transformar em código correcto: critérios verificáveis, um a um.

**Regra de ouro:** se um critério de aceitação não pode ser **testado automaticamente**, não é um critério. "Deve funcionar bem" é uma esperança; "quando o admin envia uma mensagem, ela aparece no histórico com estado *enviado* dentro de 5 segundos" é um critério.

### Como ler este documento

- **Histórias de utilizador** descrevem o "quê" em linguagem de negócio (para o cliente e para ti).
- **Critérios de aceitação (Given/When/Then)** descrevem o "como provar" em passos verificáveis.
- **Esquema** e **regras de negócio** são a parte técnica (o "como" interno).
- Cada critério de aceitação vira um teste (Playwright/E2E ou de integração PHP).

### O problema de negócio (porque este módulo vale dinheiro)

No Brasil, **78% das vendas B2C fecham por WhatsApp**. As PMEs vivem nele hoje, mas tudo é manual: o vendedor procura o cliente na agenda, escreve à mão, esquece follow-ups, e ninguém sabe quantas mensagens foram enviadas. O módulo centraliza isso no CRM.

> **Atenção:** a API do WhatsApp tem restrições. Existem 2 caminhos — **API oficial (Meta Cloud API)** e **bridges** como WPPConnect. Nesta spec usamos a **API oficial da Meta** por ser estável, legal para negócio e sem risco de bloqueio. Usa-se um **token** e a API exige **templates aprovados** para a primeira mensagem a um contacto (24h) e permite respostas livres dentro da janela de 24h.

---

## 1. Visão geral do módulo

O módulo WhatsApp liga o CRM ao WhatsApp Business de forma que:

1. O utilizador **envia mensagens** a partir da ficha do cliente (e a mensagem fica no histórico).
2. Mensagens **recebidas** dos clientes aparecem no CRM (quando um cliente responde, vês a conversa).
3. Existe um **histórico** por cliente (todas as mensagens, enviadas e recebidas).
4. O utilizador pode enviar **templates aprovados** (ex.: lembrete de follow-up) — obrigatório pela API.
5. O estado de cada mensagem é **rastreado** (enviada/entregue/lida/falhou).

**Fora do âmbito (por agora):** chatbot automático, campanhas em massa, fila de espera, múltiplos números por utilizador.

---

## 2. Histórias de utilizador (o "quê")

### US-1 — Enviar mensagem da ficha do cliente
Como **colaborador de vendas**, quero **enviar uma mensagem de WhatsApp a um cliente a partir da sua ficha**, para conseguir dar resposta sem sair do CRM.

### US-2 — Ver o histórico de conversa
Como **colaborador de vendas**, quero **ver todas as mensagens trocadas com um cliente** (enviadas e recebidas, por ordem cronológica), para ter contexto da relação.

### US-3 — Receber respostas dos clientes
Como **equipa comercial**, quero que **as respostas que os clientes enviam apareçam automaticamente no CRM**, para não perder nenhum lead que respondeu.

### US-4 — Enviar template aprovado
Como **gestor**, quero **enviar mensagens pré-aprovadas pela Meta** (ex.: "Olá! Seguimento do teu pedido...") mesmo fora da janela de 24h, para poder retomar conversas antigas legalmente.

### US-5 — Saber se a mensagem chegou
Como **gestor**, quero **ver o estado de cada mensagem** (enviada / entregue / lida / falhou), para perceber se o cliente recebeu mesmo.

---

## 3. Critérios de aceitação (Given/When/Then) — o "como provar"

> Convenção:
> - **Given** = pré-condição
> - **When** = acção
> - **Then** = resultado esperado

### CA-1 (US-1) — Envio de mensagem

- **Given** um utilizador autenticado com permissão `whatsapp.send`
- **Given** um cliente com `whatsapp` preenchido e válido (ex.: +351 912 345 678)
- **When** submete a mensagem "Olá Maria, segue o teu orçamento."
- **Then** o sistema chama a API da Meta com o número correcto
- **And** cria um registo em `whatsapp_messages` com `direction='out'`, `status='sent'`, `text='Olá Maria, segue o teu orçamento.'`
- **And** redireciona para a conversa mostrando a mensagem no histórico
- **And** a mensagem aparece na conversa do cliente em menos de 5 segundos

### CA-2 (US-1) — Validação de número inválido

- **Given** um cliente com `whatsapp` = "abc123" (inválido)
- **When** o utilizador tenta enviar
- **Then** o sistema NÃO chama a API
- **And** mostra erro: "Número de WhatsApp inválido."
- **And** NÃO cria registo em `whatsapp_messages`

### CA-3 (US-1) — Permissão

- **Given** um utilizador SEM permissão `whatsapp.send` (ex.: papel colaborador sem permissão)
- **When** tenta enviar uma mensagem
- **Then** recebe 403 e a mensagem não é enviada

### CA-4 (US-2) — Histórico ordenado

- **Given** uma conversa com 2 mensagens enviadas e 1 recebida
- **When** o utilizador abre a ficha do cliente
- **Then** as 3 mensagens aparecem ordenadas por data (mais antiga → mais recente)
- **And** cada uma mostra: texto, sentido (entrada/saída), data/hora, estado

### CA-5 (US-3) — Recepção de resposta (webhook)

- **Given** o webhook configurado na Meta API
- **When** a Meta envia uma mensagem recebida do cliente (+351 912 345 678) com texto "Sim, quero avançar"
- **Then** o sistema encontra o cliente pelo número (normalizado: +351912345678)
- **And** cria um registo em `whatsapp_messages` com `direction='in'`, `status='received'`
- **And** se o cliente não existir, cria/liga a um "lead" com esse número

### CA-6 (US-3) — Verificação do webhook (handshake)

- **Given** o modo de verificação da Meta
- **When** a Meta envia o `hub.challenge` com o `verify_token` correcto
- **Then** o endpoint responde com o mesmo `hub.challenge` (HTTP 200)
- **And** com token errado, responde HTTP 403

### CA-7 (US-4) — Envio de template aprovado

- **Given** um template aprovado pela Meta com o nome `follow_up`
- **When** o utilizador envia esse template
- **Then** o sistema envia o template pelo nome (não texto livre)
- **And** regista em `whatsapp_messages` com `is_template=1`
- **And** o cliente recebe a mensagem mesmo sem ter respondido nas últimas 24h

### CA-8 (US-5) — Actualização de estado via webhook

- **Given** uma mensagem enviada com estado `sent`
- **When** a Meta envia evento de `status` = `delivered` (e depois `read`)
- **Then** o sistema actualiza o estado no registo correspondente (sem duplicar)
- **And** o histórico mostra o estado mais recente

### CA-9 — Normalização de números

- **Given** os números "+351 912 345 678", "351912345678", "+351912345678"
- **When** são guardados/consultados
- **Then** os três mapeiam para o mesmo registo de conversa (formato único: E.164 `+351912345678`)

---

## 4. Regras de negócio (invariantes)

1. **Número único por cliente:** o número é normalizado em formato E.164 antes de qualquer operação.
2. **Uma conversa por cliente:** todas as mensagens de um cliente ficam numa única conversa (campo `conversa_id`).
3. **Janela de 24h:** texto livre só é permitido se a última mensagem recebida do cliente foi há menos de 24h. Fora da janela → obrigatório template.
4. **Estado da mensagem:** só evolui `sent → delivered → read → failed` (nunca volta para trás).
5. **Idempotência do webhook:** a mesma mensagem/estado recebida duas vezes não duplica registos (chave única `external_id`).
6. **Não se reenvia com sucesso conhecido:** se o estado já é `delivered`, não se volta a `sent`.
7. **Tudo registado:** nunca envia sem guardar registo primeiro (fonte de verdade = BD, não a API).

---

## 5. Esquema de dados (migração `002_whatsapp.sql`)

```sql
CREATE TABLE IF NOT EXISTS whatsapp_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversa_id INT NOT NULL,
    external_id VARCHAR(64) NULL UNIQUE,      -- id da Meta (idempotência)
    direction ENUM('in','out') NOT NULL,       -- recebida / enviada
    text TEXT NOT NULL,
    is_template TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('pending','sent','delivered','read','failed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_conv FOREIGN KEY (conversa_id) REFERENCES conversas(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS conversas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    numero_e164 VARCHAR(20) NOT NULL UNIQUE,   -- +351912345678
    last_message_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cli FOREIGN KEY (cliente_id) REFERENCES clientes(id)
) ENGINE=InnoDB;

CREATE INDEX idx_msg_conv ON whatsapp_messages(conversa_id, created_at);
CREATE INDEX idx_conv_cli ON conversas(cliente_id);
```

> Nota: usando `ENUM` aqui? Não. Como no módulo de clientes, para portabilidade de testes usamos `VARCHAR` com valores controlados pelo código. (O esqueleto `produto-base` já evita ENUM por isto.)

**Esquema final sugerido (sem ENUM, teste-portável):**

```sql
direction VARCHAR(10) NOT NULL DEFAULT 'out',  -- 'in' | 'out'
status    VARCHAR(10) NOT NULL DEFAULT 'pending' -- 'pending'|'sent'|'delivered'|'read'|'failed'
```

---

## 6. Configuração necessária (config.php)

```php
define('WHATSAPP_TOKEN', '');
define('WHATSAPP_PHONE_ID', '');
define('WHATSAPP_VERIFY_TOKEN', '');
define('WHATSAPP_API_URL', 'https://graph.facebook.com/v19.0');
```

Todas vazias por defeito → o módulo fica em "modo simulação" (CA se o token estiver vazio, o envio não faz chamada real; útil para testes e demo).

---

## 7. Testes a escrever (mapeamento)

| Critério | Tipo de teste | Como |
|---|---|---|
| CA-1, CA-2 | Integração PHP | `WhatsappService` com PDO da base de testes + client HTTP *mockado* |
| CA-3 | Integração PHP | `roles.php` / `can()` |
| CA-4 | Integração PHP | ordenação do histórico |
| CA-5, CA-6 | Integração PHP | processamento de webhook (sem HTTP real) |
| CA-7 | Integração PHP | selecção de template |
| CA-8 | Integração PHP | actualização de estado (idempotente) |
| CA-9 | Unit PHP | `normalizarNumero()` |
| CA-1..8 (fluxo completo) | E2E Playwright | login → enviar → ver no histórico (com mock da API) |

> **Regra da spec:** escreve-se o teste de CA-1 ANTES de implementar (TDD). Vermelho primeiro, depois verde. É isto que garante que o "sem bugs" é consequência do processo.

---

## 8. Ordem de implementação recomendada

1. **Migração** `002_whatsapp.sql` + `WhatsappService::normalizarNumero()` + teste (CA-9)
2. **Service básico** (guardar registo, listar conversa) + testes (CA-4)
3. **HTTP client mockável** + envio real (CA-1, CA-2, CA-3)
4. **Webhook** recepção + verificação + idempotência (CA-5, CA-6, CA-8)
5. **Templates** (CA-7)
6. **UI** na ficha do cliente (view) + E2E Playwright (fluxo completo)

Cada passo é uma fatia pequena, testada, com demo possível ao cliente.

---

## 9. Critérios de "pronto" (definition of done)

- [x] Migração `002_whatsapp.sql` criada e aplicada
- [x] Todos os testes CA-1..CA-9 verdes (`php tests/run.php`) — 54/54
- [ ] E2E Playwright do fluxo completo verde (pendente — fluxo verificado manualmente via HTTP)
- [x] Modo simulação funciona sem token (para demo)
- [x] Documentado o que a Meta exige (token, phone_id, templates) no README do módulo
- [x] Nenhuma credencial real no código (só em config.php)

> **Status (2026-08-01):** implementado — service, controller, views, webhook, handshake,
> modo simulação e 54/54 testes verdes. Bugs encontrados e corrigidos no processo:
> hash da password do admin estava errado na migração 001; router não carregava services;
> client de simulação gerava external_id duplicado entre requests.
