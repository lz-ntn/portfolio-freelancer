<?php
/**
 * Verificação de autenticação e permissões
 */

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function requireAuth(): void
{
    if (!isLoggedIn()) {
        $_SESSION['redirect_after'] = $_SERVER['REQUEST_URI'] ?? '/';
        redirect('index.php?page=login');
    }
}

function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    $stmt = getDB()->prepare('SELECT id, nome, email, role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

/**
 * Impede acesso a utilizadores sem o papel (role) exigido.
 */
function requireRole(string $requiredRole): void
{
    requireAuth();

    $user = currentUser();
    if ($user && !hasRole($user, $requiredRole)) {
        http_response_code(403);
        die('Sem permissão para aceder a esta página.');
    }
}
