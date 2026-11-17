<?php
require_once 'auth.php';
require_once 'conexao.php';

exigirLogin('CLIENTE');

$idCliente = (int) $_SESSION['id_usuario'];

$stmt = $pdo->prepare("
    SELECT c.id_chamado, c.avaria, c.status, c.criado_em,
           v.marca, v.modelo, v.placa,
           u.nome AS prestador_nome
    FROM chamado c
    INNER JOIN veiculo v ON v.id_veiculo = c.id_veiculo
    LEFT JOIN usuario u ON u.id_usuario = c.id_prestador
    WHERE c.id_cliente = ?
    ORDER BY c.id_chamado DESC
");
$stmt->execute([$idCliente]);
$chamados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$nomesAvaria = [
    'pneu'    => 'Pneu furado',
    'bateria' => 'Bateria',
    'guincho' => 'Guincho',
    'pane'    => 'Pane mecânica',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e66927">
    <title>Histórico | MecGo</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="app branco">

    <header class="topo-app">
        <a href="perfil.php" class="voltar">‹ Voltar</a>
        <h1>Histórico</h1>
        <span class="direita"></span>
    </header>

    <main class="content">
        <?php if (count($chamados) === 0): ?>
            <article class="card" style="text-align:center;color:var(--muted);">
                Você ainda não possui nenhum chamado no seu histórico.
            </article>
        <?php else: ?>
            <?php foreach ($chamados as $ch): ?>
                <?php
                $status = $ch['status'];
                if ($status === 'concluido') {
                    $badgeClass = 'badge-concl';
                    $badgeTexto = 'Concluído';
                } elseif ($status === 'cancelado') {
                    $badgeClass = 'badge-canc';
                    $badgeTexto = 'Cancelado';
                } else {
                    $badgeClass = 'badge-andamento';
                    $badgeTexto = 'Em andamento';
                }
                $prestadorLabel = !empty($ch['prestador_nome']) ? $ch['prestador_nome'] : 'Aguardando prestador';
                $dataFormatada = date('d/m/Y H:i', strtotime($ch['criado_em']));
                ?>
                <a href="acompanhar.php?id_chamado=<?= (int) $ch['id_chamado'] ?>" style="text-decoration:none;color:inherit;display:block;">
                    <article class="card hist-item">
                        <div>
                            <strong><?= htmlspecialchars($nomesAvaria[$ch['avaria']] ?? ucfirst($ch['avaria'])) ?></strong>
                            <small>
                                <?= htmlspecialchars($prestadorLabel) ?> ·
                                <?= htmlspecialchars($ch['marca'] . ' ' . $ch['modelo']) ?> ·
                                <?= $dataFormatada ?>
                            </small>
                        </div>
                        <span class="badge <?= $badgeClass ?>"><?= $badgeTexto ?></span>
                    </article>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <nav class="nav-inferior" aria-label="Navegação principal">
        <a href="perfil.php" class="ativo"><span class="ico">👤</span>Perfil</a>
        <a href="servicos.php"><span class="ico">🔧</span>Serviços</a>
        <a href="inicio.php"><span class="ico">🏠</span>Início</a>
        <a href="notificacoes.php"><span class="ico">🔔</span>Notificações<span class="ponto-notif"></span></a>
    </nav>
</div>
</body>
</html>
