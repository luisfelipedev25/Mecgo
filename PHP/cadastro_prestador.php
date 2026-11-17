<?php
require_once 'conexao.php';

$erro = '';
$nome = '';
$email = '';
$cpf = '';
$telefone = '';
$empresa = '';
$cnpj = '';
$categoriasSel = ['pneu'];
$raio = 30;
$bio = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $empresa = trim($_POST['empresa'] ?? '');
    $cnpj = trim($_POST['cnpj'] ?? '');
    $categoriasSel = $_POST['categoria'] ?? [];
    $raio = (int) ($_POST['raio'] ?? 30);
    $bio = trim($_POST['bio'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirma_senha = $_POST['confirma_senha'] ?? '';
    $lgpd = isset($_POST['lgpd']);

    // Validações
    if ($nome === '' || $email === '' || $cpf === '' || $telefone === '' || $senha === '') {
        $erro = 'Preencha todos os campos pessoais e de acesso obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } elseif (!is_array($categoriasSel) || count($categoriasSel) === 0) {
        $erro = 'Selecione pelo menos uma categoria de atendimento.';
    } elseif (strlen($senha) < 8) {
        $erro = 'A senha deve ter no mínimo 8 caracteres.';
    } elseif ($senha !== $confirma_senha) {
        $erro = 'As senhas não conferem.';
    } elseif (!$lgpd) {
        $erro = 'Você precisa concordar com os termos da LGPD.';
    } elseif (empty($_FILES['portfolio']['name'][0])) {
        $erro = 'Envie pelo menos 1 foto do seu portfólio.';
    } else {
        try {
            // Verifica se e-mail ou CPF já existem
            $stmtVerifica = $pdo->prepare('SELECT id_usuario FROM usuario WHERE email = ? OR cpf = ? LIMIT 1');
            $stmtVerifica->execute([$email, $cpf]);

            if ($stmtVerifica->fetch()) {
                $erro = 'Este e-mail ou CPF já está cadastrado.';
            } else {
                // Prepara diretório de upload
                $dirUpload = __DIR__ . '/../uploads/prestadores/';
                if (!is_dir($dirUpload)) {
                    mkdir($dirUpload, 0775, true);
                }

                $extPermitidas = ['jpg', 'jpeg', 'png', 'webp'];

                // Upload opcional da foto de perfil
                $caminhoFotoPerfil = null;
                if (!empty($_FILES['foto']['name']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                    $extPerfil = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                    if (!in_array($extPerfil, $extPermitidas, true)) {
                        throw new RuntimeException('A foto de perfil deve ser JPG, PNG ou WEBP.');
                    }
                    $nomeArqPerfil = 'perfil_' . uniqid('', true) . '.' . $extPerfil;
                    if (move_uploaded_file($_FILES['foto']['tmp_name'], $dirUpload . $nomeArqPerfil)) {
                        $caminhoFotoPerfil = 'uploads/prestadores/' . $nomeArqPerfil;
                    }
                }

                // Upload das fotos obrigatórias do portfólio
                $caminhosPortfolio = [];
                $totalArqs = count($_FILES['portfolio']['name']);
                for ($i = 0; $i < $totalArqs; $i++) {
                    if ($_FILES['portfolio']['error'][$i] === UPLOAD_ERR_OK) {
                        $extPort = strtolower(pathinfo($_FILES['portfolio']['name'][$i], PATHINFO_EXTENSION));
                        if (!in_array($extPort, $extPermitidas, true)) {
                            throw new RuntimeException('As fotos do portfólio devem ser JPG, PNG ou WEBP.');
                        }
                        $nomeArqPort = 'port_' . uniqid('', true) . '.' . $extPort;
                        if (move_uploaded_file($_FILES['portfolio']['tmp_name'][$i], $dirUpload . $nomeArqPort)) {
                            $caminhosPortfolio[] = 'uploads/prestadores/' . $nomeArqPort;
                        }
                    }
                }

                if (count($caminhosPortfolio) === 0) {
                    throw new RuntimeException('Não foi possível salvar as fotos do portfólio.');
                }

                $pdo->beginTransaction();

                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

                // 1. Insere na tabela usuario com status 'pendente' para aprovação do Admin
                $sqlUsuario = 'INSERT INTO usuario (nome, email, cpf, telefone, senha, tipo, status, aceite_lgpd_em)
                               VALUES (?, ?, ?, ?, ?, "PRESTADOR", "pendente", NOW())';
                $stmtUsuario = $pdo->prepare($sqlUsuario);
                $stmtUsuario->execute([$nome, $email, $cpf, $telefone, $senhaHash]);

                $idPrestador = (int) $pdo->lastInsertId();

                // 2. Insere na tabela prestador
                $categoriasStr = implode(',', array_map('trim', $categoriasSel));
                $sqlPrestador = 'INSERT INTO prestador (id_prestador, empresa, cnpj, categorias, raio_km, bio, foto_perfil)
                                 VALUES (?, ?, ?, ?, ?, ?, ?)';
                $stmtPrestador = $pdo->prepare($sqlPrestador);
                $stmtPrestador->execute([
                    $idPrestador,
                    $empresa !== '' ? $empresa : null,
                    $cnpj !== '' ? $cnpj : null,
                    $categoriasStr,
                    $raio,
                    $bio !== '' ? $bio : null,
                    $caminhoFotoPerfil
                ]);

                // 3. Insere as fotos na tabela prestador_portfolio
                $sqlPort = 'INSERT INTO prestador_portfolio (id_prestador, caminho_arquivo) VALUES (?, ?)';
                $stmtPort = $pdo->prepare($sqlPort);
                foreach ($caminhosPortfolio as $arq) {
                    $stmtPort->execute([$idPrestador, $arq]);
                }

                $pdo->commit();

                header('Location: index.php?cadastro_prestador=ok');
                exit;
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro = 'Erro ao cadastrar prestador: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e66927">
    <title>Cadastro de Prestador | MecGo</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="app sem-nav">

    <header class="topbar centro">
        <div class="brand grande">Mec<span>Go</span></div>
        <span class="selo">Área do Prestador</span>
        <h1>Cadastre-se como prestador</h1>
        <p class="sub">Mecânicos, eletricistas, chaveiros, guinchos e oficinas.</p>
    </header>

    <main class="content">

        <div class="alert alert-info" role="note">
            Depois do envio, a equipe MecGo analisa seu CPF/CNPJ e seu portfólio antes de ativar a conta.
        </div>

        <?php if ($erro !== ''): ?>
            <div style="background:#fdecea;color:#b71c1c;padding:12px;border-radius:10px;margin-bottom:14px;font-size:14px;">
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <section class="card">
            <form action="" method="post" enctype="multipart/form-data">

                <!-- 1. Dados pessoais -->
                <h2 class="secao-form">Seus dados</h2>

                <div class="campo">
                    <label for="nome">Nome completo</label>
                    <input type="text" id="nome" name="nome" maxlength="100"
                           value="<?= htmlspecialchars($nome) ?>"
                           placeholder="Seu nome" autocomplete="name" required>
                </div>

                <div class="campo">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" maxlength="120"
                           value="<?= htmlspecialchars($email) ?>"
                           placeholder="voce@email.com" autocomplete="email" required>
                </div>

                <div class="campo">
                    <label for="cpf">CPF</label>
                    <input type="text" id="cpf" name="cpf" maxlength="14"
                           value="<?= htmlspecialchars($cpf) ?>"
                           inputmode="numeric" placeholder="000.000.000-00" required>
                </div>

                <div class="campo">
                    <label for="telefone">Telefone (WhatsApp)</label>
                    <input type="tel" id="telefone" name="telefone" maxlength="15"
                           value="<?= htmlspecialchars($telefone) ?>"
                           placeholder="(86) 91234-5678" autocomplete="tel-national" required>
                </div>

                <!-- 2. Oficina / empresa -->
                <h2 class="secao-form">Oficina ou empresa</h2>

                <div class="campo">
                    <label for="empresa">Nome da oficina <span class="opcional">(opcional)</span></label>
                    <input type="text" id="empresa" name="empresa" maxlength="100"
                           value="<?= htmlspecialchars($empresa) ?>"
                           placeholder="Ex.: Oficina do Zé">
                </div>

                <div class="campo">
                    <label for="cnpj">CNPJ <span class="opcional">(opcional)</span></label>
                    <input type="text" id="cnpj" name="cnpj" maxlength="18"
                           value="<?= htmlspecialchars($cnpj) ?>"
                           inputmode="numeric" placeholder="00.000.000/0000-00">
                </div>

                <!-- 3. Atuação -->
                <h2 class="secao-form">Como você atua</h2>

                <div class="campo">
                    <span class="rotulo">Categorias de atendimento</span>
                    <div class="escolhas">
                        <div class="escolha">
                            <input type="checkbox" id="cat_pneu" name="categoria[]" value="pneu" <?= in_array('pneu', $categoriasSel, true) ? 'checked' : '' ?>>
                            <label for="cat_pneu"><span class="ico">🛞</span>Pneus</label>
                        </div>
                        <div class="escolha">
                            <input type="checkbox" id="cat_bateria" name="categoria[]" value="bateria" <?= in_array('bateria', $categoriasSel, true) ? 'checked' : '' ?>>
                            <label for="cat_bateria"><span class="ico">🔋</span>Bateria e elétrica</label>
                        </div>
                        <div class="escolha">
                            <input type="checkbox" id="cat_guincho" name="categoria[]" value="guincho" <?= in_array('guincho', $categoriasSel, true) ? 'checked' : '' ?>>
                            <label for="cat_guincho"><span class="ico">🚛</span>Guincho</label>
                        </div>
                        <div class="escolha">
                            <input type="checkbox" id="cat_mecanica" name="categoria[]" value="mecanica" <?= in_array('mecanica', $categoriasSel, true) ? 'checked' : '' ?>>
                            <label for="cat_mecanica"><span class="ico">⚙️</span>Mecânica geral</label>
                        </div>
                        <div class="escolha">
                            <input type="checkbox" id="cat_chaveiro" name="categoria[]" value="chaveiro" <?= in_array('chaveiro', $categoriasSel, true) ? 'checked' : '' ?>>
                            <label for="cat_chaveiro"><span class="ico">🔑</span>Chaveiro veicular</label>
                        </div>
                        <div class="escolha">
                            <input type="checkbox" id="cat_inspecao" name="categoria[]" value="inspecao" <?= in_array('inspecao', $categoriasSel, true) ? 'checked' : '' ?>>
                            <label for="cat_inspecao"><span class="ico">🔍</span>Inspeção preventiva</label>
                        </div>
                    </div>
                    <p class="dica">Marque todas as que você atende.</p>
                </div>

                <div class="campo">
                    <label for="raio">Raio de atendimento</label>
                    <select id="raio" name="raio" required>
                        <option value="5" <?= $raio === 5 ? 'selected' : '' ?>>Até 5 km</option>
                        <option value="10" <?= $raio === 10 ? 'selected' : '' ?>>Até 10 km</option>
                        <option value="15" <?= $raio === 15 ? 'selected' : '' ?>>Até 15 km</option>
                        <option value="20" <?= $raio === 20 ? 'selected' : '' ?>>Até 20 km</option>
                        <option value="30" <?= $raio === 30 ? 'selected' : '' ?>>Até 30 km</option>
                    </select>
                    <p class="dica">Você só recebe chamados dentro dessa distância.</p>
                </div>

                <div class="campo">
                    <label for="bio">Sobre você <span class="opcional">(opcional)</span></label>
                    <textarea id="bio" name="bio" rows="3" maxlength="500"
                              placeholder="Conte sua experiência, anos de profissão, equipamentos..."><?= htmlspecialchars($bio) ?></textarea>
                </div>

                <!-- 4. Fotos -->
                <h2 class="secao-form">Fotos</h2>

                <div class="campo">
                    <label for="foto">Foto de perfil <span class="opcional">(opcional)</span></label>
                    <input type="file" id="foto" name="foto" accept="image/*">
                </div>

                <div class="campo">
                    <label for="portfolio">Portfólio</label>
                    <input type="file" id="portfolio" name="portfolio[]" accept="image/*" multiple required>
                    <p class="dica">Envie pelo menos 1 foto de um serviço seu (oficina, ferramentas ou trabalho feito).</p>
                </div>

                <!-- 5. Acesso -->
                <h2 class="secao-form">Acesso</h2>

                <div class="campo">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" minlength="8" maxlength="72"
                           placeholder="Mínimo 8 caracteres" autocomplete="new-password" required>
                    <p class="dica">Use letras e números.</p>
                </div>

                <div class="campo">
                    <label for="confirma_senha">Confirmar senha</label>
                    <input type="password" id="confirma_senha" name="confirma_senha" minlength="8" maxlength="72"
                           placeholder="Repita a senha" autocomplete="new-password" required>
                </div>

                <div class="check">
                    <input type="checkbox" id="lgpd" name="lgpd" value="1" required>
                    <label for="lgpd">
                        Concordo com o tratamento dos meus dados pessoais (nome, CPF/CNPJ, telefone e
                        localização) para a prestação do serviço, conforme a LGPD.
                    </label>
                </div>

                <button type="submit" class="btn">🛠️ Enviar cadastro</button>
            </form>
        </section>

        <p class="rodape-link">
            Já tem conta? <a href="index.php">Entrar</a>
        </p>
    </main>
</div>
</body>
</html>
