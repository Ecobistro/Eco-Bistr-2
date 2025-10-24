<?php
// Habilitar exibição de erros durante desenvolvimento
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
iniciarSessao();

$receitaId = (int)($_GET['id'] ?? 0);
$usuarioLogado = getUsuarioLogado();

if (!$receitaId) {
    header('Location: index.php');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Erro de conexão com o banco de dados: ' . $e->getMessage());
}

// Buscar receita - INCLUINDO as novas colunas de cobertura, recheio e outros
try {
    $stmt = $db->prepare("
        SELECT r.*, u.nome_usuario, u.biografia, c.nome as categoria_nome, c.slug as categoria_slug,
               (SELECT COUNT(*) FROM favoritos f WHERE f.receita_id = r.id) as total_favoritos,
               (SELECT COUNT(*) FROM curtidas cu WHERE cu.receita_id = r.id) as total_curtidas,
               (SELECT COUNT(*) FROM comentarios co WHERE co.receita_id = r.id AND co.ativo = 1) as total_comentarios
        FROM receitas r
        JOIN usuarios u ON r.usuario_id = u.id
        JOIN categorias c ON r.categoria_id = c.id
        WHERE r.id = ? AND r.ativo = 1
    ");
    $stmt->execute([$receitaId]);
    $receita = $stmt->fetch();
    
    if (!$receita) {
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    die('Erro ao buscar receita: ' . $e->getMessage());
}

// Incrementar visualizações
try {
    $stmt = $db->prepare("UPDATE receitas SET visualizacoes = visualizacoes + 1 WHERE id = ?");
    $stmt->execute([$receitaId]);
} catch (Exception $e) {
    // Log do erro, mas não interrompe a execução
    error_log('Erro ao incrementar visualizações: ' . $e->getMessage());
}

// Verificar se usuário favoritou/curtiu
$jaFavoritou = false;
$jaCurtiu = false;
if ($usuarioLogado) {
    try {
        $stmt = $db->prepare("SELECT 1 FROM favoritos WHERE usuario_id = ? AND receita_id = ?");
        $stmt->execute([$usuarioLogado['id'], $receitaId]);
        $jaFavoritou = (bool)$stmt->fetch();
        
        $stmt = $db->prepare("SELECT 1 FROM curtidas WHERE usuario_id = ? AND receita_id = ?");
        $stmt->execute([$usuarioLogado['id'], $receitaId]);
        $jaCurtiu = (bool)$stmt->fetch();
    } catch (Exception $e) {
        error_log('Erro ao verificar favoritos/curtidas: ' . $e->getMessage());
    }
}

// Buscar comentários
try {
    $stmt = $db->prepare("
        SELECT c.*, u.nome_usuario 
        FROM comentarios c
        JOIN usuarios u ON c.usuario_id = u.id
        WHERE c.receita_id = ? AND c.ativo = 1
        ORDER BY c.data_comentario DESC
    ");
    $stmt->execute([$receitaId]);
    $comentarios = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Erro ao buscar comentários: ' . $e->getMessage());
    $comentarios = [];
}

// Processar novo comentário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comentario']) && $usuarioLogado) {
    $comentario = sanitizar($_POST['comentario']);
    
    if (!empty($comentario)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO comentarios (receita_id, usuario_id, comentario) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$receitaId, $usuarioLogado['id'], $comentario]);
            
            // Recarregar página para mostrar novo comentário
            header("Location: receita.php?id=$receitaId");
            exit;
        } catch (Exception $e) {
            error_log('Erro ao inserir comentário: ' . $e->getMessage());
            $erro_comentario = 'Erro ao inserir comentário. Tente novamente.';
        }
    }
}

// Quebrar ingredientes e modo de preparo em arrays
$ingredientes = $receita['ingredientes'] ? explode(';', $receita['ingredientes']) : [];
$modosPreparo = $receita['modo_preparo'] ? explode(';', $receita['modo_preparo']) : [];

// Quebrar ingredientes e modo de preparo da cobertura (se existir)
$ingredientesCobertura = [];
$modosPreparoCobertura = [];
if (!empty($receita['tem_cobertura']) && !empty($receita['ingredientes_cobertura'])) {
    $ingredientesCobertura = explode(';', $receita['ingredientes_cobertura']);
}
if (isset($receita['tem_cobertura']) && $receita['tem_cobertura'] && !empty($receita['modo_preparo_cobertura'])) {
    $modosPreparoCobertura = explode(';', $receita['modo_preparo_cobertura']);
}

// Quebrar ingredientes e modo de preparo do recheio (se existir)
$ingredientesRecheio = [];
$modosPreparoRecheio = [];
if (isset($receita['tem_recheio']) && $receita['tem_recheio'] && !empty($receita['ingredientes_recheio'])) {
    $ingredientesRecheio = explode(';', $receita['ingredientes_recheio']);
}
if (isset($receita['tem_recheio']) && $receita['tem_recheio'] && !empty($receita['modo_preparo_recheio'])) {
    $modosPreparoRecheio = explode(';', $receita['modo_preparo_recheio']);
}

// Quebrar ingredientes e modo de preparo da seção "Outros" (se existir)
$ingredientesOutros = [];
$modosPreparoOutros = [];
if (isset($receita['tem_outros']) && $receita['tem_outros'] && isset($receita['ingredientes_outros']) && !empty($receita['ingredientes_outros'])) {
    $ingredientesOutros = explode(';', $receita['ingredientes_outros']);
}
if (isset($receita['tem_outros']) && $receita['tem_outros'] && !empty($receita['modo_preparo_outros'])) {
    $modosPreparoOutros = explode(';', $receita['modo_preparo_outros']);
}

// Função auxiliar para verificar se está seguindo (se não existir)
if (!function_exists('estaSeguindo')) {
    function estaSeguindo($usuarioId, $seguidoId) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT 1 FROM seguidores WHERE seguidor_id = ? AND seguido_id = ?");
            $stmt->execute([$usuarioId, $seguidoId]);
            return (bool)$stmt->fetch();
        } catch (Exception $e) {
            error_log('Erro ao verificar seguimento: ' . $e->getMessage());
            return false;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($receita['titulo']); ?> - Eco Bistrô</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .receita-header {
            position: relative;
            width: 100%;
            height: 400px;
            border-radius: 20px;
            overflow: hidden;
            margin-bottom: 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }
        
        .receita-meta-detail {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
            padding: 1rem;
            background: var(--background-light);
            border-radius: 15px;
        }
        
        .meta-item {
            text-align: center;
            padding: 1rem;
        }
        
        .meta-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--accent-color);
            margin-bottom: 0.5rem;
        }
        
        .meta-label {
            font-size: 0.9rem;
            color: var(--text-light);
        }
        
        .ingredientes, .modo-preparo {
            margin: 2rem 0;
            padding: 2rem;
            background: var(--background-light);
            border-radius: 15px;
        }
        
        .ingredientes h3, .modo-preparo h3 {
            margin-bottom: 1.5rem;
            color: var(--text-dark);
        }
        
        .ingredientes ul {
            list-style: none;
            padding: 0;
        }
        
        .ingredientes li {
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .modo-preparo ol {
            counter-reset: step-counter;
            padding-left: 0;
        }
        
        .modo-preparo li {
            counter-increment: step-counter;
            margin-bottom: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 10px;
            position: relative;
            padding-left: 3rem;
        }
        
        .modo-preparo li:before {
            content: counter(step-counter);
            position: absolute;
            left: 1rem;
            top: 1rem;
            background: var(--accent-color);
            color: white;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.8rem;
        }
        
        .comentarios {
            margin-top: 3rem;
        }
        
        .comentario {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: var(--background-light);
            border-radius: 15px;
        }
        
        .comentario-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: var(--text-dark);
            flex-shrink: 0;
        }
        
        .comentario-content {
            flex: 1;
        }
        
        .comentario-author {
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .comentario-date {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-bottom: 0.5rem;
        }
        
        .comentario-text {
            line-height: 1.5;
        }
        
        .receita-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }
        
        .receita-card:hover {
            transform: translateY(-5px);
        }
        
        .receita-img {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .receita-content {
            padding: 1.5rem;
        }
        
        .receita-title {
            margin: 0 0 1rem 0;
            color: var(--text-dark);
        }
        
        .receita-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.9rem;
            color: var(--text-light);
        }
        
        .alert {
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 5px;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="nav-container">
            <a href="index.php" class="logo">ECO BISTRÔ</a>
            <nav class="nav-menu">
                <a href="receitas.php" class="nav-link">RECEITAS</a>
                <?php if ($usuarioLogado): ?>
                    <a href="perfil.php" class="nav-link">@<?php echo htmlspecialchars($usuarioLogado['nome_usuario']); ?></a>
                    <a href="nova-receita.php" class="btn btn-primary">+ NOVA RECEITA</a>
                    <a href="logout.php" class="nav-link">SAIR</a>
                <?php else: ?>
                    <a href="login.php" class="nav-link">LOGIN</a>
                    <a href="cadastro.php" class="btn btn-primary">CADASTRE-SE</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Receita Content -->
    <div class="container" style="padding: 2rem 0;">
        <div class="receita-detail">
            <!-- Exibir erros se houver -->
            <?php if (isset($erro_comentario)): ?>
                <div class="alert"><?php echo $erro_comentario; ?></div>
            <?php endif; ?>
            
            <!-- Header da receita -->
            <div class="receita-header">
                <?php if ($receita['imagem']): ?>
                    <img src="uploads/receitas/<?php echo htmlspecialchars($receita['imagem']); ?>" alt="<?php echo htmlspecialchars($receita['titulo']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <?php echo htmlspecialchars($receita['titulo']); ?>
                <?php endif; ?>
                
                <!-- Ações sobre a imagem -->
                <div style="position: absolute; top: 1rem; right: 1rem; display: flex; gap: 1rem;">
                    <?php if ($usuarioLogado): ?>
                        <button onclick="favoritar(<?php echo $receitaId; ?>)" class="btn <?php echo $jaFavoritou ? 'btn-primary' : 'btn-secondary'; ?>" style="opacity: 0.9;">
                            <?php echo $jaFavoritou ? '⭐ Favoritado' : '⭐ Favoritar'; ?>
                        </button>
                        <button onclick="curtir(<?php echo $receitaId; ?>)" class="btn <?php echo $jaCurtiu ? 'btn-primary' : 'btn-secondary'; ?>" style="opacity: 0.9;">
                            <?php echo $jaCurtiu ? '❤️ Curtido' : '❤️ Curtir'; ?>
                        </button>
                    <?php endif; ?>
                    <button onclick="compartilhar()" class="btn btn-secondary" style="opacity: 0.9;">
                        🔗 Compartilhar
                    </button>
                </div>
            </div>
            
            <!-- Informações da receita -->
            <div class="receita-info">
                <h1 style="font-size: 2.5rem; margin-bottom: 1rem; color: var(--text-dark);">
                    <?php echo htmlspecialchars($receita['titulo']); ?>
                </h1>
                
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap;">
                    <span style="background: var(--primary-color); padding: 0.5rem 1rem; border-radius: 15px; font-weight: 600;">
                        👤 <?php echo htmlspecialchars($receita['nome_usuario']); ?>
                    </span>
                    <span style="background: var(--secondary-color); padding: 0.5rem 1rem; border-radius: 15px; font-weight: 600;">
                        📂 <?php echo htmlspecialchars($receita['categoria_nome']); ?>
                    </span>
                    <span style="color: var(--text-light);">
                        👁️ <?php echo number_format($receita['visualizacoes']); ?> visualizações
                    </span>
                    
                    <!-- Indicadores de seções especiais -->
                    <?php if (isset($receita['tem_cobertura']) && $receita['tem_cobertura']): ?>
                        <span style="background: linear-gradient(45deg, #FFD3A5, #FD9853); padding: 0.5rem 1rem; border-radius: 15px; font-weight: 600; color: white;">
                            🍰 Com Cobertura
                        </span>
                    <?php endif; ?>
                    
                    <?php if (isset($receita['tem_recheio']) && $receita['tem_recheio']): ?>
                        <span style="background: linear-gradient(45deg, #A8E6CF, #FFD3A5); padding: 0.5rem 1rem; border-radius: 15px; font-weight: 600; color: var(--text-dark);">
                            🥧 Com Recheio
                        </span>
                    <?php endif; ?>
                    
                    <?php if (isset($receita['tem_outros']) && $receita['tem_outros']): ?>
                        <span style="background: linear-gradient(45deg, #FD9853, #A8E6CF); padding: 0.5rem 1rem; border-radius: 15px; font-weight: 600; color: var(--text-dark);">
                            ✨ <?php echo !empty($receita['titulo_outros']) ? htmlspecialchars($receita['titulo_outros']) : 'Outros'; ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <?php if ($receita['descricao']): ?>
                    <p style="font-size: 1.1rem; color: var(--text-light); margin-bottom: 2rem; line-height: 1.6;">
                        <?php echo nl2br(htmlspecialchars($receita['descricao'])); ?>
                    </p>
                <?php endif; ?>
                
                <!-- Meta informações -->
                <div class="receita-meta-detail">
                    <div class="meta-item">
                        <div class="meta-value">⏱️ <?php echo formatarTempo($receita['tempo_preparo']); ?></div>
                        <div class="meta-label">Tempo de preparo</div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-value">🍽️ <?php echo $receita['porcoes']; ?></div>
                        <div class="meta-label">Porções</div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-value">📊 <?php echo $receita['dificuldade']; ?></div>
                        <div class="meta-label">Dificuldade</div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-value">❤️ <?php echo $receita['total_curtidas']; ?></div>
                        <div class="meta-label">Curtidas</div>
                    </div>
                </div>
                
                <!-- Ingredientes -->
                <?php if (!empty($ingredientes)): ?>
                <div class="ingredientes">
                    <h3>🥕 Ingredientes</h3>
                    <ul>
                        <?php foreach ($ingredientes as $ingrediente): ?>
                            <?php if (trim($ingrediente)): ?>
                                <li><?php echo htmlspecialchars(trim($ingrediente)); ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <!-- Modo de Preparo -->
                <?php if (!empty($modosPreparo)): ?>
                <div class="modo-preparo">
                    <h3>👨‍🍳 Modo de Preparo</h3>
                    <ol>
                        <?php foreach ($modosPreparo as $passo): ?>
                            <?php if (trim($passo)): ?>
                                <li><?php echo htmlspecialchars(trim($passo)); ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </div>
                <?php endif; ?>
                
                <!-- Seção de Recheio (se existir) -->
                <?php if (isset($receita['tem_recheio']) && $receita['tem_recheio'] && (!empty($ingredientesRecheio) || !empty($modosPreparoRecheio))): ?>
                    <div class="recheio-section" style="margin-top: 3rem; padding: 2rem; background: linear-gradient(135deg, rgba(168, 230, 207, 0.1), rgba(255, 211, 165, 0.1)); border-radius: 20px; border-left: 5px solid var(--primary-color);">
                        <h3 style="color: var(--accent-color); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            🥧 Recheio
                        </h3>
                        
                        <?php if (!empty($ingredientesRecheio)): ?>
                            <div class="ingredientes-recheio" style="margin-bottom: 2rem;">
                                <h4 style="margin-bottom: 1rem; color: var(--text-dark);">Ingredientes do Recheio:</h4>
                                <ul style="list-style: none; padding: 0;">
                                    <?php foreach ($ingredientesRecheio as $ingrediente): ?>
                                        <?php if (trim($ingrediente)): ?>
                                            <li style="padding: 0.75rem 1rem 0.75rem 2.5rem; margin-bottom: 0.5rem; background: rgba(255, 255, 255, 0.7); border-radius: 10px; position: relative;">
                                                <span style="position: absolute; left: 0.75rem; top: 0.75rem; color: var(--accent-color); font-size: 0.9rem;">🔶</span>
                                                <?php echo htmlspecialchars(trim($ingrediente)); ?>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($modosPreparoRecheio)): ?>
                            <div class="preparo-recheio">
                                <h4 style="margin-bottom: 1rem; color: var(--text-dark);">Modo de Preparo do Recheio:</h4>
                                <ol style="padding-left: 0; counter-reset: recheio-counter;">
                                    <?php foreach ($modosPreparoRecheio as $index => $passo): ?>
                                        <?php if (trim($passo)): ?>
                                            <li style="padding: 1rem; margin-bottom: 1rem; background: rgba(255, 255, 255, 0.7); border-radius: 10px; position: relative; padding-left: 3rem; counter-increment: recheio-counter;">
                                                <span style="position: absolute; left: 1rem; top: 1rem; background: var(--accent-color); color: white; width: 1.5rem; height: 1.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.8rem;">
                                                    <?php echo $index + 1; ?>
                                                </span>
                                                <?php echo htmlspecialchars(trim($passo)); ?>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ol>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Seção de Cobertura (se existir) -->
                <?php if (isset($receita['tem_cobertura']) && $receita['tem_cobertura'] && (!empty($ingredientesCobertura) || !empty($modosPreparoCobertura))): ?>
                    <div class="cobertura-section" style="margin-top: 3rem; padding: 2rem; background: linear-gradient(135deg, rgba(255, 211, 165, 0.1), rgba(253, 152, 83, 0.1)); border-radius: 20px; border-left: 5px solid var(--accent-color);">
                        <h3 style="color: var(--accent-color); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            🍰 Cobertura
                        </h3>
                        
                        <?php if (!empty($ingredientesCobertura)): ?>
                            <div class="ingredientes-cobertura" style="margin-bottom: 2rem;">
                                <h4 style="margin-bottom: 1rem; color: var(--text-dark);">Ingredientes da Cobertura:</h4>
                                <ul style="list-style: none; padding: 0;">
                                    <?php foreach ($ingredientesCobertura as $ingrediente): ?>
                                        <?php if (trim($ingrediente)): ?>
                                            <li style="padding: 0.75rem 1rem 0.75rem 2.5rem; margin-bottom: 0.5rem; background: rgba(255, 255, 255, 0.7); border-radius: 10px; position: relative;">
                                                <span style="position: absolute; left: 0.75rem; top: 0.75rem; color: var(--accent-color); font-size: 0.9rem;">🔸</span>
                                                <?php echo htmlspecialchars(trim($ingrediente)); ?>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($modosPreparoCobertura)): ?>
                            <div class="preparo-cobertura">
                                <h4 style="margin-bottom: 1rem; color: var(--text-dark);">Modo de Preparo da Cobertura:</h4>
                                <ol style="padding-left: 0; counter-reset: cobertura-counter;">
                                    <?php foreach ($modosPreparoCobertura as $index => $passo): ?>
                                        <?php if (trim($passo)): ?>
                                            <li style="padding: 1rem; margin-bottom: 1rem; background: rgba(255, 255, 255, 0.7); border-radius: 10px; position: relative; padding-left: 3rem; counter-increment: cobertura-counter;">
                                                <span style="position: absolute; left: 1rem; top: 1rem; background: var(--accent-color); color: white; width: 1.5rem; height: 1.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.8rem;">
                                                    <?php echo $index + 1; ?>
                                                </span>
                                                <?php echo htmlspecialchars(trim($passo)); ?>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ol>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Seção "Outros" (se existir) -->
                <?php if (isset($receita['tem_outros']) && $receita['tem_outros'] && (!empty($ingredientesOutros) || !empty($modosPreparoOutros))): ?>
                    <div class="outros-section" style="margin-top: 3rem; padding: 2rem; background: linear-gradient(135deg, rgba(253, 152, 83, 0.1), rgba(168, 230, 207, 0.1)); border-radius: 20px; border-left: 5px solid var(--secondary-color);">
                        <h3 style="color: var(--accent-color); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            ✨ <?php echo !empty($receita['titulo_outros']) ? htmlspecialchars($receita['titulo_outros']) : 'Outros'; ?>
                        </h3>
                        
                        <?php if (!empty($ingredientesOutros)): ?>
                            <div class="ingredientes-outros" style="margin-bottom: 2rem;">
                                <h4 style="margin-bottom: 1rem; color: var(--text-dark);">
                                    <?php echo !empty($receita['titulo_outros']) ? 'Ingredientes/Materiais:' : 'Ingredientes dos Outros:'; ?>
                                </h4>
                                <ul style="list-style: none; padding: 0;">
                                    <?php foreach ($ingredientesOutros as $ingrediente): ?>
                                        <?php if (trim($ingrediente)): ?>
                                            <li style="padding: 0.75rem 1rem 0.75rem 2.5rem; margin-bottom: 0.5rem; background: rgba(255, 255, 255, 0.7); border-radius: 10px; position: relative;">
                                                <span style="position: absolute; left: 0.75rem; top: 0.75rem; color: var(--accent-color); font-size: 0.9rem;">🔹</span>
                                                <?php echo htmlspecialchars(trim($ingrediente)); ?>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($modosPreparoOutros)): ?>
                            <div class="preparo-outros">
                                <h4 style="margin-bottom: 1rem; color: var(--text-dark);">Modo de Preparo:</h4>
                                <ol style="padding-left: 0; counter-reset: outros-counter;">
                                    <?php foreach ($modosPreparoOutros as $index => $passo): ?>
                                        <?php if (trim($passo)): ?>
                                            <li style="padding: 1rem; margin-bottom: 1rem; background: rgba(255, 255, 255, 0.7); border-radius: 10px; position: relative; padding-left: 3rem; counter-increment: outros-counter;">
                                                <span style="position: absolute; left: 1rem; top: 1rem; background: var(--secondary-color); color: var(--text-dark); width: 1.5rem; height: 1.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.8rem;">
                                                    <?php echo $index + 1; ?>
                                                </span>
                                                <?php echo htmlspecialchars(trim($passo)); ?>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ol>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Estatísticas -->
                <div style="display: flex; gap: 2rem; justify-content: center; margin: 2rem 0; padding: 1rem; background: var(--background-light); border-radius: 15px; flex-wrap: wrap;">
                    <div style="text-align: center;">
                        <div style="font-size: 1.5rem; font-weight: 700; color: var(--accent-color);">
                            <?php echo $receita['total_favoritos']; ?>
                        </div>
                        <div style="font-size: 0.9rem; color: var(--text-light);">Favoritos</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 1.5rem; font-weight: 700; color: var(--accent-color);">
                            <?php echo $receita['total_curtidas']; ?>
                        </div>
                        <div style="font-size: 0.9rem; color: var(--text-light);">Curtidas</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 1.5rem; font-weight: 700; color: var(--accent-color);">
                            <?php echo $receita['total_comentarios']; ?>
                        </div>
                        <div style="font-size: 0.9rem; color: var(--text-light);">Comentários</div>
                    </div>
                </div>
                
                <!-- Comentários -->
                <div class="comentarios">
                    <h3>💬 Comentários (<?php echo count($comentarios); ?>)</h3>
                    
                    <?php if ($usuarioLogado): ?>
                        <form method="POST" style="margin-bottom: 2rem;">
                            <div style="display: flex; gap: 1rem; align-items: flex-start;">
                                <div class="comentario-avatar">
                                    <?php echo strtoupper(substr($usuarioLogado['nome_usuario'], 0, 1)); ?>
                                </div>
                                <div style="flex: 1;">
                                    <textarea 
                                        name="comentario" 
                                        class="form-control" 
                                        placeholder="Adicione um comentário..."
                                        rows="3"
                                        required
                                        style="width: 100%; padding: 1rem; border: 1px solid #ddd; border-radius: 10px; font-family: inherit; resize: vertical;"
                                    ></textarea>
                                    <button type="submit" class="btn btn-primary" style="margin-top: 0.5rem;">
                                        Comentar
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php else: ?>
                        <p style="text-align: center; padding: 2rem; background: var(--background-light); border-radius: 15px;">
                            <a href="login.php" style="color: var(--accent-color); text-decoration: none; font-weight: 600;">Faça login</a> para comentar nesta receita
                        </p>
                    <?php endif; ?>
                    
                    <?php if (empty($comentarios)): ?>
                        <p style="text-align: center; color: var(--text-light); font-style: italic;">
                            Seja o primeiro a comentar esta receita!
                        </p>
                    <?php else: ?>
                        <?php foreach ($comentarios as $comentario): ?>
                            <div class="comentario" data-comentario-id="<?php echo $comentario['id']; ?>">
                                <div class="comentario-avatar">
                                    <?php echo strtoupper(substr($comentario['nome_usuario'], 0, 1)); ?>
                                </div>
                                <div class="comentario-content">
                                    <div class="comentario-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                        <div>
                                            <div class="comentario-author">@<?php echo htmlspecialchars($comentario['nome_usuario']); ?></div>
                                            <div class="comentario-date">
                                                <?php echo date('d/m/Y H:i', strtotime($comentario['data_comentario'])); ?>
                                                <?php if (isset($comentario['data_editado']) && $comentario['data_editado']): ?>
                                                    <span style="font-size: 0.8rem; color: var(--text-light);"> • editado</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ($usuarioLogado && $usuarioLogado['id'] == $comentario['usuario_id']): ?>
                                            <div class="comentario-acoes" style="display: flex; gap: 0.5rem;">
                                                <button onclick="editarComentario(<?php echo $comentario['id']; ?>, '<?php echo addslashes($comentario['comentario']); ?>')" 
                                                        style="background: none; border: none; color: var(--text-light); cursor: pointer; font-size: 0.8rem; padding: 0.25rem;">
                                                    ✏️ Editar
                                                </button>
                                                <button onclick="excluirComentario(<?php echo $comentario['id']; ?>)" 
                                                        style="background: none; border: none; color: var(--accent-color); cursor: pointer; font-size: 0.8rem; padding: 0.25rem;">
                                                    🗑️ Excluir
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="comentario-text">
                                        <?php echo nl2br(htmlspecialchars($comentario['comentario'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Receitas relacionadas -->
                <?php
                try {
                    $stmt = $db->prepare("
                        SELECT r.*, u.nome_usuario 
                        FROM receitas r
                        JOIN usuarios u ON r.usuario_id = u.id
                        WHERE r.categoria_id = ? AND r.id != ? AND r.ativo = 1
                        ORDER BY RAND()
                        LIMIT 4
                    ");
                    $stmt->execute([$receita['categoria_id'], $receitaId]);
                    $receitasRelacionadas = $stmt->fetchAll();
                } catch (Exception $e) {
                    error_log('Erro ao buscar receitas relacionadas: ' . $e->getMessage());
                    $receitasRelacionadas = [];
                }
                
                if (!empty($receitasRelacionadas)):
                ?>
                <div style="margin-top: 4rem;">
                    <h2 style="text-align: center; margin-bottom: 2rem;">Receitas Relacionadas</h2>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                        <?php foreach ($receitasRelacionadas as $rel): ?>
                            <a href="receita.php?id=<?php echo $rel['id']; ?>" class="receita-card" style="text-decoration: none;">
                                <div class="receita-img">
                                    <?php if ($rel['imagem']): ?>
                                        <img src="uploads/receitas/<?php echo htmlspecialchars($rel['imagem']); ?>" alt="<?php echo htmlspecialchars($rel['titulo']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($rel['titulo']); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="receita-content">
                                    <h4 class="receita-title"><?php echo htmlspecialchars($rel['titulo']); ?></h4>
                                    <div class="receita-meta">
                                        <span>⏱️ <?php echo formatarTempo($rel['tempo_preparo']); ?></span>
                                        <span>👤 <?php echo htmlspecialchars($rel['nome_usuario']); ?></span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
        
                <!-- Seção do Autor -->
                <div class="receita-author-section" style="margin: 2rem 0; padding: 2rem; background: var(--background-light); border-radius: var(--radius); box-shadow: var(--shadow);">
                    <h3 style="margin-bottom: 1.5rem; color: var(--text-dark);">👨‍🍳 Sobre o Autor</h3>
                    <div style="display: flex; align-items: center; gap: 1.5rem;">
                        <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--primary-color); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; color: var(--text-dark); flex-shrink: 0;">
                            <?php echo strtoupper(substr($receita['nome_usuario'], 0, 1)); ?>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="margin-bottom: 0.5rem; font-size: 1.3rem;">
                                <a href="perfil-usuario.php?id=<?php echo $receita['usuario_id']; ?>" 
                                   style="color: var(--text-dark); text-decoration: none;">
                                    @<?php echo htmlspecialchars($receita['nome_usuario']); ?>
                                </a>
                            </h4>
                            <?php if ($receita['biografia']): ?>
                                <p style="color: var(--text-light); margin: 0.5rem 0; line-height: 1.5;">
                                    <?php echo htmlspecialchars(substr($receita['biografia'], 0, 150)) . (strlen($receita['biografia']) > 150 ? '...' : ''); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div style="display: flex; gap: 1rem; align-items: center; margin-top: 1rem; flex-wrap: wrap;">
                                <a href="perfil-usuario.php?id=<?php echo $receita['usuario_id']; ?>" 
                                   class="btn btn-secondary" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                                    Ver Perfil
                                </a>
                                
                                <?php if ($usuarioLogado && $usuarioLogado['id'] != $receita['usuario_id']): ?>
                                    <?php $estaSeguindoAutor = estaSeguindo($usuarioLogado['id'], $receita['usuario_id']); ?>
                                    <button onclick="seguirUsuario(<?php echo $receita['usuario_id']; ?>, this)" 
                                            class="btn <?php echo $estaSeguindoAutor ? 'btn-secondary' : 'btn-primary'; ?>" 
                                            style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                                        <?php echo $estaSeguindoAutor ? '✓ Seguindo' : '+ Seguir'; ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts JavaScript -->
    <script>
        function favoritar(receitaId) {
            fetch('ajax/favoritar.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'receita_id=' + receitaId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Erro ao favoritar');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao processar solicitação');
            });
        }
        
        function curtir(receitaId) {
            fetch('ajax/curtir.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'receita_id=' + receitaId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Erro ao curtir');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao processar solicitação');
            });
        }
        
        function compartilhar() {
            if (navigator.share) {
                navigator.share({
                    title: '<?php echo addslashes($receita['titulo']); ?>',
                    text: 'Confira esta receita deliciosa!',
                    url: window.location.href
                }).catch(console.error);
            } else {
                // Fallback para navegadores que não suportam Web Share API
                navigator.clipboard.writeText(window.location.href).then(() => {
                    alert('Link copiado para a área de transferência!');
                }).catch(() => {
                    // Fallback adicional
                    prompt('Copie o link:', window.location.href);
                });
            }
        }
        
        function seguirUsuario(usuarioId, button) {
            fetch('ajax/seguir.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'usuario_id=' + usuarioId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.seguindo) {
                        button.textContent = '✓ Seguindo';
                        button.className = 'btn btn-secondary';
                    } else {
                        button.textContent = '+ Seguir';
                        button.className = 'btn btn-primary';
                    }
                } else {
                    alert(data.message || 'Erro ao processar solicitação');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao processar solicitação');
            });
        }
        
        function editarComentario(comentarioId, textoAtual) {
            const novoTexto = prompt('Editar comentário:', textoAtual);
            if (novoTexto && novoTexto.trim() && novoTexto !== textoAtual) {
                fetch('ajax/editar_comentario.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'comentario_id=' + comentarioId + '&comentario=' + encodeURIComponent(novoTexto)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Erro ao editar comentário');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao processar solicitação');
                });
            }
        }
        
        function excluirComentario(comentarioId) {
            if (confirm('Tem certeza que deseja excluir este comentário?')) {
                fetch('ajax/excluir_comentario.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'comentario_id=' + comentarioId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Erro ao excluir comentário');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao processar solicitação');
                });
            }
        }
    </script>
</body>
</html>