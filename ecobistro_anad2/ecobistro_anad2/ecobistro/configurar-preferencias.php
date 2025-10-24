<?php
require_once 'config.php';
iniciarSessao();

// Verificar se usuário está logado
if (!estaLogado()) {
    header('Location: login.php');
    exit;
}

$usuarioLogado = getUsuarioLogado();
$erro = '';
$sucesso = '';

// Se o usuário já configurou preferências, redirecionar
if (!empty($usuarioLogado['preferencias_configuradas'] ?? null)) {
    header('Location: index.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// Buscar tipos de preferências disponíveis
$stmt = $db->query("SELECT * FROM tipos_preferencias WHERE ativo = 1 ORDER BY tipo, valor");
$tiposPreferencias = $stmt->fetchAll();

// Organizar por tipo
$preferenciasPorTipo = [];
foreach ($tiposPreferencias as $pref) {
    $preferenciasPorTipo[$pref['tipo']][] = $pref;
}

// Processar formulário
if ($_POST) {
    $preferenciasSelecionadas = $_POST['preferencias'] ?? [];
    
    try {
        $db->beginTransaction();
        
        // Limpar preferências existentes (caso o usuário esteja reconfigurando)
        $stmt = $db->prepare("DELETE FROM preferencias_receitas WHERE usuario_id = ?");
        $stmt->execute([$usuarioLogado['id']]);
        
        // Inserir novas preferências
        if (!empty($preferenciasSelecionadas)) {
            $stmt = $db->prepare("
                INSERT INTO preferencias_receitas (usuario_id, tipo_preferencia, valor, descricao) 
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($preferenciasSelecionadas as $preferencia) {
                // Buscar descrição da preferência
                $stmtDesc = $db->prepare("SELECT descricao FROM tipos_preferencias WHERE valor = ? AND tipo = ?");
                $stmtDesc->execute([$preferencia['valor'], $preferencia['tipo']]);
                $descricao = $stmtDesc->fetchColumn();
                
                $stmt->execute([
                    $usuarioLogado['id'],
                    $preferencia['tipo'],
                    $preferencia['valor'],
                    $descricao
                ]);
            }
        }
        
        // Marcar que o usuário configurou preferências
        $stmt = $db->prepare("UPDATE usuarios SET preferencias_configuradas = 1 WHERE id = ?");
        $stmt->execute([$usuarioLogado['id']]);
        
        $db->commit();
        
        $sucesso = 'Preferências salvas com sucesso! Redirecionando...';
        header("refresh:2;url=index.php");
        
    } catch (Exception $e) {
        $db->rollBack();
        $erro = 'Erro ao salvar preferências. Tente novamente.';
        error_log("Erro ao salvar preferências: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Preferências - Eco Bistrô</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .preferencias-container {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-strong);
        }
        
        .preferencias-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .preferencias-header h1 {
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .preferencias-header p {
            color: var(--text-light);
            font-size: 1.1rem;
        }
        
        .tipo-preferencia {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: var(--background-light);
            border-radius: 15px;
            border-left: 4px solid var(--primary);
        }
        
        .tipo-preferencia h3 {
            color: var(--text-dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .opcoes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .opcao-preferencia {
            position: relative;
        }
        
        .opcao-preferencia input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }
        
        .opcao-preferencia label {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: white;
            border: 2px solid #E9ECEF;
            border-radius: 12px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
        }
        
        .opcao-preferencia label:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-soft);
        }
        
        .opcao-preferencia input[type="checkbox"]:checked + label {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .opcao-preferencia .icone {
            font-size: 1.2rem;
        }
        
        .opcao-preferencia .texto {
            flex: 1;
        }
        
        .opcao-preferencia .descricao {
            font-size: 0.85rem;
            opacity: 0.8;
            margin-top: 0.25rem;
        }
        
        .btn-container {
            text-align: center;
            margin-top: 2rem;
        }
        
        .btn-pular {
            background: transparent;
            color: var(--text-light);
            border: 2px solid #E9ECEF;
            padding: 0.75rem 2rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 500;
            margin-right: 1rem;
            transition: var(--transition);
        }
        
        .btn-pular:hover {
            border-color: var(--text-light);
            color: var(--text-dark);
        }
        
        .progress-bar {
            width: 100%;
            height: 4px;
            background: #E9ECEF;
            border-radius: 2px;
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            width: 100%;
            animation: progress 2s ease-in-out;
        }
        
        @keyframes progress {
            from { width: 0%; }
            to { width: 100%; }
        }
        
        .info-box {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .info-box h3 {
            margin-bottom: 0.5rem;
        }
        
        .info-box p {
            opacity: 0.9;
            margin: 0;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="nav-container">
            <a href="index.php" class="logo">ECO BISTRÔ</a>
            <nav class="nav-menu">
                <span style="color: var(--text-light);">Bem-vindo, <?php echo sanitizar($usuarioLogado['nome_usuario']); ?>!</span>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container" style="min-height: 80vh; padding: 2rem 0;">
        <div class="preferencias-container">
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
            
            <div class="preferencias-header">
                <h1>🍽️ Configure suas Preferências</h1>
                <p>Nos conte um pouco sobre seus gostos e necessidades alimentares para personalizarmos sua experiência!</p>
            </div>
            
            <div class="info-box">
                <h3>🎯 Por que configurar preferências?</h3>
                <p>Com base nas suas preferências, recomendaremos receitas mais adequadas ao seu perfil e estilo de vida.</p>
            </div>
            
            <?php if ($erro): ?>
                <div class="alert alert-error"><?php echo $erro; ?></div>
            <?php endif; ?>
            
            <?php if ($sucesso): ?>
                <div class="alert alert-success"><?php echo $sucesso; ?></div>
            <?php endif; ?>
            
            <form method="POST" id="preferenciasForm">
                <?php 
                $tiposLabels = [
                    'gosto_alimentar' => '😋 Gostos Alimentares',
                    'restricao_alimentar' => '🚫 Restrições Alimentares', 
                    'condicao_medica' => '🏥 Condições Médicas',
                    'preferencia_culinaria' => '👨‍🍳 Preferências Culinárias'
                ];
                
                foreach ($preferenciasPorTipo as $tipo => $preferencias): 
                ?>
                    <div class="tipo-preferencia">
                        <h3><?php echo $tiposLabels[$tipo]; ?></h3>
                        <div class="opcoes-grid">
                            <?php foreach ($preferencias as $pref): ?>
                                <div class="opcao-preferencia">
                                    <input type="checkbox" 
                                           id="pref_<?php echo $pref['id']; ?>" 
                                           name="preferencias[<?php echo $pref['id']; ?>][tipo]" 
                                           value="<?php echo $pref['tipo']; ?>"
                                           data-valor="<?php echo $pref['valor']; ?>">
                                    <label for="pref_<?php echo $pref['id']; ?>">
                                        <span class="icone"><?php echo $pref['icone']; ?></span>
                                        <div class="texto">
                                            <div><?php echo ucfirst(str_replace('_', ' ', $pref['valor'])); ?></div>
                                            <?php if ($pref['descricao']): ?>
                                                <div class="descricao"><?php echo $pref['descricao']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="btn-container">
                    <a href="index.php" class="btn-pular">Pular por enquanto</a>
                    <button type="submit" class="btn btn-primary">
                        Salvar Preferências
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Adicionar valor da preferência ao formulário
        document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const valor = this.dataset.valor;
                const tipo = this.value;
                const id = this.id.replace('pref_', '');
                
                // Criar input hidden para o valor
                let hiddenInput = document.getElementById('valor_' + id);
                if (this.checked) {
                    if (!hiddenInput) {
                        hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'preferencias[' + id + '][valor]';
                        hiddenInput.id = 'valor_' + id;
                        this.parentNode.appendChild(hiddenInput);
                    }
                    hiddenInput.value = valor;
                } else {
                    if (hiddenInput) {
                        hiddenInput.remove();
                    }
                }
            });
        });
        
        // Validação do formulário
        document.getElementById('preferenciasForm').addEventListener('submit', function(e) {
            const checkboxes = document.querySelectorAll('input[type="checkbox"]:checked');
            
            if (checkboxes.length === 0) {
                e.preventDefault();
                alert('Selecione pelo menos uma preferência ou clique em "Pular por enquanto".');
                return;
            }
            
            // Adicionar loading ao botão
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<div class="loading"></div> Salvando...';
            submitBtn.disabled = true;
        });
        
        // Animação de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.preferencias-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                container.style.transition = 'all 0.6s ease';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 200);
        });
    </script>
</body>
</html>
