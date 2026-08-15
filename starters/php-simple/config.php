<?php
/**
 * Configuração do projeto
 * Copia este ficheiro e preenche com os teus dados.
 */

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'meu_projeto');
define('DB_USER', 'root');
define('DB_PASS', '');

// App
define('APP_NAME', 'Meu Projeto');
define('APP_URL', 'http://localhost/meu-projeto');
define('TIMEZONE', 'Europe/Lisbon');

date_default_timezone_set(TIMEZONE);

// PDO connection
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            die('Erro de conexão: ' . $e->getMessage());
        }
    }
    return $pdo;
}
