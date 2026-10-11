<?php
require_once 'conexao.php';

echo "<h2>Testes de Conexão com o Banco MecGo</h2>";

// Consulta os usuários no MySQL
$stmt = $pdo->query("SELECT id_usuario, nome, email, tipo FROM usuario");
$usuarios = $stmt->fetchAll();

echo "<h3>Usuários Cadastrados:</h3><ul>";
foreach ($usuarios as $u) {
    echo "<li><strong>{$u['nome']}</strong> ({$u['tipo']}) - {$u['email']}</li>";
}
echo "</ul>";

// Consulta os veículos
$stmtVeiculos = $pdo->query("SELECT marca, modelo, placa, cor FROM veiculo");
$veiculos = $stmtVeiculos->fetchAll();

echo "<h3>Veículos na Garagem:</h3><ul>";
foreach ($veiculos as $v) {
    echo "<li>{$v['marca']} {$v['modelo']} ({$v['cor']}) - Placa: <strong>{$v['placa']}</strong></li>";
}
echo "</ul>";
?>