<?php
require_once 'config.php';
iniciarSessao();

$usuarioId = intval($_GET['id'] ?? 0);
$tipo = $_GET['tipo'] ?? 'seguidores'; // 'seguidores' ou 'seguindo'

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

if ($tipo === 'seguidores') {
    // Buscar seguidores
    $stmt = $db->prepare("
        SELECT u.*, s.data_seguimento,
               (SELECT COUNT(*) FROM receitas WHERE usuario_id = u.id AND ativo = 1) as total_receitas
        FROM usuarios u 
        JOIN seguidores s ON u.id = s.seguidor_id 
        WHERE s.seguido_id = ? AND u.ativo = 1
        ORDER BY s.data_seguimento DESC
    ");
    $stmt->execute([$usuarioId]);
    $usuarios = $stmt->fetchAll();
    $titulo = "Seguidores de @" . $usuario['nome_usuario'];
} else {
    // Buscar quem está seguindo
    $stmt = $db->prepare("
        SELECT u.*, s.data_seguimento,
               (SELECT COUNT(*) FROM receitas WHERE usuario_id = u.id AND ativo = 1) as total_receitas
        FROM usuarios u 
        JOIN seguidores s ON u.id = s.seguido_id 
        WHERE s.seguidor_id = ? AND u.ativo = 1
        ORDER BY s.data_seguimento DESC
    ");
    $stmt->execute([$usuarioId]);
    $usuarios = $stmt->fetchAll();
    $titulo = "Seguindo - @" . $usuario['nome_usuario'];
}

// Se o usuário logado, buscar quem ele está seguindo
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
    <title><?php echo $titulo; ?> - Eco Bistrô</title>
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
        <div style="text-align: center; margin-bottom: 2rem;">
            <h1><?php echo $titulo; ?></h1>
            <a href="<?php echo $usuarioLogado && $usuarioLogado['id'] == $usuarioId ? 'perfil.php' : 'perfil-usuario.php?id=' . $usuarioId; ?>" 
               class="btn btn-secondary" style="margin-top: 1rem;">
                ← Voltar ao perfil
            </a>
        </div>

        <?php if (empty($usuarios)): ?>
            <div style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 20px; box-shadow: var(--shadow);">
                <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;">👥</div>
                <h3 style="margin-bottom: 1rem; color: var(--text-dark);">
                    <?php echo $tipo === 'seguidores' ? 'Nenhum seguidor ainda' : 'Não está seguindo ninguém'; ?>
                </h3>
                <p style="color: var(--text-light);">
                    <?php echo $tipo === 'seguidores' 
                        ? 'Este usuário ainda não possui seguidores.' 
                        : 'Este usuário ainda não está seguindo outras pessoas.'; ?>
                </p>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 2rem;">
                <?php foreach ($usuarios as $u): ?>
                    <div style="background: white; padding: 1.5rem; border-radius: 20px; box-shadow: var(--shadow); text-align: center;">
                        <div style="width: 80px; height: 80px; margin: 0 auto 1rem; border-radius: 50%; background: var(--primary-color); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--text-dark);">
                            <?php if ($u['foto_perfil']): ?>
                                <img src="uploads/perfil/<?php echo $u['foto_perfil']; ?>" 
                                     alt="<?php echo sanitizar($u['nome_usuario']); ?>" 
                                     style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                            <?php else: ?>
                                <?php echo strtoupper(substr($u['nome_usuario'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        
                        <h3 style="margin-bottom: 0.5rem;">
                            <a href="perfil-usuario.php?id=<?php echo $u['id']; ?>" 
                               style="color: var(--text-dark); text-decoration: none;">
                                @<?php echo sanitizar($u['nome_usuario']); ?>
                            </a>
                        </h3>
                        
                        <?php if ($u['biografia']): ?>
                            <p style="color: var(--text-light); font-size: 0.9rem; margin-bottom: 1rem; line-height: 1.4;">
                                <?php echo sanitizar(substr($u['biografia'], 0, 80)) . (strlen($u['biografia']) > 80 ? '...' : ''); ?>
                            </p>
                        <?php endif; ?>
                        
                        <p style="color: var(--text-light); font-size: 0.8rem; margin-bottom: 1rem;">
                            <?php echo $u['total_receitas']; ?> receitas • 
                            Desde <?php echo date('M/Y', strtotime($u['data_seguimento'])); ?>
                        </p>
                        
                        <?php if ($usuarioLogado && $usuarioLogado['id'] != $u['id']): ?>
                            <button onclick="seguirUsuario(<?php echo $u['id']; ?>, this)" 
                                    class="btn <?php echo in_array($u['id'], $seguindoIds) ? 'btn-secondary' : 'btn-primary'; ?>" 
                                    style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                                <?php echo in_array($u['id'], $seguindoIds) ? 'Seguindo' : 'Seguir'; ?>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
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

        // Animações
        document.addEventListener('DOMContentLoaded', function() {
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