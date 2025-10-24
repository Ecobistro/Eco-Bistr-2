<?php
require_once 'config.php';
iniciarSessao();

if (!estaLogado()) {
    header('Location: login.php?redirect=nova-receita.php');
    exit;
}

$usuarioLogado = getUsuarioLogado();
$db = Database::getInstance()->getConnection();

// Buscar categorias
try {
    $stmt = $db->query("SELECT * FROM categorias ORDER BY nome");
    $categorias = $stmt->fetchAll();
} catch (Exception $e) {
    $categorias = [];
    error_log("Erro ao buscar categorias: " . $e->getMessage());
}

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
    
    // Campos das seções opcionais
    $temCobertura = isset($_POST['tem_cobertura']) ? 1 : 0;
    $temRecheio = isset($_POST['tem_recheio']) ? 1 : 0;
    $temOutros = isset($_POST['tem_outros']) ? 1 : 0;
    
    $ingredientesCobertura = $_POST['ingredientes_cobertura'] ?? '';
    $modoPreparoCobertura = $_POST['modo_preparo_cobertura'] ?? '';
    $ingredientesRecheio = $_POST['ingredientes_recheio'] ?? '';
    $modoPreparoRecheio = $_POST['modo_preparo_recheio'] ?? '';
    $tituloOutros = sanitizar($_POST['titulo_outros'] ?? '');
    $ingredientesOutros = $_POST['ingredientes_outros'] ?? '';
    $modoPreparoOutros = $_POST['modo_preparo_outros'] ?? '';
    
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
    } elseif ($temCobertura && empty($ingredientesCobertura)) {
        $erro = 'Se marcou cobertura, deve informar os ingredientes da cobertura.';
    } elseif ($temRecheio && empty($ingredientesRecheio)) {
        $erro = 'Se marcou recheio, deve informar os ingredientes do recheio.';
    } elseif ($temOutros && (empty($tituloOutros) || empty($ingredientesOutros))) {
        $erro = 'Se marcou "Outros", deve informar o título e os ingredientes desta seção.';
    } elseif ($temOutros && strlen($tituloOutros) > 100) {
        $erro = 'O título da seção "Outros" deve ter no máximo 100 caracteres.';
    } else {
        // Verificar se categoria existe
        try {
            $stmt = $db->prepare("SELECT id FROM categorias WHERE id = ?");
            $stmt->execute([$categoriaId]);
            if (!$stmt->fetch()) {
                $erro = 'Categoria inválida.';
            }
        } catch (Exception $e) {
            $erro = 'Erro ao verificar categoria.';
            error_log("Erro ao verificar categoria: " . $e->getMessage());
        }
        
        if (!$erro) {
            // Upload da imagem (opcional)
            $nomeImagem = null;
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
                
                $nomeImagem = uploadImagem($_FILES['imagem'], 'receitas');
                if (!$nomeImagem) {
                    $erro = 'Erro ao fazer upload da imagem. Verifique o formato (JPG, PNG, GIF, WEBP) e tamanho (máximo 5MB).';
                }
            } elseif (isset($_FILES['imagem']) && $_FILES['imagem']['error'] !== UPLOAD_ERR_NO_FILE) {
                switch ($_FILES['imagem']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $erro = 'Arquivo muito grande. Tamanho máximo: 5MB.';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $erro = 'Upload incompleto. Tente novamente.';
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                    case UPLOAD_ERR_CANT_WRITE:
                        $erro = 'Erro no servidor durante upload.';
                        break;
                    default:
                        $erro = 'Erro desconhecido no upload.';
                        break;
                }
            }
            
            if (!$erro) {
                try {
                    // Processar ingredientes e modo de preparo principal
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
                    
                    // Processar cobertura se marcada
                    $ingredientesCoberturaFinal = null;
                    $modoPreparoCoberturaFinal = null;
                    if ($temCobertura && !empty($ingredientesCobertura)) {
                        $ingredientesCoberturaLimpos = explode("\n", $ingredientesCobertura);
                        $ingredientesCoberturaLimpos = array_map('trim', $ingredientesCoberturaLimpos);
                        $ingredientesCoberturaLimpos = array_filter($ingredientesCoberturaLimpos);
                        $ingredientesCoberturaFinal = implode(';', $ingredientesCoberturaLimpos);
                        
                        if (!empty($modoPreparoCobertura)) {
                            $modoPreparoCoberturaLimpo = explode("\n", $modoPreparoCobertura);
                            $modoPreparoCoberturaLimpo = array_map('trim', $modoPreparoCoberturaLimpo);
                            $modoPreparoCoberturaLimpo = array_filter($modoPreparoCoberturaLimpo);
                            $modoPreparoCoberturaLimpo = array_map(function($passo) {
                                return preg_replace('/^\d+\.?\s*/', '', $passo);
                            }, $modoPreparoCoberturaLimpo);
                            $modoPreparoCoberturaFinal = implode(';', $modoPreparoCoberturaLimpo);
                        }
                    }
                    
                    // Processar recheio se marcado
                    $ingredientesRecheioFinal = null;
                    $modoPreparoRecheioFinal = null;
                    if ($temRecheio && !empty($ingredientesRecheio)) {
                        $ingredientesRecheioLimpos = explode("\n", $ingredientesRecheio);
                        $ingredientesRecheioLimpos = array_map('trim', $ingredientesRecheioLimpos);
                        $ingredientesRecheioLimpos = array_filter($ingredientesRecheioLimpos);
                        $ingredientesRecheioFinal = implode(';', $ingredientesRecheioLimpos);
                        
                        if (!empty($modoPreparoRecheio)) {
                            $modoPreparoRecheioLimpo = explode("\n", $modoPreparoRecheio);
                            $modoPreparoRecheioLimpo = array_map('trim', $modoPreparoRecheioLimpo);
                            $modoPreparoRecheioLimpo = array_filter($modoPreparoRecheioLimpo);
                            $modoPreparoRecheioLimpo = array_map(function($passo) {
                                return preg_replace('/^\d+\.?\s*/', '', $passo);
                            }, $modoPreparoRecheioLimpo);
                            $modoPreparoRecheioFinal = implode(';', $modoPreparoRecheioLimpo);
                        }
                    }
                    
                    // Processar seção "Outros" se marcada
                    $ingredientesOutrosFinal = null;
                    $modoPreparoOutrosFinal = null;
                    if ($temOutros && !empty($ingredientesOutros)) {
                        $ingredientesOutrosLimpos = explode("\n", $ingredientesOutros);
                        $ingredientesOutrosLimpos = array_map('trim', $ingredientesOutrosLimpos);
                        $ingredientesOutrosLimpos = array_filter($ingredientesOutrosLimpos);
                        $ingredientesOutrosFinal = implode(';', $ingredientesOutrosLimpos);
                        
                        if (!empty($modoPreparoOutros)) {
                            $modoPreparoOutrosLimpo = explode("\n", $modoPreparoOutros);
                            $modoPreparoOutrosLimpo = array_map('trim', $modoPreparoOutrosLimpo);
                            $modoPreparoOutrosLimpo = array_filter($modoPreparoOutrosLimpo);
                            $modoPreparoOutrosLimpo = array_map(function($passo) {
                                return preg_replace('/^\d+\.?\s*/', '', $passo);
                            }, $modoPreparoOutrosLimpo);
                            $modoPreparoOutrosFinal = implode(';', $modoPreparoOutrosLimpo);
                        }
                    }
                    
                    $colunaData = 'data_criacao';
                    
                    $sql = "INSERT INTO receitas (titulo, descricao, tempo_preparo, porcoes, dificuldade, 
                                                imagem, usuario_id, categoria_id, ingredientes, modo_preparo, 
                                                tem_cobertura, tem_recheio, tem_outros,
                                                ingredientes_cobertura, modo_preparo_cobertura,
                                                ingredientes_recheio, modo_preparo_recheio,
                                                titulo_outros, ingredientes_outros, modo_preparo_outros,
                                                $colunaData)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    
                    $stmt = $db->prepare($sql);
                    
                    $resultado = $stmt->execute([
                        $titulo, 
                        $descricao, 
                        $tempoPreparo, 
                        $porcoes, 
                        $dificuldade,
                        $nomeImagem, 
                        $usuarioLogado['id'], 
                        $categoriaId, 
                        $ingredientesFinal, 
                        $modoPreparoFinal,
                        $temCobertura,
                        $temRecheio,
                        $temOutros,
                        $ingredientesCoberturaFinal,
                        $modoPreparoCoberturaFinal,
                        $ingredientesRecheioFinal,
                        $modoPreparoRecheioFinal,
                        $tituloOutros,
                        $ingredientesOutrosFinal,
                        $modoPreparoOutrosFinal
                    ]);
                    
                    if ($resultado) {
                        $receitaId = $db->lastInsertId();
                        $sucesso = 'Receita criada com sucesso! Redirecionando...';
                        
                        error_log("Receita criada com sucesso - ID: $receitaId");
                        
                        // Limpar dados do formulário
                        $titulo = $descricao = $ingredientes = $modoPreparo = '';
                        $ingredientesCobertura = $modoPreparoCobertura = '';
                        $ingredientesRecheio = $modoPreparoRecheio = '';
                        $tituloOutros = $ingredientesOutros = $modoPreparoOutros = '';
                        $tempoPreparo = $porcoes = $categoriaId = 0;
                        $dificuldade = 'Fácil';
                        $temCobertura = $temRecheio = $temOutros = 0;
                        
                        echo "<script>
                            setTimeout(function() {
                                window.location.href = 'receita.php?id=$receitaId';
                            }, 2000);
                        </script>";
                    } else {
                        $erro = 'Erro ao salvar receita no banco de dados.';
                        error_log("Erro no execute: " . implode(', ', $stmt->errorInfo()));
                    }
                    
                } catch (Exception $e) {
                    $erro = 'Erro ao criar receita: ' . $e->getMessage();
                    error_log("Erro ao inserir receita: " . $e->getMessage());
                    error_log("SQL Error Info: " . print_r($db->errorInfo(), true));
                    
                    if ($nomeImagem) {
                        deletarImagem($nomeImagem, 'receitas');
                    }
                }
            }
        }
    }
}

// Verificar se pasta uploads existe
$uploadDir = __DIR__ . '/uploads/receitas/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        error_log("Erro ao criar diretório de uploads: $uploadDir");
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Receita - Eco Bistro</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(var(--primary-color-rgb), 0.2);
        }
        
        .secoes-opcionais {
            background: var(--background-light);
            padding: 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 2rem;
        }
        
        .checkbox-container {
            display: flex;
            gap: 2rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--primary-color);
        }
        
        .checkbox-item label {
            font-weight: 600;
            color: var(--text-dark);
            cursor: pointer;
        }
        
        .secao-adicional {
            display: none;
            margin-top: 1.5rem;
            padding: 1.5rem;
            background: white;
            border-radius: var(--radius);
            border: 2px dashed var(--primary-color);
        }
        
        .secao-adicional.show {
            display: block;
            animation: slideDown 0.3s ease;
        }
        
        .secao-adicional.secao-outros {
            border-color: var(--accent-color);
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .secao-titulo {
            color: var(--accent-color);
            margin-bottom: 1rem;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .secao-outros .secao-titulo {
            color: var(--accent-color);
        }
        
        @media (max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr !important; }
            .grid-3 { grid-template-columns: 1fr !important; }
            .checkbox-container { 
                flex-direction: column; 
                gap: 1rem; 
            }
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
                    Nova Receita
                </h1>
                <p style="color: var(--text-light); font-size: 1.1rem;">
                    Compartilhe sua criação culinária com a comunidade!
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
                               value="<?php echo htmlspecialchars($titulo ?? ''); ?>" 
                               placeholder="Ex: Bolo de Chocolate" maxlength="100" required>
                        <div class="char-counter"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="categoria_id" class="form-label">Categoria *</label>
                        <select id="categoria_id" name="categoria_id" class="form-control" required>
                            <option value="">Selecione uma categoria</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo $categoria['id']; ?>" 
                                        <?php echo ($categoriaId ?? 0) == $categoria['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitizar($categoria['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 2rem;">
                    <label for="descricao" class="form-label">Descrição</label>
                    <textarea id="descricao" name="descricao" class="form-control" rows="3" 
                              placeholder="Descreva sua receita... (opcional)" maxlength="500"><?php echo htmlspecialchars($descricao ?? ''); ?></textarea>
                </div>

                <!-- Meta informações -->
                <div class="grid-3" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                    <div class="form-group">
                        <label for="tempo_preparo" class="form-label">Tempo de preparo (min) *</label>
                        <input type="number" id="tempo_preparo" name="tempo_preparo" class="form-control" 
                               value="<?php echo $tempoPreparo ?? ''; ?>" min="1" max="600" placeholder="Ex: 30" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="porcoes" class="form-label">Porções *</label>
                        <input type="number" id="porcoes" name="porcoes" class="form-control" 
                               value="<?php echo $porcoes ?? '1'; ?>" min="1" max="50" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="dificuldade" class="form-label">Dificuldade *</label>
                        <select id="dificuldade" name="dificuldade" class="form-control" required>
                            <option value="Fácil" <?php echo ($dificuldade ?? '') === 'Fácil' ? 'selected' : ''; ?>>Fácil</option>
                            <option value="Médio" <?php echo ($dificuldade ?? '') === 'Médio' ? 'selected' : ''; ?>>Médio</option>
                            <option value="Difícil" <?php echo ($dificuldade ?? '') === 'Difícil' ? 'selected' : ''; ?>>Difícil</option>
                        </select>
                    </div>
                </div>

                <!-- Upload de Imagem -->
                <div class="form-group" style="margin-bottom: 2rem;">
                    <label for="imagem" class="form-label">Foto da receita</label>
                    <input type="file" id="imagem" name="imagem" class="form-control" accept="image/*">
                    <small style="color: var(--text-light);">JPG, PNG, GIF, WEBP • Máximo 5MB</small>
                </div>

                <!-- Seções Opcionais -->
                <div class="secoes-opcionais">
                    <h3 style="margin-bottom: 1rem; color: var(--text-dark);">Seções Adicionais</h3>
                    <p style="color: var(--text-light); margin-bottom: 1.5rem; font-size: 0.9rem;">
                        Marque se sua receita possui cobertura, recheio ou outros complementos
                    </p>
                    
                    <div class="checkbox-container">
                        <div class="checkbox-item">
                            <input type="checkbox" id="tem_cobertura" name="tem_cobertura" value="1" 
                                   <?php echo ($temCobertura ?? 0) ? 'checked' : ''; ?>>
                            <label for="tem_cobertura">Possui Cobertura</label>
                        </div>
                        
                        <div class="checkbox-item">
                            <input type="checkbox" id="tem_recheio" name="tem_recheio" value="1"
                                   <?php echo ($temRecheio ?? 0) ? 'checked' : ''; ?>>
                            <label for="tem_recheio">Possui Recheio</label>
                        </div>
                        
                        <div class="checkbox-item">
                            <input type="checkbox" id="tem_outros" name="tem_outros" value="1"
                                   <?php echo ($temOutros ?? 0) ? 'checked' : ''; ?>>
                            <label for="tem_outros">Possui Outros</label>
                        </div>
                    </div>
                    
                    <!-- Seção Cobertura -->
                    <div id="secao-cobertura" class="secao-adicional">
                        <h4 class="secao-titulo">🍰 Cobertura</h4>
                        
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label for="ingredientes_cobertura" class="form-label">Ingredientes da Cobertura</label>
                            <textarea id="ingredientes_cobertura" name="ingredientes_cobertura" class="form-control" rows="4"
                                      placeholder="Digite cada ingrediente da cobertura em uma linha:

200g de chocolate meio amargo
1 xícara de creme de leite
2 colheres de manteiga"><?php echo htmlspecialchars($ingredientesCobertura ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="modo_preparo_cobertura" class="form-label">Modo de Preparo da Cobertura</label>
                            <textarea id="modo_preparo_cobertura" name="modo_preparo_cobertura" class="form-control" rows="4"
                                      placeholder="Digite cada passo da cobertura em uma linha:

1. Derreta o chocolate em banho-maria
2. Adicione o creme de leite e mexa bem
3. Incorpore a manteiga até ficar homogênea"><?php echo htmlspecialchars($modoPreparoCobertura ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Seção Recheio -->
                    <div id="secao-recheio" class="secao-adicional">
                        <h4 class="secao-titulo">🥧 Recheio</h4>
                        
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label for="ingredientes_recheio" class="form-label">Ingredientes do Recheio</label>
                            <textarea id="ingredientes_recheio" name="ingredientes_recheio" class="form-control" rows="4"
                                      placeholder="Digite cada ingrediente do recheio em uma linha:

500g de doce de leite
200g de coco ralado
1 colher de essência de baunilha"><?php echo htmlspecialchars($ingredientesRecheio ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="modo_preparo_recheio" class="form-label">Modo de Preparo do Recheio</label>
                            <textarea id="modo_preparo_recheio" name="modo_preparo_recheio" class="form-control" rows="4"
                                      placeholder="Digite cada passo do recheio em uma linha:

1. Misture o doce de leite com o coco
2. Adicione a essência de baunilha
3. Mexa até obter consistência homogênea"><?php echo htmlspecialchars($modoPreparoRecheio ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Seção Outros -->
                    <div id="secao-outros" class="secao-adicional secao-outros">
                        <h4 class="secao-titulo">✨ Outros</h4>
                        
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label for="titulo_outros" class="form-label">Título desta seção *</label>
                            <input type="text" id="titulo_outros" name="titulo_outros" class="form-control" 
                                   value="<?php echo htmlspecialchars($tituloOutros ?? ''); ?>" 
                                   placeholder="Ex: Decoração de Flores, Molho Especial, Finalização..." maxlength="100">
                            <small style="color: var(--text-light);">Descreva o que é esta seção adicional</small>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label for="ingredientes_outros" class="form-label">Ingredientes/Materiais</label>
                            <textarea id="ingredientes_outros" name="ingredientes_outros" class="form-control" rows="4"
                                      placeholder="Digite cada ingrediente ou material em uma linha:

Flores comestíveis variadas
Açúcar cristal colorido
Corante alimentício
Folhas de hortelã"><?php echo htmlspecialchars($ingredientesOutros ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="modo_preparo_outros" class="form-label">Modo de Preparo</label>
                            <textarea id="modo_preparo_outros" name="modo_preparo_outros" class="form-control" rows="4"
                                      placeholder="Digite cada passo em uma linha:

1. Lave e seque delicadamente as flores
2. Pincele com clara em neve
3. Polvilhe com açúcar cristal
4. Deixe secar por 30 minutos"><?php echo htmlspecialchars($modoPreparoOutros ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Ingredientes Principais -->
                <div class="form-group" style="margin-bottom: 2rem;">
                    <label for="ingredientes" class="form-label">Ingredientes Principais *</label>
                    <textarea id="ingredientes" name="ingredientes" class="form-control" rows="6" required
                              placeholder="Digite cada ingrediente em uma linha:

2 xícaras de farinha de trigo
3 ovos
1 xícara de açúcar
1/2 xícara de óleo"><?php echo htmlspecialchars($ingredientes ?? ''); ?></textarea>
                    <small style="color: var(--text-light); font-size: 0.8rem;">
                        Digite cada ingrediente em uma linha separada
                    </small>
                </div>

                <!-- Modo de preparo principal -->
                <div class="form-group" style="margin-bottom: 2rem;">
                    <label for="modo_preparo" class="form-label">Modo de Preparo Principal *</label>
                    <textarea id="modo_preparo" name="modo_preparo" class="form-control" rows="8" required
                              placeholder="Digite cada passo em uma linha:

1. Preaqueça o forno a 180°C
2. Em uma tigela, misture os ingredientes secos
3. Em outra tigela, bata os ovos com açúcar
4. Incorpore os ingredientes líquidos aos secos"><?php echo htmlspecialchars($modoPreparo ?? ''); ?></textarea>
                    <small style="color: var(--text-light); font-size: 0.8rem;">
                        Digite cada passo em uma linha separada
                    </small>
                </div>

                <!-- Botões -->
                <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 3rem; flex-wrap: wrap;">
                    <button type="submit" class="btn btn-primary" style="padding: 1rem 2rem; min-width: 200px;">
                        Publicar Receita
                    </button>
                    <a href="perfil.php" class="btn btn-secondary" style="padding: 1rem 2rem; text-decoration: none; display: inline-block;">
                        Cancelar
                    </a>
                </div>

                <p style="text-align: center; margin-top: 1rem; color: var(--text-light); font-size: 0.9rem;">
                    * Campos obrigatórios
                </p>
            </form>
        </div>
    </div>

    <script>
        // Contador de caracteres para título
        document.getElementById('titulo').addEventListener('input', function() {
            const remaining = 100 - this.value.length;
            let counter = this.parentNode.querySelector('.char-counter');
            
            if (!counter) {
                counter = document.createElement('small');
                counter.className = 'char-counter';
                counter.style.color = 'var(--text-light)';
                counter.style.fontSize = '0.8rem';
                counter.style.display = 'block';
                counter.style.marginTop = '0.25rem';
                this.parentNode.appendChild(counter);
            }
            
            counter.textContent = `${remaining} caracteres restantes`;
            counter.style.color = remaining < 10 ? 'var(--accent-color)' : 'var(--text-light)';
        });

        // Controle das seções opcionais
        function toggleSecao(checkboxId, secaoId) {
            const checkbox = document.getElementById(checkboxId);
            const secao = document.getElementById(secaoId);
            
            if (checkbox.checked) {
                secao.classList.add('show');
                // Focar no primeiro campo da seção
                const firstInput = secao.querySelector('input, textarea');
                if (firstInput) {
                    setTimeout(() => firstInput.focus(), 300);
                }
            } else {
                secao.classList.remove('show');
                // Limpar campos quando desmarcar
                const inputs = secao.querySelectorAll('input, textarea');
                inputs.forEach(input => input.value = '');
            }
        }

        // Event listeners para checkboxes
        document.getElementById('tem_cobertura').addEventListener('change', function() {
            toggleSecao('tem_cobertura', 'secao-cobertura');
        });

        document.getElementById('tem_recheio').addEventListener('change', function() {
            toggleSecao('tem_recheio', 'secao-recheio');
        });

        document.getElementById('tem_outros').addEventListener('change', function() {
            toggleSecao('tem_outros', 'secao-outros');
        });

        // Mostrar seções já marcadas no carregamento
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('tem_cobertura').checked) {
                document.getElementById('secao-cobertura').classList.add('show');
            }
            if (document.getElementById('tem_recheio').checked) {
                document.getElementById('secao-recheio').classList.add('show');
            }
            if (document.getElementById('tem_outros').checked) {
                document.getElementById('secao-outros').classList.add('show');
            }
        });

        // Validação do formulário
        document.getElementById('formReceita').addEventListener('submit', function(e) {
            const titulo = document.getElementById('titulo').value.trim();
            const ingredientes = document.getElementById('ingredientes').value.trim();
            const modoPreparo = document.getElementById('modo_preparo').value.trim();
            const tempoPreparo = parseInt(document.getElementById('tempo_preparo').value);
            const porcoes = parseInt(document.getElementById('porcoes').value);
            const categoriaId = document.getElementById('categoria_id').value;
            
            // Validações básicas
            if (!titulo || !ingredientes || !modoPreparo || !tempoPreparo || !categoriaId) {
                e.preventDefault();
                alert('Por favor, preencha todos os campos obrigatórios.');
                return;
            }
            
            // Validações das seções opcionais
            const temCobertura = document.getElementById('tem_cobertura').checked;
            const temRecheio = document.getElementById('tem_recheio').checked;
            const temOutros = document.getElementById('tem_outros').checked;
            
            if (temCobertura) {
                const ingredientesCobertura = document.getElementById('ingredientes_cobertura').value.trim();
                if (!ingredientesCobertura) {
                    e.preventDefault();
                    alert('Você marcou "Possui Cobertura" mas não informou os ingredientes da cobertura.');
                    document.getElementById('ingredientes_cobertura').focus();
                    return;
                }
            }
            
            if (temRecheio) {
                const ingredientesRecheio = document.getElementById('ingredientes_recheio').value.trim();
                if (!ingredientesRecheio) {
                    e.preventDefault();
                    alert('Você marcou "Possui Recheio" mas não informou os ingredientes do recheio.');
                    document.getElementById('ingredientes_recheio').focus();
                    return;
                }
            }
            
            if (temOutros) {
                const tituloOutros = document.getElementById('titulo_outros').value.trim();
                const ingredientesOutros = document.getElementById('ingredientes_outros').value.trim();
                if (!tituloOutros || !ingredientesOutros) {
                    e.preventDefault();
                    alert('Você marcou "Possui Outros" mas não informou o título e ingredientes desta seção.');
                    if (!tituloOutros) {
                        document.getElementById('titulo_outros').focus();
                    } else {
                        document.getElementById('ingredientes_outros').focus();
                    }
                    return;
                }
            }
            
            // Mostrar loading
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '📝 Publicando...';
            submitBtn.disabled = true;
            
            // Restaurar botão em caso de erro (fallback)
            setTimeout(() => {
                if (submitBtn.disabled) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            }, 30000);
        });

        // Trigger contador de caracteres no carregamento
        document.getElementById('titulo').dispatchEvent(new Event('input'));
        
        // Efeitos visuais aprimorados
        document.addEventListener('DOMContentLoaded', function() {
            // Animação de entrada para o formulário
            const form = document.getElementById('formReceita');
            form.style.opacity = '0';
            form.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                form.style.transition = 'all 0.8s ease';
                form.style.opacity = '1';
                form.style.transform = 'translateY(0)';
            }, 100);
            
            // Adicionar indicadores visuais para seções opcionais
            const checkboxes = document.querySelectorAll('.checkbox-item input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const label = this.nextElementSibling;
                    if (this.checked) {
                        label.style.color = 'var(--accent-color)';
                        label.style.fontWeight = '700';
                    } else {
                        label.style.color = 'var(--text-dark)';
                        label.style.fontWeight = '600';
                    }
                });
            });
        });

        console.log('Nova receita com seção "Outros" - Sistema carregado com sucesso!');
    </script>
</body>
</html>