<?php
// cspell:ignore-file
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

$db = Database::getInstance()->getConnection();

// Buscar preferências atuais do usuário
$stmt = $db->prepare("
    SELECT pr.*, tp.descricao, tp.icone 
    FROM preferencias_receitas pr
    JOIN tipos_preferencias tp ON pr.tipo_preferencia = tp.tipo AND pr.valor = tp.valor
    WHERE pr.usuario_id = ? AND pr.ativo = 1
    ORDER BY pr.tipo_preferencia, pr.valor
");
$stmt->execute([$usuarioLogado['id']]);
$preferenciasAtuais = $stmt->fetchAll();

// Buscar tipos de preferências disponíveis
$stmt = $db->query("SELECT * FROM tipos_preferencias WHERE ativo = 1 ORDER BY tipo, valor");
$tiposPreferencias = $stmt->fetchAll();

// Organizar por tipo
$preferenciasPorTipo = [];
foreach ($tiposPreferencias as $pref) {
    $preferenciasPorTipo[$pref['tipo']][] = $pref;
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selecionadas = $_POST['preferencias'] ?? [];
    
    try {
        $db->beginTransaction();
        
        // Limpar preferências existentes
        $stmt = $db->prepare("DELETE FROM preferencias_receitas WHERE usuario_id = ?");
        $stmt->execute([$usuarioLogado['id']]);
        
        // Inserir novas preferências
        if (!empty($selecionadas)) {
            $stmt = $db->prepare("
                INSERT INTO preferencias_receitas (usuario_id, tipo_preferencia, valor, descricao) 
                VALUES (?, ?, ?, ?)
            ");
            
            $stmtDesc = $db->prepare("SELECT descricao FROM tipos_preferencias WHERE valor = ? AND tipo = ?");
            
            foreach ($selecionadas as $item) {
                $parts = explode('|', $item, 2);
                if (count($parts) !== 2) {
                    continue;
                }
                $tipo = $parts[0];
                $valor = $parts[1];
                
                // Buscar descrição da preferência
                $stmtDesc->execute([$valor, $tipo]);
                $descricao = $stmtDesc->fetchColumn();
                
                $stmt->execute([
                    $usuarioLogado['id'],
                    $tipo,
                    $valor,
                    $descricao
                ]);
            }
        }
        
        $db->commit();
        
        $sucesso = 'Preferências atualizadas com sucesso!';
        
        // Recarregar preferências
        $stmt = $db->prepare("
            SELECT pr.*, tp.descricao, tp.icone 
            FROM preferencias_receitas pr
            JOIN tipos_preferencias tp ON pr.tipo_preferencia = tp.tipo AND pr.valor = tp.valor
            WHERE pr.usuario_id = ? AND pr.ativo = 1
            ORDER BY pr.tipo_preferencia, pr.valor
        ");
        $stmt->execute([$usuarioLogado['id']]);
        $preferenciasAtuais = $stmt->fetchAll();
        
    } catch (Exception $e) {
        $db->rollBack();
        $erro = 'Erro ao atualizar preferências. Tente novamente.';
        error_log("Erro ao atualizar preferências: " . $e->getMessage());
    }
}

// Criar array de preferências atuais para facilitar verificação
$preferenciasAtuaisArray = [];
foreach ($preferenciasAtuais as $pref) {
    $preferenciasAtuaisArray[$pref['tipo_preferencia'] . '_' . $pref['valor']] = true;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Preferências - Eco Bistrô</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .preferencias-container {
            max-width: 900px;
            margin: 2rem auto;
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-strong);
        }
        
        .preferencias-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--background-light);
        }
        
        .preferencias-header h1 {
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .preferencias-header p {
            color: white;
            font-size: 1.1rem;
        }
        
        .preferencias-atual {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        
        .preferencias-atual h3 {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .preferencias-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.5rem;
        }
        
        .preferencia-item {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            padding-top: 1rem;
            border-top: 2px solid var(--background-light);
        }
        
        .btn-voltar {
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
        
        .btn-voltar:hover {
            border-color: var(--text-light);
            color: var(--text-dark);
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-light);
        }
        
        .empty-state .icone {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="nav-container">
            <a href="index.php" class="logo">ECO BISTRÔ</a>
            <nav class="nav-menu">
                <a href="perfil.php" class="nav-link">@<?php echo sanitizar($usuarioLogado['nome_usuario']); ?></a>
                <a href="logout.php" class="nav-link">SAIR</a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container" style="min-height: 80vh; padding: 2rem 0;">
        <div class="preferencias-container">
            <div class="preferencias-header">
                <h1>🍽️ Minhas Preferências</h1>
                <p>Gerencie suas preferências alimentares e receba recomendações personalizadas!</p>
            </div>
            
            <?php if ($erro): ?>
                <div class="alert alert-error"><?php echo $erro; ?></div>
            <?php endif; ?>
            
            <?php if ($sucesso): ?>
                <div class="alert alert-success"><?php echo $sucesso; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($preferenciasAtuais)): ?>
                <div class="preferencias-atual">
                    <h3>✅ Preferências Atuais</h3>
                    <div class="preferencias-grid">
                        <?php foreach ($preferenciasAtuais as $pref): ?>
                            <div class="preferencia-item">
                                <span><?php echo $pref['icone']; ?></span>
                                <span><?php echo ucfirst(str_replace('_', ' ', $pref['valor'])); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="icone">🍽️</div>
                    <h3>Nenhuma preferência configurada</h3>
                    <p>Configure suas preferências para receber recomendações personalizadas!</p>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="preferenciasForm">
                <?php
                $tiposLabels = [
                    'gosto_alimentar' => '😋 Gostos Alimentares',
                    'restricao_alimentar' => '🚫 Restrições Alimentares', 
                    'condicao_medica' => '🏥 Condições Médicas',
                    'preferencia_culinaria' => '👨‍🍳 Preferências Culinárias'
                ];
                
                foreach ($preferenciasPorTipo as $tipo => $prefs): ?>
                    <div class="tipo-preferencia">
                        <h3><?php echo htmlspecialchars($tiposLabels[$tipo] ?? ucfirst(str_replace('_', ' ', $tipo)), ENT_QUOTES, 'UTF-8'); ?></h3>
                        <div class="opcoes-grid">
                            <?php foreach ($prefs as $pref):
                                $inputId = 'pref_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $pref['tipo'] . '_' . $pref['valor']);
                                $key = $pref['tipo'] . '_' . $pref['valor'];
                                $isChecked = isset($preferenciasAtuaisArray[$key]);
                            ?>
                            <div class="opcao-preferencia">
                                <input type="checkbox"
                                       id="<?php echo $inputId; ?>"
                                       name="preferencias[]"
                                       value="<?php echo htmlspecialchars($pref['tipo'] . '|' . $pref['valor'], ENT_QUOTES, 'UTF-8'); ?>"
                                       <?php if ($isChecked) echo 'checked="checked"'; ?>>
                                <label for="<?php echo $inputId; ?>">
                                    <span class="icone"><?php echo htmlspecialchars($pref['icone'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <div class="texto">
                                        <div><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $pref['valor'])), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php if (!empty($pref['descricao'])): ?>
                                            <div class="descricao"><?php echo htmlspecialchars($pref['descricao'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="btn-container">
                    <a href="perfil.php" class="btn-voltar">Voltar ao Perfil</a>
                    <button type="submit" class="btn btn-primary">Atualizar Preferências</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('preferenciasForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.innerHTML = '<div class="loading"></div> Atualizando...';
                        submitBtn.disabled = true;
                    }
                });
            }

            const container = document.querySelector('.preferencias-container');
            if (container) {
                container.style.opacity = '0';
                container.style.transform = 'translateY(30px)';

                setTimeout(() => {
                    container.style.transition = 'all 0.6s ease';
                    container.style.opacity = '1';
                    container.style.transform = 'translateY(0)';
                }, 200);
            }
        });
    </script>
</body>
</html>