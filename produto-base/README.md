# Produto Base — CRM/ERP reutilizável

Núcleo de sistema de gestão para PMEs. O objetivo: **70-80% do código é igual em todos os clientes**; só 20-30% é customizado. Entrega em 3-4 semanas em vez de 3 meses.

## Estrutura

```
produto-base/
├── app/
│   ├── core/                # NÃO muda entre clientes (<5% de alterações)
│   │   ├── bootstrap.php    # carrega tudo por ordem
│   │   ├── config.php       # credenciais + constantes
│   │   ├── db.php           # conexão PDO (suporta base de testes)
│   │   ├── auth.php         # login, sessões, requireAuth/requireRole
│   │   ├── roles.php        # papéis + matriz de permissões
│   │   ├── validator.php    # validação de formulários
│   │   ├── router.php       # despacho módulo/acção
│   │   └── helpers.php      # escape, redirect, flash, CSRF
│   ├── modules/             # UM diretório por módulo (o que vendes)
│   │   ├── clientes/        # exemplo completo (service+controller+views)
│   │   ├── whatsapp/        # WhatsApp Business via Meta Cloud API
│   │   ├── dashboard/
│   │   ├── login/
│   │   └── logout/
│   └── custom/              # customização isolada por cliente
├── migrations/              # schema versionado (001, 002, ...)
│   ├── run.php
│   └── 001_initial.sql
├── public/                  # front controller único
│   └── index.php
└── tests/                   # testes de integração PHP
    ├── run.php
    └── clientes_test.php
```

## Como cada página é servida

Tudo passa por `public/index.php` (front controller). A convenção:

```
index.php?page=clientes&action=list   →  app/modules/clientes/controller.php::clientes_list()
index.php?page=clientes&action=form   →  clientes_form()   (novo/editar)
index.php?page=clientes&action=save   →  clientes_save()   (POST)
index.php?page=clientes&action=delete →  clientes_delete()
```

Vantagens do front controller: auth + CSRF centralizados, zero lógica fora de `app/`.

## Setup

```bash
# 1. Criar a base em MySQL e aplicar migrações
mysql -u root -p < migrations/001_initial.sql   # ou via phpMyAdmin

# 2. Copiar o projeto para o servidor (LAMPP/XAMPP)
cp -r produto-base/public /opt/lampp/htdocs/meu-projeto   # só o public/

# 3. Ajustar app/core/config.php (base, url, credenciais)

# 4. Correr migrações (se feitas por script)
php migrations/run.php
```

Login padrão: `admin@exemplo.com` / `admin123`

## Adicionar um novo módulo (estoque, vendas, financeiro...)

Repete o padrão do módulo `clientes`:

1. **Migração** → `migrations/002_estoque.sql` (nunca editar a 001)
2. **Service** → `app/modules/estoque/service.php` (lógica de negócio, recebe PDO)
3. **Controller** → `app/modules/estoque/controller.php` (valida, chama service, redireciona)
4. **Views** → `app/modules/estoque/views/list.php` e `form.php`
5. **Testes** → `tests/estoque_test.php` (usa o mesmo runner)

## Módulo WhatsApp Business

Módulo completo de WhatsApp: envio de mensagens e templates, histórico por cliente,
webhook da Meta Cloud API (respostas + estados delivered/read) e modo simulação para demo.

```bash
# Migração das tabelas (conversas, mensagens, templates)
mysql -u root -p < migrations/002_whatsapp.sql

# Modo simulação: deixar WHATSAPP_TOKEN vazio em app/core/config.php
# Produção: ver app/modules/whatsapp/README.md
```

Spec completa: `specs/whatsapp-business.md` · Testes: 54/54 verdes (CA-1..CA-9)

## Testes

```bash
php tests/run.php
```

O runner cria a base `produto_base_test`, aplica as migrações, limpa os dados,
corre todos os `*_test.php` e devolve código de saída 1 se algo falhar (CI-ready).

## Regras de ouro (para não deixar bugs)

1. Sem teste = não está feito.
2. Mudança no `core/` quebra todos os clientes → rever e testar globalmente.
3. Customização de cliente vive em `app/custom/CLIENTE_X/`, nunca no núcleo.
4. Schema só muda por migrações versionadas.
5. Depois de adicionar funcionalidade: pede à IA para gerar os testes + resumo.
