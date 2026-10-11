CREATE DATABASE IF NOT EXISTS mecgo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mecgo;

-- 1. Tabela USUARIO
CREATE TABLE IF NOT EXISTS usuario (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    telefone VARCHAR(20),
    cpf VARCHAR(14) NOT NULL UNIQUE,
    tipo ENUM('CLIENTE', 'PRESTADOR', 'ADMINISTRADOR') NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Tabela CLIENTE
CREATE TABLE IF NOT EXISTS cliente (
    id_cliente INT PRIMARY KEY,
    localizacao_atual VARCHAR(255),
    FOREIGN KEY (id_cliente) REFERENCES usuario(id_usuario) ON DELETE CASCADE
);

-- 3. Tabela PRESTADOR
CREATE TABLE IF NOT EXISTS prestador (
    id_prestador INT PRIMARY KEY,
    cnpj VARCHAR(18),
    disponivel BOOLEAN DEFAULT TRUE,
    raio_atendimento_km INT DEFAULT 30,
    nota_media FLOAT DEFAULT 5.0,
    aprovado BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (id_prestador) REFERENCES usuario(id_usuario) ON DELETE CASCADE
);

-- 4. Tabela ADMINISTRADOR
CREATE TABLE IF NOT EXISTS administrador (
    id_admin INT PRIMARY KEY,
    nivel_acesso VARCHAR(50) DEFAULT 'GESTOR',
    FOREIGN KEY (id_admin) REFERENCES usuario(id_usuario) ON DELETE CASCADE
);

-- 5. Tabela VEICULO
CREATE TABLE IF NOT EXISTS veiculo (
    id_veiculo INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    placa VARCHAR(10) NOT NULL UNIQUE,
    marca VARCHAR(50) NOT NULL,
    modelo VARCHAR(50) NOT NULL,
    ano INT NOT NULL,
    cor VARCHAR(30) NOT NULL,
    FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente) ON DELETE CASCADE
);

-- 6. Tabela ATENDIMENTO
CREATE TABLE IF NOT EXISTS atendimento (
    id_atendimento INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    id_prestador INT,
    id_veiculo INT NOT NULL,
    data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    categoria_avaria VARCHAR(100) NOT NULL,
    localizacao_gps VARCHAR(255) NOT NULL,
    status ENUM('SOLICITADO', 'EM_DESLOCAMENTO', 'NO_LOCAL', 'EM_ATENDIMENTO', 'CONCLUIDO', 'CANCELADO') DEFAULT 'SOLICITADO',
    valor_total DECIMAL(10,2) DEFAULT 0.00,
    FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
    FOREIGN KEY (id_prestador) REFERENCES prestador(id_prestador),
    FOREIGN KEY (id_veiculo) REFERENCES veiculo(id_veiculo)
);

-- 7. Tabela PAGAMENTO
CREATE TABLE IF NOT EXISTS pagamento (
    id_pagamento INT AUTO_INCREMENT PRIMARY KEY,
    id_atendimento INT NOT NULL UNIQUE,
    valor DECIMAL(10,2) NOT NULL,
    metodo VARCHAR(50) NOT NULL,
    status_custodia ENUM('RETIDO', 'LIBERADO', 'REEMBOLSADO') DEFAULT 'RETIDO',
    data_pagamento DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_atendimento) REFERENCES atendimento(id_atendimento)
);

-- 8. Tabela CHAT
CREATE TABLE IF NOT EXISTS chat (
    id_mensagem INT AUTO_INCREMENT PRIMARY KEY,
    id_atendimento INT NOT NULL,
    id_remetente INT NOT NULL,
    texto TEXT NOT NULL,
    data_hora_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_atendimento) REFERENCES atendimento(id_atendimento),
    FOREIGN KEY (id_remetente) REFERENCES usuario(id_usuario)
);

-- ======================================================
-- DADOS INICIAIS DE TESTE (POVOAMENTO)
-- ======================================================

-- Inserir Usuários de Teste
INSERT IGNORE INTO usuario (id_usuario, nome, email, senha, telefone, cpf, tipo) VALUES
(1, 'Carlos Motorista', 'cliente@mecgo.com', '123456', '(86) 99999-1111', '111.111.111-11', 'CLIENTE'),
(2, 'João Mecânico Autônomo', 'mecanico@mecgo.com', '123456', '(86) 98888-2222', '222.222.222-22', 'PRESTADOR'),
(3, 'José Matheus Admin', 'admin@mecgo.com', '123456', '(86) 97777-3333', '333.333.333-33', 'ADMINISTRADOR');

-- Inserir Perfis
INSERT IGNORE INTO cliente (id_cliente, localizacao_atual) VALUES (1, 'Av. Frei Serafim, Teresina - PI');
INSERT IGNORE INTO prestador (id_prestador, cnpj, disponivel, raio_atendimento_km, nota_media, aprovado) VALUES (2, '12.345.678/0001-90', TRUE, 30, 4.8, TRUE);
INSERT IGNORE INTO administrador (id_admin, nivel_acesso) VALUES (3, 'GESTOR_GERAL');

-- Inserir Veículos
INSERT IGNORE INTO veiculo (id_cliente, placa, marca, modelo, ano, cor) VALUES
(1, 'ODV-1234', 'Toyota', 'Corolla', 2022, 'Prata'),
(1, 'PIX-5678', 'Honda', 'Civic', 2020, 'Preto');
