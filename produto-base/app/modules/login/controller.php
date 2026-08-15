<?php
/**
 * Login
 */

function login_form(): void
{
    if (isLoggedIn()) {
        redirect('index.php');
    }

    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
            $error = 'Token inválido. Tenta novamente.';
        } else {
            $email = sanitize($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($email === '' || $password === '') {
                $error = 'Preenche todos os campos.';
            } else {
                $stmt = getDB()->prepare('SELECT * FROM users WHERE email = ?');
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$user['id'];

                    $redirect = $_SESSION['redirect_after'] ?? 'index.php';
                    unset($_SESSION['redirect_after']);
                    redirect($redirect);
                } else {
                    $error = 'Email ou password incorretos.';
                }
            }
        }
    }

    require APP_ROOT . '/app/modules/login/views/form.php';
}
