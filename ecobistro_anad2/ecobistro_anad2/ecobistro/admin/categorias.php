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
    
    switch ($acao) {
        case 'criar':
            $nome = sanitizar($_POST['nome'] ?? '');
            $slug = sanitizar($_POST['slug'] ?? '');
            $descricao = sanitizar($_POST['descricao'] ?? '');
            $cor = sanitizar($_POST['cor'] ?? '#A8E6CF');
            
            if (empty($nome) || empty($slug)) {
                $erro = 'Nome e slug são obrigatórios.';
            } else {
                // Verificar se slug já existe
                $stmt = $db->prepare("SELECT id FROM categorias WHERE slug = ?");
                $stmt->execute([$slug]);
                if ($stmt->fetch()) {
                    $erro = 'Slug já existe. Escolha outro.';
                } else {
                    $stmt = $db->prepare("INSERT INTO categorias (nome, slug, descricao, cor) VALUES (?, ?, ?, ?)");
                    if ($stmt->execute([$nome, $slug, $descricao, $cor])) {
                        $mensagem = 'Categoria criada com sucesso!';
                    } else {
                        $erro = 'Erro ao criar categoria.';
                    }
                }
            }
            break;
            
        case 'editar':
            $id = (int)($_POST['id'] ?? 0);
            $nome = sanitizar($_POST['nome'] ?? '');
            $slug = sanitizar($_POST['slug'] ?? '');
            $descricao = sanitizar($_POST['descricao'] ?? '');
            $cor = sanitizar($_POST['cor'] ?? '#A8E6CF');
            
            if (empty($nome) || empty($slug) || !$id) {
                $erro = 'Dados inválidos.';
            } else {
                // Verificar se slug já existe (exceto na própria categoria)
                $stmt = $db->prepare("SELECT id FROM categorias WHERE slug = ? AND id != ?");
                $stmt->execute([$slug, $id]);
                if ($stmt->fetch()) {
                    $erro = 'Slug já existe. Escolha outro.';
                } else {
                    $stmt = $db->prepare("UPDATE categorias SET nome = ?, slug = ?, descricao = ?, cor = ? WHERE id = ?");
                    if ($stmt->execute([$nome, $slug, $descricao, $cor, $id])) {
                        $mensagem = 'Categoria atualizada com sucesso!';
                    } else {
                        $erro = 'Erro ao atualizar categoria.';
                    }
                }
            }
            break;
            
        case 'excluir':
            $id = (int)($_POST['id'] ?? 0);
            
            if (!$id) {
                $erro = 'ID inválido.';
            } else {
                // Verificar se categoria tem receitas
                $stmt = $db->prepare("SELECT COUNT(*) as total FROM receitas WHERE categoria_id = ?");
                $stmt->execute([$id]);
                $totalReceitas = $stmt->fetch()['total'];
                
                if ($totalReceitas > 0) {
                    $erro = "Não é possível excluir esta categoria. Ela possui $totalReceitas receitas.";
                } else {
                    $stmt = $db->prepare("DELETE FROM categorias WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        $mensagem = 'Categoria excluída com sucesso!';
                    } else {
                        $erro = 'Erro ao excluir categoria.';
                    }
                }
            }
            break;
    }
}

// Buscar categorias com estatísticas
$stmt = $db->query("
    SELECT c.*, 
           COUNT(r.id) as total_receitas,
           AVG(r.visualizacoes) as media_visualizacoes
    FROM categorias c
    LEFT JOIN receitas r ON c.id = r.categoria_id AND r.ativo = 1
    GROUP BY c.id
    ORDER BY c.nome
");
$categorias = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Categorias - Eco Bistrô Admin</title>
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
        
        .categoria-admin-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 1rem;
            display: grid;
            grid-template-columns: auto 1fr auto auto;
            gap: 1rem;
            align-items: center;
        }
        
        .color-preview {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid #ddd;
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
            <a href="usuarios.php" style="display: block; color: white; text-decoration: none; padding: 1rem; border-radius: 10px; margin-bottom: 0.5rem; transition: var(--transition);" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
                👥 Usuários
            </a>
            <a href="receitas.php" style="display: block; color: white; text-decoration: none; padding: 1rem; border-radius: 10px; margin-bottom: 0.5rem; transition: var(--transition);" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
                🍽️ Receitas
            </a>
            <a href="comentarios.php" style="display: block; color: white; text-decoration: none; padding: 1rem; border-radius: 10px; margin-bottom: 0.5rem; transition: var(--transition);" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
                💬 Comentários
            </a>
            <a href="categorias.php" style="display: block; color: white; text-decoration: none; padding: 1rem; border-radius: 10px; margin-bottom: 0.5rem; background: var(--accent-color);">
                📁 Categorias
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="admin-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h1 style="color: var(--text-dark); margin-bottom: 0.5rem;">Gerenciar Categorias</h1>
                <p style="color: var(--text-light);">Organize as categorias de receitas</p>
            </div>
            <button onclick="abrirModal('modalNovaCategoria')" class="btn btn-primary">
                📁 Nova Categoria
            </button>
        </div>

        <!-- Mensagens -->
        <?php if ($mensagem): ?>
            <div class="alert alert-success" style="margin-bottom: 2rem;"><?php echo $mensagem; ?></div>
        <?php endif; ?>
        
        <?php if ($erro): ?>
            <div class="alert alert-error" style="margin-bottom: 2rem;"><?php echo $erro; ?></div>
        <?php endif; ?>

        <!-- Lista de categorias -->
        <?php if (empty($categorias)): ?>
            <div style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 20px; box-shadow: var(--shadow);">
                <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;">📁</div>
                <h3 style="margin-bottom: 1rem; color: var(--text-dark);">Nenhuma categoria encontrada</h3>
                <p style="color: var(--text-light); margin-bottom: 2rem;">
                    Crie sua primeira categoria para organizar as receitas
                </p>
                <button onclick="abrirModal('modalNovaCategoria')" class="btn btn-primary">📁 Criar primeira categoria</button>
            </div>
        <?php else: ?>
            <?php foreach ($categorias as $categoria): ?>
                <div class="categoria-admin-card">
                    <div class="color-preview" style="background-color: <?php echo $categoria['cor']; ?>"></div>
                    
                    <div>
                        <h4 style="margin: 0; color: var(--text-dark);"><?php echo sanitizar($categoria['nome']); ?></h4>
                        <p style="margin: 0.25rem 0; color: var(--text-light); font-size: 0.9rem;">
                            Slug: <?php echo sanitizar($categoria['slug']); ?>
                        </p>
                        <?php if ($categoria['descricao']): ?>
                            <p style="margin: 0.25rem 0; color: var(--text-light); font-size: 0.8rem;">
                                <?php echo sanitizar($categoria['descricao']); ?>
                            </p>
                        <?php endif; ?>
                        <div style="display: flex; gap: 1rem; font-size: 0.8rem; color: var(--text-light); margin-top: 0.5rem;">
                            <span>📝 <?php echo $categoria['total_receitas']; ?> receitas</span>
                            <?php if ($categoria['media_visualizacoes']): ?>
                                <span>👁️ <?php echo number_format($categoria['media_visualizacoes'], 0); ?> views médias</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 0.5rem;">
                        <button onclick="editarCategoria(<?php echo htmlspecialchars(json_encode($categoria)); ?>)" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.8rem;">
                            ✏️ Editar
                        </button>
                        
                        <?php if ($categoria['total_receitas'] == 0): ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Excluir esta categoria? Esta ação não pode ser desfeita.')">
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?php echo $categoria['id']; ?>">
                                <button type="submit" class="btn" style="background: #dc3545; color: white; padding: 0.5rem 1rem; font-size: 0.8rem;">
                                    🗑️ Excluir
                                </button>
                            </form>
                        <?php else: ?>
                            <button class="btn" style="background: #6c757d; color: white; padding: 0.5rem 1rem; font-size: 0.8rem; cursor: not-allowed;" disabled title="Não é possível excluir categorias com receitas">
                                🗑️ Excluir
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div style="margin-top: 3rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div style="background: white; padding: 1.5rem; border-radius: 15px; box-shadow: var(--shadow); text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color);">
                    <?php echo count($categorias); ?>
                </div>
                <div style="color: var(--text-light);">Total de Categorias</div>
            </div>
            
            <div style="background: white; padding: 1.5rem; border-radius: 15px; box-shadow: var(--shadow); text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: var(--secondary-color);">
                    <?php echo array_sum(array_column($categorias, 'total_receitas')); ?>
                </div>
                <div style="color: var(--text-light);">Total de Receitas</div>
            </div>
            
            <div style="background: white; padding: 1.5rem; border-radius: 15px; box-shadow: var(--shadow); text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: var(--accent-color);">
                    <?php 
                        $receitasPorCategoria = array_filter(array_column($categorias, 'total_receitas'));
                        echo !empty($receitasPorCategoria) ? number_format(array_sum($receitasPorCategoria) / count($receitasPorCategoria), 1) : 0;
                    ?>
                </div>
                <div style="color: var(--text-light);">Média por Categoria</div>
            </div>
        </div>
    </div>

    <!-- Modal Nova Categoria -->
    <div id="modalNovaCategoria" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 1.5rem;">Nova Categoria</h3>
            <form method="POST">
                <input type="hidden" name="acao" value="criar">
                
                <div class="form-group">
                    <label for="nome" class="form-label">Nome da categoria</label>
                    <input type="text" id="nome" name="nome" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="slug" class="form-label">Slug (URL)</label>
                    <input type="text" id="slug" name="slug" class="form-control" required>
                    <small style="color: var(--text-light); font-size: 0.8rem;">
                        Usado na URL. Use apenas letras, números e hífen. Ex: comidas-veganas
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="descricao" class="form-label">Descrição</label>
                    <textarea id="descricao" name="descricao" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="cor" class="form-label">Cor da categoria</label>
                    <input type="color" id="cor" name="cor" class="form-control" value="#A8E6CF">
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">Criar Categoria</button>
                    <button type="button" onclick="fecharModal('modalNovaCategoria')" class="btn btn-secondary">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Categoria -->
    <div id="modalEditarCategoria" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 1.5rem;">Editar Categoria</h3>
            <form method="POST" id="formEditarCategoria">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id" id="editId">
                
                <div class="form-group">
                    <label for="editNome" class="form-label">Nome da categoria</label>
                    <input type="text" id="editNome" name="nome" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="editSlug" class="form-label">Slug (URL)</label>
                    <input type="text" id="editSlug" name="slug" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="editDescricao" class="form-label">Descrição</label>
                    <textarea id="editDescricao" name="descricao" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="editCor" class="form-label">Cor da categoria</label>
                    <input type="color" id="editCor" name="cor" class="form-control">
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                    <button type="button" onclick="fecharModal('modalEditarCategoria')" class="btn btn-secondary">Cancelar</button>
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

        function editarCategoria(categoria) {
            document.getElementById('editId').value = categoria.id;
            document.getElementById('editNome').value = categoria.nome;
            document.getElementById('editSlug').value = categoria.slug;
            document.getElementById('editDescricao').value = categoria.descricao || '';
            document.getElementById('editCor').value = categoria.cor || '#A8E6CF';
            
            abrirModal('modalEditarCategoria');
        }

        // Auto-gerar slug baseado no nome
        document.getElementById('nome').addEventListener('input', function() {
            const slug = this.value
                .toLowerCase()
                .replace(/[^\w\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-');
            document.getElementById('slug').value = slug;
        });

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
            const cards = document.querySelectorAll('.categoria-admin-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.4s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateX(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>