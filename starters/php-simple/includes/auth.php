<?php
/**
 * Verificação de autenticação
 */

require_once __DIR__ . '/../config.php';

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function requireAuth(): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after'] = $_SERVER['REQUEST_URI'] ?? '/';
        redirect('login.php');
    }
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;

    $stmt = getDB()->prepare("SELECT id, nome, email, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}
