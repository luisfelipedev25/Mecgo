<?php
require_once 'auth.php';
require_once 'conexao.php';

exigirLogin('CLIENTE');

$idCliente = (int) $_SESSION['id_usuario'];
$erro = '';
$sucesso = isset($_GET['msg']) && $_GET['msg'] === 'cancelado'
    ? 'Chamado cancelado com sucesso.'
    : '';

// Pré-seleções vindas de servicos.php ou garagem.php
$veiculoPre = isset($_GET['id_veiculo']) ? (int) $_GET['id_veiculo'] : 0;
$avariaPre = $_GET['avaria'] ?? 'pneu';
if (!in_array($avariaPre, ['pneu', 'bateria', 'guincho', 'pane'], true)) {
    $avariaPre = 'pneu';
}
$descricaoPre = trim($_GET['servico'] ?? '');

// Processa POST (Criar chamado ou Cancelar chamado em andamento)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'criar';

    if ($acao === 'cancelar') {
        $idChamado = (int) ($_POST['id_chamado'] ?? 0);
        if ($idChamado > 0) {
            try {
                $stmtCancel = $pdo->prepare("
                    UPDATE chamado 
                    SET status = 'cancelado' 
                    WHERE id_chamado = ? AND id_cliente = ? AND status IN ('aguardando', 'orcamento_enviado')
                ");
                $stmtCancel->execute([$idChamado, $idCliente]);

                header('Location: solicitar_socorro.php?msg=cancelado');
                exit;
            } catch (PDOException $e) {
                $erro = 'Erro ao cancelar chamado: ' . $e->getMessage();
            }
        }
    } else {
        $idVeiculo = (int) ($_POST['veiculo'] ?? 0);
        $avaria = $_POST['avaria'] ?? 'pneu';
        $descricao = trim($_POST['descricao'] ?? '');
        $latitude = (float) ($_POST['latitude'] ?? -5.04120);
        $longitude = (float) ($_POST['longitude'] ?? -42.80340);

        if ($idVeiculo <= 0) {
            $erro = 'Selecione um veículo da sua garagem antes de solicitar socorro.';
        } elseif (!in_array($avaria, ['pneu', 'bateria', 'guincho', 'pane'], true)) {
            $erro = 'Selecione um tipo de problema válido.';
        } else {
            try {
                // Confirma que o veículo pertence ao cliente logado
                $stmtCheckV = $pdo->prepare('SELECT id_veiculo FROM veiculo WHERE id_veiculo = ? AND id_cliente = ? LIMIT 1');
                $stmtCheckV->execute([$idVeiculo, $idCliente]);

                if (!$stmtCheckV->fetch()) {
                    $erro = 'Veículo inválido para este usuário.';
                } else {
                    $sql = "INSERT INTO chamado (id_cliente, id_veiculo, avaria, descricao, latitude, longitude, status)
                            VALUES (?, ?, ?, ?, ?, ?, 'aguardando')";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $idCliente,
                        $idVeiculo,
                        $avaria,
                        $descricao !== '' ? $descricao : null,
                        $latitude,
                        $longitude
                    ]);

                    $novoIdChamado = $pdo->lastInsertId();

                    header('Location: acompanhar.php?id_chamado=' . $novoIdChamado);
                    exit;
                }
            } catch (PDOException $e) {
                $erro = 'Erro ao registrar pedido de socorro: ' . $e->getMessage();
            }
        }
    }
}

// Busca veículos do cliente
$stmtVeiculos = $pdo->prepare('SELECT id_veiculo, marca, modelo, placa FROM veiculo WHERE id_cliente = ? ORDER BY id_veiculo DESC');
$stmtVeiculos->execute([$idCliente]);
$veiculos = $stmtVeiculos->fetchAll(PDO::FETCH_ASSOC);

// Busca chamados em andamento do cliente
$stmtChamados = $pdo->prepare("
    SELECT c.id_chamado, c.avaria, c.descricao, c.status, c.criado_em, v.marca, v.modelo, v.placa
    FROM chamado c
    INNER JOIN veiculo v ON v.id_veiculo = c.id_veiculo
    WHERE c.id_cliente = ? AND c.status IN ('aguardando', 'orcamento_enviado', 'deslocamento', 'em_atendimento')
    ORDER BY c.id_chamado DESC
");
$stmtChamados->execute([$idCliente]);
$chamadosAtivos = $stmtChamados->fetchAll(PDO::FETCH_ASSOC);

$nomesAvaria = [
    'pneu' => 'Pneu furado',
    'bateria' => 'Bateria',
    'guincho' => 'Guincho',
    'pane' => 'Pane mecânica',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e66927">
    <title>Pedir Socorro | MecGo</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="app">

    <header class="topbar">
        <div class="topbar-linha">
            <div class="brand">Mec<span>Go</span></div>
            <a href="inicio.php" class="sair">‹ Início</a>
        </div>
        <h1>🆘 Pedir socorro</h1>
        <p class="sub">Conte o que aconteceu e envie sua localização.</p>
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

        <!-- Chamados em andamento reais do banco -->
        <?php if (count($chamadosAtivos) > 0): ?>
            <section>
                <h2 class="titulo-secao">Chamados em andamento</h2>
                <?php foreach ($chamadosAtivos as $ch): ?>
                    <article class="card chamado">
                        <div class="chamado-info">
                            <div class="chamado-titulo">
                                <?= htmlspecialchars($nomesAvaria[$ch['avaria']] ?? ucfirst($ch['avaria'])) ?>
                                <span class="badge badge-aguardando"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $ch['status']))) ?></span>
                            </div>
                            <div class="chamado-meta">
                                <?= htmlspecialchars($ch['marca'] . ' ' . $ch['modelo']) ?>
                                <span class="placa mini"><?= htmlspecialchars($ch['placa']) ?></span>
                            </div>
                            <div class="chamado-meta">🕒 <?= date('d/m/Y H:i', strtotime($ch['criado_em'])) ?></div>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <a href="acompanhar.php?id_chamado=<?= (int) $ch['id_chamado'] ?>" class="btn btn-pequeno">Ver</a>
                            <?php if (in_array($ch['status'], ['aguardando', 'orcamento_enviado'], true)): ?>
                                <form action="" method="post" onsubmit="return confirm('Cancelar este chamado?');">
                                    <input type="hidden" name="acao" value="cancelar">
                                    <input type="hidden" name="id_chamado" value="<?= (int) $ch['id_chamado'] ?>">
                                    <button type="submit" class="btn btn-perigo btn-pequeno">Cancelar</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if (count($veiculos) === 0): ?>
            <section class="card" style="text-align:center;">
                <p>Você precisa cadastrar um veículo antes de pedir socorro.</p>
                <a href="garagem.php" class="btn">🚗 Ir para Minha Garagem</a>
            </section>
        <?php else: ?>
            <form action="" method="post">
                <input type="hidden" name="acao" value="criar">
                <input type="hidden" id="latitude" name="latitude" value="-5.04120">
                <input type="hidden" id="longitude" name="longitude" value="-42.80340">

                <!-- Passo 1 -->
                <section class="card">
                    <div class="passo"><span class="passo-num">1</span> Qual veículo?</div>
                    <select id="veiculo" name="veiculo" required>
                        <option value="" disabled>Selecione...</option>
                        <?php foreach ($veiculos as $v): ?>
                            <option value="<?= (int) $v['id_veiculo'] ?>"
                                <?= ($veiculoPre === (int) $v['id_veiculo']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($v['marca'] . ' ' . $v['modelo'] . ' · ' . $v['placa']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </section>

                <!-- Passo 2 -->
                <section class="card">
                    <div class="passo"><span class="passo-num">2</span> Qual o problema?</div>

                    <div class="escolhas">
                        <div class="escolha">
                            <input type="radio" id="av_pneu" name="avaria" value="pneu" <?= $avariaPre === 'pneu' ? 'checked' : '' ?>>
                            <label for="av_pneu"><span class="ico">🛞</span>Pneu furado<small>Troca ou reparo</small></label>
                        </div>
                        <div class="escolha">
                            <input type="radio" id="av_bateria" name="avaria" value="bateria" <?= $avariaPre === 'bateria' ? 'checked' : '' ?>>
                            <label for="av_bateria"><span class="ico">🔋</span>Bateria<small>Carga ou troca</small></label>
                        </div>
                        <div class="escolha">
                            <input type="radio" id="av_guincho" name="avaria" value="guincho" <?= $avariaPre === 'guincho' ? 'checked' : '' ?>>
                            <label for="av_guincho"><span class="ico">🚛</span>Guincho<small>Reboque do veículo</small></label>
                        </div>
                        <div class="escolha">
                            <input type="radio" id="av_pane" name="avaria" value="pane" <?= $avariaPre === 'pane' ? 'checked' : '' ?>>
                            <label for="av_pane"><span class="ico">⚙️</span>Pane mecânica<small>Motor, elétrica etc.</small></label>
                        </div>
                    </div>

                    <div class="campo" style="margin:1rem 0 0;">
                        <label for="descricao">Detalhes <span class="opcional">(opcional)</span></label>
                        <textarea id="descricao" name="descricao" rows="2" maxlength="500"
                                  placeholder="Ex.: estou na faixa da direita, pisca-alerta ligado"><?= htmlspecialchars($descricaoPre) ?></textarea>
                    </div>
                </section>

                <!-- Passo 3 -->
                <section class="card">
                    <div class="passo"><span class="passo-num">3</span> Onde você está?</div>

                    <div class="local-box ok">
                        <div class="ico">📍</div>
                        <p id="local-status">Localização capturada!</p>
                        <div class="local-detalhe" id="local-detalhe">Lat -5.04120 · Lng -42.80340 · Teresina, PI</div>
                        <button type="button" class="btn btn-contorno btn-pequeno" onclick="atualizarGPS()">🎯 Atualizar localização</button>
                    </div>
                </section>

                <!-- Passo 4 -->
                <button type="submit" class="btn">
                    <span class="passo-num" style="background:#fff;color:var(--dark);width:24px;height:24px;flex-basis:24px;">4</span>
                    📤 Solicitar socorro agora
                </button>
            </form>
        <?php endif; ?>
    </main>

    <nav class="nav-inferior cinco" aria-label="Navegação principal">
        <a href="../HTML/perfil.html"><span class="ico">👤</span>Perfil</a>
        <a href="servicos.php" class="ativo"><span class="ico">🔧</span>Serviços</a>
        <a href="inicio.php"><span class="ico">🏠</span>Início</a>
        <a href="../HTML/ofertas.html"><span class="ico">🏷️</span>Ofertas</a>
        <a href="../HTML/notificacoes.html"><span class="ico">🔔</span>Notificações<span class="ponto-notif"></span></a>
    </nav>
</div>

<script>
function atualizarGPS() {
    const statusEl = document.getElementById('local-status');
    const detalheEl = document.getElementById('local-detalhe');
    if (!navigator.geolocation) {
        statusEl.textContent = 'GPS indisponível no navegador (usando posição padrão).';
        return;
    }
    statusEl.textContent = 'Buscando coordenadas...';
    navigator.geolocation.getCurrentPosition(
        function(pos) {
            const lat = pos.coords.latitude.toFixed(5);
            const lng = pos.coords.longitude.toFixed(5);
            const acc = Math.round(pos.coords.accuracy);
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            statusEl.textContent = 'Localização atualizada via GPS!';
            detalheEl.textContent = `Lat ${lat} · Lng ${lng} · precisão ~${acc} m`;
        },
        function() {
            statusEl.textContent = 'Localização padrão mantida.';
        }
    );
}
</script>
</body>
</html>
