# PHP Simple Starter

Estrutura inicial para projetos PHP + MySQL com autenticação.

## Uso

```bash
cp -r php-simple /opt/lampp/htdocs/meu-projeto
# Editar config.php com credenciais da BD
# mysql -u root -p < database/schema.sql
```

## Estrutura

```
├── config.php           # Conexão BD + constantes
├── login.php            # Auth com bcrypt
├── logout.php
├── dashboard.php        # Área protegida
├── includes/
│   ├── auth.php         # Verificação de sessão
│   └── functions.php    # Helpers (escape, format, redirect)
├── css/style.css
├── js/main.js
└── database/schema.sql
```
