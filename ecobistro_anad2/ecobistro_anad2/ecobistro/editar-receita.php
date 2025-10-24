<?php
require_once 'config.php';
iniciarSessao();

if (!estaLogado()) {
    header('Location: login.php');
    exit;
}

$usuarioLogado = getUsuarioLogado();
$receitaId = (int)($_GET['id'] ?? 0);

if (!$receitaId) {
    header('Location: perfil.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// Buscar receita e verificar se pertence ao usuário
$stmt = $db->prepare("SELECT * FROM receitas WHERE id = ? AND usuario_id = ? AND ativo = 1");
$stmt->execute([$receitaId, $usuarioLogado['id']]);
$receita = $stmt->fetch();

if (!$receita) {
    header('Location: perfil.php');
    exit;
}

// Buscar categorias
$stmt = $db->query("SELECT * FROM categorias ORDER BY nome");
$categorias = $stmt->fetchAll();

$erro = '';
$sucesso = '';

if ($_POST) {
    $titulo = sanitizar($_POST['titulo'] ?? '');
    $descricao = sanitizar($_POST['descricao'] ?? '');
    $tempoPreparo = (int)($_POST['tempo_preparo'] ?? 0);
    $porcoes = (int)($_POST['porcoes'] ?? 1);
    $dificuldade = sanitizar($_POST['dificuldade'] ?? 'Fácil');
    $categoriaId = (int)($_POST['categoria_id'] ?? 0);
    $ingredientes = $_POST['ingredientes'] ?? '';
    $modoPreparo = $_POST['modo_preparo'] ?? '';
    
    // Validações
    if (empty($titulo) || empty($ingredientes) || empty($modoPreparo) || !$tempoPreparo || !$categoriaId) {
        $erro = 'Por favor, preencha todos os campos obrigatórios.';
    } elseif (strlen($titulo) > 100) {
        $erro = 'O título deve ter no máximo 100 caracteres.';
    } elseif ($tempoPreparo < 1 || $tempoPreparo > 600) {
        $erro = 'O tempo de preparo deve estar entre 1 e 600 minutos.';
    } elseif ($porcoes < 1 || $porcoes > 50) {
        $erro = 'As porções devem estar entre 1 e 50.';
    } elseif (!in_array($dificuldade, ['Fácil', 'Médio', 'Difícil'])) {
        $erro = 'Dificuldade inválida.';
    } else {
        $nomeImagem = $receita['imagem']; // Manter imagem atual por padrão
        
        // Upload de nova imagem (opcional)
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
            if (!defined('MAX_FILE_SIZE')) {
                define('MAX_FILE_SIZE', 5 * 1024 * 1024);
            }
            if (!defined('ALLOWED_IMAGE_TYPES')) {
                define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/webp']);
            }
            if (!defined('UPLOAD_DIR_RECEITAS')) {
                define('UPLOAD_DIR_RECEITAS', 'uploads/receitas/');
            }
            
            $novaImagem = uploadImagem($_FILES['imagem'], 'receitas');
            if ($novaImagem) {
                // Deletar imagem antiga se existir
                if ($nomeImagem) {
                    deletarImagem($nomeImagem, 'receitas');
                }
                $nomeImagem = $novaImagem;
            } else {
                $erro = 'Erro ao fazer upload da imagem. Verifique o formato (JPG, PNG, GIF, WEBP) e tamanho (máximo 5MB).';
            }
        }
        
        if (!$erro) {
            try {
                // Processar ingredientes e modo de preparo
                $ingredientesLimpos = explode("\n", $ingredientes);
                $ingredientesLimpos = array_map('trim', $ingredientesLimpos);
                $ingredientesLimpos = array_filter($ingredientesLimpos);
                $ingredientesFinal = implode(';', $ingredientesLimpos);
                
                $modoPreparoLimpo = explode("\n", $modoPreparo);
                $modoPreparoLimpo = array_map('trim', $modoPreparoLimpo);
                $modoPreparoLimpo = array_filter($modoPreparoLimpo);
                $modoPreparoLimpo = array_map(function($passo) {
                    return preg_replace('/^\d+\.?\s*/', '', $passo);
                }, $modoPreparoLimpo);
                $modoPreparoFinal = implode(';', $modoPreparoLimpo);
                
                $sql = "UPDATE receitas SET 
                        titulo = ?, descricao = ?, tempo_preparo = ?, porcoes = ?, 
                        dificuldade = ?, imagem = ?, categoria_id = ?, 
                        ingredientes = ?, modo_preparo = ?
                        WHERE id = ? AND usuario_id = ?";
                
                $stmt = $db->prepare($sql);
                $resultado = $stmt->execute([
                    $titulo, $descricao, $tempoPreparo, $porcoes, $dificuldade,
                    $nomeImagem, $categoriaId, $ingredientesFinal, $modoPreparoFinal,
                    $receitaId, $usuarioLogado['id']
                ]);
                
                if ($resultado) {
                    $sucesso = 'Receita atualizada com sucesso! Redirecionando...';
                    echo "<script>
                        setTimeout(function() {
                            window.location.href = 'receita.php?id=$receitaId';
                        }, 2000);
                    </script>";
                    
                    // Atualizar dados da receita para mostrar no formulário
                    $receita['titulo'] = $titulo;
                    $receita['descricao'] = $descricao;
                    $receita['tempo_preparo'] = $tempoPreparo;
                    $receita['porcoes'] = $porcoes;
                    $receita['dificuldade'] = $dificuldade;
                    $receita['categoria_id'] = $categoriaId;
                    $receita['ingredientes'] = $ingredientesFinal;
                    $receita['modo_preparo'] = $modoPreparoFinal;
                    $receita['imagem'] = $nomeImagem;
                } else {
                    $erro = 'Erro ao atualizar receita.';
                }
                
            } catch (Exception $e) {
                $erro = 'Erro ao atualizar receita: ' . $e->getMessage();
                error_log("Erro ao atualizar receita: " . $e->getMessage());
            }
        }
    }
}

// Converter ingredientes e modo de preparo para exibição
$ingredientesTexto = str_replace(';', "\n", $receita['ingredientes']);
$modoPreparoTexto = str_replace(';', "\n", $receita['modo_preparo']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Receita - Eco Bistro</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(var(--primary-color-rgb), 0.2);
        }
        
        .current-image {
            width: 200px;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr !important; }
            .grid-3 { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="nav-container">
            <a href="index.php" class="logo">ECO BISTRO</a>
            <nav class="nav-menu">
                <a href="receitas.php" class="nav-link">RECEITAS</a>
                <a href="perfil.php" class="nav-link">@<?php echo sanitizar($usuarioLogado['nome_usuario']); ?></a>
                <a href="logout.php" class="nav-link">SAIR</a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container" style="padding: 2rem 0;">
        <div style="max-width: 800px; margin: 0 auto;">
            <div style="text-align: center; margin-bottom: 3rem;">
                <h1 style="font-size: 2.5rem; color: var(--text-dark); margin-bottom: 1rem;">
                    Editar Receita
                </h1>
                <p style="color: var(--text-light); font-size: 1.1rem;">
                    Atualize sua receita "<?php echo sanitizar($receita['titulo']); ?>"
                </p>
            </div>

            <?php if ($erro): ?>
                <div class="alert alert-error" style="margin-bottom: 2rem; padding: 1rem; background: #fee; border-left: 4px solid #f56565; border-radius: 5px; color: #c53030;">
                    <strong>Erro:</strong> <?php echo $erro; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($sucesso): ?>
                <div class="alert alert-success" style="margin-bottom: 2rem; padding: 1rem; background: #f0fff4; border-left: 4px solid #48bb78; border-radius: 5px; color: #22543d;">
                    <strong>Sucesso!</strong> <?php echo $sucesso; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="formReceita" style="background: white; padding: 2rem; border-radius: 20px; box-shadow: var(--shadow);">
                <!-- Informações básicas -->
                <div class="grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                    <div class="form-group">
                        <label for="titulo" class="form-label">Título da receita *</label>
                        <input type="text" id="titulo" name="titulo" class="form-control" 
                               value="<?php echo htmlspecialchars($receita['titulo']); ?>" 
                               placeholder="Ex: Cuscuz Delicioso" maxlength="100" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="categoria_id" class="form-label">Categoria *</label>
                        <select id="categoria_id" name="categoria_id" class="form-control" required>
                            <option value="">Selecione uma categoria</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo $categoria['id']; ?>" 
                                        <?php echo $receita['categoria_id'] == $categoria['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitizar($categoria['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 2rem;">
                    <label for="descricao" class="form-label">Descrição</label>
                    <textarea id="descricao" name="descricao" class="form-control" rows="3" 
                              placeholder="Descreva sua receita... (opcional)" maxlength="500"><?php echo htmlspecialchars($receita['descricao']); ?></textarea>
                </div>

                <!-- Meta informações -->
                <div class="grid-3" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                    <div class="form-group">
                        <label for="tempo_preparo" class="form-label">Tempo de preparo (min) *</label>
                        <input type="number" id="tempo_preparo" name="tempo_preparo" class="form-control" 
                               value="<?php echo $receita['tempo_preparo']; ?>" min="1" max="600" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="porcoes" class="form-label">Porções *</label>
                        <input type="number" id="porcoes" name="porcoes" class="form-control" 
                               value="<?php echo $receita['porcoes']; ?>" min="1" max="50" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="dificuldade" class="form-label">Dificuldade *</label>
                        <select id="dificuldade" name="dificuldade" class="form-control" required>
                            <option value="Fácil" <?php echo $receita['dificuldade'] === 'Fácil' ? 'selected' : ''; ?>>Fácil</option>
                            <option value="Médio" <?php echo $receita['dificuldade'] === 'Médio' ? 'selected' : ''; ?>>Médio</option>
                            <option value="Difícil" <?php echo $receita['dificuldade'] === 'Difícil' ? 'selected' : ''; ?>>Difícil</option>
                        </select>
                    </div>
                </div>

                <!-- Imagem atual e upload -->
                <div class="form-group" style="margin-bottom: 2rem;">
                    <label class="form-label">Imagem atual</label>
                    <?php if ($receita['imagem']): ?>
                        <div style="margin-bottom: 1rem;">
                            <img src="uploads/receitas/<?php echo $receita['imagem']; ?>" 
                                 alt="<?php echo sanitizar($receita['titulo']); ?>" 
                                 class="current-image">
                        </div>
                    <?php else: ?>
                        <p style="color: var(--text-light); margin-bottom: 1rem;">Nenhuma imagem definida</p>
                    <?php endif; ?>
                    
                    <label for="imagem" class="form-label">Nova foto da receita (opcional)</label>
                    <input type="file" id="imagem" name="imagem" class="form-control" accept="image/*">
                    <small style="color: var(--text-light);">JPG, PNG, GIF, WEBP • Máximo 5MB • Deixe vazio para manter a imagem atual</small>
                </div>

                <!-- Ingredientes -->
                <div class="form-group" style="margin-bottom: 2rem;">
                    <label for="ingredientes" class="form-label">Ingredientes *</label>
                    <textarea id="ingredientes" name="ingredientes" class="form-control" rows="6" required
                              placeholder="Digite cada ingrediente em uma linha"><?php echo htmlspecialchars($ingredientesTexto); ?></textarea>
                </div>

                <!-- Modo de preparo -->
                <div class="form-group" style="margin-bottom: 2rem;">
                    <label for="modo_preparo" class="form-label">Modo de preparo *</label>
                    <textarea id="modo_preparo" name="modo_preparo" class="form-control" rows="8" required
                              placeholder="Digite cada passo em uma linha"><?php echo htmlspecialchars($modoPreparoTexto); ?></textarea>
                </div>

                <!-- Botões -->
                <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 3rem; flex-wrap: wrap;">
                    <button type="submit" class="btn btn-primary" style="padding: 1rem 2rem; min-width: 200px;">
                        Atualizar Receita
                    </button>
                    <a href="receita.php?id=<?php echo $receitaId; ?>" class="btn btn-secondary" style="padding: 1rem 2rem; text-decoration: none; display: inline-block;">
                        Cancelar
                    </a>
                    <button type="button" onclick="confirmarExclusao()" class="btn" style="background: var(--accent-color); color: white; padding: 1rem 2rem;">
                        Excluir Receita
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Confirmação de exclusão
        function confirmarExclusao() {
            if (confirm('Tem certeza que deseja excluir esta receita? Esta ação não pode ser desfeita.')) {
                window.location.href = 'ajax/excluir-receita.php?id=<?php echo $receitaId; ?>';
            }
        }

        // Validação do formulário
        document.getElementById('formReceita').addEventListener('submit', function(e) {
            const titulo = document.getElementById('titulo').value.trim();
            const ingredientes = document.getElementById('ingredientes').value.trim();
            const modoPreparo = document.getElementById('modo_preparo').value.trim();
            
            if (!titulo || !ingredientes || !modoPreparo) {
                e.preventDefault();
                alert('Por favor, preencha todos os campos obrigatórios.');
                return;
            }
            
            // Mostrar loading
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = 'Atualizando...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>