<?php
require_once 'auth.php';
exigirLogin('CLIENTE');

$idVeiculo = isset($_GET['id_veiculo']) ? (int) $_GET['id_veiculo'] : 0;
$paramVeiculo = $idVeiculo > 0 ? '&id_veiculo=' . $idVeiculo : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e66927">
    <title>Serviços | MecGo</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="app mapa-tela">

    <div class="mapa-app escuro">
        <a href="inicio.php" class="voltar-flutuante">‹ Voltar</a>
        <span class="pino" style="left:20%;top:16%"><span>🔧</span></span>
        <span class="pino" style="left:70%;top:22%"><span>🔧</span></span>
        <span class="voce-tag" style="left:44%;top:30%">Você</span>

        <section class="folha" style="max-height:78%">
            <h2>Qual serviço você deseja:</h2>
            <a href="solicitar_socorro.php?avaria=pneu&servico=Pneu+furado<?= $paramVeiculo ?>" class="btn-servico"><span>Pneu furado</span></a>
            <a href="solicitar_socorro.php?avaria=bateria&servico=Bateria+descarregada<?= $paramVeiculo ?>" class="btn-servico"><span>Bateria descarregada</span></a>
            <a href="solicitar_socorro.php?avaria=bateria&servico=Substituição+da+bateria<?= $paramVeiculo ?>" class="btn-servico"><span>Substituição da bateria</span></a>
            <a href="solicitar_socorro.php?avaria=pane&servico=Troca+de+óleo+e+filtro<?= $paramVeiculo ?>" class="btn-servico"><span>Troca de óleo e filtro</span></a>
            <a href="solicitar_socorro.php?avaria=pane&servico=Troca+de+pastilhas+de+freio<?= $paramVeiculo ?>" class="btn-servico"><span>Troca de pastilhas de freio</span></a>
            <a href="solicitar_socorro.php?avaria=guincho&servico=Guincho<?= $paramVeiculo ?>" class="btn-servico"><span>Guincho</span></a>
            <a href="solicitar_socorro.php?avaria=pane&servico=Chaveiro+veicular<?= $paramVeiculo ?>" class="btn-servico"><span>Chaveiro veicular</span></a>
            <a href="solicitar_socorro.php?avaria=pane&servico=Inspeção+preventiva<?= $paramVeiculo ?>" class="btn-servico"><span>Inspeção preventiva</span></a>
        </section>
    </div>

    <nav class="nav-inferior cinco" aria-label="Navegação principal">
        <a href="../HTML/perfil.html"><span class="ico">👤</span>Perfil</a>
        <a href="servicos.php" class="ativo"><span class="ico">🔧</span>Serviços</a>
        <a href="inicio.php"><span class="ico">🏠</span>Início</a>
        <a href="../HTML/ofertas.html"><span class="ico">🏷️</span>Ofertas</a>
        <a href="../HTML/notificacoes.html"><span class="ico">🔔</span>Notificações<span class="ponto-notif"></span></a>
    </nav>
</div>
</body>
</html>
