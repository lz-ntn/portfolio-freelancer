# Adaptação do Módulo WhatsApp Business → crm-poupaplus

> Documento de adaptação: parte técnica (spec de integração) + parte comercial (negociação com o cliente).
> Base: módulo WhatsApp já implementado e testado em `produto-base/` (54/54 testes) — agora adaptado à stack do PoupaPlus CRM.

---

# PARTE 1 — ADAPTAÇÃO TÉCNICA

## 1.1 Porque NÃO é copiar-colar

O `produto-base` é **PHP + MySQL**; o PoupaPlus é **Vanilla JS (ES2022) + Appwrite** (sem backend próprio, só Functions). O que se reaproveita é:

| Camada | produto-base (feito) | PoupaPlus (adaptar) |
|---|---|---|
| Lógica de negócio | `WhatsappService` (PHP, PDO) | JS no frontend + **Appwrite Function** como proxy |
| Dados | Tabelas MySQL `conversas`, `whatsapp_messages`, `whatsapp_templates` | 3 **coleções Appwrite** com o mesmo schema |
| Token Meta | Em `config.php` (fica no servidor) | Em **variável de ambiente da Function** (nunca no JS público — mais seguro) |
| Webhook | Endpoint PHP | **Appwrite Function** pública |
| UI | View PHP `conversa.php` | Módulo `src/js/whatsapp/` (service + ui, padrão existente) |
| Permissões | `roles.php` | `permissoes.js` + Appwrite Teams (roles server-side) |
| Testes | Testes de integração PHP | Vitest (unit) + Playwright (E2E), com mocks de Appwrite |

**As regras de negócio portam 1:1** (são agnósticas de stack):
- E.164 (`+351912345678`) — regra 1 e CA-9
- Janela de 24h — regra 3 (texto livre só com resposta recente)
- Estados `sent → delivered → read → failed` sem voltar atrás — regra 4/6
- Idempotência via `external_id` único — regra 5
- Regista antes, envia depois (BD é a fonte de verdade) — regra 7

## 1.2 O que o PoupaPlus já tem (para aproveitar)

1. **Links `wa.me` manuais** em `leads/ui.js`, `dashboard/ui.js` — hoje "enviar WhatsApp" = abrir link no browser. **Este é o problema de negócio**: zero rastreio, zero histórico, zero follow-up automático.
2. **Follow-up tipo "Enviar WhatsApp"** em `followups.js` — existe a intenção, falta a execução real.
3. **Módulo `mensagens.js`** — mensagens via email (proposta, ajuda, info). O WhatsApp é o irmão mais forte (resposta em minutos, não dias).
4. **Padrão de proxy `send-email` Function** — exatamente o mesmo desenho que o WhatsApp precisa (credencial no servidor, validação de input, resposta JSON). O roadmap até já cita "WhatsApp Business API completa" como item em aberto.

## 1.3 Schema (coleções Appwrite — espelho do 002_whatsapp.sql)

```js
// Coleção: conversas
{
  clienteId:   'string',          // ref doc clientes
  numeroE164:  'string',          // +351912345678 (única por conversa)
  lastMessageAt: 'datetime',
  createdAt:   'datetime'
}

// Coleção: whatsapp_messages
{
  conversaId:  'string',          // ref doc conversas
  externalId:  'string',          // id da Meta (único → idempotência)
  direction:   'string',          // 'in' | 'out'
  text:        'string',
  isTemplate:  'boolean',
  status:      'string',          // 'pending'|'sent'|'delivered'|'read'|'failed'|'received'
  createdAt:   'datetime',
  updatedAt:   'datetime'
}

// Coleção: whatsapp_templates
{
  nome:          'string',        // nome aprovado na Meta (único)
  languageCode:  'string',        // 'pt_PT'
  exemplo:       'string',
  ativo:         'boolean'
}
```

## 1.4 Appwrite Function `send-whatsapp` (o coração da adaptação)

Espelho da `send-email` (que já existe e está em produção):

```
functions/send-whatsapp/src/main.js
```

**Responsabilidades (tudo o que o token toca fica aqui, no servidor):**

```js
// Entrada (JSON do frontend):
//   { action: 'send-text',   clienteId, text }
//   { action: 'send-template', clienteId, templateNome }

// Regras implementadas na Function (portadas do WhatsappService PHP):
// 1. Normalizar número → E.164 (CA-9)
// 2. Validar número (CA-2) — rejeita antes de tocar a API
// 3. Verificar janela de 24h na coleção whatsapp_messages (CA-1, regra 3)
// 4. Registar mensagem com status 'pending' ANTES de chamar a Meta (regra 7)
// 5. Chamar Graph API (curl/fetch server-side) com o token das env vars
// 6. Atualizar status para 'sent' | 'failed' + externalId

// Variáveis de ambiente (Dashboard Appwrite → Function):
//   META_ACCESS_TOKEN
//   META_PHONE_ID
//   META_API_URL  (https://graph.facebook.com/v19.0)
```

**Vantagem sobre a versão PHP:** no produto-base o token vive em `config.php` no servidor; no PoupaPlus vive em env var da Function — o frontend **nunca** o vê. Segurança superior, igual padrão já aprovado com EmailJS.

## 1.5 Appwrite Function `whatsapp-webhook` (pública)

```
functions/whatsapp-webhook/src/main.js
```

- **GET** → handshake: valida `hub_verify_token` (env var `META_VERIFY_TOKEN`), devolve `hub_challenge` (CA-6)
- **POST** → corpo JSON da Meta:
  - `messages[]` → procura cliente pelo número normalizado; se não existir, **cria lead** com esse número (CA-5); grava `direction='in'`, `status='received'`, `externalId` único (idempotente)
  - `statuses[]` → atualiza estado sem duplicar e sem voltar atrás (CA-8)
- Resposta imediata `{"status":"ok"}` (evita retries da Meta)

Configuração na Meta: callback URL `https://<funcao>.appwrite.app/...` + verificar token.

## 1.6 Frontend — módulo `src/js/whatsapp/` (padrão do projeto)

```
src/js/whatsapp/
├── service.js    # WhatsAppService extends BaseService('whatsapp_messages')
│                 #   + sendText(clienteId, text)      → chama Function send-whatsapp
│                 #   + sendTemplate(clienteId, nome)  → chama Function send-whatsapp
│                 #   + getConversa(clienteId)         → lê coleção conversas + mensagens
│                 #   + normalizarNumero(raw)          → E.164 (portado, com testes)
├── ui.js         # modal de conversa estilo chat (reutiliza ModalComponent)
└── helpers.js    # formatar data, badges de estado (sent/delivered/read/failed)
```

**Integrações (o que muda em ficheiros existentes):**

| Ficheiro | Hoje | Depois |
|---|---|---|
| `leads/ui.js`, `dashboard/ui.js` | botão `wa.me` (abre link) | botão abre **modal de conversa** no CRM |
| `followups.js` | tipo "Enviar WhatsApp" (só registo) | ao criar follow-up de tipo WhatsApp → abre modal com **texto livre ou template** |
| `clientes/` | campo `contacto` | ficha do cliente ganha secção "Conversa WhatsApp" |
| `permissoes.js` + Teams | — | permissão `whatsapp.send` (consultor pode ver, só gestor/gerente envia — igual CA-3) |

## 1.7 Testes (Vitest + Playwright, como o projeto manda)

| Critério | Tipo | Como (padrão existente) |
|---|---|---|
| CA-9 normalização | Unit Vitest | `whatsapp/service.test.js` (mock de Function) |
| CA-2 número inválido | Unit Vitest | validação antes da chamada |
| CA-1 envio + registo | Unit Vitest | mock da Function + mock de coleção |
| CA-4 histórico ordenado | Unit Vitest | ordenação por createdAt |
| CA-5/CA-8 webhook | Unit Vitest | função `processarWebhook` exportada (mesmo padrão dos testes de `send-email`) |
| CA-6 handshake | Unit Vitest | comparação de token |
| Fluxo completo | Playwright E2E | login → abrir lead → enviar (mock da Function) → ver no histórico |

## 1.8 Ordem de implementação + estimativa

| # | Passo | Estimativa |
|---|---|---|
| 1 | Coleções Appwrite + Function `send-whatsapp` (envio, janela 24h, E.164) | 1 dia |
| 2 | Function `whatsapp-webhook` (handshake + receção + estados) | 1 dia |
| 3 | Frontend: `whatsapp/service.js` + `ui.js` (modal de conversa) | 1-2 dias |
| 4 | Integração: leads, followups, ficha de cliente | 1 dia |
| 5 | Permissões + roles Teams | 0.5 dia |
| 6 | Testes Vitest + Playwright (todos os CA) | 1-2 dias |
| 7 | Configuração Meta (test number → produção) + go-live | 0.5-1 dia |
| | **Total** | **~6-8 dias de trabalho** |

> Encaixa com o roadmap atual do cliente: o item "WhatsApp Business API completa" já está listado em Médio Prazo — esta é a execução dele.

## 1.9 Riscos técnicos (ser honesto com o cliente)

1. **Meta exige templates aprovados** para a 1ª mensagem a um contacto (fora da janela 24h) — aprovação pode demorar 1-3 dias.
2. **Números de teste vs produção** — durante o go-live usa-se número de teste da Meta (mensagens só para números verificados).
3. **Custo por conversa da Meta** (ver Parte 2) — mensagens iniciadas pela empresa têm custo por conversa; respostas dentro da janela 24h são gratuitas.
4. **Sem chatbot / campanhas em massa** nesta fase (fora de âmbito) — seguem-se numa 2ª iteração se o cliente quiser.

---

# PARTE 2 — NEGOCIAÇÃO COM O CLIENTE

## 2.1 O argumento de venda (números, não opinião)

**O estado atual (factos):**
- Hoje "enviar WhatsApp" = abrir `wa.me` no browser → o consultor escreve à mão, **não fica registado**, não se sabe se chegou/lida, follow-ups perdem-se.
- O CRM já tem o tipo de follow-up "Enviar WhatsApp" criado... mas sem execução.

**O que o módulo entrega:**
1. **Histórico completo** da conversa dentro do CRM (quem enviou, quando, foi lida?)
2. **Respostas dos clientes entram sozinhas** no CRM (ninguém perde uma resposta fora de horário)
3. **Follow-ups que se executam**: tipo "Enviar WhatsApp" agora envia mesmo, com template aprovado
4. **Leads que respondem tornam-se clientes automaticamente** (número novo → lead criado)

**Números de mercado (para a conversa):**
- 78% das vendas B2C no Brasil fecham por WhatsApp
- Email: resposta em horas/dias; WhatsApp: resposta em minutos
- Um consultor que não reenvia follow-up perde vendas que já estavam 70% decididas

**Frase-gancho para o cliente:**
> "Hoje o teu CRM diz 'Enviar WhatsApp' e abre um link. Depois deste módulo, o WhatsApp passa a estar **dentro** do CRM: envia, registra, e quando o cliente responder, aparece. Nenhuma resposta se perde."

## 2.2 Modelos de preço (3 opções — deixar o cliente escolher)

### Opção A — Preço fixo por projeto (recomendada para esta 1ª fase)
- **€750 – €1.250** (ou R$ 4.500 – 7.500 se negociar em BRL)
- Escopo: implementação completa (1.8), testes, go-live, 30 dias de suporte
- Prós: simples, previsível para ambos; contra: não remunera evolução futura

### Opção B — Hora (se o cliente preferir)
- **€30–40/h** × ~50h estimadas = €1.500 – 2.000
- Prós: justo se o escopo mudar; contra: cliente pode ficar com receio de custo aberto

### Opção C — Fixo + mensalidade de manutenção (evolução natural)
- **€600 fixo** + **€50/mês** (manutenção, novos templates, 2ª iteração: chatbot)
- Prós: relação contínua, cliente vê o sistema evoluir; é o caminho para o **produto-base multi-cliente**

> Recomendação: **vender a Opção A agora** e deixar a C como evolução natural. Primeiro provar valor; depois subir o preço.

## 2.3 Custos da Meta que o cliente precisa conhecer (transparência = confiança)

**Primeiro, a boa notícia: NÃO se compra API.** A Cloud API da Meta é gratuita — não há mensalidade nem licença. O único custo possível é por **mensagem-template entregue** (desde julho/2025 a Meta cobra por mensagem, não por conversa), conforme a categoria e o país do destinatário.

### O que é grátis (o caso normal do PoupaPlus)

| Item | Custo |
|---|---|
| Registo no Meta for Developers + usar a Cloud API | **Gratuito** |
| Responder a clientes dentro da janela de 24h (o teu dia a dia: lead responde → respondes) | **Gratuito** |
| Mensagens de texto normais (não-template) | **Gratuitas** |
| Templates de categoria **Service** dentro de janela aberta | **Gratuitos** |
| Número de telefone (é o WhatsApp da empresa que já têm) | **Gratuito** |

### O único custo possível: templates pagos (por mensagem entregue)

| Categoria | Exemplo | Destinatário em Portugal (~USD) |
|---|---|---|
| **Marketing** | Campanha em massa, promoção | ~$0.059 (≈6 cêntimos) |
| **Utility** | Lembrete de follow-up, confirmação | ~$0.017 (≈1,7 cêntimos) |
| **Authentication** | Código de verificação (OTP) | ~$0.017 |
| **Service** | Resposta dentro da janela 24h | **$0** |

> Valores base da Meta (categoria "Rest of Western Europe", 2026) — **confirmar sempre** em `developers.facebook.com/docs/whatsapp/pricing` antes de fechar proposta, pois a Meta atualiza as taxas periodicamente. O valor depende do país do **destinatário**, não do teu.

### Exemplo realista para o PoupaPlus

Um consultor envia **100 follow-ups** por mês com template Utility (ex.: "Olá {{1}}, seguimento da tua proposta..."):
- 100 × $0.017 ≈ **US$ 1,70/mês**
- E se o cliente responder (janela 24h), as respostas seguintes são **grátis**

Campanhas de marketing em massa NÃO estão na Fase 1 — se o cliente quiser no futuro, aí sim há custo por mensagem (~6 cêntimos para PT), e por isso essa categoria fica propositadamente fora do escopo inicial.

> Mensagem-chave ao cliente: **"Zero mensalidade, zero compra de API. Só pagas centavos se fizeres campanhas em massa — e isso é opcional. Responder a quem te escreve é grátis."**

> ⚠️ Nota: fontes de 2026 divergem no modelo exato (per-message vs per-conversation legacy) e nos valores por país — os números acima são a leitura mais consistente (per-message desde julho/2025, Portugal ≈ Western Europe). Confirmar na página oficial da Meta antes de fechar o contrato.

## 2.4 Proposta comercial (estrutura para o orçamento/email)

```
PROPOSTA — Módulo WhatsApp Business no PoupaPlus CRM

1. PROBLEMA (estado atual)
   - Follow-ups "Enviar WhatsApp" não enviam nada (abrem link manual)
   - Sem histórico, sem rastreio, respostas de clientes perdem-se

2. SOLUÇÃO (o que fica a funcionar)
   - Envio de mensagens e templates a partir do CRM
   - Histórico completo por cliente (enviada/recebida/lida)
   - Respostas entram automaticamente no CRM (lead novo = novo cliente)
   - Estados: sent → delivered → read → failed

3. ESCOPO (fora de âmbito nesta fase)
   - Sem chatbot automático, sem campanhas em massa
   - Aprovação de templates na Meta é responsabilidade do cliente (damos o texto, eles aprovam)

4. INVESTIMENTO
   - Opção A: €X fixo, 30 dias de suporte incluídos
   - Opção C (evolução): + €Y/mês de manutenção

5. PRAZO
   - 2-3 semanas (desenvolvimento + testes + go-live)

6. RESPONSABILIDADES
   - Cliente: conta Meta for Developers, número, aprovar templates, custos Meta (só se fizer templates pagos)
   - Nós: implementação, testes, configuração webhook, formação da equipa
   - CUSTOS META: API gratuita; respostas a clientes = $0; follow-up com template Utility ≈ $0.017/mensagem para PT
```

## 2.5 Script de apresentação (conversa com o dono)

**Passo 1 — Diagnosticar (fazer o cliente dizer o problema):**
> "Quantos follow-ups por WhatsApp fazes por dia? Sabes quantos foram lidos? Já te aconteceu um cliente dizer 'nunca me responderam' e tinhas respondido?"

**Passo 2 — Mostrar (demo > PowerPoint):**
> Usar o `produto-base` em modo simulação: criar cliente, enviar mensagem, simular resposta do cliente, mostrar estados. 5 minutos bastam.

**Passo 3 — Enquadrar o valor:**
> "Isto não é 'um chat'. É o teu funil de vendas a deixar de ter buracos: cada resposta entra no CRM, cada follow-up é executado, cada lead que responde vira cliente. O custo por conversa é centavos."

**Passo 4 — Fechar:**
> "Proponho fazer em 2-3 semanas por €X. Se preferires começar mais pequeno, faço primeiro o envio + histórico (1 semana) e o resto na fase 2."

**Objecções prováveis (respostas prontas):**

| Objeção | Resposta |
|---|---|
| "É caro" | "Divide o custo pelo que evita: uma venda perdida por resposta não vista paga o módulo várias vezes. E posso parcelar 50/50." |
| "A Meta é complicada" | "A configuração fica comigo. Tu só precisas de criar a conta e aprovar os templates — eu dou-te o texto pronto." |
| "Os consultores não vão usar" | "Se o WhatsApp já é o teu canal de vendas, vão usar — porque deixa de ser manual. E eu dou formação de 1h à equipa." |
| "Temos coisas mais importantes" | "Este módulo mexe no teu canal de receita principal (vendas por WhatsApp). É o que paga as outras melhorias." |

## 2.6 Fases de evolução (para manter a relação)

| Fase | O que inclui | Preço sugerido |
|---|---|---|
| **Fase 1** (agora) | Envio + histórico + webhook + estados + templates | €750–1.250 fixo |
| **Fase 2** (3-6 meses) | Respostas automáticas (chatbot básico) + lembretes de follow-up automáticos | €400–600 |
| **Fase 3** (6-12 meses) | Campanhas em massa + relatórios de WhatsApp + multi-número | €600–900 |
| **Produto multi-cliente** | Transformar o módulo em produto vendável a outras PMEs (base do produto-base) | Receita recorrente |

---

# RESUMO EXECUTIVO (para ler em 1 minuto)

**Técnico:** o módulo já testado no produto-base adapta-se ao PoupaPlus sem reescrever as regras de negócio — muda a roupagem: MySQL→coleções Appwrite, PHP→Functions (proxy com o token no servidor), view PHP→módulo `src/js/whatsapp/` no padrão do projeto. 6-8 dias de trabalho, ~7 passos.

**Negociação:** o argumento é "o teu CRM já diz 'Enviar WhatsApp' mas não envia nada — fecha-se o buraco". Vender fixo (€750–1.250), transparência nos custos Meta (**API gratuita, sem compra; respostas a clientes = $0; follow-up com template ≈ $0.017/mensagem para PT**), escopo claro (sem chatbot/campanhas agora), e fases de evolução para transformar em relação contínua.
