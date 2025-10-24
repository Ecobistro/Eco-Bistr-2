-- Active: 1761091826964@@127.0.0.1@3306
-- Script para verificar e corrigir o banco de dados do Eco Bistrô
-- Execute este script para garantir que todas as funcionalidades estão funcionando

USE eco_bistro;

-- Verificar se todas as tabelas existem
SELECT 'Verificando tabelas...' as status;

SHOW TABLES;

-- Verificar estrutura da tabela usuarios
SELECT 'Estrutura da tabela usuarios:' as info;
DESCRIBE usuarios;

-- Verificar estrutura da tabela receitas
SELECT 'Estrutura da tabela receitas:' as info;
DESCRIBE receitas;

-- Verificar estrutura da tabela comentarios
SELECT 'Estrutura da tabela comentarios:' as info;
DESCRIBE comentarios;

-- Verificar estrutura da tabela curtidas
SELECT 'Estrutura da tabela curtidas:' as info;
DESCRIBE curtidas;

-- Verificar estrutura da tabela favoritos
SELECT 'Estrutura da tabela favoritos:' as info;
DESCRIBE favoritos;

-- Verificar estrutura da tabela seguidores
SELECT 'Estrutura da tabela seguidores:' as info;
DESCRIBE seguidores;

-- Verificar estrutura da tabela pastas
SELECT 'Estrutura da tabela pastas:' as info;
DESCRIBE pastas;

-- Verificar estrutura da tabela pasta_receitas
SELECT 'Estrutura da tabela pasta_receitas:' as info;
DESCRIBE pasta_receitas;

-- Verificar se as tabelas de preferências existem
SELECT 'Verificando tabelas de preferências...' as status;

SHOW TABLES LIKE '%preferencias%';

-- Se as tabelas de preferências não existirem, criá-las
CREATE TABLE IF NOT EXISTS preferencias_receitas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo_preferencia ENUM('gosto_alimentar', 'restricao_alimentar', 'condicao_medica', 'preferencia_culinaria') NOT NULL,
    valor VARCHAR(100) NOT NULL,
    descricao TEXT,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ativo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_preferencia (usuario_id, tipo_preferencia, valor)
);

CREATE TABLE IF NOT EXISTS tipos_preferencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('gosto_alimentar', 'restricao_alimentar', 'condicao_medica', 'preferencia_culinaria') NOT NULL,
    valor VARCHAR(100) NOT NULL,
    descricao TEXT,
    icone VARCHAR(10),
    ativo BOOLEAN DEFAULT TRUE,
    UNIQUE KEY unique_tipo_valor (tipo, valor)
);

-- Adicionar coluna preferencias_configuradas se não existir
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS preferencias_configuradas BOOLEAN DEFAULT FALSE;

-- Adicionar colunas de preferências na tabela receitas se não existirem
ALTER TABLE receitas ADD COLUMN IF NOT EXISTS vegano BOOLEAN DEFAULT FALSE;
ALTER TABLE receitas ADD COLUMN IF NOT EXISTS vegetariano BOOLEAN DEFAULT FALSE;
ALTER TABLE receitas ADD COLUMN IF NOT EXISTS sem_gluten BOOLEAN DEFAULT FALSE;
ALTER TABLE receitas ADD COLUMN IF NOT EXISTS sem_lactose BOOLEAN DEFAULT FALSE;
ALTER TABLE receitas ADD COLUMN IF NOT EXISTS sem_acucar BOOLEAN DEFAULT FALSE;
ALTER TABLE receitas ADD COLUMN IF NOT EXISTS sem_sodio BOOLEAN DEFAULT FALSE;
ALTER TABLE receitas ADD COLUMN IF NOT EXISTS saudavel BOOLEAN DEFAULT FALSE;

-- Adicionar coluna foto_perfil se não existir
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS foto_perfil VARCHAR(255) DEFAULT NULL;

-- Adicionar coluna data_editado na tabela comentarios se não existir
ALTER TABLE comentarios ADD COLUMN IF NOT EXISTS data_editado DATETIME NULL AFTER data_comentario;

-- Inserir tipos de preferências se não existirem
INSERT IGNORE INTO tipos_preferencias (tipo, valor, descricao, icone) VALUES
-- Gostos alimentares
('gosto_alimentar', 'doce', 'Prefere receitas doces', '🍰'),
('gosto_alimentar', 'salgado', 'Prefere receitas salgadas', '🍽️'),
('gosto_alimentar', 'picante', 'Gosta de comidas apimentadas', '🌶️'),
('gosto_alimentar', 'azedo', 'Gosta de sabores ácidos', '🍋'),
('gosto_alimentar', 'amargo', 'Aprecia sabores amargos', '☕'),

-- Restrições alimentares
('restricao_alimentar', 'vegano', 'Não consome produtos de origem animal', '🌱'),
('restricao_alimentar', 'vegetariano', 'Não consome carne', '🥬'),
('restricao_alimentar', 'sem_gluten', 'Não pode consumir glúten', '🌾'),
('restricao_alimentar', 'sem_lactose', 'Não pode consumir lactose', '🥛'),
('restricao_alimentar', 'sem_acucar', 'Evita açúcar refinado', '🍯'),
('restricao_alimentar', 'sem_oleo', 'Evita óleos processados', '🫒'),
('restricao_alimentar', 'low_carb', 'Prefere baixo carboidrato', '🥑'),
('restricao_alimentar', 'keto', 'Segue dieta cetogênica', '🥓'),

-- Condições médicas
('condicao_medica', 'diabetes', 'Tem diabetes', '🩺'),
('condicao_medica', 'hipertensao', 'Tem hipertensão', '❤️'),
('condicao_medica', 'colesterol_alto', 'Tem colesterol alto', '🫀'),
('condicao_medica', 'intolerancia_lactose', 'Intolerante à lactose', '🥛'),
('condicao_medica', 'doenca_celiaca', 'Doença celíaca', '🌾'),
('condicao_medica', 'gastrite', 'Tem gastrite', '🫁'),
('condicao_medica', 'refluxo', 'Tem refluxo gastroesofágico', '🫁'),

-- Preferências culinárias
('preferencia_culinaria', 'rapido', 'Prefere receitas rápidas', '⚡'),
('preferencia_culinaria', 'simples', 'Prefere receitas simples', '👌'),
('preferencia_culinaria', 'elaborado', 'Gosta de receitas elaboradas', '👨‍🍳'),
('preferencia_culinaria', 'sustentavel', 'Prefere ingredientes sustentáveis', '♻️'),
('preferencia_culinaria', 'organico', 'Prefere ingredientes orgânicos', '🌿'),
('preferencia_culinaria', 'local', 'Prefere ingredientes locais', '🏠'),
('preferencia_culinaria', 'saudavel', 'Prioriza receitas saudáveis', '💚'),
('preferencia_culinaria', 'economico', 'Prefere receitas econômicas', '💰');

-- Criar índices para melhor performance
CREATE INDEX IF NOT EXISTS idx_preferencias_usuario ON preferencias_receitas(usuario_id);
CREATE INDEX IF NOT EXISTS idx_preferencias_tipo ON preferencias_receitas(tipo_preferencia);
CREATE INDEX IF NOT EXISTS idx_tipos_preferencias_tipo ON tipos_preferencias(tipo);

-- Índices para as colunas de receitas
CREATE INDEX IF NOT EXISTS idx_receitas_vegano ON receitas(vegano);
CREATE INDEX IF NOT EXISTS idx_receitas_vegetariano ON receitas(vegetariano);
CREATE INDEX IF NOT EXISTS idx_receitas_sem_gluten ON receitas(sem_gluten);
CREATE INDEX IF NOT EXISTS idx_receitas_sem_lactose ON receitas(sem_lactose);
CREATE INDEX IF NOT EXISTS idx_receitas_sem_acucar ON receitas(sem_acucar);
CREATE INDEX IF NOT EXISTS idx_receitas_sem_sodio ON receitas(sem_sodio);
CREATE INDEX IF NOT EXISTS idx_receitas_saudavel ON receitas(saudavel);

-- Verificar contagem de registros
SELECT 'Contagem de registros:' as info;

SELECT 'usuarios' as tabela, COUNT(*) as total FROM usuarios
UNION ALL
SELECT 'receitas' as tabela, COUNT(*) as total FROM receitas
UNION ALL
SELECT 'comentarios' as tabela, COUNT(*) as total FROM comentarios
UNION ALL
SELECT 'curtidas' as tabela, COUNT(*) as total FROM curtidas
UNION ALL
SELECT 'favoritos' as tabela, COUNT(*) as total FROM favoritos
UNION ALL
SELECT 'seguidores' as tabela, COUNT(*) as total FROM seguidores
UNION ALL
SELECT 'pastas' as tabela, COUNT(*) as total FROM pastas
UNION ALL
SELECT 'pasta_receitas' as tabela, COUNT(*) as total FROM pasta_receitas
UNION ALL
SELECT 'preferencias_receitas' as tabela, COUNT(*) as total FROM preferencias_receitas
UNION ALL
SELECT 'tipos_preferencias' as tabela, COUNT(*) as total FROM tipos_preferencias
UNION ALL
SELECT 'categorias' as tabela, COUNT(*) as total FROM categorias;

-- Verificar se há dados de exemplo
SELECT 'Verificando dados de exemplo...' as status;

-- Se não há categorias, inserir algumas básicas
INSERT IGNORE INTO categorias (nome, slug, descricao, cor) VALUES
('Comidas Veganas', 'veganas', 'Receitas 100% vegetais', '#A8E6CF'),
('Doces', 'doces', 'Sobremesas e doces deliciosos', '#FFD3A5'),
('Salgados', 'salgados', 'Pratos salgados variados', '#FD9853'),
('Bebidas', 'bebidas', 'Drinks e bebidas naturais', '#C7CEEA'),
('Comidas Rápidas', 'rapidas', 'Receitas práticas e rápidas', '#FFAAA5'),
('Comidas Saudáveis', 'saudaveis', 'Opções nutritivas', '#A8E6CF'),
('Massas', 'massas', 'Massas e pratos com massas', '#FFB7B2'),
('Reutilizando', 'reutilizando', 'Receitas sustentáveis', '#B5E48C');

-- Verificar se há usuário admin
SELECT 'Verificando usuário admin...' as status;

SELECT COUNT(*) as total_admin FROM usuarios WHERE nome_usuario = 'admin';

-- Se não há usuário admin, criar um
INSERT IGNORE INTO usuarios (nome_usuario, email, senha, biografia, ativo) VALUES
('admin', 'admin@ecobistro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador do sistema', 1);

-- Verificar integridade das chaves estrangeiras
SELECT 'Verificando integridade...' as status;

-- Verificar se há receitas órfãs (sem usuário)
SELECT COUNT(*) as receitas_orfas FROM receitas r 
LEFT JOIN usuarios u ON r.usuario_id = u.id 
WHERE u.id IS NULL;

-- Verificar se há comentários órfãos (sem usuário ou receita)
SELECT COUNT(*) as comentarios_orfos FROM comentarios c 
LEFT JOIN usuarios u ON c.usuario_id = u.id 
LEFT JOIN receitas r ON c.receita_id = r.id 
WHERE u.id IS NULL OR r.id IS NULL;

-- Verificar se há curtidas órfãs
SELECT COUNT(*) as curtidas_orfas FROM curtidas c 
LEFT JOIN usuarios u ON c.usuario_id = u.id 
LEFT JOIN receitas r ON c.receita_id = r.id 
WHERE u.id IS NULL OR r.id IS NULL;

-- Verificar se há favoritos órfãos
SELECT COUNT(*) as favoritos_orfos FROM favoritos f 
LEFT JOIN usuarios u ON f.usuario_id = u.id 
LEFT JOIN receitas r ON f.receita_id = r.id 
WHERE u.id IS NULL OR r.id IS NULL;

-- Verificar se há seguidores órfãos
SELECT COUNT(*) as seguidores_orfos FROM seguidores s 
LEFT JOIN usuarios u1 ON s.seguidor_id = u1.id 
LEFT JOIN usuarios u2 ON s.seguido_id = u2.id 
WHERE u1.id IS NULL OR u2.id IS NULL;

SELECT 'Verificação concluída!' as status;
