<?php
require_once 'auth.php';
require_once 'conexao.php';

exigirLogin('PRESTADOR');

$idPrestador = (int) $_SESSION['id_usuario'];

// Busca dados do prestador + usuario
$stmt = $pdo->prepare("
    SELECT u.nome, u.email, u.telefone, u.status,
           p.empresa, p.categorias, p.raio_km, p.bio
    FROM usuario u
    INNER JOIN prestador p ON p.id_prestador = u.id_usuario
    WHERE u.id_usuario = ?
    LIMIT 1
");
$stmt->execute([$idPrestador]);
$prestador = $stmt->fetch(PDO::FETCH_ASSOC);

$nomeExibicao = !empty($prestador['empresa']) ? $prestador['empresa'] : ($prestador['nome'] ?? 'Prestador');

// Busca avaliações recebidas
$stmtAv = $pdo->prepare("
    SELECT a.nota, a.comentario, a.criado_em, u.nome AS avaliador_nome
    FROM avaliacao a
    INNER JOIN usuario u ON u.id_usuario = a.id_avaliador
    WHERE a.id_avaliado = ?
    ORDER BY a.id_avaliacao DESC
");
$stmtAv->execute([$idPrestador]);
$avaliacoes = $stmtAv->fetchAll(PDO::FETCH_ASSOC);

$totalAv = count($avaliacoes);
$somaNotas = 0;
$contagemNotas = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

foreach ($avaliacoes as $av) {
    $n = (int) $av['nota'];
    if ($n >= 1 && $n <= 5) {
        $somaNotas += $n;
        $contagemNotas[$n]++;
    }
}

$mediaNota = $totalAv > 0 ? number_format($somaNotas / $totalAv, 1, ',', '.') : '—';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e66927">
    <title>Perfil | MecGo</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="app branco">

    <header class="topo-app">
        <a href="painel_chamados.php" class="voltar">‹ Voltar</a>
        <h1>Perfil</h1>
        <span class="direita"></span>
    </header>

    <main class="content">

        <div class="perfil-topo">
            <div class="foto-perfil" aria-hidden="true">🛠️</div>
            <h2><?= htmlspecialchars($nomeExibicao) ?></h2>
            <span class="sub-perfil">
                ✔ Cadastro <?= htmlspecialchars($prestador['status'] ?? 'ativo') ?> ·
                <?= htmlspecialchars($prestador['categorias'] ?? '') ?>
            </span>
        </div>

        <section class="card media-nota">
            <div>
                <div class="num"><?= $mediaNota ?></div>
                <div class="estrela">★★★★★</div>
                <small style="color:var(--muted)"><?= $totalAv ?> avaliações</small>
            </div>
            <div class="dist-notas">
                <?php for ($estrela = 5; $estrela >= 1; $estrela--): ?>
                    <?php $pct = $totalAv > 0 ? round(($contagemNotas[$estrela] / $totalAv) * 100) : 0; ?>
                    <div class="dist-linha">
                        <span><?= $estrela ?></span>
                        <div class="trilho-d"><i style="width:<?= $pct ?>%"></i></div>
                        <span><?= $pct ?>%</span>
                    </div>
                <?php endfor; ?>
            </div>
        </section>

        <section class="card">
            <h2 class="titulo-secao" style="margin-left:0">Comentários recentes</h2>
            <?php if ($totalAv === 0): ?>
                <p style="color:var(--muted);font-size:.9rem;margin:8px 0 0;">Você ainda não recebeu avaliações.</p>
            <?php else: ?>
                <?php foreach (array_slice($avaliacoes, 0, 5) as $av): ?>
                    <div class="comentario">
                        <span class="estrela"><?= str_repeat('★', (int) $av['nota']) . str_repeat('☆', 5 - (int) $av['nota']) ?></span>
                        <?= !empty($av['comentario']) ? htmlspecialchars($av['comentario']) : 'Atendimento avaliado.' ?>
                        <small><?= htmlspecialchars($av['avaliador_nome']) ?> · <?= date('d/m/Y', strtotime($av['criado_em'])) ?></small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <ul class="menu-perfil">
            <li><a href="painel_chamados.php">🛠️ Voltar aos chamados <span class="seta">›</span></a></li>
            <li><a href="logout.php">↪ Sair da conta <span class="seta">›</span></a></li>
        </ul>

    </main>

    <nav class="nav-inferior" aria-label="Navegação principal">
        <a href="painel_chamados.php"><span class="ico">🛠️</span>Chamados</a>
        <a href="chat.php"><span class="ico">💬</span>Mensagens</a>
        <a href="ganhos.php"><span class="ico">💰</span>Ganhos</a>
        <a href="perfil_prestador.php" class="ativo"><span class="ico">👤</span>Perfil</a>
    </nav>
</div>
</body>
</html>
