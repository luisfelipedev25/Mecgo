<?php
require_once 'auth.php';
require_once 'conexao.php';

exigirLogin('PRESTADOR');

$idPrestador = (int) $_SESSION['id_usuario'];
$sucesso = isset($_GET['repasse']) && $_GET['repasse'] === 'ok'
    ? 'Solicitação de repasse enviada! O valor liberado será transferido para sua conta.'
    : '';

// Busca todos os pagamentos vinculados aos orçamentos deste prestador
$stmt = $pdo->prepare("
    SELECT p.*, c.avaria
    FROM pagamento p
    INNER JOIN orcamento o ON o.id_orcamento = p.id_orcamento
    INNER JOIN chamado c ON c.id_chamado = p.id_chamado
    WHERE o.id_prestador = ?
    ORDER BY p.id_pagamento DESC
");
$stmt->execute([$idPrestador]);
$pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$disponivel = 0.0;
$emCustodia = 0.0;
$totalTaxas = 0.0;

foreach ($pagamentos as $p) {
    if ($p['status'] === 'liberado') {
        $disponivel += (float) $p['valor_prestador'];
        $totalTaxas += (float) $p['taxa_mecgo'];
    } elseif ($p['status'] === 'retido_custodia') {
        $emCustodia += (float) $p['valor_prestador'];
        $totalTaxas += (float) $p['taxa_mecgo'];
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
    <title>Ganhos | MecGo</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="app branco">

    <header class="topo-app">
        <a href="painel_chamados.php" class="voltar">‹ Voltar</a>
        <h1>Ganhos</h1>
        <span class="direita"></span>
    </header>

    <main class="content">

        <?php if ($sucesso !== ''): ?>
            <div class="alert alert-success" role="status" style="margin-bottom:12px;">
                <?= htmlspecialchars($sucesso) ?>
            </div>
        <?php endif; ?>

        <section class="card saldo">
            <span class="rotulo-s">Disponível para repasse</span>
            <strong>R$ <?= number_format($disponivel, 2, ',', '.') ?></strong>
            <?php if ($disponivel > 0): ?>
                <a href="ganhos.php?repasse=ok" class="btn" style="margin-top:.8rem">Solicitar repasse</a>
            <?php else: ?>
                <button type="button" class="btn" style="margin-top:.8rem;opacity:.6;cursor:not-allowed;" disabled>Sem saldo liberado</button>
            <?php endif; ?>
        </section>

        <div class="mini-kpis">
            <div><strong>R$ <?= number_format($emCustodia, 2, ',', '.') ?></strong><span>Em custódia</span></div>
            <div><strong>R$ <?= number_format($totalTaxas, 2, ',', '.') ?></strong><span>Taxas MecGo</span></div>
        </div>

        <section class="card">
            <h2 class="titulo-secao" style="margin-left:0">Últimos serviços</h2>
            <?php if (count($pagamentos) === 0): ?>
                <p style="color:var(--muted);font-size:.9rem;margin:8px 0 0;">Nenhum pagamento registrado ainda.</p>
            <?php else: ?>
                <ul class="linhas">
                    <?php foreach ($pagamentos as $p): ?>
                        <?php
                        if ($p['status'] === 'liberado') {
                            $badgeClass = 'badge-liberado';
                            $badgeTxt = 'Liberado';
                        } elseif ($p['status'] === 'estornado') {
                            $badgeClass = 'badge-estorno';
                            $badgeTxt = 'Estornado';
                        } else {
                            $badgeClass = 'badge-custodia';
                            $badgeTxt = 'Em custódia';
                        }
                        ?>
                        <li>
                            <span>
                                <?= htmlspecialchars($nomesAvaria[$p['avaria']] ?? ucfirst($p['avaria'])) ?><br>
                                <small style="color:var(--muted)">
                                    <?= date('d/m/Y', strtotime($p['pago_em'])) ?> · taxa R$ <?= number_format((float) $p['taxa_mecgo'], 2, ',', '.') ?>
                                </small>
                            </span>
                            <span style="text-align:right">
                                <strong>R$ <?= number_format((float) $p['valor_prestador'], 2, ',', '.') ?></strong><br>
                                <span class="badge <?= $badgeClass ?>"><?= $badgeTxt ?></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <p class="dica">O valor fica em custódia até a confirmação do atendimento. A taxa de intermediação (10%) é descontada automaticamente.</p>

    </main>

    <nav class="nav-inferior" aria-label="Navegação principal">
        <a href="painel_chamados.php"><span class="ico">🛠️</span>Chamados</a>
        <a href="chat.php"><span class="ico">💬</span>Mensagens</a>
        <a href="ganhos.php" class="ativo"><span class="ico">💰</span>Ganhos</a>
        <a href="perfil_prestador.php"><span class="ico">👤</span>Perfil</a>
    </nav>
</div>
</body>
</html>
