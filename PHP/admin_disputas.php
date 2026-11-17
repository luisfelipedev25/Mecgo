<?php
require_once 'auth.php';
require_once 'conexao.php';

exigirLogin('ADMIN');

$erro = '';
$sucesso = '';

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'estornado') {
        $sucesso = 'Disputa encerrada: valor estornado ao cliente!';
    } elseif ($_GET['msg'] === 'liberado') {
        $sucesso = 'Disputa encerrada: valor liberado ao prestador!';
    }
}

// Processa mediação da disputa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idDisputa = (int) ($_POST['id_disputa'] ?? 0);
    $decisao = $_POST['decisao'] ?? '';

    if ($idDisputa > 0 && in_array($decisao, ['estornar_cliente', 'liberar_prestador'], true)) {
        try {
            $stmtDisp = $pdo->prepare("SELECT * FROM disputa WHERE id_disputa = ? AND decisao = 'aberta' LIMIT 1");
            $stmtDisp->execute([$idDisputa]);
            $disp = $stmtDisp->fetch(PDO::FETCH_ASSOC);

            if ($disp) {
                $pdo->beginTransaction();

                // 1. Registra a decisão na disputa
                $pdo->prepare('UPDATE disputa SET decisao = ? WHERE id_disputa = ?')
                    ->execute([$decisao, $idDisputa]);

                // 2. Atualiza o pagamento correspondente em custódia
                $novoStatusPag = $decisao === 'estornar_cliente' ? 'estornado' : 'liberado';
                $pdo->prepare('UPDATE pagamento SET status = ? WHERE id_chamado = ?')
                    ->execute([$novoStatusPag, (int) $disp['id_chamado']]);

                // 3. Registra aviso no chat do chamado
                $msgChat = $decisao === 'estornar_cliente'
                    ? 'Moderação MecGo encerrou a disputa: pagamento estornado ao motorista.'
                    : 'Moderação MecGo encerrou a disputa: pagamento liberado ao prestador.';
                $pdo->prepare("INSERT INTO mensagem (id_chamado, id_remetente, conteudo, tipo) VALUES (?, NULL, ?, 'sistema')")
                    ->execute([(int) $disp['id_chamado'], $msgChat]);

                $pdo->commit();

                $param = $decisao === 'estornar_cliente' ? 'estornado' : 'liberado';
                header("Location: admin_disputas.php?msg={$param}");
                exit;
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro = 'Erro ao processar decisão da disputa: ' . $e->getMessage();
        }
    }
}

// Busca todas as disputas com dados das partes e valor pago
$stmt = $pdo->query("
    SELECT d.*,
           c.avaria,
           u_cli.nome AS cliente_nome,
           u_prest.nome AS prestador_nome,
           p.valor_total
    FROM disputa d
    INNER JOIN chamado c ON c.id_chamado = d.id_chamado
    INNER JOIN usuario u_cli ON u_cli.id_usuario = c.id_cliente
    LEFT JOIN usuario u_prest ON u_prest.id_usuario = c.id_prestador
    LEFT JOIN pagamento p ON p.id_chamado = d.id_chamado
    ORDER BY (d.decisao = 'aberta') DESC, d.id_disputa DESC
");
$disputas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$abertasCount = 0;
$idsChamados = [];
foreach ($disputas as $d) {
    if ($d['decisao'] === 'aberta') {
        $abertasCount++;
    }
    $idsChamados[] = (int) $d['id_chamado'];
}

// Carrega mensagens do chat de cada chamado em disputa
$mensagensPorChamado = [];
if (count($idsChamados) > 0) {
    $idsUnicos = array_values(array_unique($idsChamados));
    $placeholders = implode(',', array_fill(0, count($idsUnicos), '?'));
    $stmtMsgs = $pdo->prepare("
        SELECT m.id_chamado, m.conteudo, m.tipo, m.enviada_em, u.nome AS remetente_nome
        FROM mensagem m
        LEFT JOIN usuario u ON u.id_usuario = m.id_remetente
        WHERE m.id_chamado IN ($placeholders)
        ORDER BY m.id_mensagem ASC
    ");
    $stmtMsgs->execute($idsUnicos);
    foreach ($stmtMsgs->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $mensagensPorChamado[(int) $m['id_chamado']][] = $m;
    }
}

$nomesAvaria = [
    'pneu'    => 'Pneu furado',
    'bateria' => 'Bateria',
    'guincho' => 'Guincho',
    'pane'    => 'Pane mecânica',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e66927">
    <title>Disputas | Admin | MecGo</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="app admin sem-nav">

    <header class="admin-topo">
        <div class="admin-topo-linha">
            <div class="brand">Mec<span>Go</span> <span class="selo-mini">Admin</span></div>
            <a href="logout.php" class="sair">Sair ➜</a>
        </div>
        <nav class="admin-menu" aria-label="Menu do administrador">
            <a href="admin_dashboard.php">📊 Dashboard</a>
            <a href="aprovar_prestadores.php">✅ Aprovações</a>
            <a href="admin_usuarios.php">👥 Usuários</a>
            <a href="admin_financeiro.php">💰 Financeiro</a>
            <a href="admin_disputas.php" class="ativo">⚖️ Disputas</a>
        </nav>
    </header>

    <main class="content admin-conteudo">
        <div class="cabecalho-lista">
            <h1 class="admin-titulo" style="margin:0">⚖️ Disputas e suporte</h1>
            <span class="badge badge-aguardando"><?= $abertasCount ?> abertas</span>
        </div>
        <p class="dica" style="margin:0 .25rem 1rem">Medie chamados contestados e decida o destino do valor em custódia.</p>

        <?php if ($sucesso !== ''): ?>
            <div class="alert alert-success" role="status" style="margin-bottom:14px;">
                <?= htmlspecialchars($sucesso) ?>
            </div>
        <?php endif; ?>

        <?php if ($erro !== ''): ?>
            <div style="background:#fdecea;color:#b71c1c;padding:12px;border-radius:10px;margin-bottom:14px;font-size:14px;">
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <?php if (count($disputas) === 0): ?>
            <article class="card" style="text-align:center;color:var(--muted);">
                🎉 Nenhuma disputa registrada na plataforma no momento!
            </article>
        <?php else: ?>
            <div class="usr-lista">
                <?php foreach ($disputas as $d): ?>
                    <?php
                    $idD = (int) $d['id_disputa'];
                    $idCh = (int) $d['id_chamado'];
                    $msgs = $mensagensPorChamado[$idCh] ?? [];
                    $aberta = ($d['decisao'] === 'aberta');
                    ?>
                    <article class="card disputa" style="position:relative">
                        <div class="disputa-topo">
                            <div>
                                <h2>Chamado #<?= $idCh ?> · <?= htmlspecialchars($nomesAvaria[$d['avaria']] ?? ucfirst($d['avaria'])) ?></h2>
                                <span class="usr-meta">
                                    Aberto por <?= $d['aberto_por'] === 'CLIENTE' ? 'Motorista' : 'Prestador' ?>
                                    <?= $d['valor_total'] !== null ? ' · valor R$ ' . number_format((float) $d['valor_total'], 2, ',', '.') : '' ?>
                                </span>
                            </div>
                            <?php if ($aberta): ?>
                                <span class="badge badge-aguardando">Aberta</span>
                            <?php elseif ($d['decisao'] === 'estornar_cliente'): ?>
                                <span class="badge badge-estorno">Estornada ao cliente</span>
                            <?php else: ?>
                                <span class="badge badge-liberado">Liberada ao prestador</span>
                            <?php endif; ?>
                        </div>

                        <div class="partes">
                            <div><strong>Cliente</strong><br><?= htmlspecialchars($d['cliente_nome']) ?></div>
                            <div><strong>Prestador</strong><br><?= htmlspecialchars($d['prestador_nome'] ?? 'Não atribuído') ?></div>
                        </div>

                        <p style="margin:.4rem 0;font-size:.9rem">
                            <strong>Motivo:</strong> <?= htmlspecialchars($d['motivo']) ?>
                        </p>

                        <details>
                            <summary>Ver conversa do chat (<?= count($msgs) ?>)</summary>
                            <div class="trecho">
                                <?php if (count($msgs) === 0): ?>
                                    Sem mensagens registradas neste chamado.
                                <?php else: ?>
                                    <?php foreach ($msgs as $m): ?>
                                        <?= date('H:i', strtotime($m['enviada_em'])) ?>
                                        <strong><?= $m['tipo'] === 'sistema' ? 'Sistema' : htmlspecialchars($m['remetente_nome'] ?? 'Usuário') ?>:</strong>
                                        <?= htmlspecialchars($m['conteudo']) ?><br>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </details>

                        <?php if ($aberta): ?>
                            <div class="res-opcoes" style="margin-top:10px;">
                                <form action="" method="post" style="display:inline;"
                                      onsubmit="return confirm('Confirmar estorno ao motorista nesta disputa?');">
                                    <input type="hidden" name="id_disputa" value="<?= $idD ?>">
                                    <input type="hidden" name="decisao" value="estornar_cliente">
                                    <button type="submit" class="btn btn-perigo btn-pequeno">Estornar cliente</button>
                                </form>

                                <form action="" method="post" style="display:inline;"
                                      onsubmit="return confirm('Confirmar liberação do pagamento ao prestador?');">
                                    <input type="hidden" name="id_disputa" value="<?= $idD ?>">
                                    <input type="hidden" name="decisao" value="liberar_prestador">
                                    <button type="submit" class="btn btn-sucesso btn-pequeno">Liberar ao prestador</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
