<?php
/**
 * PROJETO MECGO - Plataforma de Socorro Mecânico
 * Tela do Prestador: Cadastrar e Listar Serviços / Atendimentos (Eduilton)
 * 
 * Requisitos atendidos:
 * 1. Layout mobile-first responsivo com Bootstrap 5.3 e Bootstrap Icons.
 * 2. Processamento do formulário POST com validação de campos em PHP.
 * 3. Conexão PDO com MySQL usando Prepared Statements para segurança contra SQL Injection.
 * 4. Fallback estático integrado (permite visualização estática ou execução com banco de dados).
 */

// ==========================================
// 1. CONFIGURAÇÃO E CONEXÃO PDO COM MYSQL
// ==========================================
$db_host = 'localhost';
$db_name = 'mecgo_db';
$db_user = 'root';
$db_pass = '';
$db_charset = 'utf8mb4';

$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";
$pdo_options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = null;
$conexao_ativa = false;
$msg_sucesso = '';
$msg_erro = '';

try {
    // Tenta conectar ao MySQL se disponível no servidor/XAMPP
    if (class_exists('PDO')) {
        $pdo = new PDO($dsn, $db_user, $db_pass, $pdo_options);
        $conexao_ativa = true;
    }
} catch (PDOException $e) {
    // Se não houver banco ativo (modo estático/apresentação), opera com dados simulados
    $conexao_ativa = false;
}

// Identificador do prestador Eduilton
$id_prestador = 1;
$nome_prestador = 'Eduilton Silva';

// ==========================================
// 2. PROCESSAMENTO DO FORMULÁRIO POST (PHP)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'cadastrar_servico') {
    $categoria = trim($_POST['categoria'] ?? '');
    $titulo    = trim($_POST['titulo'] ?? '');
    $preco     = filter_var($_POST['preco'] ?? 0, FILTER_VALIDATE_FLOAT);
    $raio      = filter_var($_POST['raio'] ?? 0, FILTER_VALIDATE_INT);
    $tempo     = trim($_POST['tempo_estimado'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');

    // Validação dos campos obrigatórios
    if (empty($categoria) || empty($titulo)) {
        $msg_erro = 'Por favor, preencha o tipo de socorro e o título do serviço.';
    } elseif ($preco === false || $preco <= 0) {
        $msg_erro = 'Informe um preço base válido (maior que zero).';
    } elseif ($raio === false || $raio <= 0 || $raio > 100) {
        $msg_erro = 'Informe um raio de atendimento válido (entre 1 e 100 km).';
    } else {
        // Inserção segura no banco de dados via PDO Prepared Statement
        if ($conexao_ativa && $pdo) {
            try {
                $sql = "INSERT INTO servicos_prestador 
                        (id_prestador, categoria, titulo, preco_base, raio_km, tempo_estimado, descricao, status, data_cadastro) 
                        VALUES (:id_prestador, :categoria, :titulo, :preco, :raio, :tempo, :descricao, 'ativo', NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':id_prestador' => $id_prestador,
                    ':categoria'    => $categoria,
                    ':titulo'       => $titulo,
                    ':preco'        => $preco,
                    ':raio'         => $raio,
                    ':tempo'        => $tempo,
                    ':descricao'    => $descricao,
                ]);
                $msg_sucesso = "Serviço "$titulo" cadastrado com sucesso no banco de dados!";
            } catch (PDOException $e) {
                $msg_erro = 'Erro ao salvar no banco: ' . $e->getMessage();
            }
        } else {
            // Em modo estático/sem banco conectado:
            $msg_sucesso = "Serviço "$titulo" validado com sucesso (modo de demonstração estática)!";
        }
    }
}

// ==========================================
// 3. CONSULTA DOS SERVIÇOS (PREPARED STATEMENT)
// ==========================================
$servicos_cadastrados = [];

if ($conexao_ativa && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM servicos_prestador WHERE id_prestador = :id ORDER BY id DESC");
        $stmt->execute([':id' => $id_prestador]);
        $servicos_cadastrados = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Fallback em caso de erro na consulta
        $servicos_cadastrados = [];
    }
}

// Dados padrão para visualização caso o banco esteja vazio ou em modo estático
if (empty($servicos_cadastrados)) {
    $servicos_cadastrados = [
        [
            'id' => 1,
            'categoria' => 'Borracharia',
            'titulo' => 'Troca de Pneu / Instalação de Estepe',
            'preco_base' => 90.00,
            'raio_km' => 20,
            'tempo_estimado' => '25 min',
            'descricao' => 'Troca rápida no local, aferição de pressão e aperto com torquímetro.',
            'status' => 'ativo'
        ],
        [
            'id' => 2,
            'categoria' => 'Auto Elétrica',
            'titulo' => 'Carga Rápida de Bateria (Chupeta)',
            'preco_base' => 80.00,
            'raio_km' => 25,
            'tempo_estimado' => '20 min',
            'descricao' => 'Auxílio de partida com cabos 500A profissionais e teste de alternador.',
            'status' => 'ativo'
        ],
        [
            'id' => 3,
            'categoria' => 'Reboque',
            'titulo' => 'Guincho Reboque Leve (Até 15 km)',
            'preco_base' => 180.00,
            'raio_km' => 35,
            'tempo_estimado' => '40 min',
            'descricao' => 'Transporte seguro de carros de passeio até oficina ou residência.',
            'status' => 'ativo'
        ],
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MecGo - Painel do Prestador (Eduilton)</title>
  
  <!-- Bootstrap 5.3.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- Google Fonts: Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root {
      --mec-primary: #f97316;
      --mec-primary-dark: #ea580c;
      --mec-navy: #0f172a;
      --mec-gray-bg: #f1f5f9;
      --mec-text-dark: #0f172a;
      --mec-text-muted: #64748b;
      --mec-radius: 14px;
    }
    * { box-sizing: border-box; font-family: 'Inter', -apple-system, sans-serif; }
    body { background-color: #0b1120; margin: 0; min-height: 100vh; display: flex; justify-content: center; }
    .app-screen { width: 100%; max-width: 460px; min-height: 100vh; background-color: var(--mec-gray-bg); display: flex; flex-direction: column; position: relative; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.4); padding-bottom: 75px; }
    .app-header { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; padding: 16px 20px 20px 20px; border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; }
    .brand-logo { font-size: 1.35rem; font-weight: 800; color: #fff; text-decoration: none; }
    .brand-logo span { color: var(--mec-primary); }
    .badge-prestador { background: rgba(249,115,22,0.2); color: #ffedd5; border: 1px solid rgba(249,115,22,0.4); font-size: 0.72rem; font-weight: 600; padding: 3px 8px; border-radius: 20px; }
    .prestador-card { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.12); border-radius: var(--mec-radius); padding: 14px; margin-top: 14px; }
    .avatar-icon { width: 46px; height: 46px; border-radius: 50%; background: linear-gradient(135deg, var(--mec-primary) 0%, #d97706 100%); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 700; border: 2px solid #fff; }
    .status-badge { font-size: 0.75rem; padding: 4px 10px; border-radius: 30px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
    .status-badge.online { background: #064e3b; color: #6ee7b7; border: 1px solid #059669; }
    .status-dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; box-shadow: 0 0 8px currentColor; }
    .metric-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; text-align: center; }
    .metric-val { font-size: 1.25rem; font-weight: 700; color: var(--mec-navy); margin: 0; }
    .metric-lbl { font-size: 0.72rem; color: var(--mec-text-muted); margin: 0; }
    .nav-tabs-mec { display: flex; background: #e2e8f0; padding: 4px; border-radius: 12px; margin: 16px 16px 12px 16px; gap: 4px; }
    .nav-tabs-mec .btn-tab { flex: 1; border: none; background: transparent; padding: 8px 6px; border-radius: 8px; font-size: 0.8rem; font-weight: 600; color: var(--mec-text-muted); }
    .nav-tabs-mec .btn-tab.active { background: #fff; color: var(--mec-navy); box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
    .card-mec { background: #fff; border-radius: var(--mec-radius); border: 1px solid #e2e8f0; margin-bottom: 12px; }
    .btn-mec-primary { background: var(--mec-primary); border-color: var(--mec-primary); color: #fff; font-weight: 600; border-radius: 10px; padding: 10px 16px; }
    .btn-mec-primary:hover { background: var(--mec-primary-dark); color: #fff; }
    .btn-mec-outline { border: 1.5px solid #cbd5e1; background: #fff; color: var(--mec-navy); font-weight: 600; border-radius: 10px; padding: 9px 14px; }
    .bottom-nav { position: absolute; bottom: 0; left: 0; right: 0; height: 68px; background: #fff; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-around; align-items: center; z-index: 100; }
    .nav-item-btn { background: none; border: none; color: var(--mec-text-muted); display: flex; flex-direction: column; align-items: center; gap: 3px; font-size: 0.72rem; font-weight: 600; }
    .nav-item-btn.active { color: var(--mec-primary); }
    .badge-counter { position: absolute; top: 2px; right: 14px; background: #ef4444; color: #fff; font-size: 0.65rem; font-weight: 700; width: 17px; height: 17px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
  </style>
</head>
<body>

  <div class="app-screen">

    <!-- Mensagens de Alerta (Feedback PHP) -->
    <?php if (!empty($msg_sucesso)): ?>
      <div class="alert alert-success alert-dismissible fade show m-3 mb-0" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg_sucesso) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <?php if (!empty($msg_erro)): ?>
      <div class="alert alert-danger alert-dismissible fade show m-3 mb-0" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($msg_erro) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <!-- Cabeçalho do App -->
    <header class="app-header">
      <div class="d-flex justify-content-between align-items-center">
        <a href="#" class="brand-logo"><i class="bi bi-tools text-warning"></i> Mec<span>Go</span></a>
        <span class="badge-prestador"><i class="bi bi-shield-check me-1"></i>PRESTADOR</span>
      </div>

      <div class="prestador-card d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
          <div class="avatar-icon">E</div>
          <div>
            <h6 class="mb-0 fw-bold text-white"><?= htmlspecialchars($nome_prestador) ?></h6>
            <small class="text-light opacity-75">Socorro Mecânico & Guincho</small>
            <div class="mt-1 text-warning small"><i class="bi bi-star-fill"></i> <strong class="text-white">4.9</strong> <span class="text-light opacity-50">(142 socorros)</span></div>
          </div>
        </div>
        <div class="status-badge online"><span class="status-dot"></span><span>Disponível</span></div>
      </div>
    </header>

    <!-- Resumo de Métricas -->
    <section class="px-3 pt-3">
      <div class="row g-2">
        <div class="col-4"><div class="metric-card"><p class="metric-val text-primary">3</p><p class="metric-lbl">Aguardando</p></div></div>
        <div class="col-4"><div class="metric-card"><p class="metric-val text-success">R$ 470</p><p class="metric-lbl">Ganhos Hoje</p></div></div>
        <div class="col-4"><div class="metric-card"><p class="metric-val text-warning">25 km</p><p class="metric-lbl">Raio Atuação</p></div></div>
      </div>
    </section>

    <!-- Navegação de Abas -->
    <nav class="nav-tabs-mec">
      <button class="btn-tab active" id="tabBtnServicos" onclick="trocarAba('servicos')"><i class="bi bi-card-checklist"></i> Serviços</button>
      <button class="btn-tab" id="tabBtnNovo" onclick="trocarAba('novo')"><i class="bi bi-plus-circle-fill text-warning"></i> + Novo</button>
      <button class="btn-tab" id="tabBtnChamados" onclick="trocarAba('chamados')"><i class="bi bi-broadcast"></i> Chamados (3)</button>
    </nav>

    <!-- Conteúdo Principal -->
    <main class="flex-grow-1 px-3">

      <!-- ABA: LISTAGEM DE SERVIÇOS DO PRESTADOR -->
      <div id="viewServicos" class="tab-view">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="fw-bold mb-0 text-dark">Serviços Cadastrados</h6>
          <span class="badge bg-secondary-subtle text-dark"><?= count($servicos_cadastrados) ?> ativos</span>
        </div>

        <?php foreach ($servicos_cadastrados as $servico): ?>
          <div class="card card-mec p-3">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <span class="badge bg-secondary-subtle text-dark small mb-1"><i class="bi bi-wrench me-1"></i><?= htmlspecialchars($servico['categoria']) ?></span>
                <h6 class="fw-bold mb-1"><?= htmlspecialchars($servico['titulo']) ?></h6>
                <p class="text-muted small mb-2"><?= htmlspecialchars($servico['descricao']) ?></p>
                <div class="small text-secondary"><i class="bi bi-clock me-1"></i>~<?= htmlspecialchars($servico['tempo_estimado']) ?> | <i class="bi bi-compass me-1"></i>Raio <?= htmlspecialchars($servico['raio_km']) ?> km</div>
              </div>
              <div class="text-end">
                <span class="fw-bold fs-6 text-success d-block">R$ <?= number_format($servico['preco_base'], 2, ',', '.') ?></span>
                <span class="badge bg-success-subtle text-success border border-success-subtle mt-1"><?= ucfirst($servico['status']) ?></span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- ABA: FORMULÁRIO DE CADASTRO -->
      <div id="viewNovo" class="tab-view d-none">
        <div class="card card-mec p-3">
          <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-plus-square-fill text-warning me-2"></i>Cadastrar Novo Serviço</h6>
          <form method="POST" action="">
            <input type="hidden" name="acao" value="cadastrar_servico">
            
            <div class="mb-3">
              <label class="form-label small fw-bold">Tipo de Socorro *</label>
              <select class="form-select form-select-sm" name="categoria" required>
                <option value="Troca de Pneu / Borracharia">Troca de Pneu / Borracharia</option>
                <option value="Carga / Bateria">Carga / Troca de Bateria</option>
                <option value="Guincho / Reboque">Guincho / Reboque</option>
                <option value="Mecânica Rápida">Mecânica Rápida / Diagnóstico</option>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold">Título do Serviço *</label>
              <input type="text" class="form-control form-control-sm" name="titulo" placeholder="Ex: Chupeta com Cabo Reforçado" required>
            </div>

            <div class="row g-2 mb-3">
              <div class="col-6">
                <label class="form-label small fw-bold">Preço Base (R$) *</label>
                <input type="number" step="0.01" class="form-control form-control-sm" name="preco" placeholder="80,00" required>
              </div>
              <div class="col-6">
                <label class="form-label small fw-bold">Raio Máx. (km) *</label>
                <input type="number" class="form-control form-control-sm" name="raio" placeholder="25" required>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold">Tempo Estimado *</label>
              <input type="text" class="form-control form-control-sm" name="tempo_estimado" value="25 a 35 min" required>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold">Descrição</label>
              <textarea class="form-control form-control-sm" name="descricao" rows="2" placeholder="Equipamentos e procedimentos inclusos..."></textarea>
            </div>

            <div class="d-grid gap-2">
              <button type="submit" class="btn btn-sm btn-mec-primary"><i class="bi bi-save me-1"></i>Salvar Serviço</button>
            </div>
          </form>
        </div>
      </div>

      <!-- ABA: CHAMADOS PRÓXIMOS -->
      <div id="viewChamados" class="tab-view d-none">
        <h6 class="fw-bold mb-2 text-dark"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Chamados Próximos</h6>
        
        <div class="card card-mec p-3 border-start border-warning border-4">
          <div class="d-flex justify-content-between">
            <h6 class="fw-bold mb-1">Honda Civic 2021</h6>
            <span class="fw-bold text-success">R$ 90,00</span>
          </div>
          <small class="text-danger fw-semibold"><i class="bi bi-exclamation-circle me-1"></i>Pneu Furado</small>
          <div class="small text-muted mb-2">Av. Frei Serafim - 2.4 km de distância</div>
          <button class="btn btn-sm btn-mec-primary w-100" onclick="alert('Chamado aceito pelo prestador Eduilton!')">Aceitar Atendimento</button>
        </div>
      </div>

    </main>

    <!-- Barra Inferior -->
    <nav class="bottom-nav">
      <button class="nav-item-btn active" onclick="trocarAba('servicos')"><i class="bi bi-wrench"></i><span>Serviços</span></button>
      <button class="nav-item-btn" onclick="trocarAba('novo')"><i class="bi bi-plus-circle"></i><span>Cadastrar</span></button>
      <button class="nav-item-btn" onclick="trocarAba('chamados')"><i class="bi bi-broadcast"></i><span>Chamados</span><span class="badge-counter">3</span></button>
    </nav>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function trocarAba(aba) {
      document.querySelectorAll('.tab-view').forEach(v => v.classList.add('d-none'));
      document.querySelectorAll('.btn-tab').forEach(b => b.classList.remove('active'));
      if (aba === 'servicos') {
        document.getElementById('viewServicos').classList.remove('d-none');
        document.getElementById('tabBtnServicos').classList.add('active');
      } else if (aba === 'novo') {
        document.getElementById('viewNovo').classList.remove('d-none');
        document.getElementById('tabBtnNovo').classList.add('active');
      } else if (aba === 'chamados') {
        document.getElementById('viewChamados').classList.remove('d-none');
        document.getElementById('tabBtnChamados').classList.add('active');
      }
    }
  </script>
</body>
</html>