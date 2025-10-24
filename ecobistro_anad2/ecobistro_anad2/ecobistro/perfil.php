<?php
require_once 'config.php';
iniciarSessao();

if (!estaLogado()) {
    header('Location: login.php');
    exit;
}

$usuarioLogado = getUsuarioLogado();
$db = Database::getInstance()->getConnection();

// Buscar estatísticas do usuário
$stmt = $db->prepare("SELECT COUNT(*) as total FROM receitas WHERE usuario_id = ? AND ativo = 1");
$stmt->execute([$usuarioLogado['id']]);
$totalReceitas = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM seguidores WHERE seguido_id = ?");
$stmt->execute([$usuarioLogado['id']]);
$totalSeguidores = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM seguidores WHERE seguidor_id = ?");
$stmt->execute([$usuarioLogado['id']]);
$totalSeguindo = $stmt->fetch()['total'];

// Buscar receitas do usuário
$stmt = $db->prepare("
    SELECT r.*, c.nome as categoria_nome,
           (SELECT COUNT(*) FROM favoritos f WHERE f.receita_id = r.id) as total_favoritos,
           (SELECT COUNT(*) FROM curtidas cu WHERE cu.receita_id = r.id) as total_curtidas
    FROM receitas r
    JOIN categorias c ON r.categoria_id = c.id
    WHERE r.usuario_id = ? AND r.ativo = 1
    ORDER BY r.data_criacao DESC
");
$stmt->execute([$usuarioLogado['id']]);
$minhasReceitas = $stmt->fetchAll();

// Buscar receitas favoritas
$stmt = $db->prepare("
    SELECT r.*, u.nome_usuario, c.nome as categoria_nome,
           (SELECT COUNT(*) FROM favoritos f WHERE f.receita_id = r.id) as total_favoritos
    FROM receitas r
    JOIN usuarios u ON r.usuario_id = u.id
    JOIN categorias c ON r.categoria_id = c.id
    JOIN favoritos fav ON fav.receita_id = r.id
    WHERE fav.usuario_id = ? AND r.ativo = 1
    ORDER BY fav.data_favoritado DESC
");
$stmt->execute([$usuarioLogado['id']]);
$receitasFavoritas = $stmt->fetchAll();

// Buscar pastas do usuário
$stmt = $db->prepare("SELECT * FROM pastas WHERE usuario_id = ? ORDER BY nome");
$stmt->execute([$usuarioLogado['id']]);
$pastas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@<?php echo sanitizar($usuarioLogado['nome_usuario']); ?> - Eco Bistrô</title>
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
                <a href="nova-receita.php" class="btn btn-primary">+ NOVA RECEITA</a>
                <a href="logout.php" class="nav-link">SAIR</a>
            </nav>
        </div>
    </header>

    <!-- Profile Content -->
    <div class="container" style="padding: 2rem 0;">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <?php if (!empty($usuarioLogado['foto_perfil']) && file_exists("uploads/perfil/{$usuarioLogado['foto_perfil']}")): ?>
                    <img src="uploads/perfil/<?php echo $usuarioLogado['foto_perfil']; ?>" 
                         alt="Foto de <?php echo sanitizar($usuarioLogado['nome_usuario']); ?>" 
                         style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                <?php else: ?>
                    <?php echo strtoupper(substr($usuarioLogado['nome_usuario'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <h1>@<?php echo sanitizar($usuarioLogado['nome_usuario']); ?></h1>
            <?php if ($usuarioLogado['biografia']): ?>
                <p style="color: var(--text-light); max-width: 600px; margin: 0 auto;">
                    <?php echo nl2br(sanitizar($usuarioLogado['biografia'])); ?>
                </p>
            <?php endif; ?>
            
            <div class="profile-stats">
                <div class="stat">
                    <div class="stat-number"><?php echo $totalSeguindo; ?></div>
                    <div class="stat-label">SEGUINDO</div>
                </div>
                <div class="stat">
                    <div class="stat-number"><?php echo $totalSeguidores; ?></div>
                    <div class="stat-label">SEGUIDORES</div>
                </div>
                <div class="stat">
                    <div class="stat-number"><?php echo $totalReceitas; ?></div>
                    <div class="stat-label">RECEITAS</div>
                </div>
            </div>
            
            <div style="margin-top: 1.5rem; display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="editar-perfil.php" class="btn btn-secondary">Editar Perfil</a>
                <a href="minhas-preferencias.php" class="btn btn-primary">🍽️ Preferências</a>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="tabs">
            <button class="tab active" onclick="mostrarTab('postagens')">Postagens</button>
            <button class="tab" onclick="mostrarTab('favoritos')">Favoritos</button>
            <button class="tab" onclick="mostrarTab('pastas')">Pastas</button>
        </div>

        <!-- Tab Content: Postagens -->
        <div id="tab-postagens" class="tab-content">
            <?php if (empty($minhasReceitas)): ?>
                <div style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 20px; box-shadow: var(--shadow);">
                    <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;">🍴</div>
                    <h3 style="margin-bottom: 1rem; color: var(--text-dark);">Nenhuma receita postada ainda</h3>
                    <p style="color: var(--text-light); margin-bottom: 2rem;">
                        Que tal compartilhar sua primeira receita deliciosa?
                    </p>
                    <a href="nova-receita.php" class="btn btn-primary">+ Postar primeira receita</a>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 2rem;">
                    <?php foreach ($minhasReceitas as $receita): ?>
                        <div class="receita-card">
                            <div class="receita-img">
                                <?php if ($receita['imagem']): ?>
                                    <img src="uploads/receitas/<?php echo $receita['imagem']; ?>" alt="<?php echo sanitizar($receita['titulo']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <?php echo sanitizar($receita['titulo']); ?>
                                <?php endif; ?>
                                
                                <!-- Ações da receita -->
                                <div style="position: absolute; top: 0.5rem; right: 0.5rem; display: flex; gap: 0.5rem;">
                                    <a href="editar-receita.php?id=<?php echo $receita['id']; ?>" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                        ✏️ Editar
                                    </a>
                                </div>
                            </div>
                            <div class="receita-content">
                                <h3 class="receita-title"><?php echo sanitizar($receita['titulo']); ?></h3>
                                <div class="receita-meta">
                                    <span>⏱️ <?php echo formatarTempo($receita['tempo_preparo']); ?></span>
                                    <span>🏷️ <?php echo sanitizar($receita['categoria_nome']); ?></span>
                                    <span>❤️ <?php echo $receita['total_curtidas']; ?></span>
                                </div>
                                <div class="receita-actions">
                                    <a href="receita.php?id=<?php echo $receita['id']; ?>" class="btn-ver">VER</a>
                                    <span style="font-size: 0.8rem; color: var(--text-light);">
                                        <?php echo date('d/m/Y', strtotime($receita['data_criacao'])); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab Content: Favoritos -->
        <div id="tab-favoritos" class="tab-content" style="display: none;">
            <?php if (empty($receitasFavoritas)): ?>
                <div style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 20px; box-shadow: var(--shadow);">
                    <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;">⭐</div>
                    <h3 style="margin-bottom: 1rem; color: var(--text-dark);">Nenhuma receita favoritada ainda</h3>
                    <p style="color: var(--text-light); margin-bottom: 2rem;">
                        Explore receitas incríveis e favorite suas preferidas!
                    </p>
                    <a href="receitas.php" class="btn btn-primary">Explorar receitas</a>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 2rem;">
                    <?php foreach ($receitasFavoritas as $receita): ?>
                        <div class="receita-card">
                            <div class="receita-img">
                                <?php if ($receita['imagem']): ?>
                                    <img src="uploads/receitas/<?php echo $receita['imagem']; ?>" alt="<?php echo sanitizar($receita['titulo']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <?php echo sanitizar($receita['titulo']); ?>
                                <?php endif; ?>
                                
                                <!-- Botão de desfavoritar -->
                                <div style="position: absolute; top: 0.5rem; right: 0.5rem;">
                                    <button onclick="favoritar(<?php echo $receita['id']; ?>)" class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                        ⭐ Favoritado
                                    </button>
                                </div>
                            </div>
                            <div class="receita-content">
                                <h3 class="receita-title"><?php echo sanitizar($receita['titulo']); ?></h3>
                                <div class="receita-meta">
                                    <span>⏱️ <?php echo formatarTempo($receita['tempo_preparo']); ?></span>
                                    <span>👤 <?php echo sanitizar($receita['nome_usuario']); ?></span>
                                    <span>❤️ <?php echo $receita['total_favoritos']; ?></span>
                                </div>
                                <div class="receita-actions">
                                    <a href="receita.php?id=<?php echo $receita['id']; ?>" class="btn-ver">VER</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab Content: Pastas -->
        <div id="tab-pastas" class="tab-content" style="display: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h3>Minhas Pastas</h3>
                <button onclick="criarPasta()" class="btn btn-primary">+ Nova Pasta</button>
            </div>
            
            <!-- Pastas padrão -->
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                <div class="categoria-card" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));">
                    <div class="categoria-icon">⭐</div>
                    <h4>Favoritos</h4>
                    <p style="font-size: 0.9rem; color: var(--text-light);"><?php echo count($receitasFavoritas); ?> receitas</p>
                </div>
                
                <div class="categoria-card" style="background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));">
                    <div class="categoria-icon">⏰</div>
                    <h4>Fazer mais tarde</h4>
                    <p style="font-size: 0.9rem; color: var(--text-light);">0 receitas</p>
                </div>
            </div>
            
            <!-- Pastas personalizadas -->
            <?php if (empty($pastas)): ?>
                <div style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 20px; box-shadow: var(--shadow);">
                    <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;">📁</div>
                    <h3 style="margin-bottom: 1rem; color: var(--text-dark);">Nenhuma pasta criada ainda</h3>
                    <p style="color: var(--text-light); margin-bottom: 2rem;">
                        Organize suas receitas em pastas personalizadas!
                    </p>
                    <button onclick="criarPasta()" class="btn btn-primary">+ Criar primeira pasta</button>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1.5rem;">
                    <?php foreach ($pastas as $pasta): ?>
                        <?php
                        // Buscar número de receitas na pasta
                        $stmt = $db->prepare("SELECT COUNT(*) as total FROM pasta_receitas WHERE pasta_id = ?");
                        $stmt->execute([$pasta['id']]);
                        $totalReceitasPasta = $stmt->fetch()['total'];
                        ?>
                        <div class="categoria-card" onclick="abrirPasta(<?php echo $pasta['id']; ?>)" style="cursor: pointer;">
                            <div class="categoria-icon">📁</div>
                            <h4><?php echo sanitizar($pasta['nome']); ?></h4>
                            <p style="font-size: 0.9rem; color: var(--text-light);"><?php echo $totalReceitasPasta; ?> receitas</p>
                            <?php if ($pasta['descricao']): ?>
                                <p style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.5rem;">
                                    <?php echo sanitizar($pasta['descricao']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para criar pasta -->
    <div id="modalCriarPasta" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 20px; max-width: 400px; width: 90%;">
            <h3 style="margin-bottom: 1.5rem;">Nova Pasta</h3>
            <form id="formCriarPasta">
                <div class="form-group">
                    <label for="nomePasta" class="form-label">Nome da pasta</label>
                    <input type="text" id="nomePasta" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="descricaoPasta" class="form-label">Descrição (opcional)</label>
                    <textarea id="descricaoPasta" class="form-control" rows="3"></textarea>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">Criar Pasta</button>
                    <button type="button" onclick="fecharModalPasta()" class="btn btn-secondary">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Controle de tabs
        function mostrarTab(tabName) {
            // Esconder todas as tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Remover classe active de todos os botões
            document.querySelectorAll('.tab').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Mostrar tab selecionada
            document.getElementById(`tab-${tabName}`).style.display = 'block';
            
            // Adicionar classe active ao botão clicado
            event.target.classList.add('active');
        }

        // Função para favoritar/desfavoritar
        async function favoritar(receitaId) {
            try {
                const response = await fetch('ajax/favoritar.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ receita_id: receitaId })
                });
                
                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.message || 'Erro ao favoritar');
                }
            } catch (error) {
                console.error('Erro:', error);
            }
        }

        // Funções de pasta
        function criarPasta() {
            document.getElementById('modalCriarPasta').style.display = 'flex';
            document.getElementById('nomePasta').focus();
        }

        function fecharModalPasta() {
            document.getElementById('modalCriarPasta').style.display = 'none';
            document.getElementById('formCriarPasta').reset();
        }

        function abrirPasta(pastaId) {
            window.location.href = `pasta.php?id=${pastaId}`;
        }

        // Evento para criar pasta
        document.getElementById('formCriarPasta').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const nome = document.getElementById('nomePasta').value.trim();
            const descricao = document.getElementById('descricaoPasta').value.trim();
            
            if (!nome) {
                alert('Por favor, insira um nome para a pasta.');
                return;
            }
            
            try {
                const response = await fetch('ajax/criar-pasta.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ nome, descricao })
                });
                
                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.message || 'Erro ao criar pasta');
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao criar pasta');
            }
        });

        // Fechar modal ao clicar fora
        document.getElementById('modalCriarPasta').addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModalPasta();
            }
        });

        // Animações
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.receita-card, .categoria-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 50);
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