# Módulo WhatsApp Business

Liga o CRM ao **WhatsApp Business via Meta Cloud API** (API oficial — sem bridges, sem risco de bloqueio).

## O que faz (histórias de utilizador)

| História | O que o utilizador consegue |
|---|---|
| US-1 | Enviar mensagem a partir da ficha do cliente (fica no histórico) |
| US-2 | Ver o histórico completo da conversa (enviadas + recebidas, ordenadas) |
| US-3 | Respostas dos clientes aparecem automaticamente no CRM (via webhook) |
| US-4 | Enviar templates aprovados pela Meta (funciona fora da janela de 24h) |
| US-5 | Ver o estado de cada mensagem: pending → sent → delivered → read → failed |

## Como funciona (3 conceitos-chave)

1. **Fonte de verdade = base de dados.** O sistema regista a mensagem PRIMEIRO e só depois chama a API. Se a API falhar, o estado vira `failed` e nada se perde.
2. **Janela de 24h (regra da Meta).** Texto livre só é permitido se o cliente respondeu nas últimas 24h. Fora da janela, a Meta exige **template aprovado** — o módulo bloqueia o texto livre e sugere o template.
3. **Idempotência.** Cada mensagem tem `external_id` único da Meta. Se a Meta reenviar o mesmo webhook, nada duplica.

## Páginas e endpoints

| URL | Função |
|---|---|
| `index.php?page=whatsapp&action=conversa&cliente_id=N` | Histórico + envio |
| `index.php?page=whatsapp&action=enviar` (POST) | Envia texto ou template |
| `index.php?page=whatsapp&action=webhook` (GET) | Handshake de verificação da Meta (CA-6) |
| `index.php?page=whatsapp&action=webhook` (POST) | Recebe mensagens e estados (CA-5, CA-8) |

O link "💬 WhatsApp" aparece na lista de clientes.

## Colocar em produção (o que a Meta exige)

1. **Criar app no Meta for Developers** → adicionar o produto WhatsApp.
2. **Obter credenciais:**
   - `WHATSAPP_TOKEN` — token de acesso permanente (System User token)
   - `WHATSAPP_PHONE_ID` — ID do número de telefone (WhatsApp Business API)
3. **Preencher `app/core/config.php`:**
   ```php
   define('WHATSAPP_TOKEN', 'EAAxxxx...');
   define('WHATSAPP_PHONE_ID', '1234567890');
   define('WHATSAPP_VERIFY_TOKEN', 'um-token-secreto-teu'); // escolhes tu
   define('WHATSAPP_API_URL', 'https://graph.facebook.com/v19.0');
   ```
4. **Criar templates no painel da Meta** (ex.: `follow_up`) e adicioná-los na tabela `whatsapp_templates`.
5. **Configurar o webhook no Meta for Developers** → WhatsApp → Configuration:
   - Callback URL: `https://teudominio.com/index.php?page=whatsapp&action=webhook`
   - Verify token: o mesmo de `WHATSAPP_VERIFY_TOKEN`
   - Subscrever eventos: `messages` e `message_status` (para delivered/read)
6. **Testar** com o modo simulação primeiro (token vazio) e depois com o próprio número (modo teste da Meta).

> Segurança: o ficheiro `config.php` não deve ir para o git (adicionar ao `.gitignore`).
> O webhook é público de propósito (a Meta não faz login) — por isso a única proteção é o `verify_token`.

## Modo simulação (demo sem gastar nada)

Com `WHATSAPP_TOKEN` vazio, o módulo **não chama a API real**: regista as mensagens como `sent` e mostra um aviso no ecrã. É o mesmo comportamento dos testes — serve para demo ao cliente sem custos.

## Testes

```bash
php tests/run.php        # corre tudo (clientes + whatsapp)
```

Cobertura: CA-1..CA-9 da spec `specs/whatsapp-business.md` — envio, validação, permissões, histórico, webhook, handshake, templates, estados e normalização E.164.

## Limitações atuais (fora do âmbito)

- Sem chatbot automático
- Sem campanhas em massa
- Sem múltiplos números por utilizador
- Sem ficheiros/mídia (só texto e templates)
