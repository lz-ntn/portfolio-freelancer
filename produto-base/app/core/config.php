<?php
/**
 * Configuração do produto base
 * Copia este ficheiro para cada projeto e ajusta os valores.
 */

// Paths
define('APP_ROOT', dirname(__DIR__, 2));

// Database (produção)
define('DB_HOST', 'localhost');
define('DB_NAME', 'produto_base');
define('DB_USER', 'root');
define('DB_PASS', '');

// App
define('APP_NAME', 'Produto Base');
define('APP_URL', 'http://localhost/produto-base');
define('TIMEZONE', 'Europe/Lisbon');

date_default_timezone_set(TIMEZONE);

// WhatsApp Business API (Meta Cloud API)
// Token/phone_id vazios => modo simulação (envio registado, sem chamada real)
define('WHATSAPP_TOKEN', '');
define('WHATSAPP_PHONE_ID', '');
define('WHATSAPP_VERIFY_TOKEN', '');
define('WHATSAPP_API_URL', 'https://graph.facebook.com/v19.0');
