<?php
/**
 * MecGo - Cadastro do Cliente/Motorista
 * Local: htdocs/mecgo/cadastro_cliente.php
 *
 * Base: Documento de Requisitos MecGo v1.0
 *  - RF01: cadastro dos perfis de usuário (aqui, o perfil Cliente).
 *  - RF02: login por e-mail OU CPF  -> ambos são únicos na tabela.
 *  - Diagrama de classes, Usuário: idUsuario, nome, email, senha, telefone, cpf.
 *  - RNF03: senha armazenada de forma protegida (password_hash / bcrypt).
 *  - RNF09 (LGPD): registro do aceite do tratamento de dados pessoais.
 *
 * Pré-requisitos:
 *  - conexao.php na raiz, expondo uma conexão PDO em $pdo ($conexao e $conn também aceitos).
 *
 * Tabela (execute uma vez no phpMyAdmin):
 *
 *  CREATE TABLE IF NOT EXISTS usuarios (
 *      id_usuario      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *      nome            VARCHAR(100) NOT NULL,
 *      email           VARCHAR(120) NOT NULL,
 *      cpf             CHAR(11)     NOT NULL,
 *      telefone        VARCHAR(11)  NOT NULL,
 *      senha_hash      VARCHAR(255) NOT NULL,
 *      tipo            ENUM('cliente','prestador','administrador') NOT NULL DEFAULT 'cliente',
 *      status          ENUM('ativo','suspenso','bloqueado') NOT NULL DEFAULT 'ativo',
 *      aceite_lgpd_em  DATETIME NOT NULL,
 *      criado_em       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *      UNIQUE KEY uq_usuarios_email (email),
 *      UNIQUE KEY uq_usuarios_cpf (cpf)
 *  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 * Depois que a tabela existir, você pode ligar a garagem aos usuários:
 *
 *  ALTER TABLE veiculos
 *      ADD CONSTRAINT fk_veiculos_usuario FOREIGN KEY (id_usuario)
 *      REFERENCES usuarios(id_usuario) ON DELETE CASCADE;
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/conexao.php';

if (!isset($pdo)) {
    $pdo = $conexao ?? $conn ?? null;
}
if (!($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Falha na conexão com o banco de dados.');
}

// Quem já está logado vai direto para a garagem
if (!empty($_SESSION['usuario_id']) || !empty($_SESSION['cliente_id'])) {
    header('Location: garagem.php');
    exit;
}

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------
function e(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function somenteDigitos(string $valor): string
{
    return preg_replace('/\D/', '', $valor) ?? '';
}

/** Valida CPF pelos dígitos verificadores (recebe só números). */
function cpfValido(string $cpf): bool
{
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($c = 0; $c < $t; $c++) {
            $soma += (int) $cpf[$c] * (($t + 1) - $c);
        }
        $dv = ((10 * $soma) % 11) % 10;
        if ((int) $cpf[$t] !== $dv) {
            return false;
        }
    }
    return true;
}

/** Telefone brasileiro: DDD + 8 dígitos (fixo) ou DDD + 9 dígitos começando em 9 (celular). */
function telefoneValido(string $tel): bool
{
    if (!preg_match('/^[1-9][1-9](9[0-9]{8}|[2-8][0-9]{7})$/', $tel)) {
        return false;
    }
    return true;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$erros = [];
$dados = ['nome' => '', 'email' => '', 'cpf' => '', 'telefone' => '', 'lgpd' => false];

// ---------------------------------------------------------------
// Processamento POST
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Requisição inválida (CSRF).');
    }

    $nome      = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['nome'] ?? ''))) ?? '');
    $email     = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $cpf       = somenteDigitos((string) ($_POST['cpf'] ?? ''));
    $telefone  = somenteDigitos((string) ($_POST['telefone'] ?? ''));
    $senha     = (string) ($_POST['senha'] ?? '');
    $confirma  = (string) ($_POST['confirma_senha'] ?? '');
    $lgpd      = isset($_POST['lgpd']) && $_POST['lgpd'] === '1';

    // Nunca devolvemos a senha ao formulário
    $dados = [
        'nome' => $nome, 'email' => $email, 'cpf' => $cpf,
        'telefone' => $telefone, 'lgpd' => $lgpd,
    ];

    // Validação
    if (mb_strlen($nome) < 3 || mb_strlen($nome) > 100 || !preg_match('/^[\p{L}][\p{L}\s\'.-]+$/u', $nome)) {
        $erros['nome'] = 'Informe seu nome completo (3 a 100 letras).';
    }
    if (mb_strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros['email'] = 'Informe um e-mail válido.';
    }
    if (!cpfValido($cpf)) {
        $erros['cpf'] = 'CPF inválido.';
    }
    if (!telefoneValido($telefone)) {
        $erros['telefone'] = 'Telefone inválido. Use DDD + número, ex.: (86) 91234-5678.';
    }
    if (strlen($senha) < 8) {
        $erros['senha'] = 'A senha deve ter pelo menos 8 caracteres.';
    } elseif (strlen($senha) > 72) {
        $erros['senha'] = 'A senha pode ter no máximo 72 caracteres.';
    } elseif (!preg_match('/[A-Za-z]/', $senha) || !preg_match('/\d/', $senha)) {
        $erros['senha'] = 'Use letras e números na senha.';
    }
    if (!isset($erros['senha']) && !hash_equals($senha, $confirma)) {
        $erros['confirma_senha'] = 'As senhas não conferem.';
    }
    if (!$lgpd) {
        $erros['lgpd'] = 'É necessário aceitar o tratamento dos dados para criar a conta.';
    }

    // Duplicidade de e-mail e CPF
    if (!$erros) {
        $stmt = $pdo->prepare(
            'SELECT email, cpf FROM usuarios WHERE email = :email OR cpf = :cpf'
        );
        $stmt->execute([':email' => $email, ':cpf' => $cpf]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $existente) {
            if ($existente['email'] === $email) {
                $erros['email'] = 'Este e-mail já está cadastrado.';
            }
            if ($existente['cpf'] === $cpf) {
                $erros['cpf'] = 'Este CPF já está cadastrado.';
            }
        }
    }

    // Persistência
    if (!$erros) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO usuarios
                    (nome, email, cpf, telefone, senha_hash, tipo, status, aceite_lgpd_em)
                 VALUES
                    (:nome, :email, :cpf, :tel, :hash, \'cliente\', \'ativo\', NOW())'
            );
            $stmt->execute([
                ':nome'  => $nome,
                ':email' => $email,
                ':cpf'   => $cpf,
                ':tel'   => $telefone,
                ':hash'  => password_hash($senha, PASSWORD_DEFAULT),
            ]);

            // Renova o token CSRF e avisa na tela de login
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            $_SESSION['flash'] = [
                'tipo' => 'success',
                'msg'  => 'Conta criada com sucesso! Faça login para continuar.',
            ];
            header('Location: login_cliente.php');
            exit;
        } catch (PDOException $ex) {
            // 23000 = violação de chave única (cadastro simultâneo com mesmo e-mail/CPF)
            if ($ex->getCode() === '23000') {
                $erros['geral'] = 'E-mail ou CPF já cadastrado.';
            } else {
                error_log('[MecGo][cadastro] ' . $ex->getMessage());
                $erros['geral'] = 'Não foi possível criar a conta agora. Tente novamente.';
            }
        }
    }
}

// Formata para reexibir nos campos com máscara
$cpfExibe = $dados['cpf'] !== '' && strlen($dados['cpf']) === 11
    ? substr($dados['cpf'], 0, 3) . '.' . substr($dados['cpf'], 3, 3) . '.' . substr($dados['cpf'], 6, 3) . '-' . substr($dados['cpf'], 9)
    : $dados['cpf'];

$telExibe = $dados['telefone'];
if (strlen($telExibe) === 11) {
    $telExibe = '(' . substr($telExibe, 0, 2) . ') ' . substr($telExibe, 2, 5) . '-' . substr($telExibe, 7);
} elseif (strlen($telExibe) === 10) {
    $telExibe = '(' . substr($telExibe, 0, 2) . ') ' . substr($telExibe, 2, 4) . '-' . substr($telExibe, 6);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0d1b2a">
    <title>Criar conta | MecGo</title>

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
            padding: 1.5rem 1rem 3.4rem;
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
        .input-group .is-invalid ~ .btn-olho { border-color: #dc3545; }
        .form-check-input:checked {
            background-color: var(--mg-accent);
            border-color: var(--mg-accent);
        }
        .form-check-input { width: 1.3em; height: 1.3em; margin-top: .1em; }
        .forca { height: 6px; border-radius: 3px; background: #e6e9ee; overflow: hidden; }
        .forca > div { height: 100%; width: 0; transition: width .2s ease, background-color .2s ease; }
        a.mg-link { color: var(--mg-accent-dark); font-weight: 600; text-decoration: none; }
        a.mg-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<header class="mg-topbar">
    <div class="mg-wrap text-center">
        <div class="mg-brand fs-2">Mec<span>Go</span></div>
        <h1 class="h5 mt-2 mb-0 fw-bold">Crie sua conta</h1>
        <p class="mb-0 text-white-50 small">Socorro mecânico quando você mais precisa.</p>
    </div>
</header>

<main class="mg-wrap mg-main px-3 pb-4">

    <?php if (!empty($erros['geral'])): ?>
        <div class="alert alert-danger shadow-sm"><?= e($erros['geral']) ?></div>
    <?php endif; ?>

    <section class="card mg-card">
        <div class="card-body p-3 p-sm-4">
            <form method="post" action="cadastro_cliente.php" id="formCadastro" novalidate>
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

                <!-- Nome -->
                <div class="mb-3">
                    <label for="nome" class="form-label fw-semibold">Nome completo</label>
                    <input type="text" id="nome" name="nome" maxlength="100" autocomplete="name"
                           class="form-control <?= isset($erros['nome']) ? 'is-invalid' : '' ?>"
                           placeholder="Seu nome" value="<?= e($dados['nome']) ?>" required>
                    <div class="invalid-feedback"><?= e($erros['nome'] ?? '') ?></div>
                </div>

                <!-- E-mail -->
                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">E-mail</label>
                    <input type="email" id="email" name="email" maxlength="120" autocomplete="email"
                           inputmode="email" autocapitalize="none"
                           class="form-control <?= isset($erros['email']) ? 'is-invalid' : '' ?>"
                           placeholder="voce@email.com" value="<?= e($dados['email']) ?>" required>
                    <div class="invalid-feedback"><?= e($erros['email'] ?? '') ?></div>
                </div>

                <!-- CPF -->
                <div class="mb-3">
                    <label for="cpf" class="form-label fw-semibold">CPF</label>
                    <input type="text" id="cpf" name="cpf" maxlength="14" inputmode="numeric" autocomplete="off"
                           class="form-control <?= isset($erros['cpf']) ? 'is-invalid' : '' ?>"
                           placeholder="000.000.000-00" value="<?= e($cpfExibe) ?>" required>
                    <div class="invalid-feedback"><?= e($erros['cpf'] ?? '') ?></div>
                </div>

                <!-- Telefone -->
                <div class="mb-3">
                    <label for="telefone" class="form-label fw-semibold">Telefone (WhatsApp)</label>
                    <input type="tel" id="telefone" name="telefone" maxlength="15" inputmode="tel" autocomplete="tel-national"
                           class="form-control <?= isset($erros['telefone']) ? 'is-invalid' : '' ?>"
                           placeholder="(86) 91234-5678" value="<?= e($telExibe) ?>" required>
                    <div class="invalid-feedback"><?= e($erros['telefone'] ?? '') ?></div>
                </div>

                <!-- Senha -->
                <div class="mb-3">
                    <label for="senha" class="form-label fw-semibold">Senha</label>
                    <div class="input-group has-validation">
                        <input type="password" id="senha" name="senha" maxlength="72" autocomplete="new-password"
                               class="form-control <?= isset($erros['senha']) ? 'is-invalid' : '' ?>"
                               placeholder="Mínimo 8 caracteres" required>
                        <button type="button" class="btn btn-olho" data-alvo="senha" aria-label="Mostrar ou ocultar senha">
                            <i class="bi bi-eye"></i>
                        </button>
                        <div class="invalid-feedback"><?= e($erros['senha'] ?? '') ?></div>
                    </div>
                    <div class="forca mt-2" aria-hidden="true"><div id="forcaBarra"></div></div>
                    <div class="form-text" id="forcaTexto">Use letras e números.</div>
                </div>

                <!-- Confirmar senha -->
                <div class="mb-3">
                    <label for="confirma_senha" class="form-label fw-semibold">Confirmar senha</label>
                    <div class="input-group has-validation">
                        <input type="password" id="confirma_senha" name="confirma_senha" maxlength="72"
                               autocomplete="new-password"
                               class="form-control <?= isset($erros['confirma_senha']) ? 'is-invalid' : '' ?>"
                               placeholder="Repita a senha" required>
                        <button type="button" class="btn btn-olho" data-alvo="confirma_senha" aria-label="Mostrar ou ocultar senha">
                            <i class="bi bi-eye"></i>
                        </button>
                        <div class="invalid-feedback"><?= e($erros['confirma_senha'] ?? '') ?></div>
                    </div>
                </div>

                <!-- LGPD -->
                <div class="form-check mb-4">
                    <input class="form-check-input <?= isset($erros['lgpd']) ? 'is-invalid' : '' ?>"
                           type="checkbox" value="1" id="lgpd" name="lgpd" <?= $dados['lgpd'] ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="lgpd">
                        Concordo com o tratamento dos meus dados pessoais (nome, CPF, telefone e localização)
                        para a prestação do serviço de socorro, conforme a LGPD.
                    </label>
                    <?php if (isset($erros['lgpd'])): ?>
                        <div class="text-danger small mt-1"><?= e($erros['lgpd']) ?></div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-mg w-100 fs-6" id="btnCadastrar">
                    <i class="bi bi-person-plus-fill me-1"></i> Criar conta
                </button>
            </form>
        </div>
    </section>

    <p class="text-center mt-4 mb-0 text-secondary">
        Já tem conta? <a href="login_cliente.php" class="mg-link">Entrar</a>
    </p>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    // --- Máscaras ---
    function soNumeros(v) { return v.replace(/\D/g, ''); }

    var cpf = document.getElementById('cpf');
    cpf.addEventListener('input', function () {
        var v = soNumeros(cpf.value).slice(0, 11);
        v = v.replace(/^(\d{3})(\d)/, '$1.$2')
             .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
             .replace(/\.(\d{3})(\d)/, '.$1-$2');
        cpf.value = v;
    });

    var tel = document.getElementById('telefone');
    tel.addEventListener('input', function () {
        var v = soNumeros(tel.value).slice(0, 11);
        if (v.length > 10) {
            v = v.replace(/^(\d{2})(\d{5})(\d{0,4}).*/, '($1) $2-$3');
        } else if (v.length > 6) {
            v = v.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
        } else if (v.length > 2) {
            v = v.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
        } else if (v.length > 0) {
            v = v.replace(/^(\d{0,2})/, '($1');
        }
        tel.value = v;
    });

    // --- Mostrar/ocultar senha ---
    document.querySelectorAll('.btn-olho').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var campo = document.getElementById(btn.getAttribute('data-alvo'));
            var icone = btn.querySelector('i');
            var mostrar = campo.type === 'password';
            campo.type = mostrar ? 'text' : 'password';
            icone.className = mostrar ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });

    // --- Indicador de força da senha (apenas orientação visual) ---
    var senha = document.getElementById('senha');
    var barra = document.getElementById('forcaBarra');
    var texto = document.getElementById('forcaTexto');
    senha.addEventListener('input', function () {
        var s = senha.value, pontos = 0;
        if (s.length >= 8) pontos++;
        if (s.length >= 12) pontos++;
        if (/[a-z]/.test(s) && /[A-Z]/.test(s)) pontos++;
        if (/\d/.test(s)) pontos++;
        if (/[^A-Za-z0-9]/.test(s)) pontos++;

        var niveis = [
            { w: '0%',   c: '#e6e9ee', t: 'Use letras e números.' },
            { w: '25%',  c: '#dc3545', t: 'Senha fraca' },
            { w: '50%',  c: '#fd7e14', t: 'Senha razoável' },
            { w: '75%',  c: '#ffc107', t: 'Senha boa' },
            { w: '100%', c: '#198754', t: 'Senha forte' }
        ];
        var n = s.length === 0 ? 0 : Math.min(4, Math.max(1, pontos - 1));
        barra.style.width = niveis[n].w;
        barra.style.backgroundColor = niveis[n].c;
        texto.textContent = niveis[n].t;
    });

    // --- Evita duplo envio ---
    document.getElementById('formCadastro').addEventListener('submit', function () {
        var b = document.getElementById('btnCadastrar');
        b.disabled = true;
        b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Criando conta...';
    });
})();
</script>
</body>
</html>
