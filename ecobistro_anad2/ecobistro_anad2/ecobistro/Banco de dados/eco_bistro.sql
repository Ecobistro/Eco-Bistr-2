-- Active: 1761077000773@@127.0.0.1@3306@mysql
-- Banco de Dados: eco_bistro
CREATE DATABASE eco_bistro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE eco_bistro;

-- Tabela de usuários
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_usuario VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    biografia TEXT,
    avatar VARCHAR(255) DEFAULT 'default-avatar.jpg',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ativo BOOLEAN DEFAULT TRUE
);

CREATE TABLE tipo_usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_usuario VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    biografia TEXT,
    avatar VARCHAR(255) DEFAULT 'default-avatar.jpg',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ativo BOOLEAN DEFAULT TRUE
);

-- Tabela de categorias
CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    slug VARCHAR(50) NOT NULL,
    descricao TEXT,
    cor VARCHAR(7) DEFAULT '#A8E6CF'
);

-- Inserir categorias do PDF
INSERT INTO categorias (nome, slug, descricao, cor) VALUES
('Comidas Veganas', 'veganas', 'Receitas 100% vegetais', '#A8E6CF'),
('Doces', 'doces', 'Sobremesas e doces deliciosos', '#FFD3A5'),
('Salgados', 'salgados', 'Pratos salgados variados', '#FD9853'),
('Bebidas', 'bebidas', 'Drinks e bebidas naturais', '#C7CEEA'),
('Comidas Rápidas', 'rapidas', 'Receitas práticas e rápidas', '#FFAAA5'),
('Comidas Saudáveis', 'saudaveis', 'Opções nutritivas', '#A8E6CF'),
('Massas', 'massas', 'Massas e pratos com massas', '#FFB7B2'),
('Reutilizando', 'reutilizando', 'Receitas sustentáveis', '#B5E48C');

-- Tabela de receitas
CREATE TABLE receitas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(100) NOT NULL,
    descricao TEXT,
    tempo_preparo INT NOT NULL, -- em minutos
    porcoes INT DEFAULT 1,
    dificuldade ENUM('Fácil', 'Médio', 'Difícil') DEFAULT 'Fácil',
    imagem VARCHAR(255),
    usuario_id INT NOT NULL,
    categoria_id INT NOT NULL,
    ingredientes TEXT NOT NULL,
    modo_preparo TEXT NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ativo BOOLEAN DEFAULT TRUE,
    visualizacoes INT DEFAULT 0,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
);

-- Tabela de ingredientes (para busca avançada)
CREATE TABLE ingredientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo ENUM('Vegetal', 'Proteína', 'Grão', 'Tempero', 'Outros') DEFAULT 'Outros'
);

-- Tabela de relacionamento receita-ingredientes
CREATE TABLE receita_ingredientes (
    receita_id INT,
    ingrediente_id INT,
    quantidade VARCHAR(50),
    PRIMARY KEY (receita_id, ingrediente_id),
    FOREIGN KEY (receita_id) REFERENCES receitas(id) ON DELETE CASCADE,
    FOREIGN KEY (ingrediente_id) REFERENCES ingredientes(id) ON DELETE CASCADE
);

-- Tabela de favoritos
CREATE TABLE favoritos (
    usuario_id INT,
    receita_id INT,
    data_favoritado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, receita_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (receita_id) REFERENCES receitas(id) ON DELETE CASCADE
);

-- Tabela de pastas de receitas
CREATE TABLE pastas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    usuario_id INT NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabela de relacionamento pasta-receitas
CREATE TABLE pasta_receitas (
    pasta_id INT,
    receita_id INT,
    PRIMARY KEY (pasta_id, receita_id),
    FOREIGN KEY (pasta_id) REFERENCES pastas(id) ON DELETE CASCADE,
    FOREIGN KEY (receita_id) REFERENCES receitas(id) ON DELETE CASCADE
);

-- Tabela de comentários
CREATE TABLE comentarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receita_id INT NOT NULL,
    usuario_id INT NOT NULL,
    comentario TEXT NOT NULL,
    data_comentario TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ativo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (receita_id) REFERENCES receitas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabela de curtidas
CREATE TABLE curtidas (
    usuario_id INT,
    receita_id INT,
    data_curtida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, receita_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (receita_id) REFERENCES receitas(id) ON DELETE CASCADE
);

-- Tabela de seguidores
CREATE TABLE seguidores (
    seguidor_id INT,
    seguido_id INT,
    data_seguimento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (seguidor_id, seguido_id),
    FOREIGN KEY (seguidor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (seguido_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Dados de exemplo
-- Usuário exemplo
INSERT INTO usuarios (nome_usuario, email, senha, biografia) VALUES
('user1', 'user1@ecobistro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Apaixonado por culinária sustentável!');

-- Ingredientes básicos
INSERT INTO ingredientes (nome, tipo) VALUES
('Milharina', 'Grão'),
('Água', 'Outros'),
('Sal', 'Tempero'),
('Tofu', 'Proteína'),
('Grão de bico', 'Grão'),
('Batata doce', 'Vegetal'),
('Arroz integral', 'Grão'),
('Legumes variados', 'Vegetal');

-- Receita exemplo: Cuscuz
INSERT INTO receitas (titulo, descricao, tempo_preparo, porcoes, usuario_id, categoria_id, ingredientes, modo_preparo, imagem) VALUES
('Cuscuz', 'Cuscuz tradicional, prático e delicioso', 15, 4, 1, 1, '300g de milharina;1 copo de água;sal a gosto', '1. Coloque a milharina e o sal em um recipiente;2. Acrescente água aos poucos e mexa bem;3. Após a mistura, coloque tudo em uma cuscuzeira e leve ao fogo;4. Quando a água da cuscuzeira começar a ferver coloque em fogo baixo e deixe no vapor por 5 minutos;5. Sirva', 'cuscuz.jpg');

-- Adicionar colunas para foto de perfil na tabela usuarios
ALTER TABLE usuarios ADD COLUMN foto_perfil VARCHAR(255) DEFAULT NULL;

-- Criar pasta para uploads de fotos de perfil
-- mkdir uploads/perfil (criar manualmente)

-- Dados de exemplo com foto
UPDATE usuarios SET foto_perfil = 'default-avatar.jpg' WHERE id = 1;

SHOW TABLES LIKE 'seguidores';

ALTER TABLE comentarios ADD COLUMN data_editado DATETIME NULL AFTER data_comentario;
__________________________________________________________________________________

-- Atualizações no banco de dados para suporte a pastas especiais
USE eco_bistro;

-- Alterar tabela pasta_receitas para suportar pastas especiais
ALTER TABLE pasta_receitas ADD COLUMN usuario_id INT NULL AFTER pasta_id;
ALTER TABLE pasta_receitas ADD COLUMN data_adicionado DATETIME DEFAULT CURRENT_TIMESTAMP;

-- Alterar chave primária para permitir pastas especiais n
ALTER TABLE pasta_receitas DROP PRIMARY KEY;
ALTER TABLE pasta_receitas ADD PRIMARY KEY (receita_id, pasta_id, COALESCE(usuario_id, 0));

-- Adicionar índices para performance
CREATE INDEX idx_pasta_receitas_usuario ON pasta_receitas(usuario_id);
CREATE INDEX idx_pasta_receitas_data ON pasta_receitas(data_adicionado);

-- Inserir algumas receitas de exemplo para testar
INSERT INTO receitas (titulo, descricao, tempo_preparo, porcoes, usuario_id, categoria_id, ingredientes, modo_preparo, imagem) VALUES
('Salada de Quinoa', 'Salada nutritiva com quinoa e legumes', 20, 2, 1, 6, '1 xícara de quinoa;2 tomates;1 pepino;Azeite;Limão;Sal', '1. Cozinhe a quinoa;2. Corte os legumes;3. Misture tudo;4. Tempere com azeite, limão e sal', NULL),
('Smoothie Verde', 'Vitamina verde refrescante', 5, 1, 1, 4, '1 banana;Folhas de espinafre;1 maçã;Água de coco;Mel', '1. Bata todos os ingredientes no liquidificador;2. Sirva gelado', NULL),
('Pão Integral', 'Pão caseiro integral', 180, 8, 1, 3, '2 xícaras de farinha integral;1 colher de fermento;Água morna;Sal;Azeite', '1. Misture os ingredientes secos;2. Adicione a água e azeite;3. Sove a massa;4. Deixe descansar;5. Asse por 40 minutos', NULL);

-- Criar view para facilitar consultas de pastas especiais
CREATE VIEW vw_pasta_receitas_completa AS
SELECT 
    pr.receita_id,
    pr.pasta_id,
    pr.usuario_id as pasta_usuario_id,
    pr.data_adicionado,
    r.titulo,
    r.imagem,
    r.tempo_preparo,
    r.usuario_id as receita_usuario_id,
    u.nome_usuario,
    c.nome as categoria_nome,
    CASE 
        WHEN pr.pasta_id = 'favoritos' THEN 'Favoritos'
        WHEN pr.pasta_id = 'fazer-mais-tarde' THEN 'Fazer mais tarde'
        ELSE p.nome
    END as pasta_nome
FROM pasta_receitas pr
JOIN receitas r ON pr.receita_id = r.id
JOIN usuarios u ON r.usuario_id = u.id
JOIN categorias c ON r.categoria_id = c.id
LEFT JOIN pastas p ON pr.pasta_id = p.id
WHERE r.ativo = 1;

-- Procedure para adicionar receita aos favoritos
DELIMITER //
CREATE PROCEDURE sp_adicionar_favorito(
    IN p_usuario_id INT,
    IN p_receita_id INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Adicionar aos favoritos (se não existir)
    INSERT IGNORE INTO favoritos (usuario_id, receita_id) 
    VALUES (p_usuario_id, p_receita_id);
    
    -- Adicionar também na tabela pasta_receitas para compatibilidade
    INSERT IGNORE INTO pasta_receitas (receita_id, pasta_id, usuario_id) 
    VALUES (p_receita_id, 'favoritos', p_usuario_id);
    
    COMMIT;
END //
DELIMITER ;

-- Procedure para remover receita dos favoritos
DELIMITER //
CREATE PROCEDURE sp_remover_favorito(
    IN p_usuario_id INT,
    IN p_receita_id INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Remover dos favoritos
    DELETE FROM favoritos 
    WHERE usuario_id = p_usuario_id AND receita_id = p_receita_id;
    
    -- Remover da tabela pasta_receitas
    DELETE FROM pasta_receitas 
    WHERE receita_id = p_receita_id AND pasta_id = 'favoritos' AND usuario_id = p_usuario_id;
    
    COMMIT;
END //
DELIMITER ;

-- Inserir alguns dados de exemplo nas pastas especiais N
INSERT INTO pasta_receitas (receita_id, pasta_id, usuario_id) VALUES
(1, 'fazer-mais-tarde', 1),
(2, 'fazer-mais-tarde', 1);

-- Verificar se as tabelas estão funcionando corretamente
SELECT 'Verificação das tabelas:' as status;
SELECT COUNT(*) as total_receitas FROM receitas WHERE ativo = 1;
SELECT COUNT(*) as total_pastas FROM pastas;
SELECT COUNT(*) as total_pasta_receitas FROM pasta_receitas;
SELECT COUNT(*) as total_favoritos FROM favoritos;

-- Mostrar estrutura das tabelas atualizadas
DESCRIBE pasta_receitas;
DESCRIBE pastas;
------------------------------
-- Script para adicionar suporte a seções opcionais nas receitas
-- Execute este script no seu banco de dados eco_bistro

USE eco_bistro;

-- Adicionar novas colunas na tabela receitas
ALTER TABLE receitas 
ADD COLUMN tem_cobertura BOOLEAN DEFAULT FALSE AFTER modo_preparo,
ADD COLUMN tem_recheio BOOLEAN DEFAULT FALSE AFTER tem_cobertura,
ADD COLUMN ingredientes_cobertura TEXT NULL AFTER tem_recheio,
ADD COLUMN modo_preparo_cobertura TEXT NULL AFTER ingredientes_cobertura,
ADD COLUMN ingredientes_recheio TEXT NULL AFTER modo_preparo_cobertura,
ADD COLUMN modo_preparo_recheio TEXT NULL AFTER ingredientes_recheio;

-- Criar índices para melhor performance nas consultas
CREATE INDEX idx_receitas_cobertura ON receitas(tem_cobertura);
CREATE INDEX idx_receitas_recheio ON receitas(tem_recheio);

-- Inserir algumas receitas de exemplo com seções opcionais
INSERT INTO receitas (
    titulo, descricao, tempo_preparo, porcoes, dificuldade, 
    usuario_id, categoria_id, ingredientes, modo_preparo,
    tem_cobertura, ingredientes_cobertura, modo_preparo_cobertura,
    tem_recheio, ingredientes_recheio, modo_preparo_recheio
) VALUES (
    'Bolo de Chocolate com Cobertura',
    'Delicioso bolo de chocolate com cobertura cremosa',
    90, 12, 'Médio', 1, 2,
    '2 xícaras de farinha;3 ovos;1 xícara de açúcar;1/2 xícara de óleo;1 xícara de chocolate em pó;1 colher de fermento',
    '1. Preaqueça o forno a 180°C;2. Misture os ingredientes secos;3. Bata os ovos com açúcar;4. Incorpore os líquidos aos secos;5. Asse por 40 minutos',
    TRUE,
    '200g de chocolate meio amargo;1 xícara de creme de leite;2 colheres de manteiga',
    '1. Derreta o chocolate em banho-maria;2. Adicione o creme de leite;3. Incorpore a manteiga até ficar homogênea',
    FALSE, NULL, NULL
);

INSERT INTO receitas (
    titulo, descricao, tempo_preparo, porcoes, dificuldade, 
    usuario_id, categoria_id, ingredientes, modo_preparo,
    tem_cobertura, ingredientes_cobertura, modo_preparo_cobertura,
    tem_recheio, ingredientes_recheio, modo_preparo_recheio
) VALUES (
    'Torta de Morango Especial',
    'Torta com recheio cremoso e cobertura de morangos frescos',
    120, 10, 'Difícil', 1, 2,
    '2 xícaras de farinha;4 ovos;1 xícara de açúcar;1/2 xícara de manteiga;1 colher de fermento',
    '1. Faça a massa da torta;2. Asse por 25 minutos;3. Deixe esfriar completamente',
    TRUE,
    '500g de morangos frescos;2 colheres de açúcar;1 pacote de gelatina sem sabor',
    '1. Corte os morangos em fatias;2. Dissolva a gelatina;3. Misture com os morangos e açúcar;4. Espalhe sobre a torta',
    TRUE,
    '400g de creme de leite fresco;200g de cream cheese;1/2 xícara de açúcar de confeiteiro;1 colher de essência de baunilha',
    '1. Bata o creme de leite até formar picos;2. Misture o cream cheese com açúcar;3. Incorpore a baunilha;4. Misture delicadamente com o creme batido'
);

-- Criar view para facilitar consultas com seções opcionais
CREATE VIEW vw_receitas_completas AS
SELECT 
    r.*,
    u.nome_usuario,
    c.nome as categoria_nome,
    c.slug as categoria_slug,
    CASE 
        WHEN r.tem_cobertura = 1 AND r.tem_recheio = 1 THEN 'Cobertura e Recheio'
        WHEN r.tem_cobertura = 1 THEN 'Com Cobertura'
        WHEN r.tem_recheio = 1 THEN 'Com Recheio'
        ELSE 'Simples'
    END as tipo_receita,
    (SELECT COUNT(*) FROM favoritos f WHERE f.receita_id = r.id) as total_favoritos,
    (SELECT COUNT(*) FROM curtidas cu WHERE cu.receita_id = r.id) as total_curtidas,
    (SELECT COUNT(*) FROM comentarios co WHERE co.receita_id = r.id AND co.ativo = 1) as total_comentarios
FROM receitas r
JOIN usuarios u ON r.usuario_id = u.id
JOIN categorias c ON r.categoria_id = c.id
WHERE r.ativo = 1;

-- Função para contar seções adicionais de uma receita
DELIMITER //
CREATE FUNCTION fn_contar_secoes_adicionais(receita_id INT) 
RETURNS INT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE total_secoes INT DEFAULT 0;
    
    SELECT 
        COALESCE(tem_cobertura, 0) + COALESCE(tem_recheio, 0)
    INTO total_secoes
    FROM receitas 
    WHERE id = receita_id;
    
    RETURN COALESCE(total_secoes, 0);
END //
DELIMITER ;

-- Procedure para buscar receitas com filtro por tipo
DELIMITER //
-- Script SQL simplificado para adicionar seções opcionais
-- Execute este script no seu banco eco_bistro

USE eco_bistro;

-- Adicionar as novas colunas na tabela receitas

-- Verificar se as colunas foram adicionadas
DESCRIBE receitas;

-- Inserir uma receita de exemplo para testar
INSERT INTO receitas (
    titulo, descricao, tempo_preparo, porcoes, dificuldade, 
    usuario_id, categoria_id, ingredientes, modo_preparo,
    tem_cobertura, ingredientes_cobertura, modo_preparo_cobertura,
    tem_recheio, ingredientes_recheio, modo_preparo_recheio
) VALUES (
    'Bolo de Chocolate Especial',
    'Bolo delicioso com cobertura e recheio',
    120, 12, 'Médio', 1, 2,
    '2 xícaras de farinha;3 ovos;1 xícara de açúcar;1/2 xícara de óleo',
    '1. Misture os ingredientes secos;2. Adicione os líquidos;3. Asse por 40 minutos',
    1,
    '200g de chocolate;1 xícara de creme de leite;2 colheres de manteiga',
    '1. Derreta o chocolate;2. Adicione o creme;3. Misture a manteiga',
    1,
    '400g de doce de leite;200g de coco ralado',
    '1. Misture o doce de leite com coco;2. Mexa até ficar homogêneo'
);

-- Verificar se funcionou
SELECT 
    titulo, 
    tem_cobertura, 
    tem_recheio,
    CASE 
        WHEN tem_cobertura = 1 AND tem_recheio = 1 THEN 'Cobertura e Recheio'
        WHEN tem_cobertura = 1 THEN 'Só Cobertura'  
        WHEN tem_recheio = 1 THEN 'Só Recheio'
        ELSE 'Simples'
    END as tipo_receita
FROM receitas 
ORDER BY id DESC 
LIMIT 5;
DELIMITER ;

-- Verificações finais
SELECT 'Verificação das novas colunas:' as status;
DESCRIBE receitas;

SELECT 'Total de receitas por tipo:' as info;
SELECT 
    CASE 
        WHEN tem_cobertura = 1 AND tem_recheio = 1 THEN 'Cobertura e Recheio'
        WHEN tem_cobertura = 1 THEN 'Só Cobertura'
        WHEN tem_recheio = 1 THEN 'Só Recheio'
        ELSE 'Simples'
    END as tipo,
    COUNT(*) as quantidade
FROM receitas 
WHERE ativo = 1
GROUP BY tem_cobertura, tem_recheio;

-- Exemplos de uso das novas funcionalidades:
-- CALL sp_buscar_receitas_por_tipo('cobertura', 5);
-- CALL sp_buscar_receitas_por_tipo('completa', 10);
-- SELECT *, fn_contar_secoes_adicionais(id) as secoes FROM receitas WHERE id = 1;
UPDATE receitas 
SET tem_cobertura = 1, 
    ingredientes_cobertura = '200g de chocolate;1 xícara de creme de leite',
    modo_preparo_cobertura = '1. Derreta o chocolate;2. Misture com o creme de leite'
WHERE id = 1;

----------------------------------------------------------------------------------------------------------------------------------------

-- Adicionar colunas para seção "Outros" na tabela receitas
USE eco_bistro;

ALTER TABLE receitas 
ADD COLUMN tem_outros BOOLEAN DEFAULT FALSE AFTER tem_recheio,
ADD COLUMN titulo_outros VARCHAR(100) NULL AFTER tem_outros,
ADD COLUMN ingredientes_outros TEXT NULL AFTER titulo_outros,
ADD COLUMN modo_preparo_outros TEXT NULL AFTER ingredientes_outros;

-- Criar índice para melhor performance
CREATE INDEX idx_receitas_outros ON receitas(tem_outros);

-- Atualizar view existente para incluir a nova seção
DROP VIEW IF EXISTS vw_receitas_completas;

CREATE VIEW vw_receitas_completas AS
SELECT 
    r.*,
    u.nome_usuario,
    c.nome as categoria_nome,
    c.slug as categoria_slug,
    CASE 
        WHEN r.tem_cobertura = 1 AND r.tem_recheio = 1 AND r.tem_outros = 1 THEN 'Completa (Cobertura, Recheio e Outros)'
        WHEN r.tem_cobertura = 1 AND r.tem_recheio = 1 THEN 'Cobertura e Recheio'
        WHEN r.tem_cobertura = 1 AND r.tem_outros = 1 THEN 'Cobertura e Outros'
        WHEN r.tem_recheio = 1 AND r.tem_outros = 1 THEN 'Recheio e Outros'
        WHEN r.tem_cobertura = 1 THEN 'Com Cobertura'
        WHEN r.tem_recheio = 1 THEN 'Com Recheio'
        WHEN r.tem_outros = 1 THEN 'Com Outros'
        ELSE 'Simples'
    END as tipo_receita,
    (SELECT COUNT(*) FROM favoritos f WHERE f.receita_id = r.id) as total_favoritos,
    (SELECT COUNT(*) FROM curtidas cu WHERE cu.receita_id = r.id) as total_curtidas,
    (SELECT COUNT(*) FROM comentarios co WHERE co.receita_id = r.id AND co.ativo = 1) as total_comentarios
FROM receitas r
JOIN usuarios u ON r.usuario_id = u.id
JOIN categorias c ON r.categoria_id = c.id
WHERE r.ativo = 1;

-- Atualizar função para contar seções adicionais
DROP FUNCTION IF EXISTS fn_contar_secoes_adicionais;

DELIMITER //
CREATE FUNCTION fn_contar_secoes_adicionais(receita_id INT) 
RETURNS INT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE total_secoes INT DEFAULT 0;
    
    SELECT 
        COALESCE(tem_cobertura, 0) + COALESCE(tem_recheio, 0) + COALESCE(tem_outros, 0)
    INTO total_secoes
    FROM receitas 
    WHERE id = receita_id;
    
    RETURN COALESCE(total_secoes, 0);
END //
DELIMITER ;

-- Inserir receita de exemplo com seção "Outros"
INSERT INTO receitas (
    titulo, descricao, tempo_preparo, porcoes, dificuldade, 
    usuario_id, categoria_id, ingredientes, modo_preparo,
    tem_cobertura, ingredientes_cobertura, modo_preparo_cobertura,
    tem_recheio, ingredientes_recheio, modo_preparo_recheio,
    tem_outros, titulo_outros, ingredientes_outros, modo_preparo_outros
) VALUES (
    'Torta Especial Completa',
    'Torta elaborada com massa, recheio, cobertura e decoração especial',
    180, 16, 'Difícil', 1, 2,
    '3 xícaras de farinha;4 ovos;1 xícara de açúcar;1/2 xícara de manteiga;1 colher de fermento',
    '1. Faça a massa da torta;2. Asse por 30 minutos;3. Deixe esfriar completamente',
    1,
    '300g de chocolate branco;1 xícara de creme de leite;Corante alimentício azul',
    '1. Derreta o chocolate branco;2. Misture com o creme;3. Adicione corante para cor azul claro',
    1,
    '500g de mousse de maracujá;200g de gelatina incolor;Polpa de maracujá',
    '1. Prepare o mousse;2. Dissolva a gelatina;3. Misture tudo delicadamente',
    1,
    'Decoração de Flores Comestíveis',
    'Flores comestíveis variadas;Açúcar cristal;Corante alimentício;Folhas de hortelã',
    '1. Lave e seque as flores comestíveis;2. Pincele com clara em neve;3. Polvilhe açúcar cristal;4. Deixe secar;5. Decore a torta com as flores'
);

-- Verificar se as alterações foram aplicadas
DESCRIBE receitas;

-- Mostrar estatísticas atualizadas
SELECT 'Total de receitas por tipo após atualização:' as info;
SELECT 
    CASE 
        WHEN tem_cobertura = 1 AND tem_recheio = 1 AND tem_outros = 1 THEN 'Completa'
        WHEN tem_cobertura = 1 AND tem_recheio = 1 THEN 'Cobertura e Recheio'
        WHEN tem_cobertura = 1 AND tem_outros = 1 THEN 'Cobertura e Outros'
        WHEN tem_recheio = 1 AND tem_outros = 1 THEN 'Recheio e Outros'
        WHEN tem_cobertura = 1 THEN 'Só Cobertura'
        WHEN tem_recheio = 1 THEN 'Só Recheio'
        WHEN tem_outros = 1 THEN 'Só Outros'
        ELSE 'Simples'
    END as tipo,
    COUNT(*) as quantidade
FROM receitas 
WHERE ativo = 1
GROUP BY tem_cobertura, tem_recheio, tem_outros
ORDER BY quantidade DESC;