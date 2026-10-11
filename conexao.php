<?php
// conexao.php - Conexão com o banco MySQL via PDO
$host     = 'localhost';
$db       = 'mecgo';
$user     = 'root';
$pass     = ''; // Senha padrão do XAMPP é vazia
$charset  = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Erro ao conectar ao Banco de Dados: " . $e->getMessage());
}
?>
