<?php
require_once 'auth.php';
require_once 'conexao.php';

exigirLogin('ADMIN');

$erro = '';
$sucesso = '';

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'aprovado') {
        $sucesso = 'Prestador aprovado com sucesso! A conta já está ativa para receber chamados.';
    } elseif ($_GET['msg'] === 'recusado') {
        $sucesso = 'Solicitação de prestador recusada e conta bloqueada.';
    }
}

// Processa aprovação ou recusa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idPrestador = (int) ($_POST['id_prestador'] ?? 0);
    $decisao = $_POST['decisao'] ?? '';

    if ($idPrestador > 0 && in_array($decisao, ['aprovar', 'recusar'], true)) {
        try {
            $novoStatus = $decisao === 'aprovar' ? 'ativo' : 'suspenso';
            $stmtUpd = $pdo->prepare("UPDATE usuario SET status = ? WHERE id_usuario = ? AND tipo = 'PRESTADOR'");
            $stmtUpd->execute([$novoStatus, $idPrestador]);

            $param = $decisao === 'aprovar' ? 'aprovado' : 'recusado';
            header("Location: aprovar_prestadores.php?msg={$param}");
            exit;
        } catch (PDOException $e) {
            $erro = 'Erro ao atualizar status do prestador: ' . $e->getMessage();
        }
    }
}

// Busca todos os prestadores pendentes
$stmt = $pdo->query("
    SELECT u.id_usuario, u.nome, u.email, u.cpf, u.telefone, u.criado_em,
           p.empresa, p.cnpj, p.categorias, p.raio_km, p.bio, p.foto_perfil
    FROM usuario u
    INNER JOIN prestador p ON p.id_prestador = u.id_usuario
    WHERE u.tipo = 'PRESTADOR' AND u.status = 'pendente'
    ORDER BY u.id_usuario ASC
");
$pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Busca fotos do portfólio para cada pendente
$portfoliosPorPrestador = [];
if (count($pendentes) > 0) {
    $ids = array_column($pendentes, 'id_usuario');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmtFotos = $pdo->prepare("SELECT id_prestador, caminho_arquivo FROM prestador_portfolio WHERE id_prestador IN ($placeholders)");
    $stmtFotos->execute($ids);
    foreach ($stmtFotos->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $portfoliosPorPrestador[(int) $f['id_prestador']][] = $f['caminho_arquivo'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e66927">
    <title>Aprovação de Prestadores | MecGo Admin</title>
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
            <a href="aprovar_prestadores.php" class="ativo">✅ Aprovações</a>
            <a href="admin_usuarios.php">👥 Usuários</a>
            <a href="admin_financeiro.php">💰 Financeiro</a>
            <a href="admin_disputas.php">⚖️ Disputas</a>
        </nav>
    </header>

    <main class="content admin-conteudo">

        <div class="cabecalho-lista">
            <h1 class="admin-titulo" style="margin:0;">✅ Aprovação de prestadores</h1>
            <span class="badge badge-aguardando"><?= count($pendentes) ?> pendentes</span>
        </div>

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

        <?php if (count($pendentes) === 0): ?>
            <article class="card" style="text-align:center;color:var(--muted);margin-top:1rem;">
                🎉 Nenhum prestador pendente de aprovação no momento!
            </article>
        <?php else: ?>
            <p class="dica" style="margin:0 .25rem 1rem;">
                Mostrando <?= count($pendentes) ?> solicitações. Confira os dados e o portfólio antes de aprovar.
            </p>

            <div class="prest-lista" id="lista">
                <?php foreach ($pendentes as $p): ?>
                    <?php
                    $idP = (int) $p['id_usuario'];
                    $fotos = $portfoliosPorPrestador[$idP] ?? [];
                    $nomePrincipal = !empty($p['empresa']) ? $p['empresa'] . ' (' . $p['nome'] . ')' : $p['nome'];
                    ?>
                    <article class="card prest">
                        <div class="prest-corpo">
                            <div class="prest-topo">
                                <div>
                                    <h2 class="prest-nome"><?= htmlspecialchars($nomePrincipal) ?></h2>
                                    <div class="pedido-meta">
                                        📞 <?= htmlspecialchars($p['telefone']) ?> · ✉️ <?= htmlspecialchars($p['email']) ?>
                                    </div>
                                </div>
                                <span class="badge badge-categoria"><?= htmlspecialchars($p['categorias']) ?></span>
                            </div>
                            <div class="prest-dados">
                                <div><strong>CPF:</strong> <?= htmlspecialchars($p['cpf']) ?></div>
                                <?php if (!empty($p['cnpj'])): ?>
                                    <div><strong>CNPJ:</strong> <?= htmlspecialchars($p['cnpj']) ?></div>
                                <?php endif; ?>
                                <div><strong>Raio:</strong> Até <?= (int) $p['raio_km'] ?> km</div>
                                <div><strong>Portfólio:</strong> <span class="ok-txt">✔ <?= count($fotos) ?> foto(s) enviada(s)</span></div>
                            </div>
                        </div>

                        <div class="prest-acoes">
                            <a href="#modal-docs-<?= $idP ?>" class="btn btn-contorno">📄 Ver docs</a>

                            <form action="" method="post" style="display:inline;"
                                  onsubmit="return confirm('Recusar o cadastro de <?= htmlspecialchars(addslashes($p['nome'])) ?>?');">
                                <input type="hidden" name="id_prestador" value="<?= $idP ?>">
                                <input type="hidden" name="decisao" value="recusar">
                                <button type="submit" class="btn btn-perigo">✕ Recusar</button>
                            </form>

                            <form action="" method="post" style="display:inline;">
                                <input type="hidden" name="id_prestador" value="<?= $idP ?>">
                                <input type="hidden" name="decisao" value="aprovar">
                                <button type="submit" class="btn btn-sucesso">✓ Aprovar</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Modais de conferência de documentos e portfólio real -->
    <?php foreach ($pendentes as $p): ?>
        <?php
        $idP = (int) $p['id_usuario'];
        $fotos = $portfoliosPorPrestador[$idP] ?? [];
        ?>
        <div class="modal centro" id="modal-docs-<?= $idP ?>" role="dialog" aria-modal="true">
            <a href="#lista" class="modal-fundo" aria-label="Fechar"></a>
            <div class="modal-box" style="max-height:85vh;overflow-y:auto;">
                <div class="modal-topo">
                    <div>
                        <h2><?= htmlspecialchars($p['nome']) ?></h2>
                        <p>Conferência de cadastro e fotos enviadas</p>
                    </div>
                    <a href="#lista" class="fechar" aria-label="Fechar">✕</a>
                </div>

                <ul class="lista-docs">
                    <li><span>CPF: <?= htmlspecialchars($p['cpf']) ?></span><span class="badge badge-ok">Informado</span></li>
                    <li><span>CNPJ: <?= !empty($p['cnpj']) ? htmlspecialchars($p['cnpj']) : 'Não informado (Autônomo)' ?></span><span class="badge badge-ok">OK</span></li>
                    <li><span>Categorias: <?= htmlspecialchars($p['categorias']) ?></span><span class="badge badge-ok">OK</span></li>
                </ul>

                <?php if (!empty($p['bio'])): ?>
                    <p style="font-size:.88rem;background:#f8f8f8;padding:10px;border-radius:8px;margin-bottom:12px;">
                        <strong>Bio:</strong> <?= htmlspecialchars($p['bio']) ?>
                    </p>
                <?php endif; ?>

                <h3 style="font-size:.95rem;margin:0 0 8px;">Fotos do Portfólio (<?= count($fotos) ?>)</h3>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px;margin-bottom:14px;">
                    <?php foreach ($fotos as $caminho): ?>
                        <a href="../<?= htmlspecialchars($caminho) ?>" target="_blank" rel="noopener">
                            <img src="../<?= htmlspecialchars($caminho) ?>" alt="Foto do portfólio"
                                 style="width:100%;height:100px;object-fit:cover;border-radius:8px;border:1px solid #ddd;">
                        </a>
                    <?php endforeach; ?>
                </div>

                <a href="#lista" class="btn btn-contorno">Fechar</a>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>
