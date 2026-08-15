<?php
/**
 * Aplica as migrações pendentes à base de produção.
 *
 * Uso:
 *   php migrations/run.php
 *   (garante que a base "produto_base" já existe)
 */

require_once __DIR__ . '/../app/core/bootstrap.php';

$files = glob(__DIR__ . '/*.sql');
sort($files);

$applied = 0;
foreach ($files as $file) {
    $name = basename($file);
    $sql = file_get_contents($file);
    getDB()->exec($sql);
    echo 'Aplicada: ' . $name . PHP_EOL;
    $applied++;
}

echo 'Migrações aplicadas: ' . $applied . PHP_EOL;
