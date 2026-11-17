<?php
require_once 'auth.php';
require_once 'conexao.php';

exigirLogin('ADMIN');

$erro = '';
$sucesso = '';

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'liberado_um') {
        $sucesso = 'Pagamento liberado para repasse ao prestador!';
    } elseif ($_GET['msg'] === 'liberado_todos') {
        $sucesso = 'Todos os valores em custódia de chamados concluídos foram liberados!';
    }
}

// Processa liberação de custódia
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    try {
        if ($acao === 'liberar_um') {
            $idPagamento = (int) ($_POST['id_pagamento'] ?? 0);
            if ($idPagamento > 0) {
                $pdo->prepare("UPDATE pagamento SET status = 'liberado' WHERE id_pagamento = ? AND status = 'retido_custodia'")
                    ->execute([$idPagamento]);
                header('Location: admin_financeiro.php?msg=liberado_um');
                exit;
            }
        } elseif ($acao === 'liberar_todos') {
            $pdo->exec("
                UPDATE pagamento p
                INNER JOIN chamado c ON c.id_chamado = p.id_chamado
                SET p.status = 'liberado'
                WHERE p.status = 'retido_custodia' AND c.status = 'concluido'
            ");
            header('Location: admin_financeiro.php?msg=liberado_todos');
            exit;
        }
    } catch (PDOException $e) {
        $erro = 'Erro ao liberar repasse: ' . $e->getMessage();
    }
}

// Busca todas as transações com nomes de cliente e prestador
$stmt = $pdo->query("
    SELECT p.*,
           c.avaria, c.status AS chamado_status,
           u_cli.nome AS cliente_nome,
           u_prest.nome AS prestador_nome
    FROM pagamento p
    INNER JOIN chamado c ON c.id_chamado = p.id_chamado
    INNER JOIN usuario u_cli ON u_cli.id_usuario = c.id_cliente
    INNER JOIN orcamento o ON o.id_orcamento = p.id_orcamento
    INNER JOIN usuario u_prest ON u_prest.id_usuario = o.id_prestador
    ORDER BY p.id_pagamento DESC
");
$transacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$volumeTotal = 0.0;
$taxaRetida = 0.0;
$emCustodia = 0.0;
$totalLiberado = 0.0;
$custodiasList = [];

foreach ($transacoes as $t) {
    if ($t['status'] !== 'estornado') {
        $volumeTotal += (float) $t['valor_total'];
        $taxaRetida += (float) $t['taxa_mecgo'];
    }
    if ($t['status'] === 'retido_custodia') {
        $emCustodia += (float) $t['valor_prestador'];
        $custodiasList[] = $t;
    } elseif ($t['status'] === 'liberado') {
        $totalLiberado += (float) $t['valor_prestador'];
    }
}

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
    <title>Financeiro | Admin | MecGo</title>
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
            <a href="admin_usuarios.php">👥 Usuários</a>
            <a href="admin_financeiro.php" class="ativo">💰 Financeiro</a>
            <a href="admin_disputas.php">⚖️ Disputas</a>
        </nav>
    </header>

    <main class="content admin-conteudo">
        <h1 class="admin-titulo">💰 Financeiro e repasses</h1>

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

        <div class="kpis">
            <div class="kpi"><span class="kpi-rotulo">Volume transacionado</span><strong>R$ <?= number_format($volumeTotal, 2, ',', '.') ?></strong></div>
            <div class="kpi"><span class="kpi-rotulo">Taxa retida (10%)</span><strong>R$ <?= number_format($taxaRetida, 2, ',', '.') ?></strong></div>
            <div class="kpi kpi-alerta"><span class="kpi-rotulo">Em custódia</span><strong>R$ <?= number_format($emCustodia, 2, ',', '.') ?></strong></div>
            <div class="kpi"><span class="kpi-rotulo">Repasses liberados</span><strong>R$ <?= number_format($totalLiberado, 2, ',', '.') ?></strong></div>
        </div>

        <div class="graficos" style="grid-template-columns:1fr">
            <section class="card">
                <h2 class="grafico-titulo">Transações recentes</h2>
                <?php if (count($transacoes) === 0): ?>
                    <p style="color:var(--muted);font-size:.9rem;">Nenhuma transação financeira registrada ainda.</p>
                <?php else: ?>
                    <div class="tabela-wrap">
                        <table class="tab">
                            <thead>
                                <tr>
                                    <th>Chamado</th>
                                    <th>Serviço</th>
                                    <th>Partes</th>
                                    <th class="num">Valor</th>
                                    <th class="num">Taxa</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transacoes as $t): ?>
                                    <?php
                                    if ($t['status'] === 'liberado') {
                                        $badgeClass = 'badge-liberado';
                                        $badgeTxt = 'Liberado';
                                    } elseif ($t['status'] === 'estornado') {
                                        $badgeClass = 'badge-estorno';
                                        $badgeTxt = 'Estornado';
                                    } else {
                                        $badgeClass = 'badge-custodia';
                                        $badgeTxt = 'Em custódia';
                                    }
                                    ?>
                                    <tr>
                                        <td>#<?= (int) $t['id_chamado'] ?></td>
                                        <td><?= htmlspecialchars($nomesAvaria[$t['avaria']] ?? ucfirst($t['avaria'])) ?></td>
                                        <td><?= htmlspecialchars($t['cliente_nome'] . ' → ' . $t['prestador_nome']) ?></td>
                                        <td class="num">R$ <?= number_format((float) $t['valor_total'], 2, ',', '.') ?></td>
                                        <td class="num">R$ <?= number_format((float) $t['taxa_mecgo'], 2, ',', '.') ?></td>
                                        <td><span class="badge <?= $badgeClass ?>"><?= $badgeTxt ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card">
                <h2 class="grafico-titulo">Valores em custódia aguardando liberação</h2>
                <?php if (count($custodiasList) === 0): ?>
                    <p style="color:var(--muted);font-size:.9rem;">Nenhum valor retido em custódia no momento.</p>
                <?php else: ?>
                    <ul class="linhas">
                        <?php foreach ($custodiasList as $c): ?>
                            <li style="align-items:center;gap:10px;">
                                <span>
                                    <?= htmlspecialchars($c['prestador_nome']) ?> (Chamado #<?= (int) $c['id_chamado'] ?>)<br>
                                    <small style="color:var(--muted)">
                                        <?= htmlspecialchars($c['metodo']) ?> · Status do chamado: <?= htmlspecialchars($c['chamado_status']) ?>
                                    </small>
                                </span>
                                <span style="display:flex;align-items:center;gap:8px;">
                                    <strong>R$ <?= number_format((float) $c['valor_prestador'], 2, ',', '.') ?></strong>
                                    <form action="" method="post" style="display:inline;">
                                        <input type="hidden" name="acao" value="liberar_um">
                                        <input type="hidden" name="id_pagamento" value="<?= (int) $c['id_pagamento'] ?>">
                                        <button type="submit" class="btn btn-pequeno">Liberar</button>
                                    </form>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <form action="" method="post" style="margin-top:1rem;">
                        <input type="hidden" name="acao" value="liberar_todos">
                        <button type="submit" class="btn">Aprovar repasses de chamados concluídos</button>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>
</body>
</html>
