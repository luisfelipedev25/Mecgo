<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function exigirLogin(?string $perfilPermitido = null): void
{
    if (!isset($_SESSION['id_usuario'])) {
        header('Location: index.php');
        exit;
    }

    if ($perfilPermitido !== null && ($_SESSION['perfil'] ?? '') !== $perfilPermitido) {
        header('Location: index.php');
        exit;
    }
}
?>
