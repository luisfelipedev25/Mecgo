<?php
session_start();
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header('Location: admin_login.php');
    exit;
}

$db_host = 'localhost';
$db_name = 'mecgo_db';
$db_user = 'root';
$db_pass = '';
$db_charset = 'utf8mb4';

$msg_sucesso = '';
$msg_erro = '';

try {
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Erro ao conectar: " . $e->getMessage());
}

// Processamento POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = filter_input(INPUT_POST, 'acao', FILTER_SANITIZE_SPECIAL_CHARS);

    if ($acao === 'alterar_status') {
        $id = filter_input(INPUT_POST, 'id_prestador', FILTER_VALIDATE_INT);
        $status = filter_input(INPUT_POST, 'status_moderacao', FILTER_SANITIZE_SPECIAL_CHARS);
        $motivo = filter_input(INPUT_POST, 'motivo_rejeicao', FILTER_SANITIZE_SPECIAL_CHARS);

        if ($id && in_array($status, ['aprovado', 'pendente', 'rejeitado', 'suspenso'])) {
            $stmt = $pdo->prepare("UPDATE socorristas SET status_moderacao = :status, motivo_rejeicao = :motivo WHERE id = :id");
            $stmt->execute([':status' => $status, ':motivo' => ($status === 'rejeitado' ? $motivo : null), ':id' => $id]);
            $msg_sucesso = "Status do prestador atualizado com sucesso!";
        }
    } elseif ($acao === 'atualizar_conta_admin') {
        $nome = filter_input(INPUT_POST, 'admin_nome', FILTER_SANITIZE_SPECIAL_CHARS);
        $email = filter_input(INPUT_POST, 'admin_email', FILTER_VALIDATE_EMAIL);
        $nova_senha = $_POST['nova_senha'] ?? '';

        if ($nome && $email) {
            if (!empty($nova_senha)) {
                $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE usuarios_admin SET nome = :nome, email = :email, senha = :senha WHERE id = :id");
                $stmt->execute([':nome' => $nome, ':email' => $email, ':senha' => $hash, ':id' => $_SESSION['admin_id']]);
            } else {
                $stmt = $pdo->prepare("UPDATE usuarios_admin SET nome = :nome, email = :email WHERE id = :id");
                $stmt->execute([':nome' => $nome, ':email' => $email, ':id' => $_SESSION['admin_id']]);
            }
            $_SESSION['admin_nome'] = $nome;
            $_SESSION['admin_email'] = $email;
            $msg_sucesso = "Conta do Administrador atualizada com sucesso!";
        } else {
            $msg_erro = "Por favor, preencha nome e e-mail válidos.";
        }
    }
}

// Consultas
$filtro = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'todos';

if (in_array($filtro, ['pendente', 'aprovado', 'rejeitado', 'suspenso'])) {
    $stmt = $pdo->prepare("SELECT * FROM socorristas WHERE status_moderacao = :status ORDER BY id DESC");
    $stmt->execute([':status' => $filtro]);
} else {
    $stmt = $pdo->query("SELECT * FROM socorristas ORDER BY id DESC");
}
$prestadores = $stmt->fetchAll();

$stmt_admin = $pdo->prepare("SELECT * FROM usuarios_admin WHERE id = :id");
$stmt_admin->execute([':id' => $_SESSION['admin_id']]);
$admin_dados = $stmt_admin->fetch();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MecGo - Moderação & Gestão de Contas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; }
        .card-custom { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand text-warning fw-bold" href="#"><i class="bi bi-wrench-adjustable-circle-fill me-2"></i>MecGo Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMenu">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="admin_dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="aprovar_prestadores.php">Aprovação & Moderação</a></li>
                </ul>
                <div class="d-flex text-white align-items-center gap-3">
                    <small><i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($_SESSION['admin_nome']) ?></small>
                    <a href="admin_dashboard.php?logout=1" class="btn btn-outline-light btn-sm">Sair</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container my-4">

        <?php if (!empty($msg_sucesso)): ?>
            <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?= $msg_sucesso ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <?php if (!empty($msg_erro)): ?>
            <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= $msg_erro ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <ul class="nav nav-pills mb-4 gap-2 border-bottom pb-3" id="pills-tab" role="tablist">
            <li class="nav-item">
                <button class="nav-link active fw-bold" id="pills-moderacao-tab" data-bs-toggle="pill" data-bs-target="#pills-moderacao" type="button" role="tab"><i class="bi bi-shield-check me-1"></i> Moderação de Prestadores</button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold" id="pills-conta-tab" data-bs-toggle="pill" data-bs-target="#pills-conta" type="button" role="tab"><i class="bi bi-person-gear me-1"></i> Minha Conta Admin</button>
            </li>
        </ul>

        <div class="tab-content" id="pills-tabContent">
            <!-- ABA 1: MODERAÇÃO -->
            <div class="tab-pane fade show active" id="pills-moderacao" role="tabpanel">
                <div class="btn-group btn-group-sm mb-3">
                    <a href="?status=todos" class="btn btn-outline-secondary <?= $filtro === 'todos' ? 'active' : '' ?>">Todos</a>
                    <a href="?status=pendente" class="btn btn-outline-warning text-dark <?= $filtro === 'pendente' ? 'active' : '' ?>">Pendentes</a>
                    <a href="?status=aprovado" class="btn btn-outline-success <?= $filtro === 'aprovado' ? 'active' : '' ?>">Aprovados</a>
                    <a href="?status=rejeitado" class="btn btn-outline-danger <?= $filtro === 'rejeitado' ? 'active' : '' ?>">Rejeitados</a>
                </div>

                <div class="card card-custom">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nome / CPF</th>
                                        <th>Serviço</th>
                                        <th>Contato</th>
                                        <th>Status Moderação</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($prestadores)): ?>
                                        <tr><td colspan="5" class="text-center py-4 text-muted">Nenhum prestador encontrado.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($prestadores as $p): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($p['nome']) ?></strong>
                                                    <br><small class="text-muted"><?= htmlspecialchars($p['documento'] ?? 'N/I') ?></small>
                                                </td>
                                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($p['tipo']) ?></span></td>
                                                <td><?= htmlspecialchars($p['telefone']) ?></td>
                                                <td>
                                                    <?php if ($p['status_moderacao'] === 'aprovado'): ?>
                                                        <span class="badge bg-success">Aprovado</span>
                                                    <?php elseif ($p['status_moderacao'] === 'pendente'): ?>
                                                        <span class="badge bg-warning text-dark">Pendente</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Rejeitado</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <form action="" method="POST" class="d-inline">
                                                        <input type="hidden" name="acao" value="alterar_status">
                                                        <input type="hidden" name="id_prestador" value="<?= $p['id'] ?>">
                                                        <input type="hidden" name="status_moderacao" value="aprovado">
                                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Aprovar"><i class="bi bi-check-lg"></i></button>
                                                    </form>
                                                    <form action="" method="POST" class="d-inline">
                                                        <input type="hidden" name="acao" value="alterar_status">
                                                        <input type="hidden" name="id_prestador" value="<?= $p['id'] ?>">
                                                        <input type="hidden" name="status_moderacao" value="rejeitado">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Rejeitar"><i class="bi bi-x-lg"></i></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ABA 2: GESTÃO DE CONTA -->
            <div class="tab-pane fade" id="pills-conta" role="tabpanel">
                <div class="row justify-content-center">
                    <div class="col-12 col-md-6">
                        <div class="card card-custom p-4">
                            <h5 class="fw-bold mb-3 text-secondary"><i class="bi bi-person-gear me-2"></i>Alterar Dados da Conta</h5>
                            <form action="" method="POST">
                                <input type="hidden" name="acao" value="atualizar_conta_admin">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Nome</label>
                                    <input type="text" name="admin_nome" class="form-control" value="<?= htmlspecialchars($admin_dados['nome'] ?? '') ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">E-mail</label>
                                    <input type="email" name="admin_email" class="form-control" value="<?= htmlspecialchars($admin_dados['email'] ?? '') ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Nova Senha (opcional)</label>
                                    <input type="password" name="nova_senha" class="form-control" placeholder="Deixe em branco para não alterar">
                                </div>
                                <button type="submit" class="btn btn-warning w-100 fw-bold">Salvar Alterações</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>