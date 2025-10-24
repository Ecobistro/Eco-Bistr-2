<?php
require_once 'config.php';
iniciarSessao();

$usuarioLogado = getUsuarioLogado();
$db = Database::getInstance()->getConnection();

$termoBusca = sanitizar($_GET['busca'] ?? '');
$usuarios = [];

if (!empty($termoBusca)) {
    // Buscar usuários por nome de usuário ou biografia
    $stmt = $db->prepare("
        SELECT u.*, 
               (SELECT COUNT(*) FROM receitas WHERE usuario_id = u.id AND ativo = 1) as total_receitas,
               (SELECT COUNT(*) FROM seguidores WHERE seguido_id = u.id) as total_seguidores
        FROM usuarios u 
        WHERE u.ativo = 1 
        AND (u.nome_usuario LIKE ? OR u.biografia LIKE ?)
        ORDER BY u.nome_usuario
        LIMIT 20
    ");
    $stmt->execute(["%$termoBusca%", "%$termoBusca%"]);
    $usuarios = $stmt->fetchAll();
} else {
    // Mostrar usuários sugeridos (mais ativos)
    $stmt = $db->prepare("
        SELECT u.*, 
               (SELECT COUNT(*) FROM receitas WHERE usuario_id = u.id AND ativo = 1) as total_receitas,
               (SELECT COUNT(*) FROM seguidores WHERE seguido_id = u.id) as total_seguidores
        FROM usuarios u 
        WHERE u.ativo = 1
        ORDER BY total_receitas DESC, total_seguidores DESC
        LIMIT 12
    ");
    $stmt->execute();
    $usuarios = $stmt->fetchAll();
}

// Se usuário logado, buscar quem ele está seguindo
$seguindoIds = [];
if ($usuarioLogado) {
    $stmt = $db->prepare("SELECT seguido_id FROM seguidores WHERE seguidor_id = ?");
    $stmt->execute([$usuarioLogado['id']]);
    $seguindoIds = array_column($stmt->fetchAll(), 'seguido_id');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo empty($termoBusca) ? 'Descobrir Usuários' : 'Busca: ' . htmlspecialchars($termoBusca); ?> - Eco Bistrô</title>
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

    <!-- Content -->
    <div class="container" style="padding: 2rem 0;">
        <!-- Formulário de Busca -->
        <div style="text-align: center; margin-bottom: 3rem;">
            <h1 style="margin-bottom: 2rem;">
                <?php echo empty($termoBusca) ? 'Descobrir Usuários' : 'Resultados da Busca'; ?>
            </h1>
            
            <form method="GET" style="max-width: 600px; margin: 0 auto;">
                <div style="display: flex; gap: 1rem;">
                    <input 
                        type="text" 
                        name="busca" 
                        class="form-control" 
                        placeholder="Buscar usuários..." 
                        value="<?php echo htmlspecialchars($termoBusca); ?>"
                        style="flex: 1;"
                    >
                    <button type="submit" class="btn btn-primary">Buscar</button>
                </div>
            </form>
            
            <?php if (!empty($termoBusca)): ?>
                <div style="margin-top: 1rem;">
                    <a href="buscar-usuarios.php" class="btn btn-secondary">Limpar busca</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Resultados -->
        <?php if (empty($usuarios)): ?>
            <div style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 20px; box-shadow: var(--shadow);">
                <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;">👥</div>
                <h3 style="margin-bottom: 1rem; color: var(--text-dark);">
                    <?php echo empty($termoBusca) ? 'Nenhum usuário ativo encontrado' : 'Nenhum resultado encontrado'; ?>
                </h3>
                <p style="color: var(--text-light);">
                    <?php echo empty($termoBusca) 
                        ? 'Não há usuários ativos no momento.' 
                        : 'Tente usar termos diferentes na sua busca.'; ?>
                </p>
            </div>
        <?php else: ?>
            <?php if (!empty($termoBusca)): ?>
                <p style="text-align: center; margin-bottom: 2rem; color: var(--text-light);">
                    Encontrados <?php echo count($usuarios); ?> usuário<?php echo count($usuarios) != 1 ? 's' : ''; ?>
                </p>
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 2rem;">
                <?php foreach ($usuarios as $usuario): ?>
                    <div style="background: white; padding: 2rem; border-radius: 20px; box-shadow: var(--shadow); text-align: center;">
                        <!-- Avatar -->
                        <div style="width: 80px; height: 80px; margin: 0 auto 1rem; border-radius: 50%; background: var(--primary-color); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--text-dark);">
                            <?php if ($usuario['foto_perfil']): ?>
                                <img src="uploads/perfil/<?php echo $usuario['foto_perfil']; ?>" 
                                     alt="<?php echo sanitizar($usuario['nome_usuario']); ?>" 
                                     style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                            <?php else: ?>
                                <?php echo strtoupper(substr($usuario['nome_usuario'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Nome do usuário -->
                        <h3 style="margin-bottom: 0.5rem;">
                            <a href="perfil-usuario.php?id=<?php echo $usuario['id']; ?>" 
                               style="color: var(--text-dark); text-decoration: none;">
                                @<?php echo sanitizar($usuario['nome_usuario']); ?>
                            </a>
                        </h3>
                        
                        <!-- Biografia -->
                        <?php if ($usuario['biografia']): ?>
                            <p style="color: var(--text-light); font-size: 0.9rem; margin-bottom: 1rem; line-height: 1.4;">
                                <?php echo sanitizar(substr($usuario['biografia'], 0, 100)) . (strlen($usuario['biografia']) > 100 ? '...' : ''); ?>
                            </p>
                        <?php endif; ?>
                        
                        <!-- Estatísticas -->
                        <div style="display: flex; justify-content: center; gap: 1rem; margin-bottom: 1.5rem; font-size: 0.9rem;">
                            <div style="text-align: center;">
                                <div style="font-weight: 600; color: var(--text-dark);"><?php echo $usuario['total_receitas']; ?></div>
                                <div style="color: var(--text-light); font-size: 0.8rem;">Receitas</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-weight: 600; color: var(--text-dark);"><?php echo $usuario['total_seguidores']; ?></div>
                                <div style="color: var(--text-light); font-size: 0.8rem;">Seguidores</div>
                            </div>
                        </div>
                        
                        <!-- Botões de ação -->
                        <div style="display: flex; gap: 0.5rem; justify-content: center;">
                            <a href="perfil-usuario.php?id=<?php echo $usuario['id']; ?>" class="btn btn-secondary" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                                Ver Perfil
                            </a>
                            
                            <?php if ($usuarioLogado && $usuarioLogado['id'] != $usuario['id']): ?>
                                <button onclick="seguirUsuario(<?php echo $usuario['id']; ?>, this)" 
                                        class="btn <?php echo in_array($usuario['id'], $seguindoIds) ? 'btn-secondary' : 'btn-primary'; ?>" 
                                        style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                                    <?php echo in_array($usuario['id'], $seguindoIds) ? 'Seguindo' : 'Seguir'; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Mostrar mais resultados se for busca -->
            <?php if (!empty($termoBusca) && count($usuarios) >= 20): ?>
                <div style="text-align: center; margin-top: 2rem;">
                    <p style="color: var(--text-light);">Mostrando os primeiros 20 resultados</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        // Função para seguir/deixar de seguir usuário
        async function seguirUsuario(usuarioId, btn) {
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

        // Auto-focus no campo de busca
        document.addEventListener('DOMContentLoaded', function() {
            const buscaInput = document.querySelector('input[name="busca"]');
            if (buscaInput && !buscaInput.value) {
                buscaInput.focus();
            }
            
            // Animações dos cards
            const cards = document.querySelectorAll('[style*="background: white"]');
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
</body>
</html>