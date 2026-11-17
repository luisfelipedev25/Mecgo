<?php
require_once 'auth.php';
require_once 'conexao.php';

exigirLogin('CLIENTE');

$idCliente = (int) $_SESSION['id_usuario'];

// Busca a última mensagem/notificação de cada chamado do cliente
$stmt = $pdo->prepare("
    SELECT c.id_chamado, c.avaria,
           u_prest.nome AS prestador_nome,
           m.conteudo, m.enviada_em,
           (SELECT COUNT(*) FROM mensagem m2 WHERE m2.id_chamado = c.id_chamado) AS total_msgs
    FROM chamado c
    LEFT JOIN usuario u_prest ON u_prest.id_usuario = c.id_prestador
    INNER JOIN mensagem m ON m.id_mensagem = (
        SELECT MAX(m3.id_mensagem) FROM mensagem m3 WHERE m3.id_chamado = c.id_chamado
    )
    WHERE c.id_cliente = ?
    ORDER BY m.id_mensagem DESC
");
$stmt->execute([$idCliente]);
$notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e66927">
    <title>Notificações | MecGo</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="app branco">

    <header class="topo-app">
        <a href="inicio.php" class="voltar">‹ Voltar</a>
        <h1>Notificações</h1>
        <span class="direita"></span>
    </header>

    <main class="content">
        <div class="aba-barra">Mensagens</div>

        <?php if (count($notificacoes) === 0): ?>
            <article class="card" style="text-align:center;color:var(--muted);margin-top:12px;">
                Nenhuma mensagem ou atualização nos seus chamados ainda.
            </article>
        <?php else: ?>
            <?php foreach ($notificacoes as $n): ?>
                <?php
                $titulo = !empty($n['prestador_nome'])
                    ? $n['prestador_nome']
                    : 'MecGo · Chamado #' . (int) $n['id_chamado'];
                $inicial = mb_strtoupper(mb_substr($titulo, 0, 1, 'UTF-8'), 'UTF-8');
                $hora = date('d/m H:i', strtotime($n['enviada_em']));
                ?>
                <a href="acompanhar.php?id_chamado=<?= (int) $n['id_chamado'] ?>#chat" class="notif">
                    <span class="av"><?= htmlspecialchars($inicial) ?></span>
                    <strong><?= htmlspecialchars($titulo) ?></strong>
                    <time><?= $hora ?></time>
                    <small><?= htmlspecialchars($n['conteudo']) ?></small>
                    <span class="cont"><?= (int) $n['total_msgs'] ?></span>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <nav class="nav-inferior" aria-label="Navegação principal">
        <a href="perfil.php"><span class="ico">👤</span>Perfil</a>
        <a href="servicos.php"><span class="ico">🔧</span>Serviços</a>
        <a href="inicio.php"><span class="ico">🏠</span>Início</a>
        <a href="notificacoes.php" class="ativo"><span class="ico">🔔</span>Notificações<span class="ponto-notif"></span></a>
    </nav>
</div>
</body>
</html>
