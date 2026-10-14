<?php
session_start();
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header('Location: admin_login.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin_login.php');
    exit;
}

$db_host = 'localhost';
$db_name = 'mecgo_db';
$db_user = 'root';
$db_pass = '';
$db_charset = 'utf8mb4';

try {
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Estatísticas para os cards
    $total_chamados = $pdo->query("SELECT COUNT(*) FROM chamados")->fetchColumn() ?: 0;
    $chamados_ativos = $pdo->query("SELECT COUNT(*) FROM chamados WHERE status = 'em_andamento'")->fetchColumn() ?: 0;
    $prestadores_aprovados = $pdo->query("SELECT COUNT(*) FROM socorristas WHERE status_moderacao = 'aprovado'")->fetchColumn() ?: 0;
    $prestadores_pendentes = $pdo->query("SELECT COUNT(*) FROM socorristas WHERE status_moderacao = 'pendente'")->fetchColumn() ?: 0;

    // Dados para o Gráfico de Tipos de Serviço
    $stmt_servicos = $pdo->query("SELECT tipo, COUNT(*) as qtd FROM socorristas GROUP BY tipo");
    $servicos_data = $stmt_servicos->fetchAll();

    $labels_servicos = array_column($servicos_data, 'tipo');
    $valores_servicos = array_column($servicos_data, 'qtd');

    // Dados para o Gráfico de Status dos Chamados
    $stmt_status = $pdo->query("SELECT status, COUNT(*) as qtd FROM chamados GROUP BY status");
    $status_data = $stmt_status->fetchAll();

    $labels_status = array_column($status_data, 'status');
    $valores_status = array_column($status_data, 'qtd');

} catch (PDOException $e) {
    die("Erro ao conectar ao banco de dados: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MecGo - Dashboard de Métricas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <li class="nav-item"><a class="nav-link active" href="admin_dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="aprovar_prestadores.php">Aprovação & Moderação</a></li>
                </ul>
                <div class="d-flex text-white align-items-center gap-3">
                    <small><i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($_SESSION['admin_nome']) ?></small>
                    <a href="?logout=1" class="btn btn-outline-light btn-sm">Sair</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <h4 class="fw-bold mb-4 text-secondary"><i class="bi bi-speedometer2 me-2"></i>Visão Geral e Métricas</h4>

        <!-- Cards resumo -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card card-custom bg-primary text-white p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-white-50 text-uppercase fw-semibold">Chamados Ativos</small>
                            <h2 class="mb-0 fw-bold"><?= $chamados_ativos ?></h2>
                        </div>
                        <i class="bi bi-activity fs-1 text-white-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card card-custom bg-success text-white p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-white-50 text-uppercase fw-semibold">Prestadores Ativos</small>
                            <h2 class="mb-0 fw-bold"><?= $prestadores_aprovados ?></h2>
                        </div>
                        <i class="bi bi-check-circle fs-1 text-white-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card card-custom bg-warning text-dark p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-dark-50 text-uppercase fw-semibold">Pendentes Moderação</small>
                            <h2 class="mb-0 fw-bold"><?= $prestadores_pendentes ?></h2>
                        </div>
                        <i class="bi bi-hourglass-split fs-1 text-dark-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card card-custom bg-dark text-white p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-white-50 text-uppercase fw-semibold">Total Chamados</small>
                            <h2 class="mb-0 fw-bold"><?= $total_chamados ?></h2>
                        </div>
                        <i class="bi bi-card-checklist fs-1 text-white-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seção de Gráficos (Chart.js) -->
        <div class="row g-4">
            <div class="col-12 col-lg-6">
                <div class="card card-custom p-3">
                    <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-pie-chart-fill me-2"></i>Prestadores por Categoria de Serviço</h6>
                    <canvas id="chartServicos"></canvas>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card card-custom p-3">
                    <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-bar-chart-fill me-2"></i>Status dos Chamados de Socorro</h6>
                    <canvas id="chartChamados"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gráfico de Rosca - Tipos de Serviço
        const ctxServicos = document.getElementById('chartServicos').getContext('2d');
        new Chart(ctxServicos, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($labels_servicos) ?>,
                datasets: [{
                    data: <?= json_encode($valores_servicos) ?>,
                    backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6c757d']
                }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });

        // Gráfico de Barras - Status de Chamados
        const ctxChamados = document.getElementById('chartChamados').getContext('2d');
        new Chart(ctxChamados, {
            type: 'bar',
            data: {
                labels: <?= json_encode($labels_status) ?>,
                datasets: [{
                    label: 'Quantidade',
                    data: <?= json_encode($valores_status) ?>,
                    backgroundColor: '#ffc107'
                }]
            },
            options: { responsive: true, scales: { y: { beginAtZero: true } } }
        });
    </script>
</body>
</html>