<?php
require_once 'config.php';
iniciarSessao();

$slug = $_GET['slug'] ?? '';
if (!$slug) {
    header('Location: receitas.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$usuarioLogado = getUsuarioLogado();

// Buscar categoria
$stmt = $db->prepare("SELECT * FROM categorias WHERE slug = ?");
$stmt->execute([$slug]);
$categoria = $stmt->fetch();

if (!$categoria) {
    header('Location: receitas.php');
    exit;
}

// Filtros adicionais
$busca = $_GET['busca'] ?? '';
$ordenacao = $_GET['ordenacao'] ?? 'recentes';

// Construir query
$where = "WHERE r.ativo = 1 AND c.slug = ?";
$params = [$slug];

if ($busca) {
    $where .= " AND (r.titulo LIKE ? OR r.ingredientes LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

// Ordenação
$orderBy = "ORDER BY ";
switch ($ordenacao) {
    case 'populares':
        $orderBy .= "(SELECT COUNT(*) FROM favoritos f WHERE f.receita_id = r.id) DESC, r.visualizacoes DESC";
        break;
    case 'curtidas':
        $orderBy .= "(SELECT COUNT(*) FROM curtidas cu WHERE cu.receita_id = r.id) DESC";
        break;
    case 'tempo':
        $orderBy .= "r.tempo_preparo ASC";
        break;
    default:
        $orderBy .= "r.data_criacao DESC";
}

// Buscar receitas da categoria
$stmt = $db->prepare("
    SELECT r.*, u.nome_usuario, c.nome as categoria_nome, c.slug as categoria_slug,
           (SELECT COUNT(*) FROM favoritos f WHERE f.receita_id = r.id) as total_favoritos,
           (SELECT COUNT(*) FROM curtidas cu WHERE cu.receita_id = r.id) as total_curtidas
    FROM receitas r
    JOIN usuarios u ON r.usuario_id = u.id
    JOIN categorias c ON r.categoria_id = c.id
    $where
    $orderBy
    LIMIT 20
");
$stmt->execute($params);
$receitas = $stmt->fetchAll();

// Ícones das categorias
$iconesCategorias = [
    'veganas' => '🌱',
    'doces' => '🍰',
    'salgados' => '🍽️',
    'bebidas' => '🥤',
    'rapidas' => '⚡',
    'saudaveis' => '💚',
    'massas' => '🍝',
    'reutilizando' => '♻️'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitizar($categoria['nome']); ?> - Eco Bistrô</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="nav-container">
            <a href="index.php" class="logo">ECO BISTRÔ</a>
            <nav class="nav-menu">
                <a href="receitas.php" class="nav-link">RECEITAS</a>
                <?php if ($usuarioLogado): ?>
                    <a href="perfil.php" class="nav-link">@<?php echo sanitizar($usuarioLogado['nome_usuario']); ?></a>
                    <a href="nova-receita.php" class="btn btn-primary">+ NOVA RECEITA</a>
                    <a href="logout.php" class="nav-link">SAIR</a>
                <?php else: ?>
                    <a href="login.php" class="nav-link">LOGIN</a>
                    <a href="cadastro.php" class="btn btn-primary">CADASTRE-SE</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Category Header -->
    <section class="section" style="background: linear-gradient(135deg, <?php echo $categoria['cor']; ?>, var(--secondary-color)); padding: 3rem 0;">
        <div class="container">
            <div style="text-align: center; color: var(--text-dark);">
                <div style="font-size: 4rem; margin-bottom: 1rem;">
                    <?php echo $iconesCategorias[$categoria['slug']] ?? '🍴'; ?>
                </div>
                <h1 style="font-size: 3rem; margin-bottom: 1rem;">
                    <?php echo strtoupper(sanitizar($categoria['nome'])); ?>
                </h1>
                <?php if ($categoria['descricao']): ?>
                    <p style="font-size: 1.2rem; max-width: 600px; margin: 0 auto;">
                        <?php echo sanitizar($categoria['descricao']); ?>
                    </p>
                <?php endif; ?>
                <div style="margin-top: 1rem; opacity: 0.8;">
                    <a href="receitas.php" style="color: var(--text-dark); text-decoration: none;">← Todas as receitas</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Filtros -->
    <section class="section" style="padding: 2rem 0;">
        <div class="container">
            <div style="background: white; padding: 2rem; border-radius: 20px; box-shadow: var(--shadow); margin-bottom: 2rem;">
                <form method="GET" style="display: grid; grid-template-columns: 1fr 150px auto; gap: 1rem; align-items: end;">
                    <input type="hidden" name="slug" value="<?php echo htmlspecialchars($slug); ?>">
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Buscar nesta categoria</label>
                        <input type="text" name="busca" class="form-control" 
                               placeholder="Digite ingredientes ou nome da receita..." 
                               value="<?php echo htmlspecialchars($busca); ?>">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Ordenar por</label>
                        <select name="ordenacao" class="form-control">
                            <option value="recentes" <?php echo $ordenacao === 'recentes' ? 'selected' : ''; ?>>Mais recentes</option>
                            <option value="populares" <?php echo $ordenacao === 'populares' ? 'selected' : ''; ?>>Mais populares</option>
                            <option value="curtidas" <?php echo $ordenacao === 'curtidas' ? 'selected' : ''; ?>>Mais curtidas</option>
                            <option value="tempo" <?php echo $ordenacao === 'tempo' ? 'selected' : ''; ?>>Tempo de preparo</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">🔍 Buscar</button>
                </form>
            </div>
            
            <!-- Filtros ativos -->
            <?php if ($busca): ?>
                <div style="margin-bottom: 2rem;">
                    <p style="margin-bottom: 1rem; color: var(--text-light);">Filtros ativos:</p>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <span style="background: var(--primary-color); padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.9rem;">
                            Busca: "<?php echo sanitizar($busca); ?>"
                            <a href="?slug=<?php echo urlencode($slug); ?>&ordenacao=<?php echo urlencode($ordenacao); ?>" 
                               style="margin-left: 0.5rem; color: var(--text-dark);">✕</a>
                        </span>
                        <a href="categoria.php?slug=<?php echo urlencode($slug); ?>" 
                           style="background: var(--accent-color); color: white; padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.9rem; text-decoration: none;">
                            Limpar filtros
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Resultados -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h2 style="color: var(--text-dark);">Receitas de <?php echo sanitizar($categoria['nome']); ?></h2>
                <p style="color: var(--text-light);"><?php echo count($receitas); ?> receitas encontradas</p>
            </div>
            
            <?php if (empty($receitas)): ?>
                <div style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 20px; box-shadow: var(--shadow);">
                    <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;">
                        <?php echo $iconesCategorias[$categoria['slug']] ?? '🍴'; ?>
                    </div>
                    <h3 style="margin-bottom: 1rem; color: var(--text-dark);">
                        Nenhuma receita encontrada<?php echo $busca ? ' para sua busca' : ''; ?>
                    </h3>
                    <p style="color: var(--text-light); margin-bottom: 2rem;">
                        <?php if ($busca): ?>
                            Tente ajustar sua busca ou explore outras opções
                        <?php else: ?>
                            Seja o primeiro a compartilhar uma receita nesta categoria!
                        <?php endif; ?>
                    </p>
                    <?php if ($busca): ?>
                        <a href="categoria.php?slug=<?php echo urlencode($slug); ?>" class="btn btn-secondary" style="margin-right: 1rem;">
                            Ver todas de <?php echo sanitizar($categoria['nome']); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($usuarioLogado): ?>
                        <a href="nova-receita.php" class="btn btn-primary">+ Adicionar receita</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="destaques-grid">
                    <?php foreach ($receitas as $receita): ?>
                        <div class="receita-card">
                            <div class="receita-img">
                                <?php if ($receita['imagem']): ?>
                                    <img src="uploads/receitas/<?php echo $receita['imagem']; ?>" 
                                         alt="<?php echo sanitizar($receita['titulo']); ?>" 
                                         style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; font-weight: 600;">
                                        <?php echo sanitizar($receita['titulo']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="receita-content">
                                <h3 class="receita-title"><?php echo sanitizar($receita['titulo']); ?></h3>
                                <div class="receita-meta">
                                    <span>⏱️ <?php echo formatarTempo($receita['tempo_preparo']); ?></span>
                                    <span>👤 <?php echo sanitizar($receita['nome_usuario']); ?></span>
                                    <span>❤️ <?php echo $receita['total_curtidas']; ?></span>
                                </div>
                                <?php if ($receita['descricao']): ?>
                                    <p style="font-size: 0.9rem; color: var(--text-light); margin: 0.5rem 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                        <?php echo sanitizar($receita['descricao']); ?>
                                    </p>
                                <?php endif; ?>
                                <div class="receita-actions">
                                    <a href="receita.php?id=<?php echo $receita['id']; ?>" class="btn-ver">VER</a>
                                    <span style="background: <?php echo $categoria['cor']; ?>; color: var(--text-dark); padding: 0.25rem 0.5rem; border-radius: 10px; font-size: 0.8rem;">
                                        <?php echo $iconesCategorias[$categoria['slug']] ?? '🍴'; ?> <?php echo sanitizar($categoria['nome']); ?>
                                    </span>
                                    <?php if ($usuarioLogado): ?>
                                        <button onclick="favoritar(<?php echo $receita['id']; ?>)" 
                                                class="btn-favoritar" 
                                                style="background: none; border: none; color: var(--accent-color); cursor: pointer;">
                                            ⭐ <?php echo $receita['total_favoritos']; ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Outras Categorias -->
    <section class="section" style="background: white;">
        <div class="container">
            <h2 style="text-align: center; margin-bottom: 2rem;">Explore Outras Categorias</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                <?php
                $stmt = $db->prepare("SELECT * FROM categorias WHERE slug != ? ORDER BY nome");
                $stmt->execute([$slug]);
                $outrasCategorias = $stmt->fetchAll();
                
                foreach ($outrasCategorias as $outraCategoria):
                ?>
                    <a href="categoria.php?slug=<?php echo $outraCategoria['slug']; ?>" 
                       class="categoria-card" 
                       style="padding: 1.5rem; text-decoration: none;">
                        <div class="categoria-icon" style="font-size: 2rem; margin-bottom: 0.5rem;">
                            <?php echo $iconesCategorias[$outraCategoria['slug']] ?? '🍴'; ?>
                        </div>
                        <h4 style="font-size: 0.9rem;"><?php echo strtoupper(sanitizar($outraCategoria['nome'])); ?></h4>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-quote">
                COZINHAR PODE SER LEVE, DIVERTIDO E SUSTENTÁVEL.
            </div>
            <div class="footer-contact">
                <div class="contact-item">
                    <span>📞</span>
                    <div>
                        <strong>TELEFONE</strong><br>
                        (12) 3456-7890
                    </div>
                </div>
                <div class="contact-item">
                    <span>📧</span>
                    <div>
                        <strong>E-MAIL</strong><br>
                        ecobistro@gmail.com
                    </div>
                </div>
            </div>
            <p style="margin-top: 2rem; opacity: 0.8;">
                Seja bem-vindo(a) ao Eco Bistrô, onde cozinhar é um gesto de amor.
            </p>
        </div>
    </footer>

    <script>
        // Função para favoritar receita
        async function favoritar(receitaId) {
            try {
                const response = await fetch('ajax/favoritar.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ receita_id: receitaId })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.message || 'Erro ao favoritar receita');
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao favoritar receita');
            }
        }

        // Animações ao carregar
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.receita-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>