<?php
/**
 * Ponto de entrada único (front controller).
 * Todas as páginas passam por aqui → auth e CSRF centralizados.
 */

require_once __DIR__ . '/../app/core/bootstrap.php';

session_start();

$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? 'list';

dispatch($page, $action);
