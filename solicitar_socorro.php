<?php
/**
 * MecGo - Solicitar Socorro (Cliente/Motorista)
 * Local: htdocs/mecgo/solicitar_socorro.php
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/conexao.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Falha na conexão com o banco de dados.');
}

// ---------------------------------------------------------------
// Autenticação
// ---------------------------------------------------------------
$usuarioId = (int) ($_SESSION['usuario_id'] ?? $_SESSION['cliente_id'] ?? 0);
if ($usuarioId <= 0) {
    header('Location: login_cliente.php');
    exit;
}

// ---------------------------------------------------------------
// Constantes e helpers
// ---------------------------------------------------------------
const AVARIAS = [
    'pneu'     => ['label' => 'Pneu furado',   'icone' => 'bi-record-circle',        'dica' => 'Troca ou reparo'],
    'bateria'  => ['label' => 'Bateria',       'icone' => 'bi-battery-charging',     'dica' => 'Carga ou troca'],
    'guincho'  => ['label' => 'Guincho',       'icone' => 'bi-truck',                'dica' => 'Reboque do veículo'],
    'pane'     => ['label' => 'Pane mecânica', 'icone' => 'bi-gear-wide-connected',  'dica' => 'Motor, elétrica etc.'],
];

// Status ativos mapeados exatamente como estão no ENUM da tabela 'atendimento'
const STATUS_ABERTOS = ['SOLICITADO', 'EM_DESLOCAMENTO', 'NO_LOCAL', 'EM_ATENDIMENTO'];

function e(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatarPlaca(string $placa): string
{
    return preg_match('/^[A-Z]{3}[0-9]{4}$/', $placa)
        ? substr($placa, 0, 3) . '-' . substr($placa, 3)
        : $placa;
}

function classeStatus(string $status): string
{
    return match ($status) {
        'SOLICITADO'      => 'text-bg-warning',
        'EM_DESLOCAMENTO', 'NO_LOCAL' => 'text-bg-primary',
        'EM_ATENDIMENTO'  => 'text-bg-info',
        'CONCLUIDO'       => 'text-bg-success',
        default           => 'text-bg-secondary',
    };
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// ---------------------------------------------------------------
// Veículos do cliente (Tabela 'veiculo' e coluna 'id_cliente')
// ---------------------------------------------------------------
$stmt = $pdo->prepare(
    'SELECT id_veiculo, placa, marca, modelo, ano
       FROM veiculo
      WHERE id_cliente = :uid
   ORDER BY marca, modelo'
);
$stmt->execute([':uid' => $usuarioId]);
$veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$idsVeiculos = array_map('intval', array_column($veiculos, 'id_veiculo'));

// Veículo pré-selecionado (vindo de garagem.php?veiculo=ID)
$veiculoPre = filter_input(INPUT_GET, 'veiculo', FILTER_VALIDATE_INT) ?: 0;
if (!in_array($veiculoPre, $idsVeiculos, true)) {
    $veiculoPre = count($veiculos) === 1 ? (int) $veiculos[0]['id_veiculo'] : 0;
}

$erros = [];
$dados = ['veiculo' => $veiculoPre, 'avaria' => '', 'descricao' => '', 'lat' => '', 'lng' => ''];

// ---------------------------------------------------------------
// Processamento POST
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Requisição inválida (CSRF).');
    }

    $acao = $_POST['acao'] ?? '';

    // ---------- Solicitar ----------
    if ($acao === 'solicitar') {
        $veiculoId = filter_var($_POST['veiculo'] ?? null, FILTER_VALIDATE_INT);
        $avariaKey = trim((string) ($_POST['avaria'] ?? ''));
        $descricao = trim(strip_tags((string) ($_POST['descricao'] ?? '')));
        $latRaw    = trim((string) ($_POST['latitude'] ?? ''));
        $lngRaw    = trim((string) ($_POST['longitude'] ?? ''));

        $dados = [
            'veiculo' => (int) $veiculoId, 'avaria' => $avariaKey,
            'descricao' => $descricao, 'lat' => $latRaw, 'lng' => $lngRaw,
        ];

        // Validação
        if (!$veiculoId || !in_array($veiculoId, $idsVeiculos, true)) {
            $erros['veiculo'] = 'Selecione um dos seus veículos.';
        }
        if (!array_key_exists($avariaKey, AVARIAS)) {
            $erros['avaria'] = 'Selecione o tipo de problema.';
        }
        if (mb_strlen($descricao) > 500) {
            $erros['descricao'] = 'A descrição pode ter no máximo 500 caracteres.';
        }

        $lat = filter_var($latRaw, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($lngRaw, FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lng === false
            || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            $erros['local'] = 'Não conseguimos sua localização. Toque em "Usar minha localização".';
        }

        // Já existe chamado aberto para este veículo?
        if (!$erros) {
            $marcas = implode(',', array_fill(0, count(STATUS_ABERTOS), '?'));
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM atendimento
                  WHERE id_cliente = ? AND id_veiculo = ? AND status IN ($marcas)"
            );
            $stmt->execute(array_merge([$usuarioId, $veiculoId], STATUS_ABERTOS));
            if ((int) $stmt->fetchColumn() > 0) {
                $erros['geral'] = 'Já existe um chamado em andamento para este veículo.';
            }
        }

        // Persistência na tabela 'atendimento'
        if (!$erros) {
            try {
                $gps = round($lat, 7) . ',' . round($lng, 7);
                $categoriaAvaria = AVARIAS[$avariaKey]['label'];
                if ($descricao !== '') {
                    $categoriaAvaria .= ' - ' . $descricao;
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO atendimento
                        (id_cliente, id_veiculo, categoria_avaria, localizacao_gps, status)
                     VALUES
                        (:uid, :vid, :cat, :gps, 'SOLICITADO')"
                );
                $stmt->execute([
                    ':uid' => $usuarioId,
                    ':vid' => $veiculoId,
                    ':cat' => $categoriaAvaria,
                    ':gps' => $gps
                ]);

                $_SESSION['flash'] = [
                    'tipo' => 'success',
                    'msg'  => 'Socorro solicitado! Estamos procurando um prestador próximo.',
                ];
                header('Location: solicitar_socorro.php');
                exit;
            } catch (PDOException $ex) {
                error_log('[MecGo][socorro] ' . $ex->getMessage());
                $erros['geral'] = 'Não foi possível registrar o chamado. Tente novamente.';
            }
        }
    }

    // ---------- Cancelar (Apenas se o status for SOLICITADO) ----------
    if ($acao === 'cancelar') {
        $atendId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($atendId) {
            try {
                $stmt = $pdo->prepare(
                    "UPDATE atendimento SET status = 'CANCELADO'
                      WHERE id_atendimento = :id AND id_cliente = :uid AND status = 'SOLICITADO'"
                );
                $stmt->execute([':id' => $atendId, ':uid' => $usuarioId]);
                $_SESSION['flash'] = $stmt->rowCount()
                    ? ['tipo' => 'success', 'msg' => 'Chamado cancelado.']
                    : ['tipo' => 'warning', 'msg' => 'Este chamado não pode mais ser cancelado por aqui.'];
            } catch (PDOException $ex) {
                error_log('[MecGo][socorro] ' . $ex->getMessage());
                $_SESSION['flash'] = ['tipo' => 'danger', 'msg' => 'Erro ao cancelar o chamado.'];
            }
        }
        header('Location: solicitar_socorro.php');
        exit;
    }
}

// ---------------------------------------------------------------
// Chamados em andamento do cliente
// ---------------------------------------------------------------
$marcas = implode(',', array_fill(0, count(STATUS_ABERTOS), '?'));
$stmt = $pdo->prepare(
    "SELECT a.id_atendimento, a.categoria_avaria, a.status, a.data_hora,
            v.marca, v.modelo, v.placa
       FROM atendimento a
       JOIN veiculo v ON v.id_veiculo = a.id_veiculo
      WHERE a.id_cliente = ? AND a.status IN ($marcas)
   ORDER BY a.data_hora DESC"
);
$stmt->execute(array_merge([$usuarioId], STATUS_ABERTOS));
$chamados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0d1b2a">
    <title>Pedir Socorro | MecGo</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --mg-dark: #0d1b2a;
            --mg-dark-2: #1b263b;
            --mg-accent: #ff6b00;
            --mg-accent-dark: #e05e00;
            --mg-bg: #f3f5f8;
        }
        body {
            background: var(--mg-bg);
            padding-bottom: 84px;
            -webkit-tap-highlight-color: transparent;
        }
        .mg-topbar {
            background: linear-gradient(135deg, var(--mg-dark), var(--mg-dark-2));
            color: #fff;
            padding: 1.25rem 1rem 3.2rem;
            border-radius: 0 0 28px 28px;
        }
        .mg-wrap { max-width: 560px; margin-left: auto; margin-right: auto; }
        .mg-brand { font-weight: 800; letter-spacing: .5px; }
        .mg-brand span { color: var(--mg-accent); }
        .mg-main { margin-top: -2.2rem; }
        .mg-card {
            border: 0;
            border-radius: 18px;
            box-shadow: 0 6px 20px rgba(13, 27, 42, .08);
        }
        .btn-mg {
            background: var(--mg-accent);
            border-color: var(--mg-accent);
            color: #fff;
            font-weight: 700;
            border-radius: 12px;
            padding: .85rem 1rem;
        }
        .btn-mg:hover, .btn-mg:focus, .btn-mg:active {
            background: var(--mg-accent-dark) !important;
            border-color: var(--mg-accent-dark) !important;
            color: #fff;
        }
        .btn-mg:disabled { background: #c9ced6; border-color: #c9ced6; color: #fff; }
        .form-control, .form-select { min-height: 48px; border-radius: 12px; }
        .form-control:focus, .form-select:focus {
            border-color: var(--mg-accent);
            box-shadow: 0 0 0 .2rem rgba(255, 107, 0, .18);
        }

        .passo-titulo {
            display: flex; align-items: center; gap: .6rem;
            font-weight: 700; margin-bottom: .75rem;
        }
        .passo-num {
            width: 28px; height: 28px; border-radius: 50%;
            background: var(--mg-dark); color: #fff;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: .85rem; flex: 0 0 28px;
        }

        .avaria-opcao { position: absolute; opacity: 0; pointer-events: none; }
        .avaria-label {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            text-align: center; gap: .1rem; height: 100%;
            padding: 1rem .5rem;
            border: 2px solid #dde2e9; border-radius: 16px;
            background: #fff; color: #44505f; cursor: pointer;
            transition: all .15s ease;
        }
        .avaria-label i { font-size: 2rem; color: #6b7685; }
        .avaria-label strong { font-size: .95rem; }
        .avaria-label small { color: #8a94a2; font-size: .72rem; }
        .avaria-opcao:checked + .avaria-label {
            border-color: var(--mg-accent);
            background: rgba(255, 107, 0, .08);
        }
        .avaria-opcao:checked + .avaria-label i { color: var(--mg-accent); }
        .avaria-opcao:focus-visible + .avaria-label { box-shadow: 0 0 0 .2rem rgba(255, 107, 0, .25); }
        .avaria-invalida .avaria-label { border-color: #dc3545; }

        .local-box {
            border: 2px dashed #cfd5de; border-radius: 16px;
            padding: 1rem; text-align: center; background: #fafbfc;
        }
        .local-box.ok { border: 2px solid #198754; background: rgba(25, 135, 84, .06); }
        .local-box.erro { border-color: #dc3545; background: rgba(220, 53, 69, .05); }
        .local-icone { font-size: 2rem; color: #6b7685; }
        .local-box.ok .local-icone { color: #198754; }
        .local-box.erro .local-icone { color: #dc3545; }

        .placa-badge {
            display: inline-block; background: #fff; border: 2px solid var(--mg-dark);
            border-top: 7px solid #1f4fbf; border-radius: 5px;
            padding: 0 .5rem; font-weight: 800; letter-spacing: 2px;
            color: var(--mg-dark); font-size: .8rem;
        }
        .chamado-item { border-left: 5px solid var(--mg-accent); }

        .mg-bottom-nav {
            position: fixed; left: 0; right: 0; bottom: 0; background: #fff;
            box-shadow: 0 -4px 16px rgba(13, 27, 42, .1);
            padding-bottom: env(safe-area-inset-bottom); z-index: 1030;
        }
        .mg-bottom-nav a {
            flex: 1; text-align: center; padding: .6rem 0 .5rem; color: #6b7685;
            text-decoration: none; font-size: .72rem; font-weight: 600;
        }
        .mg-bottom-nav a i { display: block; font-size: 1.35rem; }
        .mg-bottom-nav a.ativo { color: var(--mg-accent); }
    </style>
</head>
<body>

<header class="mg-topbar">
    <div class="mg-wrap">
        <div class="d-flex align-items-center justify-content-between">
            <div class="mg-brand fs-4">Mec<span>Go</span></div>
            <a href="logout.php" class="text-white-50 small text-decoration-none">
                <i class="bi bi-box-arrow-right"></i> Sair
            </a>
        </div>
        <h1 class="h4 mt-3 mb-0 fw-bold"><i class="bi bi-life-preserver me-2"></i>Pedir socorro</h1>
        <p class="mb-0 text-white-50 small">Conte o que aconteceu e envie sua localização.</p>
    </div>
</header>

<main class="mg-wrap mg-main px-3 pb-4">

    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['tipo']) ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= e($flash['msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($erros['geral'])): ?>
        <div class="alert alert-danger shadow-sm"><?= e($erros['geral']) ?></div>
    <?php endif; ?>

    <!-- Chamados em andamento -->
    <?php if ($chamados): ?>
        <section class="mb-4">
            <h2 class="h6 fw-bold text-uppercase text-secondary px-1 mb-2">Chamados em andamento</h2>
            <?php foreach ($chamados as $c): ?>
                <article class="card mg-card chamado-item mb-2">
                    <div class="card-body p-3 d-flex align-items-center gap-3">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="fw-bold"><?= e($c['categoria_avaria']) ?></span>
                                <span class="badge <?= classeStatus($c['status']) ?>"><?= e($c['status']) ?></span>
                            </div>
                            <div class="small text-secondary mb-1">
                                <?= e($c['marca']) ?> <?= e($c['modelo']) ?>
                                <span class="placa-badge ms-1"><?= e(formatarPlaca($c['placa'])) ?></span>
                            </div>
                            <div class="small text-secondary">
                                <i class="bi bi-clock"></i>
                                <?= e(date('d/m/Y H:i', strtotime($c['data_hora']))) ?>
                            </div>
                        </div>
                        <?php if ($c['status'] === 'SOLICITADO'): ?>
                            <form method="post" action="solicitar_socorro.php"
                                  onsubmit="return confirm('Cancelar este chamado?');">
                                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                                <input type="hidden" name="acao" value="cancelar">
                                <input type="hidden" name="id" value="<?= (int) $c['id_atendimento'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius:12px">
                                    Cancelar
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <!-- Formulário de solicitação -->
    <?php if (!$veiculos): ?>
        <section class="card mg-card">
            <div class="card-body text-center py-5">
                <i class="bi bi-car-front text-secondary" style="font-size:3rem"></i>
                <p class="mt-2 mb-3 text-secondary">Para pedir socorro, cadastre um veículo na sua garagem primeiro.</p>
                <a href="garagem.php" class="btn btn-mg">
                    <i class="bi bi-plus-circle me-1"></i> Ir para a garagem
                </a>
            </div>
        </section>
    <?php else: ?>
        <form method="post" action="solicitar_socorro.php" id="formSocorro" novalidate>
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <input type="hidden" name="acao" value="solicitar">
            <input type="hidden" name="latitude" id="latitude" value="<?= e($dados['lat']) ?>">
            <input type="hidden" name="longitude" id="longitude" value="<?= e($dados['lng']) ?>">

            <!-- Passo 1: veículo -->
            <section class="card mg-card mb-3">
                <div class="card-body p-3">
                    <div class="passo-titulo"><span class="passo-num">1</span> Qual veículo?</div>
                    <select name="veiculo" id="veiculo"
                            class="form-select <?= isset($erros['veiculo']) ? 'is-invalid' : '' ?>" required>
                        <option value="" disabled <?= $dados['veiculo'] ? '' : 'selected' ?>>Selecione...</option>
                        <?php foreach ($veiculos as $v): ?>
                            <option value="<?= (int) $v['id_veiculo'] ?>"
                                <?= (int) $dados['veiculo'] === (int) $v['id_veiculo'] ? 'selected' : '' ?>>
                                <?= e($v['marca'] . ' ' . $v['modelo']) ?> · <?= e(formatarPlaca($v['placa'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?= e($erros['veiculo'] ?? '') ?></div>
                </div>
            </section>

            <!-- Passo 2: avaria -->
            <section class="card mg-card mb-3">
                <div class="card-body p-3">
                    <div class="passo-titulo"><span class="passo-num">2</span> Qual o problema?</div>
                    <div class="row g-2 <?= isset($erros['avaria']) ? 'avaria-invalida' : '' ?>">
                        <?php foreach (AVARIAS as $key => $a): ?>
                            <div class="col-6 position-relative">
                                <input type="radio" class="avaria-opcao" name="avaria"
                                       id="avaria_<?= e($key) ?>" value="<?= e($key) ?>"
                                       <?= $dados['avaria'] === $key ? 'checked' : '' ?>>
                                <label for="avaria_<?= e($key) ?>" class="avaria-label">
                                    <i class="bi <?= e($a['icone']) ?>"></i>
                                    <strong><?= e($a['label']) ?></strong>
                                    <small><?= e($a['dica']) ?></small>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (isset($erros['avaria'])): ?>
                        <div class="text-danger small mt-2"><?= e($erros['avaria']) ?></div>
                    <?php endif; ?>

                    <div class="mt-3">
                        <label for="descricao" class="form-label small fw-semibold mb-1">
                            Detalhes <span class="text-secondary fw-normal">(opcional)</span>
                        </label>
                        <textarea id="descricao" name="descricao" rows="2" maxlength="500"
                                  class="form-control <?= isset($erros['descricao']) ? 'is-invalid' : '' ?>"
                                  placeholder="Ex.: estou na faixa da direita, pisca-alerta ligado"><?= e($dados['descricao']) ?></textarea>
                        <div class="invalid-feedback"><?= e($erros['descricao'] ?? '') ?></div>
                    </div>
                </div>
            </section>

            <!-- Passo 3: localização -->
            <section class="card mg-card mb-3">
                <div class="card-body p-3">
                    <div class="passo-titulo"><span class="passo-num">3</span> Onde você está?</div>

                    <div class="local-box <?= isset($erros['local']) ? 'erro' : '' ?>" id="localBox" aria-live="polite">
                        <i class="bi bi-geo-alt local-icone" id="localIcone"></i>
                        <p class="mb-2 mt-1" id="localTexto">
                            <?= isset($erros['local']) ? e($erros['local']) : 'Precisamos da sua posição para enviar o prestador mais próximo.' ?>
                        </p>
                        <div class="small text-secondary mb-2 d-none" id="localDetalhe"></div>
                        <button type="button" class="btn btn-outline-dark" id="btnLocal" style="border-radius:12px">
                            <i class="bi bi-crosshair me-1"></i> <span>Usar minha localização</span>
                        </button>
                        <div class="mt-2 d-none" id="linkMapaBox">
                            <a href="#" target="_blank" rel="noopener" id="linkMapa" class="small">
                                <i class="bi bi-map"></i> Conferir no mapa
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Passo 4: enviar -->
            <button type="submit" class="btn btn-mg w-100 fs-6" id="btnEnviar" disabled>
                <span class="passo-num bg-white text-dark me-1" style="width:24px;height:24px;flex:0 0 24px">4</span>
                <i class="bi bi-send-fill me-1"></i> Solicitar socorro agora
            </button>
        </form>
    <?php endif; ?>
</main>

<nav class="mg-bottom-nav d-flex" aria-label="Navegação principal">
    <a href="solicitar_socorro.php" class="ativo"><i class="bi bi-life-preserver"></i>Socorro</a>
    <a href="garagem.php"><i class="bi bi-car-front-fill"></i>Garagem</a>
    <a href="logout.php"><i class="bi bi-person-circle"></i>Conta</a>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var form = document.getElementById('formSocorro');
    if (!form) return;

    var btnLocal  = document.getElementById('btnLocal');
    var btnEnviar = document.getElementById('btnEnviar');
    var inLat     = document.getElementById('latitude');
    var inLng     = document.getElementById('longitude');
    var box       = document.getElementById('localBox');
    var icone     = document.getElementById('localIcone');
    var texto     = document.getElementById('localTexto');
    var detalhe   = document.getElementById('localDetalhe');
    var linkBox   = document.getElementById('linkMapaBox');
    var linkMapa  = document.getElementById('linkMapa');
    var rotulo    = btnLocal.querySelector('span');

    function formularioPronto() {
        var veiculo = form.querySelector('[name="veiculo"]').value;
        var avaria  = form.querySelector('[name="avaria"]:checked');
        var temLoc  = inLat.value !== '' && inLng.value !== '';
        btnEnviar.disabled = !(veiculo && avaria && temLoc);
    }

    function mostrarSucesso(lat, lng, precisao) {
        inLat.value = lat.toFixed(7);
        inLng.value = lng.toFixed(7);

        box.className = 'local-box ok';
        icone.className = 'bi bi-geo-alt-fill local-icone';
        texto.textContent = 'Localização capturada!';
        detalhe.classList.remove('d-none');
        detalhe.textContent = 'Lat ' + lat.toFixed(5) + ' · Lng ' + lng.toFixed(5)
            + (precisao ? ' · precisão ~' + Math.round(precisao) + ' m' : '');
        linkMapa.href = 'https://www.google.com/maps?q=' + lat.toFixed(6) + ',' + lng.toFixed(6);
        linkBox.classList.remove('d-none');
        rotulo.textContent = 'Atualizar localização';
        formularioPronto();
    }

    function mostrarErro(msg) {
        inLat.value = '';
        inLng.value = '';
        box.className = 'local-box erro';
        icone.className = 'bi bi-exclamation-triangle local-icone';
        texto.textContent = msg;
        detalhe.classList.add('d-none');
        linkBox.classList.add('d-none');
        rotulo.textContent = 'Tentar novamente';
        formularioPronto();
    }

    function capturar() {
        if (!('geolocation' in navigator)) {
            mostrarErro('Seu navegador não suporta geolocalização.');
            return;
        }
        if (!window.isSecureContext) {
            mostrarErro('A localização só funciona em conexão segura (HTTPS) ou em localhost.');
            return;
        }

        btnLocal.disabled = true;
        rotulo.textContent = 'Buscando sinal GPS...';
        box.className = 'local-box';
        icone.className = 'bi bi-hourglass-split local-icone';
        texto.textContent = 'Obtendo sua posição...';

        navigator.geolocation.getCurrentPosition(
            function (pos) {
                btnLocal.disabled = false;
                mostrarSucesso(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy);
            },
            function (err) {
                btnLocal.disabled = false;
                var msgs = {
                    1: 'Permissão negada. Libere a localização nas configurações do navegador.',
                    2: 'Não foi possível determinar sua posição. Verifique o GPS e o sinal.',
                    3: 'A busca demorou demais. Tente novamente em local aberto.'
                };
                mostrarErro(msgs[err.code] || 'Erro ao obter a localização.');
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    }

    btnLocal.addEventListener('click', capturar);
    form.addEventListener('change', formularioPronto);

    form.addEventListener('submit', function (ev) {
        if (btnEnviar.disabled) { ev.preventDefault(); return; }
        btnEnviar.disabled = true;
        btnEnviar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
    });

    if (inLat.value !== '' && inLng.value !== '') {
        var la = parseFloat(inLat.value), lo = parseFloat(inLng.value);
        if (!isNaN(la) && !isNaN(lo)) { mostrarSucesso(la, lo, null); }
    }
    formularioPronto();
})();
</script>
</body>
</html>