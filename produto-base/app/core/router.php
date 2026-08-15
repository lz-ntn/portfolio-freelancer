<?php
/**
 * Router simples: despacha para um módulo e uma acção.
 *
 * A convenção é:
 *   - Ficheiro do módulo:  app/modules/{modulo}/controller.php
 *   - Função da acção:     {modulo}_{acao}()
 *
 * Ex.:  index.php?page=clientes&action=list  →  app/modules/clientes/controller.php::clientes_list()
 */

function dispatch(string $module, string $action): void
{
    $module = preg_replace('/[^a-z0-9_]/', '', $module) ?: 'dashboard';
    $action = preg_replace('/[^a-z0-9_]/', '', $action) ?: 'list';

    $controllerFile = APP_ROOT . '/app/modules/' . $module . '/controller.php';
    if (!file_exists($controllerFile)) {
        http_response_code(404);
        echo 'Módulo não encontrado: ' . escapeHtml($module);
        return;
    }

    // Carrega o service do módulo (lógica de negócio) antes do controller
    $serviceFile = APP_ROOT . '/app/modules/' . $module . '/service.php';
    if (file_exists($serviceFile)) {
        require_once $serviceFile;
    }

    require_once $controllerFile;

    $fn = $module . '_' . $action;
    if (!function_exists($fn)) {
        http_response_code(404);
        echo 'Ação não encontrada: ' . escapeHtml($action);
        return;
    }

    $fn();
}
