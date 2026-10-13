<?php
/**
 * MecGo - Garagem de Veículos (Cliente/Motorista)
 * Local: htdocs/mecgo/garagem.php
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
const TIPOS_VEICULO = ['Carro', 'Moto'];
const CORES_VEICULO = [
    'Branco', 'Preto', 'Prata', 'Cinza', 'Vermelho', 'Azul',
    'Verde', 'Amarelo', 'Laranja', 'Marrom', 'Bege', 'Outra',
];

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

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$erros = [];
$dados = ['tipo' => 'Carro', 'placa' => '', 'marca' => '', 'modelo' => '', 'ano' => '', 'cor' => ''];

// ---------------------------------------------------------------
// Processamento POST
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Requisição inválida (CSRF).');
    }

    $acao = $_POST['acao'] ?? '';

    // ---------- Cadastrar ----------
    if ($acao === 'cadastrar') {
        $tipo   = trim((string) ($_POST['tipo'] ?? 'Carro'));
        // Limpa a placa deixando apenas letras e números (máximo 7 caracteres)
        $placa  = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) ($_POST['placa'] ?? '')), 0, 7));
        $marca  = trim(strip_tags((string) ($_POST['marca'] ?? '')));
        $modelo = trim(strip_tags((string) ($_POST['modelo'] ?? '')));
        $anoRaw = trim((string) ($_POST['ano'] ?? ''));
        $cor    = trim((string) ($_POST['cor'] ?? ''));

        $dados = [
            'tipo' => $tipo, 'placa' => $placa, 'marca' => $marca,
            'modelo' => $modelo, 'ano' => $anoRaw, 'cor' => $cor,
        ];

        $anoMax = (int) date('Y') + 1;

        if (!preg_match('/^([A-Z]{3}[0-9]{4}|[A-Z]{3}[0-9][A-Z][0-9]{2})$/', $placa)) {
            $erros['placa'] = 'Placa inválida. Use ABC1D23 ou ABC1234.';
        }
        if (mb_strlen($marca) < 2 || mb_strlen($marca) > 50) {
            $erros['marca'] = 'Informe a marca (2 a 50 caracteres).';
        }
        if (mb_strlen($modelo) < 1 || mb_strlen($modelo) > 80) {
            $erros['modelo'] = 'Informe o modelo (até 80 caracteres).';
        }
        $ano = filter_var($anoRaw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1950, 'max_range' => $anoMax],
        ]);
        if ($ano === false) {
            $erros['ano'] = "Ano inválido (1950 a {$anoMax}).";
        }
        if (!in_array($cor, CORES_VEICULO, true)) {
            $erros['cor'] = 'Selecione a cor do veículo.';
        }

        // Verifica duplicidade na tabela 'veiculo'
        if (!$erros) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM veiculo WHERE id_cliente = :uid AND placa = :placa'
            );
            $stmt->execute([':uid' => $usuarioId, ':placa' => $placa]);
            if ((int) $stmt->fetchColumn() > 0) {
                $erros['placa'] = 'Você já cadastrou um veículo com esta placa.';
            }
        }

        // Insere na tabela 'veiculo' com tratamento para as colunas do banco
        if (!$erros) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO veiculo (id_cliente, placa, marca, modelo, ano, cor)
                     VALUES (:uid, :placa, :marca, :modelo, :ano, :cor)'
                );
                $stmt->execute([
                    ':uid'    => $usuarioId,
                    ':placa'  => $placa,
                    ':marca'  => $marca,
                    ':modelo' => $modelo,
                    ':ano'    => $ano,
                    ':cor'    => $cor,
                ]);
                $_SESSION['flash'] = ['tipo' => 'success', 'msg' => 'Veículo adicionado à sua garagem!'];
                header('Location: garagem.php');
                exit;
            } catch (PDOException $ex) {
            error_log('[MecGo][garagem] ' . $ex->getMessage());
            $erros['geral'] = 'Não foi possível salvar o veículo. Tente novamente.';
        }
        }
    }

    // ---------- Excluir ----------
    if ($acao === 'excluir') {
        $veiculoId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($veiculoId) {
            try {
                $stmt = $pdo->prepare(
                    'DELETE FROM veiculo WHERE id_veiculo = :id AND id_cliente = :uid'
                );
                $stmt->execute([':id' => $veiculoId, ':uid' => $usuarioId]);
                $_SESSION['flash'] = $stmt->rowCount()
                    ? ['tipo' => 'success', 'msg' => 'Veículo removido da garagem.']
                    : ['tipo' => 'warning', 'msg' => 'Veículo não encontrado.'];
            } catch (PDOException $ex) {
                error_log('[MecGo][garagem] ' . $ex->getMessage());
                $_SESSION['flash'] = ['tipo' => 'danger', 'msg' => 'Não foi possível remover este veículo.'];
            }
        }
        header('Location: garagem.php');
        exit;
    }
}

// ---------------------------------------------------------------
// Listagem
// ---------------------------------------------------------------
$stmt = $pdo->prepare(
    'SELECT id_veiculo, placa, marca, modelo, ano, cor
       FROM veiculo
      WHERE id_cliente = :uid
   ORDER BY id_veiculo DESC'
);
$stmt->execute([':uid' => $usuarioId]);
$veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$abrirForm = !empty($erros) || empty($veiculos);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0d1b2a">
    <title>Minha Garagem | MecGo</title>

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
            padding: .8rem 1rem;
        }
        .btn-mg:hover, .btn-mg:focus, .btn-mg:active {
            background: var(--mg-accent-dark) !important;
            border-color: var(--mg-accent-dark) !important;
            color: #fff;
        }
        .form-control, .form-select { min-height: 48px; border-radius: 12px; }
        .form-control:focus, .form-select:focus {
            border-color: var(--mg-accent);
            box-shadow: 0 0 0 .2rem rgba(255, 107, 0, .18);
        }
        .input-placa {
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 700;
            text-align: center;
        }

        .tipo-opcao { position: absolute; opacity: 0; pointer-events: none; }
        .tipo-label {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: .15rem; padding: .7rem .5rem;
            border: 2px solid #dde2e9; border-radius: 14px;
            background: #fff; color: #6b7685; font-weight: 700; cursor: pointer;
            transition: all .15s ease;
        }
        .tipo-label i { font-size: 1.6rem; }
        .tipo-opcao:checked + .tipo-label {
            border-color: var(--mg-accent);
            background: rgba(255, 107, 0, .08);
            color: var(--mg-accent-dark);
        }
        .tipo-opcao:focus-visible + .tipo-label {
            box-shadow: 0 0 0 .2rem rgba(255, 107, 0, .25);
        }

        .veiculo-item { border-left: 5px solid var(--mg-accent); }
        .veiculo-icone {
            width: 46px; height: 46px; flex: 0 0 46px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 12px; background: rgba(13, 27, 42, .06);
            color: var(--mg-dark); font-size: 1.4rem;
        }
        .placa-badge {
            display: inline-block;
            background: #fff;
            border: 2px solid var(--mg-dark);
            border-top: 8px solid #1f4fbf;
            border-radius: 6px;
            padding: .05rem .6rem;
            font-weight: 800;
            letter-spacing: 2px;
            color: var(--mg-dark);
            font-size: .95rem;
        }
        .vazio-icone { font-size: 3.2rem; color: #b8c0cc; }
        .mg-bottom-nav {
            position: fixed; left: 0; right: 0; bottom: 0;
            background: #fff;
            box-shadow: 0 -4px 16px rgba(13, 27, 42, .1);
            padding-bottom: env(safe-area-inset-bottom);
            z-index: 1030;
        }
        .mg-bottom-nav a {
            flex: 1;
            text-align: center;
            padding: .6rem 0 .5rem;
            color: #6b7685;
            text-decoration: none;
            font-size: .72rem;
            font-weight: 600;
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
        <h1 class="h4 mt-3 mb-0 fw-bold"><i class="bi bi-car-front-fill me-2"></i>Minha Garagem</h1>
        <p class="mb-0 text-white-50 small">Cadastre seus veículos para pedir socorro mais rápido.</p>
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

    <!-- Formulário de cadastro -->
    <section class="card mg-card mb-4">
        <div class="card-body p-3">
            <button class="btn w-100 d-flex justify-content-between align-items-center p-0 border-0 bg-transparent fw-bold"
                    type="button" data-bs-toggle="collapse" data-bs-target="#formVeiculo"
                    aria-expanded="<?= $abrirForm ? 'true' : 'false' ?>" aria-controls="formVeiculo">
                <span><i class="bi bi-plus-circle-fill text-warning me-2"></i>Adicionar veículo</span>
                <i class="bi bi-chevron-down"></i>
            </button>

            <div class="collapse <?= $abrirForm ? 'show' : '' ?>" id="formVeiculo">
                <form method="post" action="garagem.php" novalidate class="mt-3">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                    <input type="hidden" name="acao" value="cadastrar">

                    <!-- Tipo -->
                    <div class="mb-3">
                        <span class="form-label fw-semibold d-block">Tipo</span>
                        <div class="row g-2">
                            <div class="col-6 position-relative">
                                <input type="radio" class="tipo-opcao" name="tipo" id="tipoCarro" value="Carro"
                                       <?= $dados['tipo'] !== 'Moto' ? 'checked' : '' ?>>
                                <label for="tipoCarro" class="tipo-label"><i class="bi bi-car-front-fill"></i>Carro</label>
                            </div>
                            <div class="col-6 position-relative">
                                <input type="radio" class="tipo-opcao" name="tipo" id="tipoMoto" value="Moto"
                                       <?= $dados['tipo'] === 'Moto' ? 'checked' : '' ?>>
                                <label for="tipoMoto" class="tipo-label"><i class="bi bi-scooter"></i>Moto</label>
                            </div>
                        </div>
                    </div>

                    <!-- Placa -->
                    <div class="mb-3">
                        <label for="placa" class="form-label fw-semibold">Placa</label>
                        <input type="text" id="placa" name="placa" maxlength="8"
                               class="form-control form-control-lg input-placa <?= isset($erros['placa']) ? 'is-invalid' : '' ?>"
                               placeholder="XYZ1234 ou ABC1D23" autocapitalize="characters" autocomplete="off"
                               value="<?= e($dados['placa']) ?>" required>
                        <div class="invalid-feedback"><?= e($erros['placa'] ?? '') ?></div>
                    </div>

                    <!-- Marca / Ano -->
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label for="marca" class="form-label fw-semibold">Marca</label>
                            <input type="text" id="marca" name="marca" maxlength="50"
                                   class="form-control <?= isset($erros['marca']) ? 'is-invalid' : '' ?>"
                                   placeholder="Ex.: BMW" value="<?= e($dados['marca']) ?>" required>
                            <div class="invalid-feedback"><?= e($erros['marca'] ?? '') ?></div>
                        </div>
                        <div class="col-5">
                            <label for="ano" class="form-label fw-semibold">Ano</label>
                            <input type="number" id="ano" name="ano" inputmode="numeric"
                                   min="1950" max="<?= (int) date('Y') + 1 ?>"
                                   class="form-control <?= isset($erros['ano']) ? 'is-invalid' : '' ?>"
                                   placeholder="<?= date('Y') ?>" value="<?= e($dados['ano']) ?>" required>
                            <div class="invalid-feedback"><?= e($erros['ano'] ?? '') ?></div>
                        </div>
                    </div>

                    <!-- Modelo -->
                    <div class="mb-3">
                        <label for="modelo" class="form-label fw-semibold">Modelo</label>
                        <input type="text" id="modelo" name="modelo" maxlength="80"
                               class="form-control <?= isset($erros['modelo']) ? 'is-invalid' : '' ?>"
                               placeholder="Ex.: X4" value="<?= e($dados['modelo']) ?>" required>
                        <div class="invalid-feedback"><?= e($erros['modelo'] ?? '') ?></div>
                    </div>

                    <!-- Cor -->
                    <div class="mb-3">
                        <label for="cor" class="form-label fw-semibold">Cor</label>
                        <select id="cor" name="cor"
                                class="form-select <?= isset($erros['cor']) ? 'is-invalid' : '' ?>" required>
                            <option value="" disabled <?= $dados['cor'] === '' ? 'selected' : '' ?>>Selecione...</option>
                            <?php foreach (CORES_VEICULO as $c): ?>
                                <option value="<?= e($c) ?>" <?= $dados['cor'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?= e($erros['cor'] ?? '') ?></div>
                    </div>

                    <button type="submit" class="btn btn-mg w-100">
                        <i class="bi bi-check2-circle me-1"></i> Salvar veículo
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Listagem -->
    <section>
        <div class="d-flex justify-content-between align-items-center mb-2 px-1">
            <h2 class="h6 fw-bold mb-0 text-uppercase text-secondary">Meus veículos</h2>
            <span class="badge rounded-pill text-bg-dark"><?= count($veiculos) ?></span>
        </div>

        <?php if (!$veiculos): ?>
            <div class="card mg-card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-car-front vazio-icone"></i>
                    <p class="mt-2 mb-0 text-secondary">Sua garagem está vazia.<br>Adicione seu primeiro veículo acima.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($veiculos as $v): ?>
                <article class="card mg-card veiculo-item mb-3">
                    <div class="card-body d-flex align-items-center gap-3 p-3">
                        <div class="veiculo-icone" aria-hidden="true">
                            <i class="bi bi-car-front-fill"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-bold"><?= e($v['marca']) ?> <?= e($v['modelo']) ?></div>
                            <div class="text-secondary small mb-2">
                                <?= (int) $v['ano'] ?> · <?= e($v['cor']) ?>
                            </div>
                            <span class="placa-badge"><?= e(formatarPlaca($v['placa'])) ?></span>
                        </div>
                        <div class="d-flex flex-column gap-2">
                            <a href="solicitar_socorro.php?veiculo=<?= (int) $v['id_veiculo'] ?>"
                               class="btn btn-sm btn-mg d-flex align-items-center justify-content-center"
                               title="Pedir socorro para este veículo" aria-label="Pedir socorro">
                                <i class="bi bi-tools"></i>
                            </a>
                            <form method="post" action="garagem.php"
                                  onsubmit="return confirm('Remover este veículo da garagem?');">
                                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?= (int) $v['id_veiculo'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger w-100"
                                        style="border-radius:12px" aria-label="Excluir veículo">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>

<nav class="mg-bottom-nav d-flex" aria-label="Navegação principal">
    <a href="solicitar_socorro.php"><i class="bi bi-life-preserver"></i>Socorro</a>
    <a href="garagem.php" class="ativo"><i class="bi bi-car-front-fill"></i>Garagem</a>
    <a href="logout.php"><i class="bi bi-person-circle"></i>Conta</a>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        var campo = document.getElementById('placa');
        if (!campo) return;
        campo.addEventListener('input', function () {
            var v = campo.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 7);
            if (/^[A-Z]{3}[0-9]{2,}$/.test(v)) {
                v = v.slice(0, 3) + '-' + v.slice(3);
            }
            campo.value = v;
        });
    })();
</script>
</body>
</html>