<?php
require_once '../config.php';
iniciarSessao();

// Verificar se é admin
if (!estaLogado() || ($_SESSION['tipo_usuario'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$mensagem = '';
$erro = '';

// Processar ações
if ($_POST) {
    $acao = $_POST['acao'] ?? '';
    $receitaId = (int)($_POST['receita_id'] ?? 0);
    
    switch ($acao) {
        case 'desativar':
            $stmt = $db->prepare("UPDATE receitas SET ativo = 0 WHERE id = ?");
            if ($stmt->execute([$receitaId])) {
                $mensagem = 'Receita desativada com sucesso!';
                // Log da ação
                $stmt = $db->prepare("INSERT INTO logs_sistema (usuario_id, acao, descricao) VALUES (?, 'RECEITA_DESATIVADA', ?)");
                $stmt->execute([$_SESSION['usuario_id'], "Receita ID $receitaId desativada"]);
            } else {
                $erro = 'Erro ao desativar receita.';
            }
            break;
            
        case 'ativar':
            $stmt = $db->prepare("UPDATE receitas SET ativo = 1 WHERE id = ?");
            if ($stmt->execute([$receitaId])) {
                $mensagem = 'Receita ativada com sucesso!';
                // Log da ação
                $stmt = $db->prepare("INSERT INTO logs_sistema (usuario_id, acao, descricao) VALUES (?, 'RECEITA_ATIVADA', ?)");
                $stmt->execute([$_SESSION['usuario_id'], "Receita ID $receitaId ativada"]);
            } else {
                $erro = 'Erro ao ativar receita.';
            }
            break;
            
        case 'destacar':
            $stmt = $db->prepare("UPDATE receitas SET em_destaque = 1 WHERE id = ?");
            if ($stmt->execute([$receitaId])) {
                $mensagem = 'Receita destacada com sucesso!';
            } else {
                $erro = 'Erro ao destacar receita.';
            }
            break;
            
        case 'remover_destaque':
            $stmt = $db->prepare("UPDATE receitas SET em_destaque = 0 WHERE id = ?");
            if ($stmt->execute([$receitaId])) {
                $mensagem = 'Destaque removido com sucesso!';
            } else {
                $erro = 'Erro ao remover destaque.';
            }
            break;
    }
}

// Filtros
$filtro = $_GET['filtro'] ?? 'todas';
$categoria = $_GET['categoria'] ?? '';
$busca = $_GET['busca'] ?? '';
$ordenacao = $_GET['ordenacao'] ?? 'recentes';

// Construir query
$where = "WHERE 1=1";
$params = [];

switch ($filtro) {
    case 'ativas':
        $where .= " AND r.ativo = 1";
        break;
    case 'inativas':
        $where .= " AND r.ativo = 0";
        break;
    case 'destaque':
        $where .= " AND r.em_destaque = 1";
        break;
    case 'populares':
        $where .= " AND r.ativo = 1";
        break;
}

if ($categoria) {
    $where .= " AND c.slug = ?";
    $params[] = $categoria;
}

if ($busca) {
    $where .= " AND (r.titulo LIKE ? OR r.ingredientes LIKE ? OR u.nome_usuario LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

// Ordenação
$orderBy = "ORDER BY ";
switch ($ordenacao) {
    case 'populares':
        $orderBy .= "r.visualizacoes DESC, r.data_criacao DESC";
        break;
    case 'curtidas':
        $orderBy .= "(SELECT COUNT(*) FROM curtidas cu WHERE cu.receita_id = r.id) DESC";
        break;
    case 'comentarios':
        $orderBy .= "(SELECT COUNT(*) FROM comentarios co WHERE co.receita_id = r.id AND co.ativo = 1) DESC";
        break;
    case 'alfabetica':
        $orderBy .= "r.titulo ASC";
        break;
    default:
        $orderBy .= "r.data_criacao DESC";
}

// Buscar receitas
$stmt = $db->prepare("
    SELECT r.*, u.nome_usuario, c.nome as categoria_nome, c.slug as categoria_slug,
           (SELECT COUNT(*) FROM favoritos f WHERE f.receita_id = r.id) as total_favoritos,
           (SELECT COUNT(*) FROM curtidas cu WHERE cu.receita_id = r.id) as total_curtidas,
           (SELECT COUNT(*) FROM comentarios co WHERE co.receita_id = r.id AND co.ativo = 1) as total_comentarios
    FROM receitas r
    JOIN usuarios u ON r.usuario_id = u.id
    JOIN categorias c ON r.categoria_id = c.id
    $where
    $orderBy
    LIMIT 50
");
$stmt->execute($params);
$receitas = $stmt->fetchAll();

// Buscar categorias
$stmt = $db->query("SELECT * FROM categorias ORDER BY nome");
$categorias = $stmt->fetchAll();

// Adicionar coluna em_destaque se não existir
try {
    $db->exec("ALTER TABLE receitas ADD COLUMN em_destaque BOOLEAN DEFAULT FALSE");
} catch (PDOException $e) {
    // Coluna já existe
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Receitas - Eco Bistrô Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles.css">
    <style>
        .admin-sidebar {
            width: 250px;
            background: var(--text-dark);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding: 2rem 0;
            color: white;
        }
        
        .admin-content {
            margin-left: 250px;
            padding: 2rem;
            background: var(--background-light);
            min-height: 100vh;
        }
        
        .receita-admin-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            display: grid;
            grid-template-columns: 150px 1fr auto;
            gap: 1rem;
            align-items: center;
            position: relative;
        }
        
        .receita-admin-img {
            width: 150px;
            height: 100px;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-active { background: #28a745; color: white; }
        .badge-inactive { background: #6c757d; color: white; }
        .badge-featured { background: var(--accent-color); color: white; }
        
        .stats-mini {
            display: flex;
            gap: 1rem;
            font-size: 0.8rem;
            color: var(--text-light);
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="admin-sidebar">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h2 style="color: var(--primary-color);">ECO BISTRÔ</h2>
            <p style="font-size: 0.9rem; opacity: 0.8;">Painel Admin</p>
        </div>
        
        <nav style="padding: 0 1rem;">
            <a href="dashboard.php" style="display: block; color: white; text-decoration: none; padding: 1rem; border-radius: 10px; margin-bottom: 0.5rem; transition: var(--transition);" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
                📊 Dashboard
            </a>
            <a href="usuarios.php" style="display: block; color: white; text-decoration: none; padding: 1rem; border-radius: 10px; margin-bottom: 0.5rem; transition: var(--transition);" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
                👥 Usuários
            </a>
            <a href="receitas.php" style="display: block; color: white; text-decoration: none; padding: 1rem; border-radius: 10px; margin-bottom: 0.5rem; background: var(--accent-color);">
                🍽️ Receitas
            </a>
            <a href="comentarios.php" style="display: block; color: white; text-decoration: none; padding: 1rem; border-radius: 10px; margin-bottom: 0.5rem; transition: var(--transition);" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
                💬 Comentários
            </a>
            <a href="categorias.php" style="display: block; color: white; text-decoration: none; padding: 1rem; border-radius: 10px; margin-bottom: 0.5rem; transition: var(--transition);" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
                📁 Categorias
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="admin-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h1 style="color: var(--text-dark); margin-bottom: 0.5rem;">Gerenciar Receitas</h1>
                <p style="color: var(--text-light);">Administre as receitas da plataforma</p>
            </div>
        </div>

        <!-- Mensagens -->
        <?php if ($mensagem): ?>
            <div class="alert alert-success" style="margin-bottom: 2rem;"><?php echo $mensagem; ?></div>
        <?php endif; ?>
        
        <?php if ($erro): ?>
            <div class="alert alert-error" style="margin-bottom: 2rem;"><?php echo $erro; ?></div>
        <?php endif; ?>

        <!-- Filtros -->
        <div style="background: white; padding: 1.5rem; border-radius: 15px; box-shadow: var(--shadow); margin-bottom: 2rem;">
            <form method="GET" style="display: grid; grid-template-columns: 1fr 200px 150px 150px auto; gap: 1rem; align-items: end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Buscar receitas</label>
                    <input type="text" name="busca" class="form-control" placeholder="Título, ingredientes ou autor..." value="<?php echo htmlspecialchars($busca); ?>">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Categoria</label>
                    <select name="categoria" class="form-control">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo $cat['slug']; ?>" <?php echo $categoria === $cat['slug'] ? 'selected' : ''; ?>>
                                <?php echo sanitizar($cat['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Filtro</label>
                    <select name="filtro" class="form-control">
                        <option value="todas" <?php echo $filtro === 'todas' ? 'selected' : ''; ?>>Todas</option>
                        <option value="ativas" <?php echo $filtro === 'ativas' ? 'selected' : ''; ?>>Ativas</option>
                        <option value="inativas" <?php echo $filtro === 'inativas' ? 'selected' : ''; ?>>Inativas</option>
                        <option value="destaque" <?php echo $filtro === 'destaque' ? 'selected' : ''; ?>>Em destaque</option>
                        <option value="populares" <?php echo $filtro === 'populares' ? 'selected' : ''; ?>>Populares</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Ordenar por</label>
                    <select name="ordenacao" class="form-control">
                        <option value="recentes" <?php echo $ordenacao === 'recentes' ? 'selected' : ''; ?>>Mais recentes</option>
                        <option value="populares" <?php echo $ordenacao === 'populares' ? 'selected' : ''; ?>>Mais visualizadas</option>
                        <option value="curtidas" <?php echo $ordenacao === 'curtidas' ? 'selected' : ''; ?>>Mais curtidas</option>
                        <option value="comentarios" <?php echo $ordenacao === 'comentarios' ? 'selected' : ''; ?>>Mais comentadas</option>
                        <option value="alfabetica" <?php echo $ordenacao === 'alfabetica' ? 'selected' : ''; ?>>Alfabética</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
            </form>
        </div>

        <!-- Estatísticas resumidas -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
            <div style="background: white; padding: 1.5rem; border-radius: 15px; box-shadow: var(--shadow); text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color);">
                    <?php echo count(array_filter($receitas, fn($r) => $r['ativo'])); ?>
                </div>
                <div style="color: var(--text-light);">Receitas Ativas</div>
            </div>
            
            <div style="background: white; padding: 1.5rem; border-radius: 15px; box-shadow: var(--shadow); text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: var(--accent-color);">
                    <?php echo count(array_filter($receitas, fn($r) => ($r['em_destaque'] ?? 0))); ?>
                </div>
                <div style="color: var(--text-light);">Em Destaque</div>
            </div>
            
            <div style="background: white; padding: 1.5rem; border-radius: 15px; box-shadow: var(--shadow); text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: var(--secondary-color);">
                    <?php echo array_sum(array_column($receitas, 'total_curtidas')); ?>
                </div>
                <div style="color: var(--text-light);">Total Curtidas</div>
            </div>
            
            <div style="background: white; padding: 1.5rem; border-radius: 15px; box-shadow: var(--shadow); text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: #C7CEEA;">
                    <?php echo array_sum(array_column($receitas, 'visualizacoes')); ?>
                </div>
                <div style="color: var(--text-light);">Total Visualizações</div>
            </div>
        </div>

        <!-- Lista de receitas -->
        <div style="background: white; border-radius: 15px; box-shadow: var(--shadow);">
            <div style="padding: 1.5rem; border-bottom: 1px solid #eee;">
                <h3 style="margin: 0;">Receitas Encontradas (<?php echo count($receitas); ?>)</h3>
            </div>
            
            <?php if (empty($receitas)): ?>
                <div style="text-align: center; padding: 3rem; color: var(--text-light);">
                    <div style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;">🔍</div>
                    <h3>Nenhuma receita encontrada</h3>
                    <p>Ajuste os filtros para ver mais resultados</p>
                </div>
            <?php else: ?>
                <div style="padding: 1rem;">
                    <?php foreach ($receitas as $receita): ?>
                        <div class="receita-admin-card">
                            <!-- Imagem da receita -->
                            <div class="receita-admin-img">
                                <?php if ($receita['imagem']): ?>
                                    <img src="../uploads/receitas/<?php echo $receita['imagem']; ?>" alt="<?php echo sanitizar($receita['titulo']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    📝
                                <?php endif; ?>
                            </div>
                            
                            <!-- Informações da receita -->
                            <div style="padding: 1rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                    <h4 style="margin: 0; color: var(--text-dark);">
                                        <?php echo sanitizar($receita['titulo']); ?>
                                    </h4>
                                    
                                    <!-- Badges de status -->
                                    <span class="badge <?php echo $receita['ativo'] ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?php echo $receita['ativo'] ? 'Ativa' : 'Inativa'; ?>
                                    </span>
                                    
                                    <?php if ($receita['em_destaque'] ?? 0): ?>
                                        <span class="badge badge-featured">⭐ Destaque</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                                    <span style="color: var(--text-light); font-size: 0.9rem;">
                                        👤 <?php echo sanitizar($receita['nome_usuario']); ?>
                                    </span>
                                    <span style="color: var(--text-light); font-size: 0.9rem;">
                                        📁 <?php echo sanitizar($receita['categoria_nome']); ?>
                                    </span>
                                    <span style="color: var(--text-light); font-size: 0.9rem;">
                                        ⏱️ <?php echo formatarTempo($receita['tempo_preparo']); ?>
                                    </span>
                                </div>
                                
                                <div class="stats-mini">
                                    <span>👁️ <?php echo number_format($receita['visualizacoes']); ?> visualizações</span>
                                    <span>❤️ <?php echo $receita['total_curtidas']; ?> curtidas</span>
                                    <span>💬 <?php echo $receita['total_comentarios']; ?> comentários</span>
                                    <span>⭐ <?php echo $receita['total_favoritos']; ?> favoritos</span>
                                </div>
                                
                                <div style="margin-top: 0.5rem;">
                                    <small style="color: var(--text-light);">
                                        Publicada em: <?php echo date('d/m/Y H:i', strtotime($receita['data_criacao'])); ?>
                                    </small>
                                </div>
                            </div>
                            
                            <!-- Ações -->
                            <div style="padding: 1rem; display: flex; flex-direction: column; gap: 0.5rem;">
                                <a href="../receita.php?id=<?php echo $receita['id']; ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem; text-align: center; font-size: 0.8rem;" target="_blank">
                                    👁️ Visualizar
                                </a>
                                
                                <?php if ($receita['ativo']): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Desativar esta receita?')">
                                        <input type="hidden" name="acao" value="desativar">
                                        <input type="hidden" name="receita_id" value="<?php echo $receita['id']; ?>">
                                        <button type="submit" class="btn" style="background: #dc3545; color: white; padding: 0.5rem 1rem; font-size: 0.8rem; width: 100%;">
                                            🚫 Desativar
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="acao" value="ativar">
                                        <input type="hidden" name="receita_id" value="<?php echo $receita['id']; ?>">
                                        <button type="submit" class="btn" style="background: #28a745; color: white; padding: 0.5rem 1rem; font-size: 0.8rem; width: 100%;">
                                            ✅ Ativar
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if (!($receita['em_destaque'] ?? 0)): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="acao" value="destacar">
                                        <input type="hidden" name="receita_id" value="<?php echo $receita['id']; ?>">
                                        <button type="submit" class="btn" style="background: var(--accent-color); color: white; padding: 0.5rem 1rem; font-size: 0.8rem; width: 100%;">
                                            ⭐ Destacar
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="acao" value="remover_destaque">
                                        <input type="hidden" name="receita_id" value="<?php echo $receita['id']; ?>">
                                        <button type="submit" class="btn" style="background: #6c757d; color: white; padding: 0.5rem 1rem; font-size: 0.8rem; width: 100%;">
                                            ⭐ Remover Destaque
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Animações
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.receita-admin-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.4s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateX(0)';
                }, index * 50);
            });
        });

        // Confirmação para ações críticas
        document.querySelectorAll('form[onsubmit*="confirm"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const acao = this.querySelector('input[name="acao"]').value;
                let mensagem = '';
                
                switch(acao) {
                    case 'desativar':
                        mensagem = 'Tem certeza que deseja desativar esta receita? Ela não será mais visível para os usuários.';
                        break;
                    case 'ativar':
                        mensagem = 'Tem certeza que deseja ativar esta receita?';
                        break;
                    case 'destacar':
                        mensagem = 'Destacar esta receita na página inicial?';
                        break;
                    case 'remover_destaque':
                        mensagem = 'Remover esta receita dos destaques?';
                        break;
                }
                
                if (mensagem && !confirm(mensagem)) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>