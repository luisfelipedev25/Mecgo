<?php
require_once 'auth.php';
require_once 'conexao.php';

exigirLogin('ADMIN');

// 1. KPIs principais
$chamadosAtivos = (int) $pdo->query("
    SELECT COUNT(*) FROM chamado 
    WHERE status IN ('aguardando', 'orcamento_enviado', 'deslocamento', 'em_atendimento')
")->fetchColumn();

$prestadoresAtivos = (int) $pdo->query("
    SELECT COUNT(*) FROM usuario 
    WHERE tipo = 'PRESTADOR' AND status = 'ativo'
")->fetchColumn();

$pendentesModeracao = (int) $pdo->query("
    SELECT COUNT(*) FROM usuario 
    WHERE tipo = 'PRESTADOR' AND status = 'pendente'
")->fetchColumn();

$totalChamados = (int) $pdo->query("SELECT COUNT(*) FROM chamado")->fetchColumn();

// 2. Contagem de prestadores ativos por categoria
$stmtCats = $pdo->query("
    SELECT p.categorias
    FROM prestador p
    INNER JOIN usuario u ON u.id_usuario = p.id_prestador
    WHERE u.status = 'ativo'
");
$listaCats = $stmtCats->fetchAll(PDO::FETCH_COLUMN);

$contCats = [
    'guincho'  => 0,
    'mecanica' => 0,
    'bateria'  => 0,
    'pneu'     => 0,
    'chaveiro' => 0,
];

foreach ($listaCats as $catStr) {
    $partes = array_map('trim', explode(',', (string) $catStr));
    foreach ($partes as $c) {
        if (isset($contCats[$c])) {
            $contCats[$c]++;
        }
    }
}

$somaCats = array_sum($contCats);
$basePct = $somaCats > 0 ? $somaCats : 1;

$pctGuincho  = round(($contCats['guincho'] / $basePct) * 100, 1);
$pctMecanica = round(($contCats['mecanica'] / $basePct) * 100, 1);
$pctBateria  = round(($contCats['bateria'] / $basePct) * 100, 1);
$pctPneu     = round(($contCats['pneu'] / $basePct) * 100, 1);
$pctChaveiro = round(($contCats['chaveiro'] / $basePct) * 100, 1);

// Ângulos acumulados para o gráfico de rosca
$p1 = $pctGuincho;
$p2 = $p1 + $pctMecanica;
$p3 = $p2 + $pctBateria;
$p4 = $p3 + $pctPneu;

$gradienteRosca = $somaCats > 0
    ? "conic-gradient(#e66927 0% {$p1}%, #2b3440 {$p1}% {$p2}%, #2e7d32 {$p2}% {$p3}%, #f59e0b {$p3}% {$p4}%, #64748b {$p4}% 100%)"
    : "conic-gradient(#e5e7eb 0% 100%)";

// 3. Contagem de chamados por status
$qtdAguardando = (int) $pdo->query("SELECT COUNT(*) FROM chamado WHERE status IN ('aguardando', 'orcamento_enviado')")->fetchColumn();
$qtdAndamento  = (int) $pdo->query("SELECT COUNT(*) FROM chamado WHERE status IN ('deslocamento', 'em_atendimento')")->fetchColumn();
$qtdConcluido  = (int) $pdo->query("SELECT COUNT(*) FROM chamado WHERE status = 'concluido'")->fetchColumn();
$qtdCancelado  = (int) $pdo->query("SELECT COUNT(*) FROM chamado WHERE status = 'cancelado'")->fetchColumn();

$baseChamados = $totalChamados > 0 ? $totalChamados : 1;
$wAguardando = round(($qtdAguardando / $baseChamados) * 100, 1);
$wAndamento  = round(($qtdAndamento / $baseChamados) * 100, 1);
$wConcluido  = round(($qtdConcluido / $baseChamados) * 100, 1);
$wCancelado  = round(($qtdCancelado / $baseChamados) * 100, 1);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e66927">
    <title>Dashboard | MecGo Admin</title>
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
            <a href="admin_dashboard.php" class="ativo">📊 Dashboard</a>
            <a href="aprovar_prestadores.php">✅ Aprovações</a>
            <a href="admin_usuarios.php">👥 Usuários</a>
            <a href="admin_financeiro.php">💰 Financeiro</a>
            <a href="admin_disputas.php">⚖️ Disputas</a>
        </nav>
    </header>

    <main class="content admin-conteudo">

        <h1 class="admin-titulo">📊 Visão geral e métricas</h1>

        <!-- Indicadores -->
        <div class="kpis">
            <div class="kpi">
                <span class="kpi-rotulo">Chamados ativos</span>
                <strong><?= $chamadosAtivos ?></strong>
            </div>
            <div class="kpi">
                <span class="kpi-rotulo">Prestadores ativos</span>
                <strong><?= $prestadoresAtivos ?></strong>
            </div>
            <a href="aprovar_prestadores.php" class="kpi kpi-alerta">
                <span class="kpi-rotulo">Pendentes de moderação</span>
                <strong><?= $pendentesModeracao ?></strong>
                <small>Revisar agora ›</small>
            </a>
            <div class="kpi">
                <span class="kpi-rotulo">Total de chamados</span>
                <strong><?= $totalChamados ?></strong>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="graficos">

            <section class="card">
                <h2 class="grafico-titulo">Prestadores ativos por categoria</h2>

                <div class="rosca-wrap">
                    <div class="rosca" style="background: <?= $gradienteRosca ?>;" role="img"
                         aria-label="Distribuição de prestadores por categoria">
                        <div class="rosca-centro">
                            <strong><?= $prestadoresAtivos ?></strong>
                            <span>prestadores</span>
                        </div>
                    </div>

                    <ul class="legenda-viz">
                        <li><span class="ponto c1"></span><span>Guincho / Reboque</span><span class="leg-valor"><?= $contCats['guincho'] ?></span><span class="leg-pct"><?= number_format($pctGuincho, 1, ',', '.') ?>%</span></li>
                        <li><span class="ponto c2"></span><span>Mecânica geral</span><span class="leg-valor"><?= $contCats['mecanica'] ?></span><span class="leg-pct"><?= number_format($pctMecanica, 1, ',', '.') ?>%</span></li>
                        <li><span class="ponto c3"></span><span>Bateria / Elétrica</span><span class="leg-valor"><?= $contCats['bateria'] ?></span><span class="leg-pct"><?= number_format($pctBateria, 1, ',', '.') ?>%</span></li>
                        <li><span class="ponto c4"></span><span>Borracharia (Pneus)</span><span class="leg-valor"><?= $contCats['pneu'] ?></span><span class="leg-pct"><?= number_format($pctPneu, 1, ',', '.') ?>%</span></li>
                        <li><span class="ponto c5"></span><span>Chaveiro</span><span class="leg-valor"><?= $contCats['chaveiro'] ?></span><span class="leg-pct"><?= number_format($pctChaveiro, 1, ',', '.') ?>%</span></li>
                    </ul>
                </div>
            </section>

            <section class="card">
                <h2 class="grafico-titulo">Status dos chamados de socorro</h2>

                <div class="barras" role="img" aria-label="Chamados por status">
                    <div class="barra-linha">
                        <span class="barra-rotulo">Aguardando</span>
                        <div class="barra-area"><div class="barra" style="width:<?= $wAguardando ?>%"></div><span class="barra-valor"><?= $qtdAguardando ?></span></div>
                    </div>
                    <div class="barra-linha">
                        <span class="barra-rotulo">Em andamento</span>
                        <div class="barra-area"><div class="barra" style="width:<?= $wAndamento ?>%"></div><span class="barra-valor"><?= $qtdAndamento ?></span></div>
                    </div>
                    <div class="barra-linha">
                        <span class="barra-rotulo">Concluído</span>
                        <div class="barra-area"><div class="barra" style="width:<?= $wConcluido ?>%"></div><span class="barra-valor"><?= $qtdConcluido ?></span></div>
                    </div>
                    <div class="barra-linha">
                        <span class="barra-rotulo">Cancelado</span>
                        <div class="barra-area"><div class="barra" style="width:<?= $wCancelado ?>%"></div><span class="barra-valor"><?= $qtdCancelado ?></span></div>
                    </div>
                </div>

                <p class="dica" style="margin-top:1rem;">Total: <?= $totalChamados ?> chamados registrados.</p>
            </section>
        </div>
    </main>
</div>
</body>
</html>
