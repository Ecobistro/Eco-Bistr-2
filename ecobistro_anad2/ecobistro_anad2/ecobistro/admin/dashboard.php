<?php
require_once '../config.php';
iniciarSessao();

// Verificar se é admin
if (!estaLogado() || ($_SESSION['tipo_usuario'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$usuarioLogado = getUsuarioLogado();
$db = Database::getInstance()->getConnection();

// Estatísticas gerais
$stats = [];

// Total de usuários
$stmt = $db->query("SELECT COUNT(*) as total FROM usuarios WHERE ativo = 1");
$stats['usuarios'] = $stmt->fetch()['total'];

// Total de receitas
$stmt = $db->query("SELECT COUNT(*) as total FROM receitas WHERE ativo = 1");
$stats['receitas'] = $stmt->fetch()['total'];

// Total de comentários
$stmt = $db->query("SELECT COUNT(*) as total FROM comentarios WHERE ativo = 1");
$stats['comentarios'] = $stmt->fetch()['total'];

// Total de favoritos
$stmt = $db->query("SELECT COUNT(*) as total FROM favoritos");
$stats['favoritos'] = $stmt->fetch()['total'];

// Receitas mais visualizadas (últimos 7 dias)
$stmt = $db->query("
    SELECT r.titulo, r.visualizacoes, u.nome_usuario, r.data_criacao
    FROM receitas r
    JOIN usuarios u ON r.usuario_id = u.id
    WHERE r.ativo = 1 AND r.data_criacao >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY r.visualizacoes DESC
    LIMIT 5
");
$receitasPopulares = $stmt->fetchAll();

// Usuários mais ativos (por número de receitas)
$stmt = $db->query("
    SELECT u.nome_usuario, u.email, COUNT(r.id) as total_receitas, u.data_criacao
    FROM usuarios u
    LEFT JOIN receitas r ON u.id = r.usuario_id AND r.ativo = 1
    WHERE u.ativo = 1
    GROUP BY u.id
    ORDER BY total_receitas DESC
    LIMIT 5
");
$usuariosAtivos = $stmt->fetchAll();

// Comentários recentes
$stmt = $db->query("
    SELECT c.comentario, c.data_comentario, u.nome_usuario, r.titulo
    FROM comentarios c
    JOIN usuarios u ON c.usuario_id = u.id
    JOIN receitas r ON c.receita_id = r.id
    WHERE c.ativo = 1
    ORDER BY c.data_comentario DESC
    LIMIT 5
");
$comentariosRecentes = $stmt->fetchAll();

// Relatórios por categoria
$stmt = $db->query("
    SELECT c.nome, c.slug, COUNT(r.id) as total_receitas,
           AVG(r.visualizacoes) as media_visualizacoes
    FROM categorias c
    LEFT JOIN receitas r ON c.id = r.categoria_id AND r.ativo = 1
    GROUP BY c.id
    ORDER BY total_receitas DESC
");
$relatorioCategorias = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - Eco Bistrô</title>
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
        
        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: var(--shadow);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--accent-color);
        }
        
        .stat-label {
            color: var(--text-light);
            margin-top: 0.5rem;
        }
        
        .admin-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .admin-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .admin-table th {
            background: var(--primary-color);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
        }
        
        .admin-table td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .admin-table tr:hover {
            background: var(--background-light);
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
            <a href="dashboard.php" style="display: block; color: white; text-decoration: none; padding: 1rem; border-radius: 10px; margin-bottom: 0.5rem; background: var(--accent-color);">
                📊 Dashboard
            </a>
            <a href="usuarios.php" style="display: block; color: white; text-decoration: none; padding: 1rem; border-radius: 10px; margin-bottom: 0.5rem; transition: var(--transition);" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
                👥 Usuários
            </a>
            <a href="receitas.php" style="display: block; color: white; text-decoration: none; padding: 1rem; border-radius: 10px; margin-bottom: 0.5rem; transition: var(--transition);" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
                🍽️ Receitas
            </a>
            <a href="comentarios.php" style="display: block; color: white; text-decoration: none; padding: 1rem; border-radius: 10px; margin-bottom: 0.5rem; transition: var(--transition);" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
                💬 Comentários
            </a>
            <a href="categorias.php" style="display: block; color: white; text-decoration: none; padding: 1rem; border-radius: 10px; margin-bottom: 0.5rem; transition: var(--transition);" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
                📁 Categorias
            </a>
            <a href="relatorios.php" style="display: block; color: white; text-decoration: none; padding: 1rem; border-radius: 10px; margin-bottom: 0.5rem; transition: var(--transition);" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
                📈 Relatórios
            </a>
            <a href="../index.php" style="display: block; color: white; text-decoration: none; padding: 1rem; border-radius: 10px; margin-bottom: 0.5rem; transition: var(--transition); margin-top: 2rem; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
                🏠 Voltar ao Site
            </a>
            <a href="../logout.php" style="display: block; color: white; text-decoration: none; padding: 1rem; border-radius: 10px; margin-bottom: 0.5rem; transition: var(--transition); background: #dc3545;" onmouseover="this.style.background='#c82333'" onmouseout="this.style.background='#dc3545'">
                🚪 Logout
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="admin-content">
        <div style="margin-bottom: 2rem;">
            <h1 style="color: var(--text-dark); margin-bottom: 0.5rem;">Dashboard</h1>
            <p style="color: var(--text-light);">Bem-vindo, <?php echo sanitizar($usuarioLogado['nome_usuario']); ?>!</p>
        </div>

        <!-- Estatísticas principais -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['usuarios']); ?></div>
                <div class="stat-label">Usuários Ativos</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['receitas']); ?></div>
                <div class="stat-label">Receitas Publicadas</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['comentarios']); ?></div>
                <div class="stat-label">Comentários</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['favoritos']); ?></div>
                <div class="stat-label">Favoritos</div>
            </div>
        </div>

        <!-- Grid de conteúdo -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 3rem;">
            <!-- Receitas mais populares -->
            <div class="admin-table">
                <h3 style="padding: 1rem; margin: 0; background: var(--primary-color); color: var(--text-dark);">
                    🔥 Receitas Populares (7 dias)
                </h3>
                <table>
                    <thead>
                        <tr>
                            <th>Receita</th>
                            <th>Autor</th>
                            <th>Visualizações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($receitasPopulares)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: var(--text-light); padding: 2rem;">
                                    Nenhuma receita encontrada
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($receitasPopulares as $receita): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo sanitizar($receita['titulo']); ?></strong><br>
                                        <small style="color: var(--text-light);">
                                            <?php echo date('d/m/Y', strtotime($receita['data_criacao'])); ?>
                                        </small>
                                    </td>
                                    <td><?php echo sanitizar($receita['nome_usuario']); ?></td>
                                    <td>
                                        <span style="background: var(--accent-color); color: white; padding: 0.25rem 0.5rem; border-radius: 10px; font-size: 0.8rem;">
                                            <?php echo number_format($receita['visualizacoes']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Usuários mais ativos -->
            <div class="admin-table">
                <h3 style="padding: 1rem; margin: 0; background: var(--secondary-color); color: var(--text-dark);">
                    👑 Usuários Mais Ativos
                </h3>
                <table>
                    <thead>
                        <tr>
                            <th>Usuário</th>
                            <th>Receitas</th>
                            <th>Membro desde</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuariosAtivos as $usuario): ?>
                            <tr>
                                <td>
                                    <strong>@<?php echo sanitizar($usuario['nome_usuario']); ?></strong><br>
                                    <small style="color: var(--text-light);">
                                        <?php echo sanitizar($usuario['email']); ?>
                                    </small>
                                </td>
                                <td>
                                    <span style="background: var(--primary-color); padding: 0.25rem 0.5rem; border-radius: 10px; font-size: 0.8rem;">
                                        <?php echo $usuario['total_receitas']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($usuario['data_criacao'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Comentários recentes e relatório de categorias -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <!-- Comentários recentes -->
            <div class="admin-table">
                <h3 style="padding: 1rem; margin: 0; background: #C7CEEA; color: var(--text-dark);">
                    💭 Comentários Recentes
                </h3>
                <div style="padding: 1rem;">
                    <?php if (empty($comentariosRecentes)): ?>
                        <p style="text-align: center; color: var(--text-light); padding: 2rem;">
                            Nenhum comentário encontrado
                        </p>
                    <?php else: ?>
                        <?php foreach ($comentariosRecentes as $comentario): ?>
                            <div style="margin-bottom: 1rem; padding: 1rem; background: var(--background-light); border-radius: 10px;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                    <strong style="color: var(--text-dark);">
                                        @<?php echo sanitizar($comentario['nome_usuario']); ?>
                                    </strong>
                                    <small style="color: var(--text-light);">
                                        <?php echo date('d/m H:i', strtotime($comentario['data_comentario'])); ?>
                                    </small>
                                </div>
                                <p style="margin-bottom: 0.5rem; font-size: 0.9rem;">
                                    "<?php echo sanitizar(substr($comentario['comentario'], 0, 100)); ?><?php echo strlen($comentario['comentario']) > 100 ? '...' : ''; ?>"
                                </p>
                                <small style="color: var(--accent-color);">
                                    em: <?php echo sanitizar($comentario['titulo']); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Relatório por categorias -->
            <div class="admin-table">
                <h3 style="padding: 1rem; margin: 0; background: #FFB7B2; color: var(--text-dark);">
                    📊 Receitas por Categoria
                </h3>
                <table>
                    <thead>
                        <tr>
                            <th>Categoria</th>
                            <th>Receitas</th>
                            <th>Média Views</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($relatorioCategorias as $categoria): ?>
                            <tr>
                                <td><strong><?php echo sanitizar($categoria['nome']); ?></strong></td>
                                <td>
                                    <span style="background: var(--primary-color); padding: 0.25rem 0.5rem; border-radius: 10px; font-size: 0.8rem;">
                                        <?php echo $categoria['total_receitas']; ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($categoria['media_visualizacoes'] ?? 0, 0); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Ações rápidas -->
        <div style="margin-top: 3rem; padding: 2rem; background: white; border-radius: 15px; box-shadow: var(--shadow);">
            <h3 style="margin-bottom: 1.5rem; color: var(--text-dark);">⚡ Ações Rápidas</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <a href="usuarios.php?acao=novo" class="btn btn-primary" style="text-align: center; padding: 1rem;">
                    👤 Novo Usuário
                </a>
                <a href="categorias.php?acao=nova" class="btn btn-secondary" style="text-align: center; padding: 1rem;">
                    📁 Nova Categoria
                </a>
                <a href="receitas.php?filtro=pendentes" class="btn btn-primary" style="text-align: center; padding: 1rem;">
                    🔍 Revisar Receitas
                </a>
                <a href="relatorios.php" class="btn btn-secondary" style="text-align: center; padding: 1rem;">
                    📈 Relatórios Detalhados
                </a>
            </div>
        </div>
    </div>

    <script>
        // Atualizar estatísticas em tempo real (opcional)
        function atualizarStats() {
            // Pode implementar AJAX para atualizar dados sem recarregar
            console.log('Atualizando estatísticas...');
        }

        // Animações
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .admin-table');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Auto-refresh das estatísticas a cada 5 minutos
        setInterval(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html>