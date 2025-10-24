<?php
require_once 'config.php';
iniciarSessao();

if (!estaLogado()) {
    header('Location: login.php');
    exit;
}

$usuarioLogado = getUsuarioLogado();
$db = Database::getInstance()->getConnection();
$erro = '';
$sucesso = '';

if ($_POST) {
    $biografia = sanitizar($_POST['biografia'] ?? '');
    $senhaAtual = $_POST['senha_atual'] ?? '';
    $novaSenha = $_POST['nova_senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';
    $removerFoto = isset($_POST['remover_foto']) && $_POST['remover_foto'] == '1';
    
    // Validações básicas
    if (strlen($biografia) > 500) {
        $erro = 'A biografia deve ter no máximo 500 caracteres.';
    } elseif ($novaSenha && strlen($novaSenha) < 6) {
        $erro = 'A nova senha deve ter pelo menos 6 caracteres.';
    } elseif ($novaSenha && $novaSenha !== $confirmarSenha) {
        $erro = 'As senhas não coincidem.';
    } elseif ($novaSenha && !password_verify($senhaAtual, $usuarioLogado['senha'])) {
        $erro = 'Senha atual incorreta.';
    } else {
        // Processar alterações na foto
        $novaFoto = $usuarioLogado['foto_perfil'];
        $fotoAlterada = false;
        
        // Se usuário escolheu remover foto
        if ($removerFoto) {
            if ($usuarioLogado['foto_perfil']) {
                deletarImagem($usuarioLogado['foto_perfil'], 'perfil');
            }
            $novaFoto = null;
            $fotoAlterada = true;
        }
        // Se usuário enviou nova foto
        elseif (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_NO_FILE) {
            $errosUpload = validarImagemUpload($_FILES['foto_perfil']);
            
            if (!empty($errosUpload)) {
                $erro = implode(', ', $errosUpload);
            } else {
                // Fazer upload da nova foto
                $nomeArquivoFoto = uploadImagem($_FILES['foto_perfil'], 'perfil');
                if ($nomeArquivoFoto) {
                    // Deletar foto anterior se existir
                    if ($usuarioLogado['foto_perfil']) {
                        deletarImagem($usuarioLogado['foto_perfil'], 'perfil');
                    }
                    $novaFoto = $nomeArquivoFoto;
                    $fotoAlterada = true;
                } else {
                    $erro = 'Erro ao processar a imagem. Tente novamente.';
                }
            }
        }
        
        // Se não houve erro, atualizar no banco
        if (empty($erro)) {
            try {
                if ($novaSenha) {
                    // Atualizar biografia, senha e foto
                    $novaSenhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE usuarios SET biografia = ?, senha = ?, foto_perfil = ? WHERE id = ?");
                    $stmt->execute([$biografia, $novaSenhaHash, $novaFoto, $usuarioLogado['id']]);
                } else {
                    // Atualizar apenas biografia e foto
                    $stmt = $db->prepare("UPDATE usuarios SET biografia = ?, foto_perfil = ? WHERE id = ?");
                    $stmt->execute([$biografia, $novaFoto, $usuarioLogado['id']]);
                }
                
                $sucesso = 'Perfil atualizado com sucesso!';
                
                // Atualizar dados na sessão/cache para refletir mudanças
                $usuarioLogado['biografia'] = $biografia;
                $usuarioLogado['foto_perfil'] = $novaFoto;
                
                // Recarregar dados do usuário para garantir consistência
                $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
                $stmt->execute([$usuarioLogado['id']]);
                $usuarioLogado = $stmt->fetch();
                
            } catch (Exception $e) {
                $erro = 'Erro ao atualizar perfil. Tente novamente.';
                error_log("Erro ao atualizar perfil: " . $e->getMessage());
                
                // Se houve erro e havia uma nova foto, deletar
                if (isset($nomeArquivoFoto) && $nomeArquivoFoto && $nomeArquivoFoto !== $usuarioLogado['foto_perfil']) {
                    deletarImagem($nomeArquivoFoto, 'perfil');
                }
            }
        }
    }
}

// Função para obter URL da foto com verificação
function getFotoPerfilUrl($foto) {
    if (empty($foto)) {
        return null;
    }
    
    $caminho = "uploads/perfil/" . $foto;
    if (file_exists($caminho)) {
        return $caminho . "?" . filemtime($caminho); // Cache busting
    }
    
    return null;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil - Eco Bistrô</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .foto-upload {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .foto-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--text-dark);
            position: relative;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 3px solid var(--secondary-color);
        }
        
        .foto-preview:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .foto-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .upload-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            color: white;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 600;
            text-align: center;
            padding: 0.5rem;
        }
        
        .foto-preview:hover .upload-overlay {
            display: flex;
        }
        
        .file-input {
            display: none;
        }
        
        .photo-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .btn-photo {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .btn-upload {
            background: var(--primary-color);
            color: var(--text-dark);
        }
        
        .btn-remove {
            background: #dc3545;
            color: white;
        }
        
        .btn-photo:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .foto-info {
            font-size: 0.8rem;
            color: var(--text-light);
            margin-top: 0.5rem;
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

    <!-- Main Content -->
    <div class="container" style="padding: 2rem 0;">
        <div style="max-width: 600px; margin: 0 auto;">
            <div style="text-align: center; margin-bottom: 3rem;">
                <h1 style="margin-bottom: 1rem; color: var(--text-dark);">Editar Perfil</h1>
                <p style="color: var(--text-light);">@<?php echo sanitizar($usuarioLogado['nome_usuario']); ?></p>
            </div>

            <?php if ($erro): ?>
                <div class="alert alert-error" style="margin-bottom: 2rem;"><?php echo $erro; ?></div>
            <?php endif; ?>
            
            <?php if ($sucesso): ?>
                <div class="alert alert-success" style="margin-bottom: 2rem;"><?php echo $sucesso; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="formEditarPerfil" style="background: white; padding: 2rem; border-radius: 20px; box-shadow: var(--shadow);">
                <!-- Foto de perfil -->
                <div class="foto-upload">
                    <div class="foto-preview" onclick="document.getElementById('foto_perfil').click()">
                        <?php 
                        $fotoUrl = getFotoPerfilUrl($usuarioLogado['foto_perfil']);
                        if ($fotoUrl): 
                        ?>
                            <img id="foto-img" src="<?php echo $fotoUrl; ?>" alt="Foto de perfil" onload="this.style.display='block'; document.getElementById('foto-placeholder').style.display='none';" onerror="this.style.display='none'; document.getElementById('foto-placeholder').style.display='flex';">
                            <span id="foto-placeholder" style="display: none;"><?php echo strtoupper(substr($usuarioLogado['nome_usuario'], 0, 1)); ?></span>
                        <?php else: ?>
                            <span id="foto-placeholder"><?php echo strtoupper(substr($usuarioLogado['nome_usuario'], 0, 1)); ?></span>
                            <img id="foto-img" src="" alt="" style="display: none;">
                        <?php endif; ?>
                        <div class="upload-overlay">
                            📷 Clique para alterar
                        </div>
                    </div>
                    
                    <input 
                        type="file" 
                        id="foto_perfil" 
                        name="foto_perfil" 
                        class="file-input"
                        accept="image/jpeg,image/jpg,image/png,image/webp"
                        onchange="previewFoto(this)"
                    >
                    
                    <div class="photo-actions">
                        <button type="button" class="btn-photo btn-upload" onclick="document.getElementById('foto_perfil').click()">
                            📷 Alterar foto
                        </button>
                        <button type="button" class="btn-photo btn-remove" onclick="confirmarRemocaoFoto()" id="btn-remover" <?php echo !$usuarioLogado['foto_perfil'] ? 'style="display: none;"' : ''; ?>>
                            🗑️ Remover
                        </button>
                    </div>
                    
                    <input type="hidden" id="remover_foto" name="remover_foto" value="0">
                    
                    <div class="foto-info">
                        JPG, PNG ou WEBP - Máximo 5MB
                        <?php if ($usuarioLogado['foto_perfil']): ?>
                            <br>Foto atual: <?php echo $usuarioLogado['foto_perfil']; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Informações básicas -->
                <h3 style="margin-bottom: 1.5rem; color: var(--text-dark);">Informações do Perfil</h3>
                
                <div class="form-group">
                    <label for="nome_usuario" class="form-label">Nome de usuário</label>
                    <input type="text" id="nome_usuario" class="form-control" value="<?php echo sanitizar($usuarioLogado['nome_usuario']); ?>" disabled>
                    <small style="color: var(--text-light); font-size: 0.8rem;">
                        O nome de usuário não pode ser alterado
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" id="email" class="form-control" value="<?php echo sanitizar($usuarioLogado['email']); ?>" disabled>
                    <small style="color: var(--text-light); font-size: 0.8rem;">
                        O e-mail não pode ser alterado
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="biografia" class="form-label">Biografia</label>
                    <textarea id="biografia" name="biografia" class="form-control" rows="4" maxlength="500" placeholder="Conte um pouco sobre você e sua paixão pela culinária..."><?php echo htmlspecialchars($usuarioLogado['biografia'] ?? ''); ?></textarea>
                    <small id="contadorBiografia" style="color: var(--text-light); font-size: 0.8rem;">
                        <?php echo strlen($usuarioLogado['biografia'] ?? ''); ?>/500 caracteres
                    </small>
                </div>

                <!-- Alteração de senha -->
                <h3 style="margin: 2rem 0 1.5rem 0; color: var(--text-dark);">Alterar Senha</h3>
                <p style="color: var(--text-light); margin-bottom: 1.5rem; font-size: 0.9rem;">
                    Deixe em branco se não quiser alterar a senha
                </p>
                
                <div class="form-group">
                    <label for="senha_atual" class="form-label">Senha atual</label>
                    <input type="password" id="senha_atual" name="senha_atual" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="nova_senha" class="form-label">Nova senha</label>
                    <input type="password" id="nova_senha" name="nova_senha" class="form-control" minlength="6">
                    <small style="color: var(--text-light); font-size: 0.8rem;">
                        Mínimo 6 caracteres
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="confirmar_senha" class="form-label">Confirmar nova senha</label>
                    <input type="password" id="confirmar_senha" name="confirmar_senha" class="form-control" minlength="6">
                </div>

                <!-- Botões -->
                <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 3rem; flex-wrap: wrap;">
                    <button type="submit" class="btn btn-primary" style="padding: 1rem 2rem;">
                        💾 Salvar Alterações
                    </button>
                    <a href="perfil.php" class="btn btn-secondary" style="padding: 1rem 2rem;">
                        Cancelar
                    </a>
                </div>
            </form>

            <!-- Informações da conta -->
            <div style="background: white; padding: 2rem; border-radius: 20px; box-shadow: var(--shadow); margin-top: 2rem;">
                <h3 style="margin-bottom: 1.5rem; color: var(--text-dark);">Informações da Conta</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <strong>Membro desde:</strong><br>
                        <span style="color: var(--text-light);">
                            <?php echo date('d/m/Y', strtotime($usuarioLogado['data_criacao'])); ?>
                        </span>
                    </div>
                    <div>
                        <strong>Status da conta:</strong><br>
                        <span style="color: var(--text-light);">
                            <?php echo $usuarioLogado['ativo'] ? 'Ativa' : 'Inativa'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let fotoParaRemover = false;
        
        // Preview da foto
        function previewFoto(input) {
            const file = input.files[0];
            const preview = document.getElementById('foto-img');
            const placeholder = document.getElementById('foto-placeholder');
            const removerInput = document.getElementById('remover_foto');
            const btnRemover = document.getElementById('btn-remover');
            
            if (file) {
                // Validar tamanho (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('Arquivo muito grande! Máximo 5MB.');
                    input.value = '';
                    return;
                }
                
                // Validar tipo
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Formato não suportado! Use JPG, PNG ou WEBP.');
                    input.value = '';
                    return;
                }
                
                // Mostrar preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    placeholder.style.display = 'none';
                    removerInput.value = '0'; // Reset flag de remoção
                    btnRemover.style.display = 'inline-block';
                    fotoParaRemover = false;
                };
                reader.readAsDataURL(file);
            }
        }
        
        // Confirmar remoção da foto
        function confirmarRemocaoFoto() {
            if (confirm('Tem certeza que deseja remover a foto de perfil?')) {
                const preview = document.getElementById('foto-img');
                const placeholder = document.getElementById('foto-placeholder');
                const input = document.getElementById('foto_perfil');
                const removerInput = document.getElementById('remover_foto');
                const btnRemover = document.getElementById('btn-remover');
                
                // Resetar preview
                preview.style.display = 'none';
                preview.src = '';
                placeholder.style.display = 'flex';
                input.value = '';
                
                // Marcar para remoção
                removerInput.value = '1';
                btnRemover.style.display = 'none';
                fotoParaRemover = true;
                
                // Feedback visual
                placeholder.style.opacity = '0.5';
                placeholder.innerHTML = '❌ Foto será removida';
                
                setTimeout(() => {
                    placeholder.style.opacity = '1';
                }, 500);
            }
        }

        // Contador de caracteres da biografia
        document.getElementById('biografia').addEventListener('input', function() {
            const contador = document.getElementById('contadorBiografia');
            const atual = this.value.length;
            const maximo = 500;
            
            contador.textContent = `${atual}/${maximo} caracteres`;
            contador.style.color = atual > maximo * 0.9 ? 'var(--accent-color)' : 'var(--text-light)';
        });

        // Validação de senha em tempo real
        document.getElementById('confirmar_senha').addEventListener('input', function() {
            const novaSenha = document.getElementById('nova_senha').value;
            const confirmarSenha = this.value;
            
            if (confirmarSenha && novaSenha !== confirmarSenha) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#E9ECEF';
            }
        });

        // Mostrar/ocultar campos de senha baseado no preenchimento
        document.getElementById('nova_senha').addEventListener('input', function() {
            const senhaAtual = document.getElementById('senha_atual');
            const confirmarSenha = document.getElementById('confirmar_senha');
            
            if (this.value) {
                senhaAtual.required = true;
                confirmarSenha.required = true;
            } else {
                senhaAtual.required = false;
                confirmarSenha.required = false;
                senhaAtual.value = '';
                confirmarSenha.value = '';
            }
        });

        // Validação do formulário antes do envio
        document.getElementById('formEditarPerfil').addEventListener('submit', function(e) {
            const novaSenha = document.getElementById('nova_senha').value;
            const senhaAtual = document.getElementById('senha_atual').value;
            const confirmarSenha = document.getElementById('confirmar_senha').value;
            
            // Se está tentando alterar senha, validar campos
            if (novaSenha) {
                if (!senhaAtual) {
                    e.preventDefault();
                    alert('Para alterar a senha, você deve informar a senha atual.');
                    document.getElementById('senha_atual').focus();
                    return;
                }
                
                if (novaSenha !== confirmarSenha) {
                    e.preventDefault();
                    alert('A confirmação da nova senha não confere.');
                    document.getElementById('confirmar_senha').focus();
                    return;
                }
            }
            
            // Feedback visual de envio
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '⏳ Salvando...';
            submitBtn.disabled = true;
        });

        // Animações
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('#formEditarPerfil');
            form.style.opacity = '0';
            form.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                form.style.transition = 'all 0.6s ease';
                form.style.opacity = '1';
                form.style.transform = 'translateY(0)';
            }, 200);
            
            // Inicializar contador de biografia
            const biografia = document.getElementById('biografia');
            biografia.dispatchEvent(new Event('input'));
        });
    </script>
</body>
</html>