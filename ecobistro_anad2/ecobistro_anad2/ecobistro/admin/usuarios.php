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
    $usuarioId = (int)($_POST['usuario_id'] ?? 0);
    
    switch ($acao) {
        case 'desativar':
            $stmt = $db->prepare("UPDATE usuarios SET ativo = 0 WHERE id = ?");
            if ($stmt->execute([$usuarioId])) {
                $mensagem = 'Usuário desativado com sucesso!';
            } else {
                $erro = 'Erro ao desativar usuário.';
            }
            break;
            
        case 'ativar':
            $stmt = $db->prepare("UPDATE usuarios SET ativo = 1 WHERE id = ?");
            if ($stmt->execute([$usuarioId])) {
                $mensagem = 'Usuário ativado com sucesso!';
            } else {
                $erro = 'Erro ao ativar usuário.';
            }
            break;
            
        case 'promover':
            $stmt = $db->prepare("UPDATE usuarios SET tipo_usuario = 'admin' WHERE id = ?");
            if ($stmt->execute([$usuarioId])) {
                $mensagem = 'Usuário promovido a administrador!';
            } else {
                $erro = 'Erro ao promover usuário.';
            }
            break;
            
        case 'despromover':
            $stmt = $db->prepare("UPDATE usuarios SET tipo_usuario = 'usuario' WHERE id = ?");
            if ($stmt->execute([$usuarioId])) {
                $mensagem = 'Usuário rebaixado para usuário comum!';
            } else {
                $erro = 'Erro ao rebaixar usuário.';
            }
            break;
            
        case 'novo':
            $nomeUsuario = sanitizar($_POST['nome_usuario'] ?? '');
            $email = sanitizar($_POST['email'] ?? '');
            $senha = $_POST['senha'] ?? '';
            $tipoUsuario = $_POST['tipo_usuario'] ?? 'usuario';
            
            if (empty($nomeUsuario) || empty($email) || empty($senha)) {
                $erro = 'Todos os campos são obrigatórios.';
            } else {
                // Verificar se usuário já existe
                $stmt = $db->prepare("SELECT id FROM usuarios WHERE nome_usuario = ? OR email = ?");
                $stmt->execute([$nomeUsuario, $email]);
                if ($stmt->fetch()) {
                    $erro = 'Nome de usuário ou e-mail já existe.';
                } else {
                    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("
                        INSERT INTO usuarios (nome_usuario, email, senha, tipo_usuario, biografia) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    if ($stmt->execute([$nomeUsuario, $email, $senhaHash, $tipoUsuario, 'Novo membro da comunidade Eco Bistrô'])) {
                        $mensagem = 'Usuário criado com sucesso!';
                    } else {
                        $erro = 'Erro ao criar usuário.';
                    }
                }
            }
            break;
    }
}

// Filtros
$filtro = $_GET['filtro'] ?? 'todos';
$busca = $_GET['busca'] ?? '';

// Construir query
$where = "WHERE 1=1";
$params = [];

switch ($filtro) {
    case 'ativos':
        $where .= " AND ativo = 1";
        break;
    case 'inativos':
        $where .= " AND ativo = 0";
        break;
    case 'admins':
        $where .= " AND tipo_usuario = 'admin'";
        break;
}

if ($busca) {
    $where .= " AND (nome_usuario LIKE ? OR email LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

// Buscar usuários
$stmt = $db->prepare("
    SELECT u.*, 
           COUNT(DISTINCT r.id) as total_receitas,
           COUNT(DISTINCT f.receita_id) as total_favoritos,
           COUNT(DISTINCT c.id) as total_comentarios
    FROM usuarios u
    LEFT JOIN receitas r ON u.id = r.usuario_id AND r.ativo = 1
    LEFT JOIN favoritos f ON u.id = f.usuario_id
    LEFT JOIN comentarios c ON u.id = c.usuario_id AND c.ativo = 1
    $where
    GROUP BY u.id
    ORDER BY u.data_criacao DESC
");
$stmt->execute($params);
$usuarios = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Eco Bistrô Admin</title>
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
            vertical-align: top;
        }
        
        .admin-table tr:hover {
            background: var(--background-light);
        }
        
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-admin {
            background: var(--accent-color);
            color: white;
        }
        
        .badge-user {
            background: var(--primary-color);
            color: var(--text-dark);
        }
        
        .badge-active {
            background: #28a745;
            color: white;
        }
        
        .badge-inactive {
            background: #6c757d;
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
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
            <a href="usuarios.php" style="display: block; color: white; text-decoration: none; padding: 1rem; border-radius: 10px; margin-bottom: 0.5rem; background: var(--accent-color);">
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
        </nav>
    </div>

    <!-- Main Content -->
    <div class="admin-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h1 style="color: var(--text-dark); margin-bottom: 0.5rem;">Gerenciar Usuários</h1>
                <p style="color: var(--text-light);">Administre contas de usuários da plataforma</p>
            </div>
            <button onclick="abrirModal('modalNovoUsuario')" class="btn btn-primary">
                👤 Novo Usuário
            </button>
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
            <form method="GET" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
                    <label class="form-label">Buscar usuário</label>
                    <input type="text" name="busca" class="form-control" placeholder="Nome ou e-mail..." value="<?php echo htmlspecialchars($busca); ?>">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Filtrar por</label>
                    <select name="filtro" class="form-control">
                        <option value="todos" <?php echo $filtro === 'todos' ? 'selected' : ''; ?>>Todos</option>
                        <option value="ativos" <?php echo $filtro === 'ativos' ? 'selected' : ''; ?>>Ativos</option>
                        <option value="inativos" <?php echo $filtro === 'inativos' ? 'selected' : ''; ?>>Inativos</option>
                        <option value="admins" <?php echo $filtro === 'admins' ? 'selected' : ''; ?>>Administradores</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                
                <?php if ($busca || $filtro !== 'todos'): ?>
                    <a href="usuarios.php" class="btn btn-secondary">Limpar</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Tabela de usuários -->
        <div class="admin-table">
            <table>
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Atividade</th>
                        <th>Data de Cadastro</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usuarios)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-light);">
                                Nenhum usuário encontrado
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Estatísticas resumidas -->
        <div style="margin-top: 2rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div style="background: white; padding: 1.5rem; border-radius: 15px; box-shadow: var(--shadow); text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color);">
                    <?php echo count(array_filter($usuarios, fn($u) => $u['ativo'])); ?>
                </div>
                <div style="color: var(--text-light);">Usuários Ativos</div>
            </div>
            
            <div style="background: white; padding: 1.5rem; border-radius: 15px; box-shadow: var(--shadow); text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: var(--accent-color);">
                    <?php echo count(array_filter($usuarios, fn($u) => ($u['tipo_usuario'] ?? 'usuario') === 'admin')); ?>
                </div>
                <div style="color: var(--text-light);">Administradores</div>
            </div>
            
            <div style="background: white; padding: 1.5rem; border-radius: 15px; box-shadow: var(--shadow); text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: var(--secondary-color);">
                    <?php echo array_sum(array_column($usuarios, 'total_receitas')); ?>
                </div>
                <div style="color: var(--text-light);">Total Receitas</div>
            </div>
            
            <div style="background: white; padding: 1.5rem; border-radius: 15px; box-shadow: var(--shadow); text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: #C7CEEA;">
                    <?php echo array_sum(array_column($usuarios, 'total_comentarios')); ?>
                </div>
                <div style="color: var(--text-light);">Total Comentários</div>
            </div>
        </div>
    </div>

    <!-- Modal Novo Usuário -->
    <div id="modalNovoUsuario" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 1.5rem;">Criar Novo Usuário</h3>
            <form method="POST">
                <input type="hidden" name="acao" value="novo">
                
                <div class="form-group">
                    <label for="nome_usuario" class="form-label">Nome de usuário</label>
                    <input type="text" id="nome_usuario" name="nome_usuario" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="senha" class="form-label">Senha</label>
                    <input type="password" id="senha" name="senha" class="form-control" minlength="6" required>
                </div>
                
                <div class="form-group">
                    <label for="tipo_usuario" class="form-label">Tipo de usuário</label>
                    <select id="tipo_usuario" name="tipo_usuario" class="form-control">
                        <option value="usuario">Usuário</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">Criar Usuário</button>
                    <button type="button" onclick="fecharModal('modalNovoUsuario')" class="btn btn-secondary">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function fecharModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Fechar modal ao clicar fora
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    fecharModal(this.id);
                }
            });
        });

        // Animações
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    row.style.transition = 'all 0.4s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateX(0)';
                }, index * 50);
            });
        });
    </script>
</body>
</html> foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 1rem;">
                                        <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary-color); display: flex; align-items: center; justify-content: center; color: var(--text-dark); font-weight: 600;">
                                            <?php echo strtoupper(substr($usuario['nome_usuario'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <strong>@<?php echo sanitizar($usuario['nome_usuario']); ?></strong><br>
                                            <small style="color: var(--text-light);"><?php echo sanitizar($usuario['email']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?php echo ($usuario['tipo_usuario'] ?? 'usuario') === 'admin' ? 'badge-admin' : 'badge-user'; ?>">
                                        <?php echo ($usuario['tipo_usuario'] ?? 'usuario') === 'admin' ? '👑 Admin' : '👤 Usuário'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $usuario['ativo'] ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?php echo $usuario['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size: 0.9rem;">
                                        <div>📝 <?php echo $usuario['total_receitas']; ?> receitas</div>
                                        <div>⭐ <?php echo $usuario['total_favoritos']; ?> favoritos</div>
                                        <div>💬 <?php echo $usuario['total_comentarios']; ?> comentários</div>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($usuario['data_criacao'])); ?><br>
                                    <small style="color: var(--text-light);">
                                        <?php echo date('H:i', strtotime($usuario['data_criacao'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <?php if ($usuario['id'] != $_SESSION['usuario_id']): ?>
                                            <?php if ($usuario['ativo']): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Desativar este usuário?')">
                                                    <input type="hidden" name="acao" value="desativar">
                                                    <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                                                    <button type="submit" class="btn" style="background: #dc3545; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                                        🚫 Desativar
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="acao" value="ativar">
                                                    <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                                                    <button type="submit" class="btn" style="background: #28a745; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                                        ✅ Ativar
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if (($usuario['tipo_usuario'] ?? 'usuario') === 'usuario'): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Promover este usuário a administrador?')">
                                                    <input type="hidden" name="acao" value="promover">
                                                    <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                                                    <button type="submit" class="btn" style="background: var(--accent-color); color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                                        👑 Promover
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Remover privilégios de administrador?')">
                                                    <input type="hidden" name="acao" value="despromover">
                                                    <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                                                    <button type="submit" class="btn" style="background: #6c757d; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                                        👤 Despromover
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <small style="color: var(--text-light); font-style: italic;">Você</small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            
                        <?php