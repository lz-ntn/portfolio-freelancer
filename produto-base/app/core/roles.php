<?php
/**
 * Papéis (roles) e permissões
 */

const ROLE_ADMIN = 'admin';
const ROLE_GESTOR = 'gestor';
const ROLE_COLABORADOR = 'colaborador';

const ALL_ROLES = [ROLE_ADMIN, ROLE_GESTOR, ROLE_COLABORADOR];

/**
 * Matriz de permissões por papel.
 * A chave é a acção; o valor é a lista de papéis autorizados.
 */
function rolePermissions(): array
{
    return [
        'clientes.list'    => [ROLE_ADMIN, ROLE_GESTOR, ROLE_COLABORADOR],
        'clientes.create'  => [ROLE_ADMIN, ROLE_GESTOR],
        'clientes.update'  => [ROLE_ADMIN, ROLE_GESTOR],
        'clientes.delete'  => [ROLE_ADMIN],
        'whatsapp.view'    => [ROLE_ADMIN, ROLE_GESTOR, ROLE_COLABORADOR],
        'whatsapp.send'    => [ROLE_ADMIN, ROLE_GESTOR],
    ];
}

function hasRole(array $user, string $requiredRole): bool
{
    return $user['role'] === $requiredRole;
}

/**
 * Um utilizador tem permissão para a acção dada?
 */
function can(array $user, string $action): bool
{
    $permissions = rolePermissions();
    if (!isset($permissions[$action])) {
        return false;
    }
    return in_array($user['role'], $permissions[$action], true);
}
