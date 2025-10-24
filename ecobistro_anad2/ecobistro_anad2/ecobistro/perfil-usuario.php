<?php
require_once 'config.php';
iniciarSessao();

$usuarioId = intval($_GET['id'] ?? 0);

if (!$usuarioId) {
    header('Location: receitas.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// Buscar dados do usuário
$stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ? AND ativo = 1");
$stmt->execute([$usuarioId]);
$usuario = $stmt->fetch();

if (!$usuario) {
    header('Location: receitas.php');
    exit;
}

$usuarioLogado = getUsuarioLogado();
$podeEditar = $usuarioLogado && $usuarioLogado['id'] == $usuarioId;

// Buscar estatísticas do usuário
$stmt = $db->prepare("SELECT COUNT(*) as total FROM receitas WHERE usuario_id = ? AND ativo = 1");
$stmt->execute([$usuarioId]);
$totalReceitas = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM seguidores WHERE seguido_id = ?");
$stmt->execute([$usuarioId]);
$totalSeguidores = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM seguidores WHERE seguidor_id = ?");
$stmt->execute([$usuarioId]);
$totalSeguindo = $stmt->fetch()['total'];

// Verificar se o usuário logado está seguindo este perfil
$estaSeguindo = false;
if ($usuarioLogado && $usuarioLogado['id'] != $usuarioId) {
    $stmt = $db->prepare("SELECT COUNT(*) as seguindo FROM seguidores WHERE seguidor_id = ? AND seguido_id = ?");
    $stmt->execute([$usuarioLogado['id'], $usuarioId]);
    $estaSeguindo = $stmt->fetch()['seguindo'] > 0;
}

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
$stmt->execute([$usuarioId]);
$receitas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@<?php echo sanitizar($usuario['nome_usuario']); ?> - Eco Bistrô</title>
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

    <!-- Profile Content -->
    <div class="container" style="padding: 2rem 0;">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar" style="width: 120px; height: 120px; font-size: 3rem;">
                <?php if ($usuario['foto_perfil']): ?>
                    <img src="uploads/perfil/<?php echo $usuario['foto_perfil']; ?>" 
                         alt="<?php echo sanitizar($usuario['nome_usuario']); ?>" 
                         style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                <?php else: ?>
                    <?php echo strtoupper(substr($usuario['nome_usuario'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <h1>@<?php echo sanitizar($usuario['nome_usuario']); ?></h1>
            <?php if ($usuario['biografia']): ?>
                <p style="color: var(--text-light); max-width: 600px; margin: 0 auto;">
                    <?php echo nl2br(sanitizar($usuario['biografia'])); ?>
                </p>
            <?php endif; ?>
            
            <div class="profile-stats">
                <div class="stat">
                    <a href="seguidores.php?id=<?php echo $usuarioId; ?>&tipo=seguindo" style="color: inherit; text-decoration: none;">
                        <div class="stat-number"><?php echo $totalSeguindo; ?></div>
                        <div class="stat-label">SEGUINDO</div>
                    </a>
                </div>
                <div class="stat">
                    <a href="seguidores.php?id=<?php echo $usuarioId; ?>&tipo=seguidores" style="color: inherit; text-decoration: none;">
                        <div class="stat-number"><?php echo $totalSeguidores; ?></div>
                        <div class="stat-label">SEGUIDORES</div>
                    </a>
                </div>
                <div class="stat">
                    <div class="stat-number"><?php echo $totalReceitas; ?></div>
                    <div class="stat-label">RECEITAS</div>
                </div>
            </div>
            
            <div style="margin-top: 1.5rem;">
                <?php if ($podeEditar): ?>
                    <a href="editar-perfil.php" class="btn btn-secondary">Editar Perfil</a>
                <?php elseif ($usuarioLogado): ?>
                    <button id="btnSeguir" onclick="seguirUsuario(<?php echo $usuarioId; ?>)" 
                            class="btn <?php echo $estaSeguindo ? 'btn-secondary' : 'btn-primary'; ?>">
                        <?php echo $estaSeguindo ? 'Seguindo' : 'Seguir'; ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Receitas do usuário -->
        <div style="margin-top: 3rem;">
            <h3 style="margin-bottom: 2rem; text-align: center;">Receitas de @<?php echo sanitizar($usuario['nome_usuario']); ?></h3>
            
            <?php if (empty($receitas)): ?>
                <div style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 20px; box-shadow: var(--shadow);">
                    <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;">🍴</div>
                    <h3 style="margin-bottom: 1rem; color: var(--text-dark);">Nenhuma receita postada ainda</h3>
                    <p style="color: var(--text-light);">
                        Este usuário ainda não compartilhou nenhuma receita.
                    </p>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 2rem;">
                    <?php foreach ($receitas as $receita): ?>
                        <div class="receita-card">
                            <div class="receita-img">
                                <?php if ($receita['imagem']): ?>
                                    <img src="uploads/receitas/<?php echo $receita['imagem']; ?>" 
                                         alt="<?php echo sanitizar($receita['titulo']); ?>" 
                                         style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <?php echo sanitizar($receita['titulo']); ?>
                                <?php endif; ?>
                            </div>
                            <div class="receita-content">
                                <h3 class="receita-title"><?php echo sanitizar($receita['titulo']); ?></h3>
                                <div class="receita-meta">
                                    <span>⏱️ <?php echo formatarTempo($receita['tempo_preparo']); ?></span>
                                    <span>📁 <?php echo sanitizar($receita['categoria_nome']); ?></span>
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
    </div>

    <script>
        // Função para seguir/deixar de seguir usuário
        async function seguirUsuario(usuarioId) {
            const btn = document.getElementById('btnSeguir');
            const textoOriginal = btn.textContent;
            
            btn.disabled = true;
            btn.textContent = 'Carregando...';
            
            try {
                const response = await fetch('ajax/seguir.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ usuario_id: usuarioId })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    if (result.seguindo) {
                        btn.textContent = 'Seguindo';
                        btn.className = 'btn btn-secondary';
                    } else {
                        btn.textContent = 'Seguir';
                        btn.className = 'btn btn-primary';
                    }
                    
                    // Atualizar contador de seguidores
                    setTimeout(() => location.reload(), 500);
                } else {
                    alert(result.message || 'Erro ao seguir usuário');
                    btn.textContent = textoOriginal;
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao seguir usuário');
                btn.textContent = textoOriginal;
            }
            
            btn.disabled = false;
        }

        // Animações
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.receita-card');
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