-- Active: 1761077000773@@127.0.0.1@3306@mysql
-- Atualização do banco de dados para incluir funcionalidades administrativas
USE eco_bistro;

-- Adicionar campo tipo_usuario na tabela usuarios (se não existir)
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS tipo_usuario ENUM('usuario', 'admin') DEFAULT 'usuario';

-- Criar usuário administrador padrão
INSERT INTO usuarios (nome_usuario, email, senha, biografia, tipo_usuario) VALUES
('admin', 'admin@ecobistro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador do sistema Eco Bistrô', 'admin')
ON DUPLICATE KEY UPDATE tipo_usuario = 'admin';

-- Tabela para logs do sistema
CREATE TABLE IF NOT EXISTS logs_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    acao VARCHAR(100) NOT NULL,
    descricao TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    data_log TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Tabela para configurações do sistema
CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    descricao TEXT,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Inserir configurações padrão
INSERT INTO configuracoes (chave, valor, descricao) VALUES
('site_titulo', 'Eco Bistrô', 'Título do site'),
('site_descricao', 'Seu site favorito de receitas sustentáveis', 'Descrição do site'),
('receitas_por_pagina', '12', 'Número de receitas por página'),
('permitir_comentarios', '1', 'Permitir comentários nas receitas'),
('moderacao_comentarios', '0', 'Moderar comentários antes de publicar'),
('email_contato', 'ecobistro@gmail.com', 'E-mail de contato'),
('telefone_contato', '(12) 3456-7890', 'Telefone de contato')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

-- Tabela para relatórios agendados
CREATE TABLE IF NOT EXISTS relatorios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo ENUM('usuarios', 'receitas', 'categorias', 'atividade') NOT NULL,
    filtros JSON,
    usuario_id INT NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_execucao TIMESTAMP NULL,
    status ENUM('pendente', 'processando', 'concluido', 'erro') DEFAULT 'pendente',
    resultado_arquivo VARCHAR(255),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabela para notificações do sistema
CREATE TABLE IF NOT EXISTS notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo ENUM('sistema', 'receita', 'comentario', 'seguidor') NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    mensagem TEXT NOT NULL,
    lida BOOLEAN DEFAULT FALSE,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_leitura TIMESTAMP NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Índices para melhor performance
CREATE INDEX IF NOT EXISTS idx_receitas_categoria ON receitas(categoria_id);
CREATE INDEX IF NOT EXISTS idx_receitas_usuario ON receitas(usuario_id);
CREATE INDEX IF NOT EXISTS idx_receitas_data ON receitas(data_criacao);
CREATE INDEX IF NOT EXISTS idx_comentarios_receita ON comentarios(receita_id);
CREATE INDEX IF NOT EXISTS idx_favoritos_usuario ON favoritos(usuario_id);
CREATE INDEX IF NOT EXISTS idx_logs_usuario ON logs_sistema(usuario_id);
CREATE INDEX IF NOT EXISTS idx_logs_data ON logs_sistema(data_log);
CREATE INDEX IF NOT EXISTS idx_notificacoes_usuario ON notificacoes(usuario_id, lida);

-- View para estatísticas rápidas
CREATE OR REPLACE VIEW estatisticas_rapidas AS
SELECT 
    'usuarios_ativos' as metrica,
    COUNT(*) as valor
FROM usuarios 
WHERE ativo = 1

UNION ALL

SELECT 
    'receitas_publicadas' as metrica,
    COUNT(*) as valor
FROM receitas 
WHERE ativo = 1

UNION ALL

SELECT 
    'comentarios_total' as metrica,
    COUNT(*) as valor
FROM comentarios 
WHERE ativo = 1

UNION ALL

SELECT 
    'favoritos_total' as metrica,
    COUNT(*) as valor
FROM favoritos

UNION ALL

SELECT 
    'receitas_hoje' as metrica,
    COUNT(*) as valor
FROM receitas 
WHERE ativo = 1 AND DATE(data_criacao) = CURDATE()

UNION ALL

SELECT 
    'usuarios_hoje' as metrica,
    COUNT(*) as valor
FROM usuarios 
WHERE ativo = 1 AND DATE(data_criacao) = CURDATE();

-- Procedure para limpeza de dados antigos
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS LimparDadosAntigos()
BEGIN
    -- Limpar logs antigos (mais de 90 dias)
    DELETE FROM logs_sistema WHERE data_log < DATE_SUB(NOW(), INTERVAL 90 DAY);
    
    -- Limpar notificações lidas antigas (mais de 30 dias)
    DELETE FROM notificacoes 
    WHERE lida = TRUE AND data_leitura < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- Limpar relatórios antigos (mais de 30 dias)
    DELETE FROM relatorios 
    WHERE status = 'concluido' AND data_execucao < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    SELECT 'Limpeza concluída' as resultado;
END //
DELIMITER ;

-- Trigger para log de ações em receitas
DELIMITER //
CREATE TRIGGER IF NOT EXISTS log_receita_insert
    AFTER INSERT ON receitas
    FOR EACH ROW
BEGIN
    INSERT INTO logs_sistema (usuario_id, acao, descricao)
    VALUES (NEW.usuario_id, 'RECEITA_CRIADA', CONCAT('Receita criada: ', NEW.titulo));
END //

CREATE TRIGGER IF NOT EXISTS log_receita_update
    AFTER UPDATE ON receitas
    FOR EACH ROW
BEGIN
    IF OLD.ativo != NEW.ativo THEN
        INSERT INTO logs_sistema (usuario_id, acao, descricao)
        VALUES (NEW.usuario_id, 'RECEITA_STATUS_ALTERADO', 
                CONCAT('Status da receita alterado: ', NEW.titulo));
    END IF;
END //
DELIMITER ;

-- Dados de exemplo adicionais para demonstração
INSERT INTO receitas (titulo, descricao, tempo_preparo, porcoes, usuario_id, categoria_id, ingredientes, modo_preparo) VALUES
('Tofu Empanado Crocante', 'Tofu empanado delicioso e crocante', 20, 2, 1, 1, '200g de tofu firme;100g de farinha de rosca;2 ovos;sal a gosto;temperos a gosto', '1. Corte o tofu em fatias;2. Tempere o tofu;3. Passe no ovo batido;4. Empane na farinha de rosca;5. Frite até dourar'),
('Hambúrguer de Grão de Bico', 'Hambúrguer vegano saboroso', 30, 4, 1, 1, '300g de grão de bico cozido;1 cebola;2 dentes de alho;temperos;farinha de aveia', '1. Processe o grão de bico;2. Refogue cebola e alho;3. Misture todos os ingredientes;4. Modele os hambúrgueres;5. Asse por 15 minutos'),
('Chips de Batata Doce', 'Chips assados e crocantes', 25, 3, 1, 6, '2 batatas doces médias;azeite;sal;páprica', '1. Corte a batata em fatias finas;2. Tempere com azeite e sal;3. Asse a 180°C por 20 minutos;4. Vire na metade do tempo;5. Sirva quente');

-- Atualizar senha do usuário admin para 'admin123'
UPDATE usuarios SET senha = '$2y$10$5.6iVvV5b4X0hWhHK5ncs.OiJg0Q5oQW5Oz0J8v8vdXgV4Qd6Oe5m' 
WHERE nome_usuario = 'admin';