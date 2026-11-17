<?php
require_once 'auth.php';
require_once 'conexao.php';

exigirLogin('CLIENTE');

$idCliente = (int) $_SESSION['id_usuario'];
$erro = '';
$sucesso = '';

if (isset($_GET['msg'])) {
    $mensagensFlash = [
        'orcamento_aceito'   => 'Orçamento aceito! O mecânico já foi notificado e está a caminho.',
        'orcamento_recusado' => 'Orçamento recusado.',
        'pago'               => 'Pagamento confirmado e retido com segurança até a conclusão!',
        'avaliado'           => 'Avaliação enviada! Obrigado por usar o MecGo.',
        'cancelado'          => 'Chamado cancelado.'
    ];
    $sucesso = $mensagensFlash[$_GET['msg']] ?? '';
}

// Identifica qual chamado acompanhar (via GET ou o mais recente do cliente)
$idChamado = isset($_GET['id_chamado']) ? (int) $_GET['id_chamado'] : 0;

if ($idChamado <= 0) {
    $stmtUltimo = $pdo->prepare('SELECT id_chamado FROM chamado WHERE id_cliente = ? ORDER BY id_chamado DESC LIMIT 1');
    $stmtUltimo->execute([$idCliente]);
    $idChamado = (int) $stmtUltimo->fetchColumn();
}

// Processa ações POST (aceitar/recusar orçamento, enviar mensagem, pagar, avaliar, cancelar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $idChamado > 0) {
    $acao = $_POST['acao'] ?? '';

    try {
        // Confirma propriedade do chamado
        $stmtDono = $pdo->prepare('SELECT * FROM chamado WHERE id_chamado = ? AND id_cliente = ? LIMIT 1');
        $stmtDono->execute([$idChamado, $idCliente]);
        $chamadoAtual = $stmtDono->fetch(PDO::FETCH_ASSOC);

        if (!$chamadoAtual) {
            $erro = 'Chamado não encontrado.';
        } elseif ($acao === 'aceitar_orcamento') {
            $idOrcamento = (int) ($_POST['id_orcamento'] ?? 0);

            $stmtOrc = $pdo->prepare("SELECT * FROM orcamento WHERE id_orcamento = ? AND id_chamado = ? AND status = 'pendente' LIMIT 1");
            $stmtOrc->execute([$idOrcamento, $idChamado]);
            $orc = $stmtOrc->fetch(PDO::FETCH_ASSOC);

            if ($orc) {
                $pdo->beginTransaction();

                // Aceita o orçamento escolhido e recusa outros pendentes do mesmo chamado
                $pdo->prepare("UPDATE orcamento SET status = 'aceito' WHERE id_orcamento = ?")->execute([$idOrcamento]);
                $pdo->prepare("UPDATE orcamento SET status = 'recusado' WHERE id_chamado = ? AND id_orcamento <> ? AND status = 'pendente'")
                    ->execute([$idChamado, $idOrcamento]);

                // Atribui o prestador ao chamado e coloca em deslocamento
                $pdo->prepare("UPDATE chamado SET id_prestador = ?, status = 'deslocamento' WHERE id_chamado = ?")
                    ->execute([(int) $orc['id_prestador'], $idChamado]);

                // Registra aviso automático no chat
                $valorFmt = number_format((float) $orc['valor'], 2, ',', '.');
                $pdo->prepare("INSERT INTO mensagem (id_chamado, id_remetente, conteudo, tipo) VALUES (?, NULL, ?, 'sistema')")
                    ->execute([$idChamado, "Orçamento de R$ {$valorFmt} aceito pelo motorista. Prestador em deslocamento."]);

                $pdo->commit();
                header("Location: acompanhar.php?id_chamado={$idChamado}&msg=orcamento_aceito");
                exit;
            }
        } elseif ($acao === 'recusar_orcamento') {
            $idOrcamento = (int) ($_POST['id_orcamento'] ?? 0);
            $pdo->prepare("UPDATE orcamento SET status = 'recusado' WHERE id_orcamento = ? AND id_chamado = ? AND status = 'pendente'")
                ->execute([$idOrcamento, $idChamado]);

            header("Location: acompanhar.php?id_chamado={$idChamado}&msg=orcamento_recusado");
            exit;
        } elseif ($acao === 'enviar_mensagem') {
            $conteudo = trim($_POST['conteudo'] ?? '');
            if ($conteudo !== '') {
                $pdo->prepare("INSERT INTO mensagem (id_chamado, id_remetente, conteudo, tipo) VALUES (?, ?, ?, 'texto')")
                    ->execute([$idChamado, $idCliente, $conteudo]);
            }
            header("Location: acompanhar.php?id_chamado={$idChamado}#chat");
            exit;
        } elseif ($acao === 'pagar') {
            $metodo = ($_POST['metodo'] ?? 'PIX') === 'CARTAO' ? 'CARTAO' : 'PIX';

            $stmtOrcAceito = $pdo->prepare("SELECT * FROM orcamento WHERE id_chamado = ? AND status = 'aceito' LIMIT 1");
            $stmtOrcAceito->execute([$idChamado]);
            $orcAceito = $stmtOrcAceito->fetch(PDO::FETCH_ASSOC);

            if ($orcAceito) {
                $valorTotal = (float) $orcAceito['valor'];
                $taxaMecgo = round($valorTotal * 0.10, 2); // 10% de taxa da plataforma
                $valorPrestador = round($valorTotal - $taxaMecgo, 2);

                $stmtJaPago = $pdo->prepare('SELECT id_pagamento FROM pagamento WHERE id_chamado = ? LIMIT 1');
                $stmtJaPago->execute([$idChamado]);

                if (!$stmtJaPago->fetch()) {
                    $pdo->prepare("
                        INSERT INTO pagamento (id_chamado, id_orcamento, valor_total, taxa_mecgo, valor_prestador, metodo, status)
                        VALUES (?, ?, ?, ?, ?, ?, 'retido_custodia')
                    ")->execute([$idChamado, (int) $orcAceito['id_orcamento'], $valorTotal, $taxaMecgo, $valorPrestador, $metodo]);
                }

                header("Location: acompanhar.php?id_chamado={$idChamado}&msg=pago");
                exit;
            }
        } elseif ($acao === 'avaliar') {
            $nota = (int) ($_POST['nota'] ?? 5);
            $comentario = trim($_POST['comentario'] ?? '');
            $idPrestadorAvaliado = (int) ($chamadoAtual['id_prestador'] ?? 0);

            if ($idPrestadorAvaliado > 0 && $nota >= 1 && $nota <= 5) {
                $stmtJaAvaliou = $pdo->prepare('SELECT id_avaliacao FROM avaliacao WHERE id_chamado = ? AND id_avaliador = ? LIMIT 1');
                $stmtJaAvaliou->execute([$idChamado, $idCliente]);

                if (!$stmtJaAvaliou->fetch()) {
                    $pdo->prepare('
                        INSERT INTO avaliacao (id_chamado, id_avaliador, id_avaliado, nota, comentario)
                        VALUES (?, ?, ?, ?, ?)
                    ')->execute([$idChamado, $idCliente, $idPrestadorAvaliado, $nota, $comentario !== '' ? $comentario : null]);
                }

                header("Location: acompanhar.php?id_chamado={$idChamado}&msg=avaliado");
                exit;
            }
        } elseif ($acao === 'cancelar_chamado') {
            if (in_array($chamadoAtual['status'], ['aguardando', 'orcamento_enviado'], true)) {
                $pdo->prepare("UPDATE chamado SET status = 'cancelado' WHERE id_chamado = ? AND id_cliente = ?")
                    ->execute([$idChamado, $idCliente]);

                header("Location: acompanhar.php?id_chamado={$idChamado}&msg=cancelado");
                exit;
            }
        }
    } catch (PDOException $e) {
        $erro = 'Erro ao processar solicitação: ' . $e->getMessage();
    }
}

// Carrega dados completos do chamado
$chamado = null;
$orcamentos = [];
$orcamentoAceito = null;
$mensagens = [];
$pagamento = null;
$avaliacao = null;
$notaMediaPrestador = null;
$totalAvaliacoesPrestador = 0;

if ($idChamado > 0) {
    $stmtCh = $pdo->prepare("
        SELECT c.*,
               v.marca, v.modelo, v.placa, v.cor,
               u.nome AS prestador_nome, u.telefone AS prestador_telefone
        FROM chamado c
        INNER JOIN veiculo v ON v.id_veiculo = c.id_veiculo
        LEFT JOIN usuario u ON u.id_usuario = c.id_prestador
        WHERE c.id_chamado = ? AND c.id_cliente = ?
        LIMIT 1
    ");
    $stmtCh->execute([$idChamado, $idCliente]);
    $chamado = $stmtCh->fetch(PDO::FETCH_ASSOC);

    if ($chamado) {
        // Orçamentos do chamado
        $stmtOrcs = $pdo->prepare("
            SELECT o.*, u.nome AS prestador_nome
            FROM orcamento o
            INNER JOIN usuario u ON u.id_usuario = o.id_prestador
            WHERE o.id_chamado = ?
            ORDER BY o.id_orcamento DESC
        ");
        $stmtOrcs->execute([$idChamado]);
        $orcamentos = $stmtOrcs->fetchAll(PDO::FETCH_ASSOC);

        foreach ($orcamentos as $o) {
            if ($o['status'] === 'aceito') {
                $orcamentoAceito = $o;
                break;
            }
        }

        // Média de notas do prestador atribuído
        if (!empty($chamado['id_prestador'])) {
            $stmtNota = $pdo->prepare('SELECT AVG(nota) AS media, COUNT(*) AS qtd FROM avaliacao WHERE id_avaliado = ?');
            $stmtNota->execute([(int) $chamado['id_prestador']]);
            $resNota = $stmtNota->fetch(PDO::FETCH_ASSOC);
            if ($resNota && (int) $resNota['qtd'] > 0) {
                $notaMediaPrestador = number_format((float) $resNota['media'], 1);
                $totalAvaliacoesPrestador = (int) $resNota['qtd'];
            }
        }

        // Mensagens do chat
        $stmtMsgs = $pdo->prepare("
            SELECT m.*, u.nome AS remetente_nome
            FROM mensagem m
            LEFT JOIN usuario u ON u.id_usuario = m.id_remetente
            WHERE m.id_chamado = ?
            ORDER BY m.id_mensagem ASC
        ");
        $stmtMsgs->execute([$idChamado]);
        $mensagens = $stmtMsgs->fetchAll(PDO::FETCH_ASSOC);

        // Pagamento
        $stmtPag = $pdo->prepare('SELECT * FROM pagamento WHERE id_chamado = ? LIMIT 1');
        $stmtPag->execute([$idChamado]);
        $pagamento = $stmtPag->fetch(PDO::FETCH_ASSOC);

        // Avaliação do cliente
        $stmtAv = $pdo->prepare('SELECT * FROM avaliacao WHERE id_chamado = ? AND id_avaliador = ? LIMIT 1');
        $stmtAv->execute([$idChamado, $idCliente]);
        $avaliacao = $stmtAv->fetch(PDO::FETCH_ASSOC);
    }
}

$nomesAvaria = [
    'pneu'    => 'Pneu furado',
    'bateria' => 'Bateria',
    'guincho' => 'Guincho',
    'pane'    => 'Pane mecânica',
];

$statusAtual = $chamado['status'] ?? 'aguardando';
$emAndamento = in_array($statusAtual, ['aguardando', 'orcamento_enviado', 'deslocamento', 'em_atendimento'], true);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e66927">
    <title>Acompanhar | MecGo</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="app mapa-tela">

    <?php if (!$chamado): ?>
        <header class="topbar">
            <div class="topbar-linha">
                <div class="brand">Mec<span>Go</span></div>
                <a href="inicio.php" class="sair">‹ Início</a>
            </div>
            <h1>📍 Acompanhar chamado</h1>
        </header>
        <main class="content">
            <section class="card" style="text-align:center;">
                <p>Você ainda não possui nenhum chamado registrado.</p>
                <a href="servicos.php" class="btn">🆘 Pedir socorro agora</a>
            </section>
        </main>
    <?php else: ?>

        <!-- Sincroniza os radios de estágio visual com o status real do banco -->
        <input type="radio" name="est" id="es1" class="est-input es1" <?= in_array($statusAtual, ['aguardando', 'orcamento_enviado'], true) ? 'checked' : '' ?>>
        <input type="radio" name="est" id="es2" class="est-input es2" <?= $statusAtual === 'deslocamento' ? 'checked' : '' ?>>
        <input type="radio" name="est" id="es3" class="est-input es3">
        <input type="radio" name="est" id="es4" class="est-input es4" <?= in_array($statusAtual, ['em_atendimento', 'concluido'], true) ? 'checked' : '' ?>>

        <div class="mapa-app" role="img" aria-label="Mapa ilustrativo mostrando o caminho do mecânico até você">
            <a href="inicio.php" class="voltar-flutuante">‹ Início</a>
            <div class="sim" aria-label="Chamado">Chamado #<?= (int) $chamado['id_chamado'] ?></div>

            <div class="cena larga">
                <span class="trajeto feito" style="left:40px;top:60px;width:107px;transform:rotate(27.6deg)"></span>
                <span class="trajeto <?= in_array($statusAtual, ['deslocamento', 'em_atendimento', 'concluido'], true) ? 'feito' : '' ?>" style="left:134px;top:110px;width:83px;transform:rotate(27.6deg)"></span>
                <span class="trajeto <?= in_array($statusAtual, ['em_atendimento', 'concluido'], true) ? 'feito' : '' ?>" style="left:208px;top:148px;width:47px;transform:rotate(27.6deg)"></span>
                <span class="destino-pt" style="left:241px;top:161px"></span>
                <span class="voce-tag" style="left:215px;top:84px">Você</span>
                <span class="pino grande mec"><span>🔧</span></span>
                <span class="chegada est e1" style="left:60px;top:150px">Buscando<br><strong>mecânico</strong></span>
                <span class="chegada est e2" style="left:60px;top:150px">Chegada em<br><strong><?= htmlspecialchars($orcamentoAceito['tempo_estimado'] ?? '12 min') ?></strong></span>
                <span class="chegada est e4" style="left:60px;top:150px">Mecânico<br><strong>no local</strong></span>
            </div>

            <section class="folha" style="max-height:74%;overflow-y:auto;padding-bottom:5.5rem;">

                <?php if ($sucesso !== ''): ?>
                    <div class="alert alert-success" role="status" style="margin-bottom:10px;">
                        <?= htmlspecialchars($sucesso) ?>
                    </div>
                <?php endif; ?>

                <?php if ($erro !== ''): ?>
                    <div style="background:#fdecea;color:#b71c1c;padding:10px;border-radius:10px;margin-bottom:10px;font-size:13px;">
                        <?= htmlspecialchars($erro) ?>
                    </div>
                <?php endif; ?>

                <!-- Cabeçalho de Status Dinâmico -->
                <?php if ($statusAtual === 'aguardando'): ?>
                    <p class="status-grande">⏳ Buscando mecânicos próximos...</p>
                    <p class="dica" style="margin:0 0 .6rem">Seu chamado está visível para os prestadores online. Aguarde o envio de orçamentos.</p>
                <?php elseif ($statusAtual === 'orcamento_enviado'): ?>
                    <p class="status-grande">💰 Orçamento recebido!</p>
                    <p class="dica" style="margin:0 0 .6rem">Confira a proposta abaixo e aceite para o mecânico iniciar o deslocamento.</p>
                <?php elseif ($statusAtual === 'deslocamento'): ?>
                    <p class="status-grande">🚗 Mecânico a caminho · <?= htmlspecialchars($orcamentoAceito['tempo_estimado'] ?? '12 min') ?></p>
                    <p class="dica" style="margin:0 0 .6rem">Fique com o telefone por perto e em local visível.</p>
                <?php elseif ($statusAtual === 'em_atendimento'): ?>
                    <p class="status-grande verde">🛠️ Em atendimento no local!</p>
                    <p class="dica" style="margin:0 0 .6rem">O prestador já chegou e está realizando o serviço.</p>
                <?php elseif ($statusAtual === 'concluido'): ?>
                    <p class="status-grande verde">✅ Serviço concluído!</p>
                    <p class="dica" style="margin:0 0 .6rem">Confirme o pagamento e avalie o atendimento abaixo.</p>
                <?php elseif ($statusAtual === 'cancelado'): ?>
                    <p class="status-grande" style="color:#b71c1c;">❌ Chamado cancelado</p>
                    <p class="dica" style="margin:0 0 .6rem">Este chamado foi encerrado sem atendimento.</p>
                <?php endif; ?>

                <div class="prog" role="img" aria-label="Progresso do atendimento"><i></i></div>
                <div class="prog-leg"><span>Aberto</span><span>Orçamento</span><span>A caminho</span><span>Concluído</span></div>

                <!-- Resumo do veículo e problema -->
                <div style="background:#f8f8f8;padding:10px 12px;border-radius:10px;margin:10px 0;font-size:.88rem;">
                    <strong><?= htmlspecialchars($nomesAvaria[$chamado['avaria']] ?? ucfirst($chamado['avaria'])) ?></strong> ·
                    <?= htmlspecialchars($chamado['marca'] . ' ' . $chamado['modelo']) ?>
                    <span class="placa mini"><?= htmlspecialchars($chamado['placa']) ?></span>
                    <?php if (!empty($chamado['descricao'])): ?>
                        <div style="color:var(--muted);margin-top:4px;">“<?= htmlspecialchars($chamado['descricao']) ?>”</div>
                    <?php endif; ?>
                </div>

                <!-- Dados do prestador (quando já aceito) -->
                <?php if (!empty($chamado['prestador_nome'])): ?>
                    <div class="prest-folha">
                        <div class="foto" aria-hidden="true" style="flex-basis:48px;height:48px;font-size:1.4rem">🛠️</div>
                        <div>
                            <h2 style="font-size:1.05rem;margin:0"><?= htmlspecialchars($chamado['prestador_nome']) ?></h2>
                            <div class="nota-linha">
                                <?php if ($notaMediaPrestador !== null): ?>
                                    <strong><?= $notaMediaPrestador ?></strong> <span class="estrela">★</span> (<?= $totalAvaliacoesPrestador ?>) ·
                                <?php else: ?>
                                    Novo prestador ·
                                <?php endif; ?>
                                <?= htmlspecialchars($chamado['prestador_telefone'] ?? '') ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Lista de Orçamentos Recebidos -->
                <?php if (count($orcamentos) > 0): ?>
                    <div style="margin-top:12px;">
                        <h3 style="font-size:.95rem;margin:0 0 8px;">💰 Orçamentos</h3>
                        <?php foreach ($orcamentos as $o): ?>
                            <div style="border:1px solid #e5e5e5;border-radius:12px;padding:10px 12px;margin-bottom:8px;background:#fff;">
                                <div style="display:flex;justify-content:space-between;align-items:center;">
                                    <strong><?= htmlspecialchars($o['prestador_nome']) ?></strong>
                                    <span style="font-weight:700;color:var(--primary);font-size:1.05rem;">
                                        R$ <?= number_format((float) $o['valor'], 2, ',', '.') ?>
                                    </span>
                                </div>
                                <div style="font-size:.82rem;color:var(--muted);margin:4px 0;">
                                    Tempo estimado: <strong><?= htmlspecialchars($o['tempo_estimado'] ?: 'A combinar') ?></strong>
                                    <?php if (!empty($o['observacao'])): ?>
                                        <br><?= htmlspecialchars($o['observacao']) ?>
                                    <?php endif; ?>
                                </div>

                                <?php if ($o['status'] === 'pendente' && $emAndamento): ?>
                                    <div style="display:flex;gap:8px;margin-top:8px;">
                                        <form action="" method="post" style="flex:1;">
                                            <input type="hidden" name="acao" value="aceitar_orcamento">
                                            <input type="hidden" name="id_orcamento" value="<?= (int) $o['id_orcamento'] ?>">
                                            <button type="submit" class="btn btn-pequeno" style="width:100%;">✔ Aceitar</button>
                                        </form>
                                        <form action="" method="post">
                                            <input type="hidden" name="acao" value="recusar_orcamento">
                                            <input type="hidden" name="id_orcamento" value="<?= (int) $o['id_orcamento'] ?>">
                                            <button type="submit" class="btn btn-perigo btn-pequeno">Recusar</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="badge" style="margin-top:4px;display:inline-block;">
                                        Status: <?= htmlspecialchars(ucfirst($o['status'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Pagamento (disponível após aceitar orçamento ou concluir serviço) -->
                <?php if ($orcamentoAceito && in_array($statusAtual, ['deslocamento', 'em_atendimento', 'concluido'], true)): ?>
                    <div style="margin-top:12px;border:1px solid #e5e5e5;border-radius:12px;padding:12px;background:#fff;">
                        <h3 style="font-size:.95rem;margin:0 0 6px;">💳 Pagamento</h3>
                        <?php if ($pagamento): ?>
                            <p style="margin:0;font-size:.88rem;color:#1b5e20;">
                                ✔ Pago via <strong><?= htmlspecialchars($pagamento['metodo']) ?></strong> ·
                                R$ <?= number_format((float) $pagamento['valor_total'], 2, ',', '.') ?>
                                (<?= htmlspecialchars(str_replace('_', ' ', $pagamento['status'])) ?>)
                            </p>
                        <?php else: ?>
                            <p style="margin:0 0 8px;font-size:.85rem;color:var(--muted);">
                                Valor aprovado: <strong>R$ <?= number_format((float) $orcamentoAceito['valor'], 2, ',', '.') ?></strong>
                            </p>
                            <form action="" method="post" style="display:flex;gap:8px;align-items:center;">
                                <input type="hidden" name="acao" value="pagar">
                                <select name="metodo" style="flex:1;padding:8px;border-radius:8px;border:1px solid #ccc;">
                                    <option value="PIX">PIX</option>
                                    <option value="CARTAO">Cartão</option>
                                </select>
                                <button type="submit" class="btn btn-pequeno">Pagar agora</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Avaliação (quando concluído) -->
                <?php if ($statusAtual === 'concluido' && !empty($chamado['id_prestador'])): ?>
                    <div style="margin-top:12px;border:1px solid #e5e5e5;border-radius:12px;padding:12px;background:#fff;">
                        <h3 style="font-size:.95rem;margin:0 0 6px;">⭐ Avaliar atendimento</h3>
                        <?php if ($avaliacao): ?>
                            <p style="margin:0;font-size:.88rem;color:#1b5e20;">
                                Você avaliou com <strong><?= (int) $avaliacao['nota'] ?>/5 ★</strong>
                                <?= !empty($avaliacao['comentario']) ? '— “' . htmlspecialchars($avaliacao['comentario']) . '”' : '' ?>
                            </p>
                        <?php else: ?>
                            <form action="" method="post">
                                <input type="hidden" name="acao" value="avaliar">
                                <div class="campo" style="margin-bottom:8px;">
                                    <label for="nota">Nota (1 a 5)</label>
                                    <select id="nota" name="nota" required>
                                        <option value="5" selected>5 - Excelente</option>
                                        <option value="4">4 - Muito bom</option>
                                        <option value="3">3 - Regular</option>
                                        <option value="2">2 - Ruim</option>
                                        <option value="1">1 - Péssimo</option>
                                    </select>
                                </div>
                                <div class="campo" style="margin-bottom:8px;">
                                    <input type="text" name="comentario" maxlength="300" placeholder="Comentário opcional sobre o serviço...">
                                </div>
                                <button type="submit" class="btn btn-pequeno" style="width:100%;">Enviar avaliação</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Chat integrado com o Prestador -->
                <div id="chat" style="margin-top:12px;border:1px solid #e5e5e5;border-radius:12px;padding:12px;background:#fff;">
                    <h3 style="font-size:.95rem;margin:0 0 8px;">💬 Mensagens do chamado</h3>
                    <div style="max-height:150px;overflow-y:auto;display:flex;flex-direction:column;gap:6px;margin-bottom:8px;">
                        <?php if (count($mensagens) === 0): ?>
                            <p style="font-size:.82rem;color:var(--muted);margin:0;">Nenhuma mensagem ainda. Use o campo abaixo para falar com o mecânico.</p>
                        <?php else: ?>
                            <?php foreach ($mensagens as $m): ?>
                                <?php $minha = ((int) $m['id_remetente'] === $idCliente); ?>
                                <div style="padding:6px 10px;border-radius:10px;font-size:.84rem;max-width:85%;
                                    <?= $m['tipo'] === 'sistema'
                                        ? 'background:#f1f1f1;color:#555;align-self:center;text-align:center;'
                                        : ($minha ? 'background:#ffe8db;color:#222;align-self:flex-end;' : 'background:#eef2f7;color:#222;align-self:flex-start;') ?>">
                                    <?php if ($m['tipo'] !== 'sistema'): ?>
                                        <strong style="font-size:.75rem;display:block;"><?= $minha ? 'Você' : htmlspecialchars($m['remetente_nome'] ?? 'Mecânico') ?></strong>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($m['conteudo']) ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($emAndamento): ?>
                        <form action="" method="post" style="display:flex;gap:6px;">
                            <input type="hidden" name="acao" value="enviar_mensagem">
                            <input type="text" id="input-chat" name="conteudo" maxlength="500" placeholder="Digite uma mensagem..." style="flex:1;" required>
                            <button type="submit" class="btn btn-pequeno">Enviar</button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Botão de cancelar (apenas enquanto aguardando ou com orçamento pendente) -->
                <?php if (in_array($statusAtual, ['aguardando', 'orcamento_enviado'], true)): ?>
                    <form action="" method="post" style="margin-top:12px;" onsubmit="return confirm('Tem certeza que deseja cancelar este chamado?');">
                        <input type="hidden" name="acao" value="cancelar_chamado">
                        <button type="submit" class="btn btn-perigo" style="width:100%;">Cancelar chamado</button>
                    </form>
                <?php endif; ?>
            </section>
        </div>

    <?php endif; ?>

    <nav class="nav-inferior" aria-label="Navegação principal">
        <a href="perfil.php"><span class="ico">👤</span>Perfil</a>
        <a href="servicos.php"><span class="ico">🔧</span>Serviços</a>
        <a href="inicio.php"><span class="ico">🏠</span>Início</a>
        <a href="notificacoes.php"><span class="ico">🔔</span>Notificações<span class="ponto-notif"></span></a>
    </nav>
</div>

<?php if ($chamado && $emAndamento): ?>
<script>
// Atualiza automaticamente a cada 6s para receber orçamentos/mudanças de status do prestador em tempo real,
// pausando se o usuário estiver digitando no chat.
setInterval(function () {
    const campoChat = document.getElementById('input-chat');
    if (campoChat && (document.activeElement === campoChat || campoChat.value.trim() !== '')) {
        return;
    }
    window.location.reload();
}, 6000);
</script>
<?php endif; ?>
</body>
</html>
