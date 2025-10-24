<?php
require_once 'config.php';
iniciarSessao();

$db = Database::getInstance()->getConnection();
$usuarioLogado = getUsuarioLogado();

// Filtros
$categoria = $_GET['categoria'] ?? '';
$busca = $_GET['busca'] ?? '';
$ordenacao = $_GET['ordenacao'] ?? 'recentes';

// Construir query base
$where = "WHERE r.ativo = 1";
$params = [];

if ($categoria) {
    $where .= " AND c.slug = ?";
    $params[] = $categoria;
}

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
        $orderBy .= "(SELECT COUNT(*) FROM curtidas c WHERE c.receita_id = r.id) DESC";
        break;
    case 'tempo':
        $orderBy .= "r.tempo_preparo ASC";
        break;
    default:
        $orderBy .= "r.data_criacao DESC";
}

// Buscar receitas
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

// Buscar categorias para filtro
$stmt = $db->query("SELECT * FROM categorias ORDER BY nome");
$categorias = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receitas - Eco Bistrô</title>
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

    <!-- Filtros e Busca -->
    <section class="section" style="padding: 2rem 0;">
        <div class="container">
            <div style="background: white; padding: 2rem; border-radius: 20px; box-shadow: var(--shadow); margin-bottom: 2rem;">
                <form method="GET" style="display: grid; grid-template-columns: 1fr 200px 150px auto; gap: 1rem; align-items: end;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Buscar receitas</label>
                        <input type="text" name="busca" class="form-control" placeholder="Digite ingredientes ou nome da receita..." value="<?php echo htmlspecialchars($busca); ?>">
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
            <?php if ($categoria || $busca): ?>
                <div style="margin-bottom: 2rem;">
                    <p style="margin-bottom: 1rem; color: var(--text-light);">Filtros ativos:</p>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <?php if ($categoria): ?>
                            <span style="background: var(--primary-color); padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.9rem;">
                                Categoria: <?php 
                                    $catNome = array_filter($categorias, fn($c) => $c['slug'] === $categoria)[0]['nome'] ?? $categoria;
                                    echo sanitizar($catNome); 
                                ?>
                                <a href="?<?php echo http_build_query(array_diff_key($_GET, ['categoria' => ''])); ?>" style="margin-left: 0.5rem; color: var(--text-dark);">✕</a>
                            </span>
                        <?php endif; ?>
                        <?php if ($busca): ?>
                            <span style="background: var(--secondary-color); padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.9rem;">
                                Busca: "<?php echo sanitizar($busca); ?>"
                                <a href="?<?php echo http_build_query(array_diff_key($_GET, ['busca' => ''])); ?>" style="margin-left: 0.5rem; color: var(--text-dark);">✕</a>
                            </span>
                        <?php endif; ?>
                        <a href="receitas.php" style="background: var(--accent-color); color: white; padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.9rem; text-decoration: none;">
                            Limpar todos
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Resultados -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h1 style="color: var(--text-dark);">Receitas</h1>
                <p style="color: var(--text-light);"><?php echo count($receitas); ?> receitas encontradas</p>
            </div>
            
            <?php if (empty($receitas)): ?>
                <div style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 20px; box-shadow: var(--shadow);">
                    <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;">🔍</div>
                    <h3 style="margin-bottom: 1rem; color: var(--text-dark);">Nenhuma receita encontrada</h3>
                    <p style="color: var(--text-light); margin-bottom: 2rem;">
                        Tente ajustar seus filtros ou explore outras categorias
                    </p>
                    <a href="receitas.php" class="btn btn-primary">Ver todas as receitas</a>
                </div>
            <?php else: ?>
                <div class="destaques-grid">
    <?php foreach ($receitas as $receita): ?>
        <div class="receita-card">
            <div class="receita-img">
                <?php if ($receita['imagem']): ?>
                    <img src="uploads/receitas/<?php echo $receita['imagem']; ?>" alt="<?php echo sanitizar($receita['titulo']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <?php echo sanitizar($receita['titulo']); ?>
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
                    <span style="background: var(--primary-color); color: var(--text-dark); padding: 0.25rem 0.5rem; border-radius: 10px; font-size: 0.8rem;">
                        <?php echo sanitizar($receita['categoria_nome']); ?>
                    </span>
                    
                    <?php if ($usuarioLogado): ?>
                        <button onclick="favoritar(<?php echo $receita['id']; ?>)" 
                                class="btn-favoritar" 
                                style="background: none; border: none; color: var(--accent-color); cursor: pointer; font-size: 0.8rem;">
                            ⭐ <?php echo $receita['total_favoritos']; ?>
                        </button>
                        
                        <?php if ($usuarioLogado['id'] != $receita['usuario_id']): ?>
                            <?php $estaSeguindoAutor = estaSeguindo($usuarioLogado['id'], $receita['usuario_id']); ?>
                            <button onclick="seguirUsuario(<?php echo $receita['usuario_id']; ?>, this)" 
                                    style="background: none; border: 1px solid var(--primary-color); color: var(--primary-color); padding: 0.25rem 0.5rem; border-radius: 8px; cursor: pointer; font-size: 0.7rem; <?php echo $estaSeguindoAutor ? 'background: var(--primary-color); color: var(--text-dark);' : ''; ?>">
                                <?php echo $estaSeguindoAutor ? '✓' : '+'; ?>
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
            <?php endif; ?>
        </div>
    </section>

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
    <script>
// Função para seguir/deixar de seguir usuário
async function seguirUsuario(usuarioId, btn) {
    if (!usuarioId || btn.disabled) return;
    
    const textoOriginal = btn.textContent;
    const classesOriginais = btn.className;
    
    // Estado de loading
    btn.disabled = true;
    btn.textContent = 'Carregando...';
    btn.style.opacity = '0.7';
    
    try {
        const response = await fetch('ajax/seguir.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ usuario_id: parseInt(usuarioId) })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            // Atualizar interface baseado no resultado
            if (result.seguindo) {
                // Agora está seguindo
                if (btn.classList.contains('btn-seguir-mini')) {
                    btn.textContent = '✓ Seguindo';
                    btn.style.background = 'var(--secondary-color)';
                } else if (btn.classList.contains('btn-primary')) {
                    btn.textContent = '✓ Seguindo';
                    btn.className = 'btn btn-secondary';
                } else {
                    btn.textContent = '✓';
                    btn.style.background = 'var(--primary-color)';
                    btn.style.color = 'var(--text-dark)';
                }
            } else {
                // Agora não está seguindo
                if (btn.classList.contains('btn-seguir-mini')) {
                    btn.textContent = '+ Seguir';
                    btn.style.background = 'transparent';
                } else if (btn.classList.contains('btn-secondary')) {
                    btn.textContent = '+ Seguir';
                    btn.className = 'btn btn-primary';
                } else {
                    btn.textContent = '+';
                    btn.style.background = 'transparent';
                    btn.style.color = 'var(--primary-color)';
                }
            }
            
            // Mostrar feedback sutil
            btn.style.transform = 'scale(0.95)';
            setTimeout(() => {
                btn.style.transform = 'scale(1)';
            }, 150);
            
        } else {
            // Erro retornado pela API
            alert(result.message || 'Erro ao processar solicitação');
            btn.textContent = textoOriginal;
            btn.className = classesOriginais;
        }
        
    } catch (error) {
        console.error('Erro ao seguir usuário:', error);
        alert('Erro de conexão. Tente novamente.');
        btn.textContent = textoOriginal;
        btn.className = classesOriginais;
    } finally {
        // Restaurar estado do botão
        btn.disabled = false;
        btn.style.opacity = '1';
    }
}

// Adicionar efeitos hover para os botões de seguir
document.addEventListener('DOMContentLoaded', function() {
    // Adicionar estilos CSS dinamicamente
    const style = document.createElement('style');
    style.textContent = `
        .btn-seguir-mini:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        button[onclick*="seguirUsuario"]:hover {
            transform: translateY(-1px);
        }
        
        .btn-seguir-mini {
            transition: all 0.3s ease;
        }
    `;
    document.head.appendChild(style);
    
    // Animação de entrada para cards
    const cards = document.querySelectorAll('.receita-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
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