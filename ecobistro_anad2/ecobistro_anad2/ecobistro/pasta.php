<?php
// pasta.php - Visualizar conteúdo da pasta
require_once 'config.php';
iniciarSessao();

if (!estaLogado()) {
    header('Location: login.php');
    exit;
}

$usuarioLogado = getUsuarioLogado();
$db = Database::getInstance()->getConnection();

$pastaId = $_GET['id'] ?? null;
$erro = '';

if (!$pastaId) {
    header('Location: perfil.php');
    exit;
}

// Verificar se a pasta existe e pertence ao usuário (ou buscar pastas especiais)
if ($pastaId == 'favoritos') {
    $pasta = [
        'id' => 'favoritos',
        'nome' => 'Favoritos',
        'descricao' => 'Suas receitas favoritas',
        'usuario_id' => $usuarioLogado['id'],
        'especial' => true
    ];
} elseif ($pastaId == 'fazer-mais-tarde') {
    $pasta = [
        'id' => 'fazer-mais-tarde',
        'nome' => 'Fazer mais tarde',
        'descricao' => 'Receitas salvas para fazer depois',
        'usuario_id' => $usuarioLogado['id'],
        'especial' => true
    ];
} else {
    $stmt = $db->prepare("SELECT * FROM pastas WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$pastaId, $usuarioLogado['id']]);
    $pasta = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$pasta || empty($pasta)) {
    header('Location: perfil.php');
    exit;
}

// Buscar receitas da pasta
if ($pastaId == 'favoritos') {
    $stmt = $db->prepare("
        SELECT r.*, u.nome_usuario, c.nome as categoria_nome,
               (SELECT COUNT(*) FROM favoritos f WHERE f.receita_id = r.id) as total_favoritos,
               (SELECT COUNT(*) FROM curtidas cu WHERE cu.receita_id = r.id) as total_curtidas
        FROM receitas r
        JOIN usuarios u ON r.usuario_id = u.id
        JOIN categorias c ON r.categoria_id = c.id
        JOIN favoritos fav ON fav.receita_id = r.id
        WHERE fav.usuario_id = ? AND r.ativo = 1
        ORDER BY r.titulo ASC
    ");
    $stmt->execute([$usuarioLogado['id']]);
} elseif ($pastaId == 'fazer-mais-tarde') {
    $stmt = $db->prepare("
        SELECT r.*, u.nome_usuario, c.nome as categoria_nome,
               (SELECT COUNT(*) FROM favoritos f WHERE f.receita_id = r.id) as total_favoritos,
               (SELECT COUNT(*) FROM curtidas cu WHERE cu.receita_id = r.id) as total_curtidas
        FROM receitas r
        JOIN usuarios u ON r.usuario_id = u.id
        JOIN categorias c ON r.categoria_id = c.id
        JOIN pasta_receitas pr ON pr.receita_id = r.id
        WHERE pr.pasta_id = 'fazer-mais-tarde' AND pr.usuario_id = ? AND r.ativo = 1
        ORDER BY r.titulo ASC
    ");
    $stmt->execute([$usuarioLogado['id']]);
} else {
    $stmt = $db->prepare("
        SELECT r.*, u.nome_usuario, c.nome as categoria_nome,
               (SELECT COUNT(*) FROM favoritos f WHERE f.receita_id = r.id) as total_favoritos,
               (SELECT COUNT(*) FROM curtidas cu WHERE cu.receita_id = r.id) as total_curtidas
        FROM receitas r
        JOIN usuarios u ON r.usuario_id = u.id
        JOIN categorias c ON r.categoria_id = c.id
        JOIN pasta_receitas pr ON pr.receita_id = r.id
        WHERE pr.pasta_id = ? AND r.ativo = 1
        ORDER BY r.titulo ASC
    ");
    $stmt->execute([$pastaId]);
}

$receitas = $stmt->fetchAll();

// Buscar todas as receitas disponíveis para adicionar
$stmt = $db->prepare("
    SELECT r.*, u.nome_usuario, c.nome as categoria_nome
    FROM receitas r
    JOIN usuarios u ON r.usuario_id = u.id
    JOIN categorias c ON r.categoria_id = c.id
    WHERE r.ativo = 1
    ORDER BY r.titulo ASC
");
$stmt->execute();
$todasReceitas = $stmt->fetchAll();

// Filtrar receitas que já estão na pasta
$receitasNaPasta = array_column($receitas, 'id');
$receitasDisponiveis = array_filter($todasReceitas, function($receita) use ($receitasNaPasta) {
    return !in_array($receita['id'], $receitasNaPasta);
});
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitizar($pasta['nome']); ?> - Eco Bistrô</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .pasta-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            padding: 3rem 0;
            text-align: center;
            color: var(--text-dark);
        }
        
        .pasta-info {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .pasta-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .pasta-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .receitas-pasta {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .adicionar-receita-card {
            background: white;
            border: 2px dashed var(--primary-color);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .adicionar-receita-card:hover {
            border-color: var(--accent-color);
            background: var(--background-light);
            transform: translateY(-2px);
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .receitas-modal {
            display: grid;
            gap: 1rem;
            margin-top: 1rem;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .receita-modal-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 1px solid #E9ECEF;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .receita-modal-item:hover {
            border-color: var(--primary-color);
            background: var(--background-light);
        }
        
        .receita-modal-img {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            overflow: hidden;
        }
        
        .receita-modal-info {
            flex: 1;
        }
        
        .receita-modal-titulo {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--text-dark);
        }
        
        .receita-modal-meta {
            font-size: 0.8rem;
            color: var(--text-light);
        }
        
        .search-box {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #E9ECEF;
            border-radius: 15px;
            margin-bottom: 1rem;
            font-size: 1rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
        }
        
        .btn-remover-pasta {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .btn-remover-pasta:hover {
            background: #c82333;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="nav-container">
            <a href="index.php" class="logo">ECO BISTRÔ</a>
            <nav class="nav-menu">
                <a href="receitas.php" class="nav-link">RECEITAS</a>
                <a href="perfil.php" class="nav-link">@<?php echo sanitizar($usuarioLogado['nome_usuario']); ?></a>
                <a href="nova-receita.php" class="btn btn-primary">+ NOVA RECEITA</a>
                <a href="logout.php" class="nav-link">SAIR</a>
            </nav>
        </div>
    </header>

    <!-- Header da Pasta -->
    <section class="pasta-header">
        <div class="pasta-info">
            <div class="pasta-icon">
                <?php if ($pasta['id'] == 'favoritos'): ?>
                    ⭐
                <?php elseif ($pasta['id'] == 'fazer-mais-tarde'): ?>
                    ⏰
                <?php else: ?>
                    📁
                <?php endif; ?>
            </div>
            <h1><?php echo sanitizar($pasta['nome']); ?></h1>
            <?php if ($pasta['descricao']): ?>
                <p style="margin-top: 1rem; font-size: 1.1rem; opacity: 0.8;">
                    <?php echo sanitizar($pasta['descricao']); ?>
                </p>
            <?php endif; ?>
            <p style="margin-top: 0.5rem; font-size: 0.9rem; opacity: 0.7;">
                <?php echo count($receitas); ?> receita<?php echo count($receitas) != 1 ? 's' : ''; ?>
            </p>
        </div>
    </section>

    <!-- Conteúdo -->
    <div class="container" style="padding: 2rem 0;">
        <!-- Ações da Pasta -->
        <div class="pasta-actions">
            <div>
                <a href="perfil.php" class="btn btn-secondary">← Voltar ao Perfil</a>
            </div>
            <div style="display: flex; gap: 1rem;">
                <button onclick="adicionarReceita()" class="btn btn-primary">+ Adicionar Receita</button>
                <?php if (!isset($pasta['especial']) && $pasta['id'] != 'favoritos' && $pasta['id'] != 'fazer-mais-tarde'): ?>
                    <button onclick="editarPasta(<?php echo $pasta['id']; ?>)" class="btn btn-secondary">✏️ Editar</button>
                    <button onclick="confirmarRemocaoPasta(<?php echo $pasta['id']; ?>)" class="btn-remover-pasta">🗑️ Deletar</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Receitas -->
        <?php if (empty($receitas)): ?>
            <div class="empty-state">
                <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;">
                    <?php if ($pasta['id'] == 'favoritos'): ?>
                        ⭐
                    <?php elseif ($pasta['id'] == 'fazer-mais-tarde'): ?>
                        ⏰
                    <?php else: ?>
                        📁
                    <?php endif; ?>
                </div>
                <h3 style="margin-bottom: 1rem; color: var(--text-dark);">Pasta vazia</h3>
                <p style="color: var(--text-light); margin-bottom: 2rem;">
                    Adicione receitas para organizar suas favoritas!
                </p>
                <button onclick="adicionarReceita()" class="btn btn-primary">+ Adicionar primeira receita</button>
            </div>
        <?php else: ?>
            <div class="receitas-pasta">
                <!-- Card para adicionar receita -->
                <div class="adicionar-receita-card" onclick="adicionarReceita()">
                    <div style="font-size: 3rem; margin-bottom: 1rem; color: var(--primary-color);">+</div>
                    <h3 style="color: var(--text-dark); margin-bottom: 0.5rem;">Adicionar Receita</h3>
                    <p style="color: var(--text-light); font-size: 0.9rem;">Clique para adicionar uma nova receita à pasta</p>
                </div>

                <!-- Receitas existentes -->
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
                            
                            <!-- Botão de remover da pasta -->
                            <div style="position: absolute; top: 0.5rem; right: 0.5rem;">
                                <button onclick="removerDaPasta(<?php echo $receita['id']; ?>, '<?php echo $pasta['id']; ?>')" 
                                        class="btn btn-secondary" 
                                        style="padding: 0.25rem 0.5rem; font-size: 0.8rem; background: rgba(220, 53, 69, 0.9);">
                                    ✕ Remover
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

    <!-- Modal para adicionar receita -->
    <div id="modalAdicionarReceita" class="modal-overlay">
        <div class="modal">
            <h3 style="margin-bottom: 1.5rem;">Adicionar Receita à Pasta</h3>
            <input type="text" id="searchReceitas" class="search-box" placeholder="Buscar receitas...">
            
            <div class="receitas-modal" id="listaReceitas">
                <?php foreach ($receitasDisponiveis as $receita): ?>
                    <div class="receita-modal-item" onclick="adicionarReceitaNaPasta(<?php echo $receita['id']; ?>, '<?php echo $pasta['id']; ?>')">
                        <div class="receita-modal-img">
                            <?php if ($receita['imagem']): ?>
                                <img src="uploads/receitas/<?php echo $receita['imagem']; ?>" 
                                     alt="<?php echo sanitizar($receita['titulo']); ?>" 
                                     style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px;">
                            <?php else: ?>
                                <span style="font-size: 1.5rem;">🍽️</span>
                            <?php endif; ?>
                        </div>
                        <div class="receita-modal-info">
                            <div class="receita-modal-titulo"><?php echo sanitizar($receita['titulo']); ?></div>
                            <div class="receita-modal-meta">
                                Por <?php echo sanitizar($receita['nome_usuario']); ?> • <?php echo sanitizar($receita['categoria_nome']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="display: flex; gap: 1rem; margin-top: 2rem; justify-content: flex-end;">
                <button onclick="fecharModalAdicionar()" class="btn btn-secondary">Cancelar</button>
            </div>
        </div>
    </div>

    <!-- Modal para editar pasta -->
    <div id="modalEditarPasta" class="modal-overlay">
        <div class="modal">
            <h3 style="margin-bottom: 1.5rem;">Editar Pasta</h3>
            <form id="formEditarPasta">
                <div class="form-group">
                    <label for="nomeEdicao" class="form-label">Nome da pasta</label>
                    <input type="text" id="nomeEdicao" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="descricaoEdicao" class="form-label">Descrição (opcional)</label>
                    <textarea id="descricaoEdicao" class="form-control" rows="3"></textarea>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <button type="button" onclick="fecharModalEdicao()" class="btn btn-secondary">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let pastaAtual = '<?php echo $pasta['id']; ?>';
        
        // Abrir modal para adicionar receita
        function adicionarReceita() {
            document.getElementById('modalAdicionarReceita').style.display = 'flex';
        }

        // Fechar modal de adicionar
        function fecharModalAdicionar() {
            document.getElementById('modalAdicionarReceita').style.display = 'none';
        }

        // Buscar receitas no modal
        document.getElementById('searchReceitas').addEventListener('input', function() {
            const termo = this.value.toLowerCase();
            const items = document.querySelectorAll('.receita-modal-item');
            
            items.forEach(item => {
                const titulo = item.querySelector('.receita-modal-titulo').textContent.toLowerCase();
                const meta = item.querySelector('.receita-modal-meta').textContent.toLowerCase();
                
                if (titulo.includes(termo) || meta.includes(termo)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // Adicionar receita na pasta
        async function adicionarReceitaNaPasta(receitaId, pastaId) {
            try {
                const response = await fetch('ajax/adicionar-pasta.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        receita_id: receitaId, 
                        pasta_id: pastaId 
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.message || 'Erro ao adicionar receita à pasta');
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao adicionar receita');
            }
        }

        // Remover receita da pasta
        async function removerDaPasta(receitaId, pastaId) {
            if (!confirm('Tem certeza que deseja remover esta receita da pasta?')) {
                return;
            }
            
            try {
                const response = await fetch('ajax/remover-pasta.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        receita_id: receitaId, 
                        pasta_id: pastaId 
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.message || 'Erro ao remover receita da pasta');
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao remover receita');
            }
        }

        // Editar pasta
        function editarPasta(pastaId) {
            document.getElementById('nomeEdicao').value = '<?php echo sanitizar($pasta['nome']); ?>';
            document.getElementById('descricaoEdicao').value = '<?php echo sanitizar($pasta['descricao'] ?? ''); ?>';
            
            document.getElementById('modalEditarPasta').style.display = 'flex';
        }

        // Fechar modal de edição
        function fecharModalEdicao() {
            document.getElementById('modalEditarPasta').style.display = 'none';
        }

        // Salvar edição da pasta
        document.getElementById('formEditarPasta').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const nome = document.getElementById('nomeEdicao').value.trim();
            const descricao = document.getElementById('descricaoEdicao').value.trim();
            
            if (!nome) {
                alert('Por favor, insira um nome para a pasta.');
                return;
            }
            
            try {
                const response = await fetch('ajax/editar-pasta.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        pasta_id: pastaAtual,
                        nome: nome,
                        descricao: descricao
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.message || 'Erro ao editar pasta');
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao editar pasta');
            }
        });

        // Confirmar remoção da pasta
        function confirmarRemocaoPasta(pastaId) {
            if (confirm('Tem certeza que deseja deletar esta pasta? Esta ação não pode ser desfeita.')) {
                removerPasta(pastaId);
            }
        }

        // Remover pasta
        async function removerPasta(pastaId) {
            try {
                const response = await fetch('ajax/deletar-pasta.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ pasta_id: pastaId })
                });
                
                const result = await response.json();
                if (result.success) {
                    window.location.href = 'perfil.php';
                } else {
                    alert(result.message || 'Erro ao deletar pasta');
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao deletar pasta');
            }
        }

        // Fechar modais ao clicar fora
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        });

        // Animações de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.receita-card, .adicionar-receita-card');
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
    </script>
</body>
</html>