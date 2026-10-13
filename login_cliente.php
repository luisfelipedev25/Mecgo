<?php
/**
 * MecGo - Login do Cliente/Motorista
 * Local: htdocs/mecgo/login_cliente.php
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/conexao.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Falha na conexão com o banco de dados.');
}

// Já logado: segue para a garagem
if (!empty($_SESSION['usuario_id'])) {
    header('Location: garagem.php');
    exit;
}

// ---------------------------------------------------------------
// Constantes e helpers
// ---------------------------------------------------------------
const MAX_TENTATIVAS = 5;
const BLOQUEIO_SEGUNDOS = 300;

function e(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function somenteDigitos(string $valor): string
{
    return preg_replace('/\D/', '', $valor) ?? '';
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$erro = '';
$identificador = '';

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ---------------------------------------------------------------
// Processamento POST
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Requisição inválida (CSRF).');
    }

    $identificador = trim((string) ($_POST['identificador'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');

    $bloqueadoAte = (int) ($_SESSION['login_bloqueado_ate'] ?? 0);
    if ($bloqueadoAte > time()) {
        $minutos = (int) ceil(($bloqueadoAte - time()) / 60);
        $erro = "Muitas tentativas. Tente novamente em {$minutos} min.";
    } elseif ($identificador === '' || $senha === '') {
        $erro = 'Informe seu e-mail ou CPF e a senha.';
    } else {
        if (str_contains($identificador, '@')) {
            $campo = 'email';
            $valor = mb_strtolower($identificador);
        } else {
            $campo = 'cpf';
            $valor = somenteDigitos($identificador);
        }

        $usuario = null;
        if (($campo === 'email' && filter_var($valor, FILTER_VALIDATE_EMAIL))
            || ($campo === 'cpf' && strlen($valor) === 11)) {
            
            $stmt = $pdo->prepare(
                "SELECT id_usuario, nome, senha, tipo, status
                   FROM usuario WHERE {$campo} = :valor LIMIT 1"
            );
            $stmt->execute([':valor' => $valor]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $senhaOk = false;
        if ($usuario) {
            if (password_verify($senha, $usuario['senha']) || $senha === $usuario['senha']) {
                $senhaOk = true;
            }
        }

        if ($usuario && $senhaOk) {
            if ($usuario['status'] !== 'ativo') {
                $erro = 'Sua conta está ' . ($usuario['status'] === 'bloqueado' ? 'bloqueada' : 'suspensa')
                      . '. Entre em contato com o suporte.';
            } elseif ($usuario['tipo'] !== 'CLIENTE') {
                $erro = 'Este acesso é exclusivo para motoristas.';
            } else {
                session_regenerate_id(true);
                unset($_SESSION['login_tentativas'], $_SESSION['login_bloqueado_ate']);
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                $_SESSION['usuario_id'] = (int) $usuario['id_usuario'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['usuario_tipo'] = $usuario['tipo'];

                $destino = 'garagem.php';
                try {
                    $q = $pdo->prepare('SELECT COUNT(*) FROM veiculo WHERE id_cliente = :id');
                    $q->execute([':id' => (int) $usuario['id_usuario']]);
                    if ((int) $q->fetchColumn() > 0) {
                        $destino = 'solicitar_socorro.php';
                    }
                } catch (PDOException $ex) {
                    error_log('[MecGo][login] ' . $ex->getMessage());
                }
                header('Location: ' . $destino);
                exit;
            }
        } else {
            $erro = 'E-mail/CPF ou senha incorretos.';
        }

        if ($erro !== '') {
            $tentativas = (int) ($_SESSION['login_tentativas'] ?? 0) + 1;
            if ($tentativas >= MAX_TENTATIVAS) {
                $_SESSION['login_bloqueado_ate'] = time() + BLOQUEIO_SEGUNDOS;
                $_SESSION['login_tentativas'] = 0;
                $erro = 'Muitas tentativas. Tente novamente em 5 min.';
            } else {
                $_SESSION['login_tentativas'] = $tentativas;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0d1b2a">
    <title>Entrar | MecGo</title>

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
            padding-bottom: env(safe-area-inset-bottom);
            -webkit-tap-highlight-color: transparent;
        }
        .mg-topbar {
            background: linear-gradient(135deg, var(--mg-dark), var(--mg-dark-2));
            color: #fff;
            padding: 2rem 1rem 3.8rem;
            border-radius: 0 0 28px 28px;
        }
        .mg-wrap { max-width: 560px; margin-left: auto; margin-right: auto; }
        .mg-brand { font-weight: 800; letter-spacing: .5px; }
        .mg-brand span { color: var(--mg-accent); }
        .mg-main { margin-top: -2.4rem; }
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
        .form-control { min-height: 48px; border-radius: 12px; }
        .form-control:focus {
            border-color: var(--mg-accent);
            box-shadow: 0 0 0 .2rem rgba(255, 107, 0, .18);
        }
        .input-group .form-control { border-radius: 12px 0 0 12px; }
        .input-group .btn-olho {
            border: 1px solid #dee2e6; border-left: 0;
            border-radius: 0 12px 12px 0; background: #fff; min-width: 52px;
            color: #6b7685;
        }
        a.mg-link { color: var(--mg-accent-dark); font-weight: 600; text-decoration: none; }
        a.mg-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<header class="mg-topbar">
    <div class="mg-wrap text-center">
        <div class="mg-brand fs-1">Mec<span>Go</span></div>
        <p class="mb-0 mt-1 text-white-50">Socorro mecânico quando você mais precisa.</p>
    </div>
</header>

<main class="mg-wrap mg-main px-3 pb-4">

    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['tipo']) ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= e($flash['msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>

    <?php if ($erro !== ''): ?>
        <div class="alert alert-danger shadow-sm" role="alert">
            <i class="bi bi-exclamation-circle me-1"></i><?= e($erro) ?>
        </div>
    <?php endif; ?>

    <section class="card mg-card">
        <div class="card-body p-3 p-sm-4">
            <h1 class="h5 fw-bold mb-3">Entrar na sua conta</h1>

            <form method="post" action="login_cliente.php" id="formLogin" novalidate>
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

                <div class="mb-3">
                    <label for="identificador" class="form-label fw-semibold">E-mail ou CPF</label>
                    <input type="text" id="identificador" name="identificador" maxlength="120"
                           autocomplete="username" autocapitalize="none" spellcheck="false"
                           class="form-control" placeholder="voce@email.com ou 000.000.000-00"
                           value="<?= e($identificador) ?>" required autofocus>
                </div>

                <div class="mb-2">
                    <label for="senha" class="form-label fw-semibold">Senha</label>
                    <div class="input-group">
                        <input type="password" id="senha" name="senha" maxlength="72"
                               autocomplete="current-password" class="form-control"
                               placeholder="Sua senha" required>
                        <button type="button" class="btn btn-olho" id="btnOlho"
                                aria-label="Mostrar ou ocultar senha">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="text-end mb-4">
                    <a href="#" class="mg-link small" data-bs-toggle="modal" data-bs-target="#modalRecuperar">
                        Esqueci minha senha
                    </a>
                </div>

                <button type="submit" class="btn btn-mg w-100 fs-6" id="btnEntrar">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Entrar
                </button>
            </form>
        </div>
    </section>

    <p class="text-center mt-4 mb-0 text-secondary">
        Ainda não tem conta? <a href="cadastro_cliente.php" class="mg-link">Criar conta</a>
    </p>
</main>

<div class="modal fade" id="modalRecuperar" tabindex="-1" aria-labelledby="tituloRecuperar" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:18px">
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title h5 fw-bold" id="tituloRecuperar">Recuperar senha</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                A recuperação de senha por e-mail ainda não está disponível nesta versão.
                Por enquanto, entre em contato com o suporte do MecGo.
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-mg w-100" data-bs-dismiss="modal">Entendi</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var senha = document.getElementById('senha');
    var btn = document.getElementById('btnOlho');
    btn.addEventListener('click', function () {
        var mostrar = senha.type === 'password';
        senha.type = mostrar ? 'text' : 'password';
        btn.querySelector('i').className = mostrar ? 'bi bi-eye-slash' : 'bi bi-eye';
    });

    var campo = document.getElementById('identificador');
    campo.addEventListener('input', function () {
        var v = campo.value;
        if (!/^[\d.\-\s]+$/.test(v)) return;
        var n = v.replace(/\D/g, '').slice(0, 11);
        n = n.replace(/^(\d{3})(\d)/, '$1.$2')
             .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
             .replace(/\.(\d{3})(\d)/, '.$1-$2');
        campo.value = n;
    });

    document.getElementById('formLogin').addEventListener('submit', function () {
        var b = document.getElementById('btnEntrar');
        b.disabled = true;
        b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Entrando...';
    });
})();
</script>
</body>
</html>