-- Active: 1761077000773@@127.0.0.1@3306@mysql
-- Script para adicionar sistema de preferências de receitas
-- Execute este script após o cadastro do usuário

USE eco_bistro;

-- Tabela de preferências de receitas
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

-- Tabela de tipos de preferências disponíveis
CREATE TABLE IF NOT EXISTS tipos_preferencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('gosto_alimentar', 'restricao_alimentar', 'condicao_medica', 'preferencia_culinaria') NOT NULL,
    valor VARCHAR(100) NOT NULL,
    descricao TEXT,
    icone VARCHAR(10),
    ativo BOOLEAN DEFAULT TRUE,
    UNIQUE KEY unique_tipo_valor (tipo, valor)
);

-- Inserir tipos de preferências disponíveis
INSERT INTO tipos_preferencias (tipo, valor, descricao, icone) VALUES
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

-- Adicionar coluna para indicar se o usuário já configurou preferências
ALTER TABLE usuarios ADD COLUMN preferencias_configuradas BOOLEAN DEFAULT FALSE;

-- Adicionar colunas na tabela receitas para suportar preferências
ALTER TABLE receitas ADD COLUMN IF NOT EXISTS vegano BOOLEAN DEFAULT FALSE;
ALTER TABLE receitas ADD COLUMN IF NOT EXISTS vegetariano BOOLEAN DEFAULT FALSE;
ALTER TABLE receitas ADD COLUMN IF NOT EXISTS sem_gluten BOOLEAN DEFAULT FALSE;
ALTER TABLE receitas ADD COLUMN IF NOT EXISTS sem_lactose BOOLEAN DEFAULT FALSE;
ALTER TABLE receitas ADD COLUMN IF NOT EXISTS sem_acucar BOOLEAN DEFAULT FALSE;
ALTER TABLE receitas ADD COLUMN IF NOT EXISTS sem_sodio BOOLEAN DEFAULT FALSE;
ALTER TABLE receitas ADD COLUMN IF NOT EXISTS saudavel BOOLEAN DEFAULT FALSE;

-- Índices para melhor performance
CREATE INDEX idx_preferencias_usuario ON preferencias_receitas(usuario_id);
CREATE INDEX idx_preferencias_tipo ON preferencias_receitas(tipo_preferencia);
CREATE INDEX idx_tipos_preferencias_tipo ON tipos_preferencias(tipo);

-- Índices para as novas colunas de receitas
CREATE INDEX idx_receitas_vegano ON receitas(vegano);
CREATE INDEX idx_receitas_vegetariano ON receitas(vegetariano);
CREATE INDEX idx_receitas_sem_gluten ON receitas(sem_gluten);
CREATE INDEX idx_receitas_sem_lactose ON receitas(sem_lactose);
CREATE INDEX idx_receitas_sem_acucar ON receitas(sem_acucar);
CREATE INDEX idx_receitas_sem_sodio ON receitas(sem_sodio);
CREATE INDEX idx_receitas_saudavel ON receitas(saudavel);
