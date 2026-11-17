<?php
require_once 'auth.php';
require_once 'conexao.php';

exigirLogin('CLIENTE');

$nomeCompleto = trim($_SESSION['nome'] ?? 'Motorista');
$primeiroNome = explode(' ', $nomeCompleto)[0];

// Conta quantos prestadores ativos existem no banco
$totalMecanicos = 0;
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM prestador p
        INNER JOIN usuario u ON u.id_usuario = p.id_prestador
        WHERE u.status = 'ativo'
    ");
    $totalMecanicos = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    $totalMecanicos = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e66927">
    <title>Início | MecGo</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="app mapa-tela">

    <div class="mapa-app" role="img" aria-label="Mapa ilustrativo com você e mecânicos próximos">
        <div class="busca-flutuante">📍 Av. Miguel Rosa, Teresina</div>

        <span class="pino" style="left:18%;top:26%"><span>🔧</span></span>
        <span class="pino" style="left:72%;top:36%"><span>🔧</span></span>
        <span class="pino" style="left:58%;top:50%"><span>🔧</span></span>
        <span class="pino" style="left:84%;top:62%"><span>🔧</span></span>
        <span class="pino" style="left:12%;top:70%"><span>🔧</span></span>
        <span class="voce-tag" style="left:42%;top:60%">Você</span>

        <section class="folha" style="padding-bottom:5.4rem">
            <h2>Olá, <?= htmlspecialchars($primeiroNome) ?>! Está com problema no carro?</h2>
            <p class="centro-txt" style="margin:0 0 .8rem;font-size:.9rem;color:var(--muted)">
                <?php if ($totalMecanicos === 1): ?>
                    1 mecânico disponível perto de você.
                <?php elseif ($totalMecanicos > 1): ?>
                    <?= $totalMecanicos ?> mecânicos disponíveis perto de você.
                <?php else: ?>
                    Pronto para solicitar assistência na sua região.
                <?php endif; ?>
            </p>
            <a href="servicos.php" class="btn">Pedir socorro</a>
        </section>
    </div>

    <nav class="nav-inferior cinco" aria-label="Navegação principal">
        <a href="../HTML/perfil.html"><span class="ico">👤</span>Perfil</a>
        <a href="servicos.php"><span class="ico">🔧</span>Serviços</a>
        <a href="inicio.php" class="ativo"><span class="ico">🏠</span>Início</a>
        <a href="../HTML/ofertas.html"><span class="ico">🏷️</span>Ofertas</a>
        <a href="../HTML/notificacoes.html"><span class="ico">🔔</span>Notificações<span class="ponto-notif"></span></a>
    </nav>
</div>
</body>
</html>
