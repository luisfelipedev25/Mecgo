<?php
session_start();

// Se já estiver logado, redireciona para o dashboard
if (isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true) {
    header('Location: admin_dashboard.php');
    exit;
}

$db_host = 'localhost';
$db_name = 'mecgo_db';
$db_user = 'root';
$db_pass = '';
$db_charset = 'utf8mb4';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $senha = $_POST['senha'] ?? '';

    if (!$email || empty($senha)) {
        $erro = 'Preencha todos os campos corretamente.';
    } else {
        try {
            $dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";
            $pdo = new PDO($dsn, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            $stmt = $pdo->prepare("SELECT * FROM usuarios_admin WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($senha, $admin['senha'])) {
                $_SESSION['admin_logado'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_nome'] = $admin['nome'];
                $_SESSION['admin_email'] = $admin['email'];

                header('Location: admin_dashboard.php');
                exit;
            } else {
                $erro = 'E-mail ou senha incorretos.';
            }
        } catch (PDOException $e) {
            $erro = 'Erro de conexão: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MecGo - Login Administrativo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-card { max-width: 400px; width: 100%; border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<div class="card login-card p-4">
    <div class="text-center mb-4">
        <h3 class="fw-bold text-dark"><i class="bi bi-wrench-adjustable-circle-fill text-warning me-2"></i>MecGo</h3>
        <p class="text-muted small">Acesso Restrito ao Painel Administrativo</p>
    </div>

    <?php if (!empty($erro)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($erro) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form action="" method="POST">
        <div class="mb-3">
            <label for="email" class="form-label fw-semibold">E-mail</label>
            <input type="email" class="form-control" id="email" name="email" required placeholder="admin@mecgo.com.br">
        </div>
        <div class="mb-4">
            <label for="senha" class="form-label fw-semibold">Senha</label>
            <input type="password" class="form-control" id="senha" name="senha" required placeholder="••••••••">
        </div>
        <button type="submit" class="btn btn-warning w-100 fw-bold py-2">Entrar no Painel</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>