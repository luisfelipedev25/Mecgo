<?php
require_once 'auth.php';
require_once 'conexao.php';

exigirLogin('ADMIN');

$idAdminLogado = (int) $_SESSION['id_usuario'];
$erro = '';
$sucesso = isset($_GET['msg']) && $_GET['msg'] === 'atualizado'
    ? 'Status do usuário atualizado com sucesso!'
    : '';

// Processa alteração de status (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idAlvo = (int) ($_POST['id_usuario'] ?? 0);
    $novoStatus = $_POST['novo_status'] ?? '';

    if ($idAlvo === $idAdminLogado) {
        $erro = 'Você não pode alterar o status da sua própria conta administrativa.';
    } elseif ($idAlvo > 0 && in_array($novoStatus, ['ativo', 'suspenso', 'pendente'], true)) {
        try {
            $stmt = $pdo->prepare('UPDATE usuario SET status = ? WHERE id_usuario = ?');
            $stmt->execute([$novoStatus, $idAlvo]);

            header('Location: admin_usuarios.php?msg=atualizado');
            exit;
        } catch (PDOException $e) {
            $erro = 'Erro ao atualizar status do usuário: ' . $e->getMessage();
        }
    }
}

// Busca todos os usuários e dados extras de prestador (se aplicável)
$stmtLista = $pdo->query("
    SELECT u.id_usuario, u.nome, u.email, u.telefone, u.tipo, u.status, u.criado_em,
           p.empresa, p.categorias
    FROM usuario u
    LEFT JOIN prestador p ON p.id_prestador = u.id_usuario
    ORDER BY u.id_usuario DESC
");
$usuarios = $stmtLista->fetchAll(PDO::FETCH_ASSOC);

$labelsTipo = [
    'CLIENTE'   => 'Motorista',
    'PRESTADOR' => 'Prestador',
    'ADMIN'     => 'Administrador',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e66927">
    <title>Usuários | Admin | MecGo</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="app admin sem-nav">

    <header class="admin-topo">
        <div class="admin-topo-linha">
            <div class="brand">Mec<span>Go</span> <span class="selo-mini">Admin</span></div>
            <a href="logout.php" class="sair">Sair ➜</a>
        </div>
        <nav class="admin-menu" aria-label="Menu do administrador">
            <a href="admin_dashboard.php">📊 Dashboard</a>
            <a href="aprovar_prestadores.php">✅ Aprovações</a>
            <a href="admin_usuarios.php" class="ativo">👥 Usuários</a>
            <a href="admin_financeiro.php">💰 Financeiro</a>
            <a href="admin_disputas.php">⚖️ Disputas</a>
        </nav>
    </header>

    <main class="content admin-conteudo">
        <div class="cabecalho-lista">
            <h1 class="admin-titulo" style="margin:0">👥 Gestão de usuários</h1>
            <span class="badge badge-categoria"><?= count($usuarios) ?> contas</span>
        </div>
        <p class="dica" style="margin:0 .25rem 1rem">Gerencie o acesso de motoristas e prestadores na plataforma.</p>

        <?php if ($sucesso !== ''): ?>
            <div class="alert alert-success" role="status" style="margin-bottom:14px;">
                <?= htmlspecialchars($sucesso) ?>
            </div>
        <?php endif; ?>

        <?php if ($erro !== ''): ?>
            <div style="background:#fdecea;color:#b71c1c;padding:12px;border-radius:10px;margin-bottom:14px;font-size:14px;">
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <div class="usr-lista">
            <?php foreach ($usuarios as $u): ?>
                <?php
                $idU = (int) $u['id_usuario'];
                $statusAtual = $u['status'];
                $nomeExib = !empty($u['empresa']) ? $u['empresa'] . ' (' . $u['nome'] . ')' : $u['nome'];

                if ($statusAtual === 'ativo') {
                    $badgeClass = 'badge-ativo';
                    $badgeTxt = 'Ativo';
                } elseif ($statusAtual === 'pendente') {
                    $badgeClass = 'badge-aguardando';
                    $badgeTxt = 'Pendente';
                } else {
                    $badgeClass = 'badge-susp';
                    $badgeTxt = 'Suspenso';
                }
                ?>
                <article class="card usr">
                    <div class="usr-topo">
                        <div>
                            <h2><?= htmlspecialchars($nomeExib) ?></h2>
                            <div class="usr-meta">
                                <?= htmlspecialchars($labelsTipo[$u['tipo']] ?? $u['tipo']) ?>
                                <?= !empty($u['categorias']) ? ' · ' . htmlspecialchars($u['categorias']) : '' ?>
                                · <?= htmlspecialchars($u['email']) ?>
                            </div>
                        </div>
                    </div>

                    <div class="usr-estado">
                        <span class="badge <?= $badgeClass ?>"><?= $badgeTxt ?></span>
                    </div>

                    <?php if ($idU !== $idAdminLogado): ?>
                        <div class="usr-acoes">
                            <?php if ($statusAtual !== 'ativo'): ?>
                                <form action="" method="post" style="display:inline;">
                                    <input type="hidden" name="id_usuario" value="<?= $idU ?>">
                                    <input type="hidden" name="novo_status" value="ativo">
                                    <button type="submit" class="btn btn-contorno">Reativar / Ativar</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($statusAtual !== 'suspenso'): ?>
                                <form action="" method="post" style="display:inline;"
                                      onsubmit="return confirm('Suspender o acesso de <?= htmlspecialchars(addslashes($u['nome'])) ?>?');">
                                    <input type="hidden" name="id_usuario" value="<?= $idU ?>">
                                    <input type="hidden" name="novo_status" value="suspenso">
                                    <button type="submit" class="btn btn-perigo">Suspender</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="usr-acoes">
                            <span class="usr-meta">Conta atual</span>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </main>
</div>
</body>
</html>
