<?php
/**
 * Runner de testes de integração.
 *
 * Cria uma base de testes dedicada (produto_base_test), aplica as
 * migrações, limpa os dados e corre os testes. Cada teste é uma
 * função que usa assertTrue / assertEquals.
 *
 * Uso:
 *   php tests/run.php
 *
 * Termina com código de saída 1 se algum teste falhar (CI-ready).
 */

require_once __DIR__ . '/../app/core/bootstrap.php';

$testDb = DB_NAME . '_test';

// 1. Garante que a base de testes existe
try {
    $admin = new PDO(
        'mysql:host=' . DB_HOST . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $admin->exec('CREATE DATABASE IF NOT EXISTS ' . $testDb . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
} catch (PDOException $e) {
    die('Não foi possível criar a base de testes: ' . $e->getMessage() . PHP_EOL);
}

// 2. Limpa os dados entre execuções (ordem respeita FKs)
$pdo = getDB($testDb);
$pdo->exec('DELETE FROM whatsapp_messages');
$pdo->exec('DELETE FROM conversas');
$pdo->exec('DELETE FROM clientes');
$pdo->exec('DELETE FROM users');

// 3. Aplica migrações à base de testes
$files = glob(__DIR__ . '/../migrations/*.sql');
sort($files);
foreach ($files as $file) {
    $pdo->exec(file_get_contents($file));
}

// 4. Funções de asserção
$GLOBALS['tests_pass'] = 0;
$GLOBALS['tests_fail'] = 0;

function assertTrue(bool $condition, string $label): void
{
    if ($condition) {
        $GLOBALS['tests_pass']++;
        echo '  ✓ ' . $label . PHP_EOL;
    } else {
        $GLOBALS['tests_fail']++;
        echo '  ✗ FALHOU: ' . $label . PHP_EOL;
    }
}

function assertEquals($expected, $actual, string $label): void
{
    if ($expected === $actual) {
        $GLOBALS['tests_pass']++;
        echo '  ✓ ' . $label . PHP_EOL;
    } else {
        $GLOBALS['tests_fail']++;
        echo '  ✗ FALHOU: ' . $label
           . ' (esperado: ' . var_export($expected, true)
           . ', obtido: ' . var_export($actual, true) . ')' . PHP_EOL;
    }
}

// 5. Corre os testes de cada módulo
$testFiles = glob(__DIR__ . '/*_test.php');
sort($testFiles);
foreach ($testFiles as $file) {
    $module = basename($file, '_test.php');
    $serviceFile = APP_ROOT . '/app/modules/' . $module . '/service.php';
    if (file_exists($serviceFile)) {
        require_once $serviceFile;
    }
    echo '== ' . strtoupper($module) . ' ==' . PHP_EOL;
    require_once $file;
    echo PHP_EOL;
}

// 6. Resumo
$total = $GLOBALS['tests_pass'] + $GLOBALS['tests_fail'];
echo 'Resultado: ' . $GLOBALS['tests_pass'] . '/' . $total . ' testes passaram' . PHP_EOL;
exit($GLOBALS['tests_fail'] > 0 ? 1 : 0);
