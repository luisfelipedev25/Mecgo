<?php
require_once 'auth.php';
require_once 'conexao.php';

exigirLogin('PRESTADOR');

$idPrestador = (int) $_SESSION['id_usuario'];
$erro = '';
$sucesso = '';

if (isset($_GET['msg'])) {
    $msgs = [
        'orcamento_enviado' => 'Orçamento enviado com sucesso! Aguarde a aprovação do motorista.',
        'status_atualizado' => 'Status do atendimento atualizado!'
    ];
    $sucesso = $msgs[$_GET['msg']] ?? '';
}

// Busca perfil e raio do prestador
$stmtPrest = $pdo->prepare('SELECT * FROM prestador WHERE id_prestador = ? LIMIT 1');
$stmtPrest->execute([$idPrestador]);
$dadosPrestador = $stmtPrest->fetch(PDO::FETCH_ASSOC);
$raioKm = (int) ($dadosPrestador['raio_km'] ?? 30);

// Processa ações POST (Enviar orçamento ou Avançar etapa do atendimento)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'enviar_orcamento') {
        $idChamado = (int) ($_POST['id_chamado'] ?? 0);
        $valor = (float) str_replace(',', '.', $_POST['valor'] ?? '0');
        $chegadaMin = (int) ($_POST['chegada'] ?? 15);
        $obs = trim($_POST['obs'] ?? '');

        if ($idChamado <= 0 || $valor <= 0) {
            $erro = 'Informe um valor válido para o orçamento.';
        } else {
            try {
                // Confirma se o chamado ainda está disponível para orçamento
                $stmtCheck = $pdo->prepare("SELECT id_chamado FROM chamado WHERE id_chamado = ? AND status IN ('aguardando', 'orcamento_enviado') LIMIT 1");
                $stmtCheck->execute([$idChamado]);

                if (!$stmtCheck->fetch()) {
                    $erro = 'Este chamado já foi encerrado ou atribuído a outro prestador.';
                } else {
                    $pdo->beginTransaction();

                    $tempoEstimado = $chegadaMin === 60 ? '1 hora' : $chegadaMin . ' min';

                    $stmtOrc = $pdo->prepare("
                        INSERT INTO orcamento (id_chamado, id_prestador, valor, tempo_estimado, observacao, status)
                        VALUES (?, ?, ?, ?, ?, 'pendente')
                    ");
                    $stmtOrc->execute([
                        $idChamado,
                        $idPrestador,
                        $valor,
                        $tempoEstimado,
                        $obs !== '' ? $obs : null
                    ]);

                    // Atualiza status do chamado para orcamento_enviado
                    $pdo->prepare("UPDATE chamado SET status = 'orcamento_enviado' WHERE id_chamado = ? AND status = 'aguardando'")
                        ->execute([$idChamado]);

                    // Notifica no chat do chamado
                    $valorFmt = number_format($valor, 2, ',', '.');
                    $nomePrest = $_SESSION['nome'] ?? 'Prestador';
                    $pdo->prepare("INSERT INTO mensagem (id_chamado, id_remetente, conteudo, tipo) VALUES (?, NULL, ?, 'sistema')")
                        ->execute([$idChamado, "{$nomePrest} enviou um orçamento de R$ {$valorFmt} (chegada em {$tempoEstimado})."]);

                    $pdo->commit();

                    header('Location: painel_chamados.php?msg=orcamento_enviado');
                    exit;
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $erro = 'Erro ao enviar orçamento: ' . $e->getMessage();
            }
        }
    } elseif ($acao === 'avancar_status') {
        $idChamado = (int) ($_POST['id_chamado'] ?? 0);
        $novoStatus = $_POST['novo_status'] ?? '';

        if ($idChamado > 0 && in_array($novoStatus, ['em_atendimento', 'concluido'], true)) {
            try {
                $stmtUpd = $pdo->prepare("UPDATE chamado SET status = ? WHERE id_chamado = ? AND id_prestador = ?");
                $stmtUpd->execute([$novoStatus, $idChamado, $idPrestador]);

                if ($stmtUpd->rowCount() > 0) {
                    $textoMsg = $novoStatus === 'em_atendimento'
                        ? 'O mecânico chegou ao local e iniciou o atendimento.'
                        : 'Serviço marcado como concluído pelo mecânico.';

                    $pdo->prepare("INSERT INTO mensagem (id_chamado, id_remetente, conteudo, tipo) VALUES (?, NULL, ?, 'sistema')")
                        ->execute([$idChamado, $textoMsg]);
                }

                header('Location: painel_chamados.php?msg=status_atualizado');
                exit;
            } catch (PDOException $e) {
                $erro = 'Erro ao atualizar status: ' . $e->getMessage();
            }
        }
    }
}

// 1. Busca novos chamados abertos que este prestador ainda não orçou
$stmtNovos = $pdo->prepare("
    SELECT c.*, v.marca, v.modelo, v.tipo AS veiculo_tipo, v.ano, v.cor, v.placa, u.nome AS cliente_nome
    FROM chamado c
    INNER JOIN veiculo v ON v.id_veiculo = c.id_veiculo
    INNER JOIN usuario u ON u.id_usuario = c.id_cliente
    WHERE c.status IN ('aguardando', 'orcamento_enviado')
      AND NOT EXISTS (
          SELECT 1 FROM orcamento o WHERE o.id_chamado = c.id_chamado AND o.id_prestador = ?
      )
    ORDER BY c.id_chamado DESC
");
$stmtNovos->execute([$idPrestador]);
$novosChamados = $stmtNovos->fetchAll(PDO::FETCH_ASSOC);

// 2. Busca atendimentos deste prestador (orçamentos enviados ou chamados atribuídos a ele)
$stmtMeus = $pdo->prepare("
    SELECT c.id_chamado, c.avaria, c.status AS chamado_status, c.criado_em,
           v.marca, v.modelo, v.placa,
           u.nome AS cliente_nome,
           o.valor, o.tempo_estimado, o.status AS orcamento_status
    FROM orcamento o
    INNER JOIN chamado c ON c.id_chamado = o.id_chamado
    INNER JOIN veiculo v ON v.id_veiculo = c.id_veiculo
    INNER JOIN usuario u ON u.id_usuario = c.id_cliente
    WHERE o.id_prestador = ?
      AND c.status <> 'cancelado'
    ORDER BY c.id_chamado DESC
");
$stmtMeus->execute([$idPrestador]);
$meusAtendimentos = $stmtMeus->fetchAll(PDO::FETCH_ASSOC);

// 3. Média de notas do prestador
$stmtNota = $pdo->prepare('SELECT AVG(nota) AS media, COUNT(*) AS qtd FROM avaliacao WHERE id_avaliado = ?');
$stmtNota->execute([$idPrestador]);
$resNota = $stmtNota->fetch(PDO::FETCH_ASSOC);
$notaFormatada = ($resNota && (int) $resNota['qtd'] > 0)
    ? number_format((float) $resNota['media'], 1, ',', '.') . ' ★'
    : 'Novo';

$totalOrcEnviados = 0;
foreach ($meusAtendimentos as $m) {
    if ($m['orcamento_status'] === 'pendente') {
        $totalOrcEnviados++;
    }
}

$nomesAvaria = [
    'pneu'    => '🛞 Pneu furado',
    'bateria' => '🔋 Bateria',
    'guincho' => '🚛 Guincho',
    'pane'    => '⚙️ Pane mecânica',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e66927">
    <title>Chamados | MecGo Prestador</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="app">

    <header class="topbar">
        <div class="topbar-linha">
            <div class="brand">Mec<span>Go</span></div>
            <a href="logout.php" class="sair">Sair ➜</a>
        </div>
        <h1>🛠️ Chamados próximos</h1>
        <p class="sub">Solicitações de socorro dentro do seu raio de atendimento.</p>
    </header>

    <main class="content">

        <?php if ($sucesso !== ''): ?>
            <div class="alert alert-success" role="status" style="margin-bottom:12px;">
                <?= htmlspecialchars($sucesso) ?>
            </div>
        <?php endif; ?>

        <?php if ($erro !== ''): ?>
            <div style="background:#fdecea;color:#b71c1c;padding:12px;border-radius:10px;margin-bottom:12px;font-size:14px;">
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <!-- Online / Offline -->
        <div class="card status-online">
            <input type="checkbox" id="online" class="sw-input" checked>
            <div class="status-texto">
                <strong class="txt-on">🟢 Você está online</strong>
                <strong class="txt-off">⚪ Você está offline</strong>
                <small class="txt-on">Recebendo chamados em até <?= $raioKm ?> km</small>
                <small class="txt-off">Ative para voltar a receber chamados</small>
            </div>
            <label for="online" class="switch" aria-label="Ficar online ou offline">
                <span class="trilho"></span>
            </label>
        </div>

        <!-- Resumo -->
        <div class="resumo">
            <div><strong><?= count($novosChamados) ?></strong><span>Novos chamados</span></div>
            <div><strong><?= $totalOrcEnviados ?></strong><span>Orçamentos pendentes</span></div>
            <div><strong><?= $notaFormatada ?></strong><span>Sua nota</span></div>
        </div>

        <!-- Novos chamados -->
        <section id="chamados">
            <div class="cabecalho-lista">
                <h2 class="titulo-secao">Novos chamados</h2>
                <span class="contador"><?= count($novosChamados) ?></span>
            </div>

            <?php if (count($novosChamados) === 0): ?>
                <article class="card" style="text-align:center;color:var(--muted);">
                    Nenhum chamado novo no momento. Assim que um motorista solicitar socorro, ele aparecerá aqui!
                </article>
            <?php else: ?>
                <?php foreach ($novosChamados as $ch): ?>
                    <article class="card pedido">
                        <div class="pedido-topo">
                            <div>
                                <div class="pedido-titulo"><?= htmlspecialchars($nomesAvaria[$ch['avaria']] ?? ucfirst($ch['avaria'])) ?></div>
                                <div class="pedido-meta">
                                    <?= htmlspecialchars($ch['marca'] . ' ' . $ch['modelo']) ?> ·
                                    <?= htmlspecialchars($ch['veiculo_tipo']) ?> ·
                                    <?= (int) $ch['ano'] ?> · <?= htmlspecialchars($ch['cor']) ?>
                                </div>
                            </div>
                            <span class="distancia">Chamado #<?= (int) $ch['id_chamado'] ?></span>
                        </div>
                        <div class="pedido-meta">
                            👤 <?= htmlspecialchars($ch['cliente_nome']) ?> · 🕒 <?= date('d/m/Y H:i', strtotime($ch['criado_em'])) ?>
                        </div>

                        <?php if (!empty($ch['descricao'])): ?>
                            <p class="pedido-desc">“<?= htmlspecialchars($ch['descricao']) ?>”</p>
                        <?php endif; ?>

                        <div class="mapa" role="img" aria-label="Localização da ocorrência">
                            <span class="legenda">Lat <?= htmlspecialchars($ch['latitude']) ?> · Lng <?= htmlspecialchars($ch['longitude']) ?></span>
                            <span class="voce"></span>
                            <span class="pino">📍</span>
                        </div>

                        <div class="pedido-acoes">
                            <a href="#orcamento-<?= (int) $ch['id_chamado'] ?>" class="btn" style="width:100%;">💰 Enviar orçamento</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <!-- Meus atendimentos / orçamentos enviados -->
        <section>
            <div class="cabecalho-lista">
                <h2 class="titulo-secao">Meus atendimentos</h2>
                <span class="contador"><?= count($meusAtendimentos) ?></span>
            </div>

            <?php if (count($meusAtendimentos) === 0): ?>
                <article class="card" style="text-align:center;color:var(--muted);">
                    Você ainda não enviou orçamentos nem possui atendimentos ativos.
                </article>
            <?php else: ?>
                <?php foreach ($meusAtendimentos as $at): ?>
                    <article class="card chamado">
                        <div class="chamado-info">
                            <div class="chamado-titulo">
                                <?= htmlspecialchars($nomesAvaria[$at['avaria']] ?? ucfirst($at['avaria'])) ?>
                                <?php if ($at['orcamento_status'] === 'pendente'): ?>
                                    <span class="badge badge-aguardando">Aguardando cliente</span>
                                <?php elseif ($at['orcamento_status'] === 'recusado'): ?>
                                    <span class="badge badge-canc">Orçamento recusado</span>
                                <?php elseif ($at['chamado_status'] === 'deslocamento'): ?>
                                    <span class="badge badge-andamento">Em deslocamento</span>
                                <?php elseif ($at['chamado_status'] === 'em_atendimento'): ?>
                                    <span class="badge badge-andamento">No local</span>
                                <?php elseif ($at['chamado_status'] === 'concluido'): ?>
                                    <span class="badge badge-concl">Concluído</span>
                                <?php endif; ?>
                            </div>
                            <div class="chamado-meta">
                                <?= htmlspecialchars($at['marca'] . ' ' . $at['modelo']) ?> ·
                                <span class="placa mini"><?= htmlspecialchars($at['placa']) ?></span>
                            </div>
                            <div class="chamado-meta">
                                💰 R$ <?= number_format((float) $at['valor'], 2, ',', '.') ?> ·
                                Cliente: <?= htmlspecialchars($at['cliente_nome']) ?>
                            </div>
                        </div>

                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <?php if ($at['orcamento_status'] === 'aceito' && $at['chamado_status'] !== 'concluido'): ?>
                                <a href="chat.php?id_chamado=<?= (int) $at['id_chamado'] ?>" class="btn btn-pequeno">💬 Chat</a>

                                <?php if ($at['chamado_status'] === 'deslocamento'): ?>
                                    <form action="" method="post">
                                        <input type="hidden" name="acao" value="avancar_status">
                                        <input type="hidden" name="id_chamado" value="<?= (int) $at['id_chamado'] ?>">
                                        <input type="hidden" name="novo_status" value="em_atendimento">
                                        <button type="submit" class="btn btn-contorno btn-pequeno">📍 Cheguei</button>
                                    </form>
                                <?php elseif ($at['chamado_status'] === 'em_atendimento'): ?>
                                    <form action="" method="post">
                                        <input type="hidden" name="acao" value="avancar_status">
                                        <input type="hidden" name="id_chamado" value="<?= (int) $at['id_chamado'] ?>">
                                        <input type="hidden" name="novo_status" value="concluido">
                                        <button type="submit" class="btn btn-pequeno">✅ Concluir</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>

    <nav class="nav-inferior" aria-label="Navegação principal">
        <a href="painel_chamados.php" class="ativo"><span class="ico">🛠️</span>Chamados</a>
        <a href="chat.php"><span class="ico">💬</span>Mensagens</a>
        <a href="ganhos.php"><span class="ico">💰</span>Ganhos</a>
        <a href="perfil_prestador.php"><span class="ico">👤</span>Perfil</a>
    </nav>

    <!-- Modais de orçamento gerados dinamicamente para cada chamado novo -->
    <?php foreach ($novosChamados as $ch): ?>
        <div class="modal" id="orcamento-<?= (int) $ch['id_chamado'] ?>" role="dialog" aria-modal="true">
            <a href="#chamados" class="modal-fundo" aria-label="Fechar"></a>
            <div class="modal-box">
                <div class="modal-topo">
                    <div>
                        <h2>Orçamento · <?= htmlspecialchars($nomesAvaria[$ch['avaria']] ?? ucfirst($ch['avaria'])) ?></h2>
                        <p><?= htmlspecialchars($ch['marca'] . ' ' . $ch['modelo']) ?> · Chamado #<?= (int) $ch['id_chamado'] ?></p>
                    </div>
                    <a href="#chamados" class="fechar" aria-label="Fechar">✕</a>
                </div>

                <form action="" method="post">
                    <input type="hidden" name="acao" value="enviar_orcamento">
                    <input type="hidden" name="id_chamado" value="<?= (int) $ch['id_chamado'] ?>">

                    <div class="campo">
                        <label for="valor_<?= (int) $ch['id_chamado'] ?>">Valor do serviço</label>
                        <div class="com-prefixo">
                            <span>R$</span>
                            <input type="number" id="valor_<?= (int) $ch['id_chamado'] ?>" name="valor" min="1" step="0.01"
                                   inputmode="decimal" placeholder="0,00" required>
                        </div>
                    </div>

                    <div class="campo">
                        <label for="chegada_<?= (int) $ch['id_chamado'] ?>">Tempo para chegar ao local</label>
                        <select id="chegada_<?= (int) $ch['id_chamado'] ?>" name="chegada" required>
                            <option value="10">10 minutos</option>
                            <option value="15" selected>15 minutos</option>
                            <option value="20">20 minutos</option>
                            <option value="30">30 minutos</option>
                            <option value="45">45 minutos</option>
                            <option value="60">1 hora</option>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="obs_<?= (int) $ch['id_chamado'] ?>">Observações <span class="opcional">(opcional)</span></label>
                        <textarea id="obs_<?= (int) $ch['id_chamado'] ?>" name="obs" rows="2" maxlength="300"
                                  placeholder="Ex.: levo chave de roda e macaco"></textarea>
                    </div>

                    <p class="dica" style="margin-bottom:1rem;">
                        A taxa da plataforma é de 10% sobre o valor aprovado.
                    </p>

                    <div class="modal-acoes">
                        <a href="#chamados" class="btn btn-contorno">Cancelar</a>
                        <button type="submit" class="btn">📤 Enviar orçamento</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
// Atualiza automaticamente a cada 6s quando o prestador está Online e sem nenhum modal de orçamento aberto
setInterval(function () {
    const swOnline = document.getElementById('online');
    const modalAberto = window.location.hash.startsWith('#orcamento-');
    if (swOnline && swOnline.checked && !modalAberto) {
        window.location.reload();
    }
}, 6000);
</script>
</body>
</html>
