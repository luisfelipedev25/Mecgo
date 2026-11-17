<?php
require_once 'auth.php';
require_once 'conexao.php';

exigirLogin('CLIENTE');

$idUsuario = (int) $_SESSION['id_usuario'];
$erro = '';
$sucesso = isset($_GET['msg']) && $_GET['msg'] === 'atualizado'
    ? 'Dados do perfil atualizados com sucesso!'
    : '';

// Processa atualização de dados cadastrais
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');

    if ($nome === '' || $email === '' || $telefone === '') {
        $erro = 'Preencha nome, e-mail e telefone.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } else {
        try {
            // Verifica se outro usuário já usa este e-mail
            $stmtCheck = $pdo->prepare('SELECT id_usuario FROM usuario WHERE email = ? AND id_usuario <> ? LIMIT 1');
            $stmtCheck->execute([$email, $idUsuario]);

            if ($stmtCheck->fetch()) {
                $erro = 'Este e-mail já está sendo usado por outra conta.';
            } else {
                $stmtUpd = $pdo->prepare('UPDATE usuario SET nome = ?, email = ?, telefone = ? WHERE id_usuario = ?');
                $stmtUpd->execute([$nome, $email, $telefone, $idUsuario]);

                $_SESSION['nome'] = $nome;

                header('Location: perfil.php?msg=atualizado');
                exit;
            }
        } catch (PDOException $e) {
            $erro = 'Erro ao atualizar perfil: ' . $e->getMessage();
        }
    }
}

// Busca dados atuais do motorista
$stmt = $pdo->prepare('SELECT nome, email, cpf, telefone FROM usuario WHERE id_usuario = ? LIMIT 1');
$stmt->execute([$idUsuario]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e66927">
    <title>Perfil | MecGo</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="app branco">

    <header class="topo-app">
        <a href="inicio.php" class="voltar">‹ Voltar</a>
        <h1>Perfil</h1>
        <span class="direita"></span>
    </header>

    <main class="content">

        <div class="perfil-topo">
            <div class="foto-perfil" aria-hidden="true">🧑</div>
            <h2><?= htmlspecialchars($usuario['nome'] ?? 'Motorista') ?></h2>
            <span class="sub-perfil">Motorista · CPF <?= htmlspecialchars($usuario['cpf'] ?? '') ?></span>
        </div>

        <?php if ($sucesso !== ''): ?>
            <div class="alert alert-success" role="status" style="margin-bottom:12px;">
                <?= htmlspecialchars($sucesso) ?>
            </div>
        <?php endif; ?>

        <?php if ($erro !== ''): ?>
            <div style="background:#fdecea;color:#b71c1c;padding:12px;border-radius:10px;margin-bottom:12px;font-size:14px;">
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <form action="" method="post" class="campo-caixa">
            <div class="campo">
                <label for="nome">Nome completo</label>
                <input type="text" id="nome" name="nome" maxlength="100"
                       value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>" required>
            </div>
            <div class="campo">
                <label for="em">E-mail</label>
                <input type="email" id="em" name="email" maxlength="120"
                       value="<?= htmlspecialchars($usuario['email'] ?? '') ?>" required>
            </div>
            <div class="campo">
                <label for="tel">Telefone</label>
                <input type="tel" id="tel" name="telefone" maxlength="15"
                       value="<?= htmlspecialchars($usuario['telefone'] ?? '') ?>" required>
            </div>
            <button type="submit" class="btn btn-ouro">Confirmar</button>
        </form>

        <ul class="menu-perfil">
            <li><a href="garagem.php">🚗 Minha garagem <span class="seta">›</span></a></li>
            <li><a href="acompanhar.php">📍 Acompanhar chamado atual <span class="seta">›</span></a></li>
            <li><a href="historico.php">🧾 Histórico de serviços <span class="seta">›</span></a></li>
            <li><a href="logout.php">↪ Sair da conta <span class="seta">›</span></a></li>
        </ul>

    </main>

    <nav class="nav-inferior cinco" aria-label="Navegação principal">
        <a href="perfil.php" class="ativo"><span class="ico">👤</span>Perfil</a>
        <a href="servicos.php"><span class="ico">🔧</span>Serviços</a>
        <a href="inicio.php"><span class="ico">🏠</span>Início</a>
        <a href="acompanhar.php"><span class="ico">📍</span>Acompanhar</a>
        <a href="../HTML/notificacoes.html"><span class="ico">🔔</span>Notificações<span class="ponto-notif"></span></a>
    </nav>
</div>
</body>
</html>
