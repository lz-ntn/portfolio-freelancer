# Playbook de Execução — De desenvolvedor com vibe coding a freelancer sem bugs

> Documento operacional · Luiz Antonio · Agosto 2026
> Complementa MERCADO-ESTRATEGIA.md e MERCADO-BRASIL.md
> Foco: COMO construir e entregar com vibe coding sem deixar bugs

---

## 1. O Mindset — vibe coding é ferramenta, não estratégia

A frase "não preciso reinventar a roda" está certa **a meio caminho**. O erro é pensar que significa "deixo a IA fazer tudo". Correção:

```
QUEM MANDA NA QUALIDADE
┌─────────────────────────────────────────────┐
│  IA (vibe coding) escreve o código            │
│        ▼                                      │
│  TU és o arquiteto + revisor + gate de        │
│  qualidade (escolhes o quê, revês o como,     │
│  garantes o "não quebra")                     │
│        ▼                                      │
│  Cliente recebe sistema que funciona          │
└─────────────────────────────────────────────┘
```

A IA gera velocidade. **Tu geras confiança.** Num negócio de CRM/ERP para PMEs, a confiança é o que faz o cliente pagar €1.500+ em vez de R$100/mês na Omie. Cada bug que chega ao cliente destrói essa confiança mais rápido do que qualquer feature a constrói.

**As 3 verdades que mudam tudo:**

1. **A IA nunca testa a lógica de negócio.** Pode gerar código sintaticamente perfeito com a regra de comissão errada. Só tu (ou um teste) apanhas isso.
2. **O código da IA é código de terceiros.** Aceita-lo cegamente = dívida. Revê-lo = aprendizado + qualidade. Um "dev mediano que usa vibe coding" que **revê e testa** entrega melhor que um "sénior" apressado.
3. **Velocidade sem método só produz bugs mais depressa.** O método anti-bugs abaixo é o que transforma vibe coding de perigoso em poderoso.

---

## 2. O Sistema Anti-Bugs (o teu workflow)

Este é o coração. Um processo em 6 passos que torna "sem bugs" uma **consequência do processo**, não da sorte:

### Passo 1 — Spec primeiro (5-15 min antes de gerar código)
Escreve (ou pede à IA para escrever) o que a feature **deve fazer**, com critérios de aceitação verificáveis. Exemplo:
- *"Módulo de comissões: quando uma venda é marcada como 'fechada', calcular 5% da margem ao vendedor X, registrar num lançamento financeiro, e exibir no dashboard."*
- Critérios: (a) venda fechada cria lançamento; (b) valor = 5% da margem; (c) venda cancelada estorna o lançamento.

> Regra: **nunca** pedir código sem esta spec. A spec é o contrato entre ti e a IA.

### Passo 2 — Fatiar em pedaços pequenos
Pede UMA feature de cada vez. NUNCA "constrói o sistema de gestão completo". Sistemas completos gerados de uma vez = monólito de bugs impossível de rever.

### Passo 3 — Testes como condição de "feito"
Toda feature só conta como feita quando tem testes que validam os critérios da spec. Tens Vitest + Playwright — usa-os. Sem teste, a feature não está feita. Ponto.

### Passo 4 — Rever o que a IA gerou
Lê o diff linha a linha (ou pelo menos as partes de lógica). Perguntas de revisão:
- Entrada de dados é validada?
- Erros de base de dados são tratados?
- Permissões (roles) respeitadas?
- Alguma query N+1 / injeção?
- A spec foi cumprida?

### Passo 5 — Correr tudo antes de entregar
Suite completa: unit (Vitest) + E2E (Playwright) + testes de integração do backend. Zero tolerância a falhas na hora de entregar.

### Passo 6 — Entregar em pequenos incrementos
Demo ao cliente a cada milestone. Cliente vê progresso, dá feedback cedo, e "scope creep" morre antes de nascer.

```
SPEC → FATIA → TESTES → REVISÃO → SUITE VERDE → DEMO
   ↑                                                │
   └─────────── feedback do cliente ────────────────┘
```

---

## 3. As 10 Regras de Ouro do Vibe Coding (cola)

1. **Nunca** gerares código sem spec escrita (critérios de aceitação).
2. **Uma feature por vez** — nunca pedir "o sistema inteiro".
3. **Sem teste = não está feita.** Regra inegociável.
4. **Lê sempre** o que a IA gerou antes de aceitar.
5. **Base de dados versionada** (migrações em ficheiros) — nunca alterar schema à mão.
6. **Suite completa verde** antes de qualquer entrega/deploy.
7. **Não fazer copy-paste cego** de código de terceiros sem perceber a lógica.
8. **Núcleo estável + customização isolada** (secção 4).
9. **Documentar decisões** (pede à IA para escrever um resumo de cada mudança).
10. **Deploy só com CI verde** — automatiza as regras 5, 6 e 7 com o GitHub Actions que já tens.

---

## 4. Arquitetura do Núcleo Reutilizável

O objetivo: **70-80% do código é o mesmo em todos os clientes**; só 20-30% é customizado. É isso que te permite entregar em 3-4 semanas em vez de 3 meses — e é isso que o vibe coding acelera.

```
projeto/
├── migrations/            # schema versionado (um ficheiro por mudança)
│   001_initial.sql
│   002_whatsapp_integration.sql
├── app/
│   ├── core/              # ← NÃO MUDA entre clientes (<5% de alterações)
│   │   ├── db.php         #   conexão PDO + prepared statements
│   │   ├── auth.php       #   login, bcrypt, sessões
│   │   ├── roles.php      #   permissões por perfil
│   │   ├── router.php     #   rotas + CSRF
│   │   ├── validator.php  #   validação de inputs
│   │   └── helpers.php    #   sanitização, logs, utils
│   ├── modules/           # ← UM DIRETÓRIO POR MÓDULO (o que vendes)
│   │   ├── clientes/      #   ações, listagem, formulário, testes
│   │   ├── vendas/
│   │   ├── estoque/
│   │   ├── financeiro/
│   │   ├── comissoes/
│   │   └── relatorios/
│   └── custom/            # ← customização do cliente (isolada, versionada)
│       └── CLIENTE_X/
├── public/                # frontend (JS vanilla + Bootstrap)
│   └── assets/
├── tests/                 # Vitest (unit) + Playwright (E2E)
└── docs/                  # documentação por cliente
```

### Regras estruturais que previnem bugs
- **O núcleo só recebe mudanças testadas globalmente** — se algo quebra 1 cliente, quebra todos; por isso o núcleo é conservador.
- **Customização vive em `app/custom/CLIENTE_X/`** — nunca mexer em `core/` ou `modules/` para um cliente específico. Overrides por configuração, não por alteração do código base.
- **Módulos são cópias do teu trabalho existente** (PoupaPlus/Gestão BH) — pede à IA para extrair cada módulo para um diretório com testes. É o primeiro passo concreto do plano.

### Configuração em vez de código (o segredo do "leve")
Features que a IA geraria como código para cada cliente devem ser **config**:
- Campos extra do cliente → tabela `custom_fields` (não novo código)
- Workflows/estados do pipeline → tabela `workflows` (não código novo)
- Regras de comissão → tabela `commission_rules` (não código novo)

> Quanto menos código mudar por cliente, menos bugs por cliente. **Config vende; código custa.**

---

## 5. Estratégia de Testes — o teu escudo

O que já tens: Vitest (unit) + Playwright (E2E) + 419 testes + CI. O que falta para um ERP/PHP:

| Camada | Ferramenta | O que cobre | Prioridade |
|---|---|---|---|
| Unit (frontend) | Vitest | funções, validações, componentes | ✅ já tens |
| E2E (navegador) | Playwright | fluxos completos (login→venda→fatura) | ✅ já tens |
| **Integração backend** | PHPUnit ou script PHP com SQLite em memória | lógica de negócio em PHP (comissões, estoque, financeiro) | ⭐ ADICIONAR |
| **Schema/migrações** | Teste que roda todas as migrações do zero | DB sempre consistente | ⭐ ADICIONAR |
| Smoke pós-deploy | Playwright contra produção | "login + criar registo" antes de dizer "entregue" | ⭐ ADICIONAR |

**Razão de custo-benefício:** 1 bug fiscal/comissão que chega ao cliente custa-te reputação + horas não pagas. 30 min de teste de integração evita isso. É o investimento com melhor ROI do teu negócio.

---

## 6. Inovação Leve que Vende (diferenciação barata)

A inovação certa para ti não é IA na vanguarda — é **resolver as dores reais das PMEs com pouco esforço técnico**:

| Recurso | Esforço | Porque vende |
|---|---|---|
| **Integração WhatsApp Business** | Médio | 78% das vendas BR fecham por lá; ninguém serve bem |
| **PWA (offline-first)** | Já tens base | "Funciona sem internet" impressiona e ajuda no campo/obras |
| **Dashboard executivo** | Baixo (já tens BI) | Dono da PME decide no telemóvel |
| **Importação de dados** (Excel/CSV) | Baixo | Mata o "temos tudo no Excel" na migração |
| **Exportação + backup automático** | Baixo | Cliente sente-se dono dos dados |
| **Multi-empresa (tenants)** | Médio | Habilita o modelo SaaS mensal |
| **Relatórios agendados por email** | Baixo | Rotina automática = retenção |
| **LGPD/GDPR: exportar/eliminar dados** | Baixo | Requisito legal → argumento de venda |

**Regra de inovação:** uma feature nova só entra no produto se (a) um cliente já pediu OU (b) resolve dor de 3+ clientes. Não construas para a imaginação — construas para a procura.

---

## 7. Playbook de Entrega ao Cliente (o processo completo)

Cada projeto segue o mesmo ritual. Rotina = previsibilidade = menos bugs = mais margem.

```
1. DESCOBERTA (call 30-60 min, GRÁTIS, não fazer a proposta antes disso)
   └─ Mapear o processo real do cliente (o que faz hoje, onde dói, o que quer manter)
2. SPEC + PROPOSTA (FIXA, nunca por hora)
   └─ Escopo em fases + 50% a adiantar + datas de milestones
3. MILESTONE 1 → demo → feedback (a cada 1-2 semanas)
4. TREINO + DOCUMENTAÇÃO (o que falta à maioria dos freelancers)
5. ENTREGA (smoke test verde) + MANUTENÇÃO MENSAL
```

**Checklist de entrega profissional (o que te separa dos 90%):**
- [ ] Suite completa verde no CI
- [ ] Smoke test em produção (login + criar registo real)
- [ ] Documentação do que foi entregue (já sabes fazer — 4.718 linhas!)
- [ ] Backup do código + DB entregue ao cliente
- [ ] Formação (30-60 min em vídeo ou call)
- [ ] Acordo de manutenção assinado ANTES de entregar (nunca depois)

---

## 8. Plano de Ação — 90 dias (execução)

### Fase 1 — Dias 1-14: Normalizar o produto
- [ ] Extrair módulos do PoupaPlus/Gestão BH para `app/modules/` com testes por módulo
- [ ] Escrever spec + critérios de aceitação para o módulo WhatsApp
- [ ] Adicionar testes de integração PHP (SQLite em memória) no CI
- [ ] Criar migrações versionadas de todo o schema atual

### Fase 2 — Dias 15-45: Fechar lacunas que vendem
- [ ] WhatsApp Business (mínimo: enviar mensagens + templates)
- [ ] Importação Excel/CSV (porta de entrada p/ migrações)
- [ ] Smoke test pós-deploy no CI
- [ ] Demo pública reutilizável com dados de exemplo realistas

### Fase 3 — Dias 46-90: Vender com o método
- [ ] Perfis completos (Upwork/Workana/99freelas) com screenshots reais
- [ ] 3-5 propostas/dia com o método spec-first (mostra que entendes o negócio, não só código)
- [ ] Primeiro cliente → aplicar o playbook de entrega completo
- [ ] Manutenção mensal desde o dia 1

---

## 9. Erros que Vibe Coders Cometem (evita estes)

| Erro | Consequência | Correção |
|---|---|---|
| Pedir "o sistema inteiro" à IA de uma vez | Bug emaranhado, impossível de rever | Fatiar sempre |
| Entregar sem testes | Bug na produção, cliente zangado | Regra 3: sem teste não está feito |
| Copy-paste cego de scripts da internet | Vulnerabilidade ou lógica errada | Ler e perceber antes |
| Alterar schema à mão em produção | Perda de dados | Migrações versionadas sempre |
| Customizar o núcleo por um cliente | Quebra todos os outros | `app/custom/CLIENTE_X/` |
| Não documentar o que a IA fez | Não consegues manter o que entregaste | Pedir resumo a cada mudança |
| Deploy à pressa para "fazer feliz" | Cliente vê bug no dia 1 | Demo + smoke test primeiro |

