-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 13/11/2026 às 23:36
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `mecgo`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `avaliacao`
--

CREATE TABLE `avaliacao` (
  `id_avaliacao` int(11) NOT NULL,
  `id_chamado` int(11) NOT NULL,
  `id_avaliador` int(11) NOT NULL,
  `id_avaliado` int(11) NOT NULL,
  `nota` int(1) NOT NULL,
  `comentario` varchar(500) DEFAULT NULL,
  `criado_em` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `chamado`
--

CREATE TABLE `chamado` (
  `id_chamado` int(11) NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `id_veiculo` int(11) NOT NULL,
  `id_prestador` int(11) DEFAULT NULL,
  `avaria` enum('pneu','bateria','guincho','pane','chaveiro','inspecao') NOT NULL,
  `descricao` varchar(500) DEFAULT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `status` enum('aguardando','orcamento_enviado','deslocamento','no_local','atendimento','concluido','cancelado') NOT NULL DEFAULT 'aguardando',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `cliente`
--

CREATE TABLE `cliente` (
  `id_cliente` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `disputa`
--

CREATE TABLE `disputa` (
  `id_disputa` int(11) NOT NULL,
  `id_chamado` int(11) NOT NULL,
  `aberto_por` enum('CLIENTE','PRESTADOR') NOT NULL,
  `motivo` varchar(500) NOT NULL,
  `decisao` enum('aberta','estornar_cliente','liberar_prestador','mais_info') NOT NULL DEFAULT 'aberta',
  `criada_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `mensagem`
--

CREATE TABLE `mensagem` (
  `id_mensagem` int(11) NOT NULL,
  `id_chamado` int(11) NOT NULL,
  `id_remetente` int(11) DEFAULT NULL,
  `conteudo` text NOT NULL,
  `tipo` enum('texto','sistema') NOT NULL DEFAULT 'texto',
  `enviada_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `orcamento`
--

CREATE TABLE `orcamento` (
  `id_orcamento` int(11) NOT NULL,
  `id_chamado` int(11) NOT NULL,
  `id_prestador` int(11) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `tempo_estimado` varchar(50) DEFAULT NULL,
  `observacao` varchar(300) DEFAULT NULL,
  `status` enum('pendente','aceito','recusado') NOT NULL DEFAULT 'pendente',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pagamento`
--

CREATE TABLE `pagamento` (
  `id_pagamento` int(11) NOT NULL,
  `id_chamado` int(11) NOT NULL,
  `id_orcamento` int(11) NOT NULL,
  `valor_total` decimal(10,2) NOT NULL,
  `taxa_mecgo` decimal(10,2) NOT NULL,
  `valor_prestador` decimal(10,2) NOT NULL,
  `metodo` enum('PIX','CARTAO') NOT NULL,
  `status` enum('retido_custodia','liberado','estornado') NOT NULL DEFAULT 'retido_custodia',
  `pago_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `prestador`
--

CREATE TABLE `prestador` (
  `id_prestador` int(11) NOT NULL,
  `empresa` varchar(100) DEFAULT NULL,
  `cnpj` varchar(18) DEFAULT NULL,
  `categorias` varchar(150) NOT NULL,
  `raio_km` int(11) NOT NULL DEFAULT 30,
  `bio` varchar(500) DEFAULT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `prestador_portfolio`
--

CREATE TABLE `prestador_portfolio` (
  `id_foto` int(11) NOT NULL,
  `id_prestador` int(11) NOT NULL,
  `caminho_arquivo` varchar(255) NOT NULL,
  `enviado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuario`
--

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `cpf` varchar(14) NOT NULL,
  `telefone` varchar(15) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo` enum('CLIENTE','PRESTADOR','ADMIN') NOT NULL,
  `status` enum('ativo','pendente','suspenso') NOT NULL DEFAULT 'ativo',
  `aceite_lgpd_em` datetime NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `veiculo`
--

CREATE TABLE `veiculo` (
  `id_veiculo` int(11) NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `tipo` enum('Carro','Moto') NOT NULL DEFAULT 'Carro',
  `placa` varchar(8) NOT NULL,
  `marca` varchar(50) NOT NULL,
  `modelo` varchar(80) NOT NULL,
  `ano` int(11) NOT NULL,
  `cor` varchar(30) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `avaliacao`
--
ALTER TABLE `avaliacao`
  ADD PRIMARY KEY (`id_avaliacao`),
  ADD KEY `id_chamado` (`id_chamado`),
  ADD KEY `id_avaliador` (`id_avaliador`),
  ADD KEY `id_avaliado` (`id_avaliado`);

--
-- Índices de tabela `chamado`
--
ALTER TABLE `chamado`
  ADD PRIMARY KEY (`id_chamado`),
  ADD KEY `fk_chamado_cliente` (`id_cliente`),
  ADD KEY `fk_chamado_veiculo` (`id_veiculo`),
  ADD KEY `fk_chamado_prestador` (`id_prestador`);

--
-- Índices de tabela `cliente`
--
ALTER TABLE `cliente`
  ADD PRIMARY KEY (`id_cliente`);

--
-- Índices de tabela `disputa`
--
ALTER TABLE `disputa`
  ADD PRIMARY KEY (`id_disputa`),
  ADD KEY `fk_disputa_chamado` (`id_chamado`);

--
-- Índices de tabela `mensagem`
--
ALTER TABLE `mensagem`
  ADD PRIMARY KEY (`id_mensagem`),
  ADD KEY `fk_mensagem_chamado` (`id_chamado`),
  ADD KEY `fk_mensagem_remetente` (`id_remetente`);

--
-- Índices de tabela `orcamento`
--
ALTER TABLE `orcamento`
  ADD PRIMARY KEY (`id_orcamento`),
  ADD KEY `fk_orcamento_chamado` (`id_chamado`),
  ADD KEY `fk_orcamento_prestador` (`id_prestador`);

--
-- Índices de tabela `pagamento`
--
ALTER TABLE `pagamento`
  ADD PRIMARY KEY (`id_pagamento`),
  ADD UNIQUE KEY `id_chamado` (`id_chamado`),
  ADD KEY `fk_pagamento_orcamento` (`id_orcamento`);

--
-- Índices de tabela `prestador`
--
ALTER TABLE `prestador`
  ADD PRIMARY KEY (`id_prestador`);

--
-- Índices de tabela `prestador_portfolio`
--
ALTER TABLE `prestador_portfolio`
  ADD PRIMARY KEY (`id_foto`),
  ADD KEY `fk_portfolio_prestador` (`id_prestador`);

--
-- Índices de tabela `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `cpf` (`cpf`);

--
-- Índices de tabela `veiculo`
--
ALTER TABLE `veiculo`
  ADD PRIMARY KEY (`id_veiculo`),
  ADD KEY `fk_veiculo_cliente` (`id_cliente`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `avaliacao`
--
ALTER TABLE `avaliacao`
  MODIFY `id_avaliacao` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `chamado`
--
ALTER TABLE `chamado`
  MODIFY `id_chamado` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `disputa`
--
ALTER TABLE `disputa`
  MODIFY `id_disputa` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `mensagem`
--
ALTER TABLE `mensagem`
  MODIFY `id_mensagem` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `orcamento`
--
ALTER TABLE `orcamento`
  MODIFY `id_orcamento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pagamento`
--
ALTER TABLE `pagamento`
  MODIFY `id_pagamento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `prestador_portfolio`
--
ALTER TABLE `prestador_portfolio`
  MODIFY `id_foto` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `veiculo`
--
ALTER TABLE `veiculo`
  MODIFY `id_veiculo` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `avaliacao`
--
ALTER TABLE `avaliacao`
  ADD CONSTRAINT `avaliacao_ibfk_1` FOREIGN KEY (`id_chamado`) REFERENCES `chamado` (`id_chamado`) ON DELETE CASCADE,
  ADD CONSTRAINT `avaliacao_ibfk_2` FOREIGN KEY (`id_avaliador`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `avaliacao_ibfk_3` FOREIGN KEY (`id_avaliado`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

--
-- Restrições para tabelas `chamado`
--
ALTER TABLE `chamado`
  ADD CONSTRAINT `fk_chamado_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_chamado_prestador` FOREIGN KEY (`id_prestador`) REFERENCES `prestador` (`id_prestador`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_chamado_veiculo` FOREIGN KEY (`id_veiculo`) REFERENCES `veiculo` (`id_veiculo`) ON DELETE CASCADE;

--
-- Restrições para tabelas `cliente`
--
ALTER TABLE `cliente`
  ADD CONSTRAINT `fk_cliente_usuario` FOREIGN KEY (`id_cliente`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

--
-- Restrições para tabelas `disputa`
--
ALTER TABLE `disputa`
  ADD CONSTRAINT `fk_disputa_chamado` FOREIGN KEY (`id_chamado`) REFERENCES `chamado` (`id_chamado`) ON DELETE CASCADE;

--
-- Restrições para tabelas `mensagem`
--
ALTER TABLE `mensagem`
  ADD CONSTRAINT `fk_mensagem_chamado` FOREIGN KEY (`id_chamado`) REFERENCES `chamado` (`id_chamado`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mensagem_remetente` FOREIGN KEY (`id_remetente`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL;

--
-- Restrições para tabelas `orcamento`
--
ALTER TABLE `orcamento`
  ADD CONSTRAINT `fk_orcamento_chamado` FOREIGN KEY (`id_chamado`) REFERENCES `chamado` (`id_chamado`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_orcamento_prestador` FOREIGN KEY (`id_prestador`) REFERENCES `prestador` (`id_prestador`) ON DELETE CASCADE;

--
-- Restrições para tabelas `pagamento`
--
ALTER TABLE `pagamento`
  ADD CONSTRAINT `fk_pagamento_chamado` FOREIGN KEY (`id_chamado`) REFERENCES `chamado` (`id_chamado`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pagamento_orcamento` FOREIGN KEY (`id_orcamento`) REFERENCES `orcamento` (`id_orcamento`) ON DELETE CASCADE;

--
-- Restrições para tabelas `prestador`
--
ALTER TABLE `prestador`
  ADD CONSTRAINT `fk_prestador_usuario` FOREIGN KEY (`id_prestador`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

--
-- Restrições para tabelas `prestador_portfolio`
--
ALTER TABLE `prestador_portfolio`
  ADD CONSTRAINT `fk_portfolio_prestador` FOREIGN KEY (`id_prestador`) REFERENCES `prestador` (`id_prestador`) ON DELETE CASCADE;

--
-- Restrições para tabelas `veiculo`
--
ALTER TABLE `veiculo`
  ADD CONSTRAINT `fk_veiculo_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
