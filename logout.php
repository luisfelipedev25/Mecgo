<?php
/**
 * MecGo - Sair da conta
 * Local: htdocs/mecgo/logout.php
 * Encerra a sessão e volta para o login. Usado pelos links "Sair" e "Conta".
 */

declare(strict_types=1);

session_start();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

header('Location: login_cliente.php');
exit;
