<?php
session_start();
require_once 'conexao.php';

$erro = '';
$sucesso = '';

if (isset($_GET['cadastro']) && $_GET['cadastro'] === 'ok') {
    $sucesso = 'Conta criada com sucesso! Faça login para continuar.';
} elseif (isset($_GET['cadastro_prestador']) && $_GET['cadastro_prestador'] === 'ok') {
    $sucesso = 'Cadastro de prestador enviado! Aguarde a aprovação da equipe para entrar.';
} elseif (isset($_GET['senha_redefinida']) && $_GET['senha_redefinida'] === 'ok') {
    $sucesso = 'Senha redefinida com sucesso! Já pode entrar com a nova senha.';
}

$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $tipoSelecionado = $_POST['tipo_perfil'] ?? 'CLIENTE';

    if ($email === '' || $senha === '') {
        $erro = 'Preencha seu e-mail e senha.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id_usuario, nome, email, senha, tipo, status FROM usuario WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario || !password_verify($senha, $usuario['senha'])) {
                $erro = 'E-mail ou senha incorretos.';
            } elseif ($usuario['tipo'] !== $tipoSelecionado) {
                $erro = 'Esta conta não pertence ao perfil selecionado (' . strtolower($tipoSelecionado) . ').';
            } elseif ($usuario['status'] === 'suspenso') {
                $erro = 'Sua conta está suspensa. Entre em contato com o suporte.';
            } elseif ($usuario['status'] === 'pendente' && $usuario['tipo'] === 'PRESTADOR') {
                $erro = 'Seu cadastro de prestador ainda está aguardando aprovação da equipe.';
            } else {
                session_regenerate_id(true);
                $_SESSION['id_usuario'] = $usuario['id_usuario'];
                $_SESSION['nome'] = $usuario['nome'];
                $_SESSION['perfil'] = $usuario['tipo'];

                if ($usuario['tipo'] === 'CLIENTE') {
                    // Verifica se o cliente já tem pelo menos 1 veículo cadastrado
                    $stmtVeiculo = $pdo->prepare('SELECT id_veiculo FROM veiculo WHERE id_cliente = ? LIMIT 1');
                    $stmtVeiculo->execute([$usuario['id_usuario']]);

                    if (!$stmtVeiculo->fetch()) {
                        header('Location: garagem.php');
                    } else {
                        header('Location: inicio.php');
                    }
                    exit;
                } elseif ($usuario['tipo'] === 'PRESTADOR') {
                    header('Location: painel_chamados.php');
                    exit;
                } elseif ($usuario['tipo'] === 'ADMIN') {
                    header('Location: admin_dashboard.php');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $erro = 'Erro ao realizar login: ' . $e->getMessage();
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
    <title>Entrar | MecGo</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="app sem-nav entrada">

    <header class="topbar centro">
        <div class="brand grande">Mec<span>Go</span></div>
        <p class="sub">Socorro mecânico quando você mais precisa.</p>
    </header>

    <main class="content">
        <h1 class="boas-vindas">Bem-vindo!</h1>

        <?php if ($sucesso !== ''): ?>
            <div style="background:#e8f5e9;color:#1b5e20;padding:12px;border-radius:10px;margin-bottom:14px;font-size:14px;">
                <?= htmlspecialchars($sucesso) ?>
            </div>
        <?php endif; ?>

        <?php if ($erro !== ''): ?>
            <div style="background:#fdecea;color:#b71c1c;padding:12px;border-radius:10px;margin-bottom:14px;font-size:14px;">
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <!-- Escolha do tipo de usuário (sem JavaScript: radios + CSS) -->
        <input type="radio" name="tipo" id="t-motorista" class="tipo-input t-motorista" checked>
        <input type="radio" name="tipo" id="t-prestador" class="tipo-input t-prestador">
        <input type="radio" name="tipo" id="t-admin" class="tipo-input t-admin">

        <p class="rotulo-tipo">Eu sou</p>
        <div class="tipo-abas" role="radiogroup" aria-label="Tipo de usuário">
            <label for="t-motorista"><span class="ico">🚗</span>Motorista</label>
            <label for="t-prestador"><span class="ico">🛠️</span>Prestador</label>
            <label for="t-admin"><span class="ico">🛡️</span>Admin</label>
        </div>

        <form class="form-entrada" action="" method="post">
            <div class="campo">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>"
                       placeholder="voce@email.com" autocomplete="username" autocapitalize="none" required>
            </div>
            <div class="campo">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" placeholder="Sua senha"
                       autocomplete="current-password" required>
            </div>
            <p class="esqueci"><a href="recuperar_senha.php">Esqueci minha senha</a></p>

            <!-- Um botão "Entrar" para cada tipo; o CSS mostra só o escolhido e envia o valor certo -->
            <button type="submit" name="tipo_perfil" value="CLIENTE" class="btn acao acao-motorista">Entrar</button>
            <button type="submit" name="tipo_perfil" value="PRESTADOR" class="btn acao acao-prestador">Entrar</button>
            <button type="submit" name="tipo_perfil" value="ADMIN" class="btn acao acao-admin">Entrar</button>
        </form>

        <p class="rodape-link cad cad-motorista">Não tem conta? <a href="cadastro_cliente.php">cadastre-se aqui</a></p>
        <p class="rodape-link cad cad-prestador">Quer atender chamados? <a href="cadastro_prestador.php">cadastre-se aqui</a></p>
        <p class="rodape-link cad cad-admin">Acesso restrito à equipe gestora.</p>

        <p class="link-telas"><a href="../HTML/telas.html">Ver mapa de todas as telas</a></p>
    </main>
</div>
</body>
</html>
