<?php
/**
 * Carrega toda a camada core na ordem correcta.
 * Incluir este ficheiro em qualquer ponto de entrada.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validator.php';
require_once __DIR__ . '/router.php';
