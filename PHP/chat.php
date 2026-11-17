<?php
require_once 'auth.php';
require_once 'conexao.php';

exigirLogin('PRESTADOR');

$idPrestador = (int) $_SESSION['id_usuario'];
$erro = '';
$sucesso = isset($_GET['msg']) && $_GET['msg'] === 'status_ok'
    ? 'Status do serviço atualizado e notificado ao cliente!'
    : '';

// Identifica o chamado (via GET ou o atendimento mais recente do prestador)
$idChamado = isset($_GET['id_chamado']) ? (int) $_GET['id_chamado'] : 0;

if ($idChamado <= 0) {
    $stmtUltimo = $pdo->prepare('SELECT id_chamado FROM chamado WHERE id_prestador = ? ORDER BY id_chamado DESC LIMIT 1');
    $stmtUltimo->execute([$idPrestador]);
    $idChamado = (int) $stmtUltimo->fetchColumn();
}

// Processa POST (Atualizar status ou Enviar mensagem)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $idChamado > 0) {
    $acao = $_POST['acao'] ?? '';

    try {
        $stmtCheck = $pdo->prepare('SELECT * FROM chamado WHERE id_chamado = ? AND id_prestador = ? LIMIT 1');
        $stmtCheck->execute([$idChamado, $idPrestador]);
        $chamadoAtual = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$chamadoAtual) {
            $erro = 'Atendimento não encontrado.';
        } elseif ($acao === 'atualizar_status') {
            $novoStatus = $_POST['status'] ?? '';
            if (in_array($novoStatus, ['deslocamento', 'em_atendimento', 'concluido'], true)) {
                $pdo->prepare('UPDATE chamado SET status = ? WHERE id_chamado = ? AND id_prestador = ?')
                    ->execute([$novoStatus, $idChamado, $idPrestador]);

                $labelsStatus = [
                    'deslocamento'   => 'Em deslocamento até o local',
                    'em_atendimento' => 'No local / Em atendimento',
                    'concluido'      => 'Serviço concluído'
                ];
                $textoSistema = 'Prestador atualizou o status: ' . $labelsStatus[$novoStatus] . '.';
                $pdo->prepare("INSERT INTO mensagem (id_chamado, id_remetente, conteudo, tipo) VALUES (?, NULL, ?, 'sistema')")
                    ->execute([$idChamado, $textoSistema]);

                header("Location: chat.php?id_chamado={$idChamado}&msg=status_ok");
                exit;
            }
        } elseif ($acao === 'enviar_mensagem') {
            $conteudo = trim($_POST['mensagem'] ?? '');
            if ($conteudo !== '') {
                $pdo->prepare("INSERT INTO mensagem (id_chamado, id_remetente, conteudo, tipo) VALUES (?, ?, ?, 'texto')")
                    ->execute([$idChamado, $idPrestador, $conteudo]);
            }
            header("Location: chat.php?id_chamado={$idChamado}");
            exit;
        }
    } catch (PDOException $e) {
        $erro = 'Erro ao processar operação: ' . $e->getMessage();
    }
}

// Carrega dados completos do atendimento
$chamado = null;
$mensagens = [];

if ($idChamado > 0) {
    $stmtCh = $pdo->prepare("
        SELECT c.*,
               v.marca, v.modelo, v.placa,
               u.nome AS cliente_nome, u.telefone AS cliente_telefone
        FROM chamado c
        INNER JOIN veiculo v ON v.id_veiculo = c.id_veiculo
        INNER JOIN usuario u ON u.id_usuario = c.id_cliente
        WHERE c.id_chamado = ? AND c.id_prestador = ?
        LIMIT 1
    ");
    $stmtCh->execute([$idChamado, $idPrestador]);
    $chamado = $stmtCh->fetch(PDO::FETCH_ASSOC);

    if ($chamado) {
        $stmtMsgs = $pdo->prepare('SELECT * FROM mensagem WHERE id_chamado = ? ORDER BY id_mensagem ASC');
        $stmtMsgs->execute([$idChamado]);
        $mensagens = $stmtMsgs->fetchAll(PDO::FETCH_ASSOC);
    }
}

$nomesAvaria = [
    'pneu'    => 'Pneu furado',
    'bateria' => 'Bateria',
    'guincho' => 'Guincho',
    'pane'    => 'Pane mecânica',
];
$labelsBadge = [
    'deslocamento'   => 'Em deslocamento',
    'em_atendimento' => 'Em atendimento',
    'concluido'      => 'Concluído',
    'cancelado'      => 'Cancelado',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e66927">
    <title>Chat | MecGo Prestador</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="app">

    <?php if (!$chamado): ?>
        <header class="topbar compacta">
            <div class="topbar-linha">
                <a href="painel_chamados.php" class="sair">‹ Chamados</a>
            </div>
            <h1 style="margin-top:8px;">💬 Mensagens</h1>
        </header>
        <main class="content">
            <article class="card" style="text-align:center;color:var(--muted);">
                Você ainda não possui nenhum atendimento ativo para conversar.
            </article>
        </main>
    <?php else: ?>
        <?php
        $statusAtual = $chamado['status'];
        $inicialCliente = mb_strtoupper(mb_substr($chamado['cliente_nome'], 0, 1, 'UTF-8'), 'UTF-8');
        $ativo = in_array($statusAtual, ['deslocamento', 'em_atendimento'], true);
        ?>
        <header class="topbar compacta">
            <div class="topbar-linha">
                <a href="painel_chamados.php" class="sair">‹ Chamados</a>
                <span class="badge <?= $statusAtual === 'concluido' ? 'badge-concl' : 'badge-andamento' ?>">
                    <?= htmlspecialchars($labelsBadge[$statusAtual] ?? ucfirst($statusAtual)) ?>
                </span>
            </div>
            <div class="chat-id">
                <div class="avatar" aria-hidden="true"><?= htmlspecialchars($inicialCliente) ?></div>
                <div>
                    <div class="chat-nome"><?= htmlspecialchars($chamado['cliente_nome']) ?></div>
                    <div class="sub">
                        <?= htmlspecialchars($chamado['marca'] . ' ' . $chamado['modelo']) ?> ·
                        <?= htmlspecialchars($chamado['placa']) ?> ·
                        <?= htmlspecialchars($nomesAvaria[$chamado['avaria']] ?? ucfirst($chamado['avaria'])) ?>
                    </div>
                </div>
            </div>
        </header>

        <main class="content solto">

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

            <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($chamado['latitude'] . ',' . $chamado['longitude']) ?>"
               target="_blank" rel="noopener" class="btn btn-contorno" style="margin-bottom:1rem">
                🧭 Abrir rota no Google Maps
            </a>

            <!-- Status do serviço -->
            <section class="card">
                <h2 class="titulo-secao" style="margin-left:0;">Status do serviço</h2>

                <ol class="etapas">
                    <li class="<?= $statusAtual === 'deslocamento' ? 'atual' : '' ?>">Em deslocamento</li>
                    <li class="<?= $statusAtual === 'em_atendimento' ? 'atual' : '' ?>">No local / Atendimento</li>
                    <li class="<?= $statusAtual === 'concluido' ? 'atual' : '' ?>">Concluído</li>
                </ol>

                <?php if ($ativo): ?>
                    <form action="" method="post">
                        <input type="hidden" name="acao" value="atualizar_status">
                        <span class="rotulo">Atualizar para:</span>
                        <div class="escolhas">
                            <div class="escolha">
                                <input type="radio" id="st_desloc" name="status" value="deslocamento" <?= $statusAtual === 'deslocamento' ? 'checked' : '' ?>>
                                <label for="st_desloc"><span class="ico">🚗</span>Em deslocamento</label>
                            </div>
                            <div class="escolha">
                                <input type="radio" id="st_atend" name="status" value="em_atendimento" <?= $statusAtual === 'em_atendimento' ? 'checked' : '' ?>>
                                <label for="st_atend"><span class="ico">🔧</span>No local / Atendimento</label>
                            </div>
                            <div class="escolha">
                                <input type="radio" id="st_concl" name="status" value="concluido">
                                <label for="st_concl"><span class="ico">✅</span>Concluído</label>
                            </div>
                        </div>

                        <button type="submit" class="btn" style="margin-top:1rem;">🔄 Atualizar status</button>
                    </form>
                <?php endif; ?>

                <p class="dica">
                    O cliente é avisado a cada mudança. O pagamento fica em custódia até a conclusão do serviço.
                </p>
            </section>

            <p class="aviso-chat">🔒 O chat fica ativo durante o atendimento e registra todo o histórico do serviço.</p>

            <!-- Conversa -->
            <section class="mensagens" aria-label="Mensagens">
                <?php if (count($mensagens) === 0): ?>
                    <div class="msg-sistema">Nenhuma mensagem enviada ainda.</div>
                <?php else: ?>
                    <?php foreach ($mensagens as $m): ?>
                        <?php if ($m['tipo'] === 'sistema'): ?>
                            <div class="msg-sistema"><?= htmlspecialchars($m['conteudo']) ?> · <?= date('H:i', strtotime($m['enviada_em'])) ?></div>
                        <?php elseif ((int) $m['id_remetente'] === $idPrestador): ?>
                            <div class="msg enviada">
                                <?= htmlspecialchars($m['conteudo']) ?>
                                <small><?= date('H:i', strtotime($m['enviada_em'])) ?></small>
                            </div>
                        <?php else: ?>
                            <div class="msg recebida">
                                <?= htmlspecialchars($m['conteudo']) ?>
                                <small><?= date('H:i', strtotime($m['enviada_em'])) ?></small>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </main>

        <!-- Campo de mensagem fixo no rodapé -->
        <form class="chat-barra" action="" method="post">
            <input type="hidden" name="acao" value="enviar_mensagem">
            <input type="text" id="input-msg-prest" name="mensagem" placeholder="Digite uma mensagem..."
                   autocomplete="off" aria-label="Mensagem" required>
            <button type="submit" class="btn" aria-label="Enviar mensagem">➤</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($chamado && $ativo): ?>
<script>
setInterval(function () {
    const campo = document.getElementById('input-msg-prest');
    if (campo && (document.activeElement === campo || campo.value.trim() !== '')) {
        return;
    }
    window.location.reload();
}, 5000);
</script>
<?php endif; ?>
</body>
</html>
