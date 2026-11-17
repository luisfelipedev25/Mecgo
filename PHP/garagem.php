<?php
require_once 'auth.php';
require_once 'conexao.php';

exigirLogin('CLIENTE');

$idCliente = (int) $_SESSION['id_usuario'];
$erro = '';
$sucesso = '';

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'adicionado') {
        $sucesso = 'Veículo adicionado à sua garagem!';
    } elseif ($_GET['msg'] === 'excluido') {
        $sucesso = 'Veículo removido da sua garagem.';
    }
}

// Processa formulários (Adicionar ou Excluir veículo)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'adicionar';

    if ($acao === 'excluir') {
        $idVeiculo = (int) ($_POST['id_veiculo'] ?? 0);

        if ($idVeiculo > 0) {
            try {
                $stmtDel = $pdo->prepare('DELETE FROM veiculo WHERE id_veiculo = ? AND id_cliente = ?');
                $stmtDel->execute([$idVeiculo, $idCliente]);

                header('Location: garagem.php?msg=excluido');
                exit;
            } catch (PDOException $e) {
                $erro = 'Não foi possível excluir este veículo (ele pode estar vinculado a um chamado).';
            }
        }
    } else {
        $tipo = ($_POST['tipo'] ?? 'Carro') === 'Moto' ? 'Moto' : 'Carro';
        $placa = strtoupper(trim($_POST['placa'] ?? ''));
        $marca = trim($_POST['marca'] ?? '');
        $modelo = trim($_POST['modelo'] ?? '');
        $ano = (int) ($_POST['ano'] ?? 0);
        $cor = trim($_POST['cor'] ?? '');

        if ($placa === '' || $marca === '' || $modelo === '' || $ano < 1950 || $ano > 2027 || $cor === '') {
            $erro = 'Preencha todos os dados do veículo corretamente.';
        } elseif (strlen($placa) > 8) {
            $erro = 'A placa deve ter no máximo 8 caracteres.';
        } else {
            try {
                $sql = 'INSERT INTO veiculo (id_cliente, tipo, placa, marca, modelo, ano, cor)
                        VALUES (?, ?, ?, ?, ?, ?, ?)';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$idCliente, $tipo, $placa, $marca, $modelo, $ano, $cor]);

                header('Location: garagem.php?msg=adicionado');
                exit;
            } catch (PDOException $e) {
                $erro = 'Erro ao salvar veículo: ' . $e->getMessage();
            }
        }
    }
}

// Busca os veículos reais do cliente logado
$stmtLista = $pdo->prepare('SELECT * FROM veiculo WHERE id_cliente = ? ORDER BY id_veiculo DESC');
$stmtLista->execute([$idCliente]);
$veiculos = $stmtLista->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e66927">
    <title>Minha Garagem | MecGo</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="app">

    <header class="topbar">
        <div class="topbar-linha">
            <div class="brand">Mec<span>Go</span></div>
            <a href="perfil.php" class="sair">‹ Perfil</a>
        </div>
        <h1>🚗 Minha Garagem</h1>
        <p class="sub">Cadastre seus veículos para pedir socorro mais rápido.</p>
    </header>

    <main class="content">

        <?php if ($sucesso !== ''): ?>
            <div class="alert alert-success" role="status">
                <?= htmlspecialchars($sucesso) ?>
            </div>
        <?php endif; ?>

        <?php if ($erro !== ''): ?>
            <div style="background:#fdecea;color:#b71c1c;padding:12px;border-radius:10px;margin-bottom:14px;font-size:14px;">
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <!-- Formulário de cadastro (abre automaticamente se ainda não tiver veículos) -->
        <details class="card" <?= count($veiculos) === 0 ? 'open' : '' ?>>
            <summary>
                <span>➕ Adicionar veículo</span>
                <span class="seta">▾</span>
            </summary>

            <form class="conteudo-details" action="" method="post">
                <input type="hidden" name="acao" value="adicionar">

                <div class="campo">
                    <span class="rotulo">Tipo</span>
                    <div class="escolhas">
                        <div class="escolha">
                            <input type="radio" id="tipoCarro" name="tipo" value="Carro" checked>
                            <label for="tipoCarro"><span class="ico">🚗</span>Carro</label>
                        </div>
                        <div class="escolha">
                            <input type="radio" id="tipoMoto" name="tipo" value="Moto">
                            <label for="tipoMoto"><span class="ico">🏍️</span>Moto</label>
                        </div>
                    </div>
                </div>

                <div class="campo">
                    <label for="placa">Placa</label>
                    <input type="text" id="placa" name="placa" class="input-placa"
                           maxlength="8" placeholder="ABC1D23" autocapitalize="characters" required>
                </div>

                <div class="grade-7-5 campo">
                    <div>
                        <label for="marca">Marca</label>
                        <input type="text" id="marca" name="marca" maxlength="50" placeholder="Ex.: Fiat" required>
                    </div>
                    <div>
                        <label for="ano">Ano</label>
                        <input type="number" id="ano" name="ano" min="1950" max="2027" placeholder="2026" required>
                    </div>
                </div>

                <div class="campo">
                    <label for="modelo">Modelo</label>
                    <input type="text" id="modelo" name="modelo" maxlength="80" placeholder="Ex.: Uno Mille" required>
                </div>

                <div class="campo">
                    <label for="cor">Cor</label>
                    <select id="cor" name="cor" required>
                        <option value="" disabled selected>Selecione...</option>
                        <option>Branco</option>
                        <option>Preto</option>
                        <option>Prata</option>
                        <option>Cinza</option>
                        <option>Vermelho</option>
                        <option>Azul</option>
                        <option>Verde</option>
                        <option>Amarelo</option>
                        <option>Laranja</option>
                        <option>Marrom</option>
                        <option>Bege</option>
                        <option>Outra</option>
                    </select>
                </div>

                <button type="submit" class="btn">✔ Salvar veículo</button>
            </form>
        </details>

        <!-- Listagem dinâmica do banco -->
        <section>
            <div class="cabecalho-lista">
                <h2 class="titulo-secao">Meus veículos</h2>
                <span class="contador"><?= count($veiculos) ?></span>
            </div>

            <?php if (count($veiculos) === 0): ?>
                <article class="card" style="text-align:center;color:var(--muted);">
                    Você ainda não tem veículos cadastrados. Adicione seu primeiro carro ou moto acima!
                </article>
            <?php else: ?>
                <?php foreach ($veiculos as $v): ?>
                    <article class="card veiculo">
                        <div class="veiculo-icone" aria-hidden="true">
                            <?= $v['tipo'] === 'Moto' ? '🏍️' : '🚗' ?>
                        </div>
                        <div class="veiculo-info">
                            <div class="veiculo-nome">
                                <?= htmlspecialchars($v['marca'] . ' ' . $v['modelo']) ?>
                            </div>
                            <div class="veiculo-detalhe">
                                <?= htmlspecialchars($v['tipo']) ?> · <?= (int) $v['ano'] ?> · <?= htmlspecialchars($v['cor']) ?>
                            </div>
                            <span class="placa"><?= htmlspecialchars($v['placa']) ?></span>
                        </div>
                        <div class="veiculo-acoes">
                            <a href="solicitar_socorro.php?id_veiculo=<?= (int) $v['id_veiculo'] ?>"
                               class="btn btn-icone" title="Pedir socorro" aria-label="Pedir socorro">🛠️</a>

                            <form action="" method="post" style="display:inline;"
                                  onsubmit="return confirm('Deseja remover este veículo da garagem?');">
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id_veiculo" value="<?= (int) $v['id_veiculo'] ?>">
                                <button type="submit" class="btn btn-perigo btn-icone"
                                        title="Excluir" aria-label="Excluir veículo">🗑️</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>

    <nav class="nav-inferior cinco" aria-label="Navegação principal">
        <a href="perfil.php" class="ativo"><span class="ico">👤</span>Perfil</a>
        <a href="servicos.php"><span class="ico">🔧</span>Serviços</a>
        <a href="inicio.php"><span class="ico">🏠</span>Início</a>
        <a href="acompanhar.php"><span class="ico">📍</span>Acompanhar</a>
        <a href="../HTML/notificacoes.html"><span class="ico">🔔</span>Notificações<span class="ponto-notif"></span></a>
    </nav>
</div>
</body>
</html>
