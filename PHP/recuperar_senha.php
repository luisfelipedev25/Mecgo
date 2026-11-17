<?php
require_once 'conexao.php';

$erro = '';
$email = '';
$cpf = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $novaSenha = $_POST['nova_senha'] ?? '';
    $confirmaSenha = $_POST['confirma_senha'] ?? '';

    if ($email === '' || $cpf === '' || $novaSenha === '') {
        $erro = 'Preencha todos os campos para redefinir sua senha.';
    } elseif (strlen($novaSenha) < 8) {
        $erro = 'A nova senha deve ter no mínimo 8 caracteres.';
    } elseif ($novaSenha !== $confirmaSenha) {
        $erro = 'As senhas não conferem.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id_usuario FROM usuario WHERE email = ? AND cpf = ? LIMIT 1');
            $stmt->execute([$email, $cpf]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                $erro = 'Não encontramos nenhuma conta com este e-mail e CPF.';
            } else {
                $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE usuario SET senha = ? WHERE id_usuario = ?')
                    ->execute([$hash, (int) $usuario['id_usuario']]);

                header('Location: index.php?senha_redefinida=ok');
                exit;
            }
        } catch (PDOException $e) {
            $erro = 'Erro ao redefinir senha: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e66927">
    <title>Recuperar senha | MecGo</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="app sem-nav entrada">

    <header class="topbar centro">
        <div class="brand grande">Mec<span>Go</span></div>
        <p class="sub">Recupere o acesso à sua conta.</p>
    </header>

    <main class="content">
        <section class="card">
            <h1 style="font-size:1.2rem;margin-bottom:.4rem">Esqueci minha senha</h1>
            <p class="dica" style="margin-bottom:1rem">Confirme seu e-mail e CPF cadastrados para definir uma nova senha.</p>

            <?php if ($erro !== ''): ?>
                <div style="background:#fdecea;color:#b71c1c;padding:12px;border-radius:10px;margin-bottom:14px;font-size:14px;">
                    <?= htmlspecialchars($erro) ?>
                </div>
            <?php endif; ?>

            <form action="" method="post">
                <div class="campo">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>"
                           placeholder="voce@email.com" required>
                </div>
                <div class="campo">
                    <label for="cpf">CPF cadastrado</label>
                    <input type="text" id="cpf" name="cpf" value="<?= htmlspecialchars($cpf) ?>"
                           maxlength="14" placeholder="000.000.000-00" required>
                </div>
                <div class="campo">
                    <label for="nova_senha">Nova senha</label>
                    <input type="password" id="nova_senha" name="nova_senha" minlength="8" maxlength="72"
                           placeholder="Mínimo 8 caracteres" required>
                </div>
                <div class="campo">
                    <label for="confirma_senha">Confirmar nova senha</label>
                    <input type="password" id="confirma_senha" name="confirma_senha" minlength="8" maxlength="72"
                           placeholder="Repita a nova senha" required>
                </div>
                <button type="submit" class="btn">Redefinir senha</button>
            </form>
        </section>
        <p class="rodape-link"><a href="index.php">‹ Voltar ao login</a></p>
    </main>
</div>
</body>
</html>
