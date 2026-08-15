<?php
/**
 * Conexão PDO (MySQL).
 * Suporta várias bases (ex.: uma base de testes) via parâmetro.
 */

function getDB(string $dbName = DB_NAME): PDO
{
    static $connections = [];

    if (!isset($connections[$dbName])) {
        try {
            $connections[$dbName] = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . $dbName . ';charset=utf8mb4',
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

    return $connections[$dbName];
}
