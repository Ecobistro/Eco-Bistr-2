<?php
require_once 'config.php';
iniciarSessao();

// Redirecionar se já estiver logado
if (estaLogado()) {
    header('Location: index.php');
    exit;
}

$erro = '';
$sucesso = '';

if ($_POST) {
    $nomeUsuario = sanitizar($_POST['nome_usuario'] ?? '');
    $email = sanitizar($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';
    $biografia = sanitizar($_POST['biografia'] ?? 'Apaixonado por culinária sustentável!');
    
    // Validações básicas
    if (empty($nomeUsuario) || empty($email) || empty($senha) || empty($confirmarSenha)) {
        $erro = 'Por favor, preencha todos os campos obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Por favor, insira um e-mail válido.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($senha !== $confirmarSenha) {
        $erro = 'As senhas não coincidem.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $nomeUsuario)) {
        $erro = 'Nome de usuário deve conter apenas letras, números e underscore.';
    } elseif (strlen($nomeUsuario) < 3 || strlen($nomeUsuario) > 20) {
        $erro = 'Nome de usuário deve ter entre 3 e 20 caracteres.';
    } else {
        // Validar foto de perfil se enviada
        $nomeArquivoFoto = null;
        if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_NO_FILE) {
            $errosUpload = validarImagemUpload($_FILES['foto_perfil']);
            
            if (!empty($errosUpload)) {
                $erro = implode(', ', $errosUpload);
            } else {
                // Fazer upload da foto
                $nomeArquivoFoto = uploadImagem($_FILES['foto_perfil'], 'perfil');
                if (!$nomeArquivoFoto) {
                    $erro = 'Erro ao processar a imagem. Tente novamente.';
                }
            }
        }
        
        // Se não houve erro com a imagem, continuar com o cadastro
        if (empty($erro)) {
            $db = Database::getInstance()->getConnection();
            
            // Verificar se usuário já existe
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE nome_usuario = ? OR email = ?");
            $stmt->execute([$nomeUsuario, $email]);
            
            if ($stmt->fetch()) {
                $erro = 'Nome de usuário ou e-mail já está em uso.';
                
                // Se houve erro, deletar foto enviada
                if ($nomeArquivoFoto) {
                    deletarImagem($nomeArquivoFoto, 'perfil');
                }
            } else {
                // Criar usuário
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                
                try {
                    $stmt = $db->prepare("
                        INSERT INTO usuarios (nome_usuario, email, senha, biografia, foto_perfil) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $nomeUsuario, 
                        $email, 
                        $senhaHash, 
                        $biografia,
                        $nomeArquivoFoto
                    ]);
                    
                    $usuarioId = $db->lastInsertId();
                    
                    // Fazer login automaticamente
                    $_SESSION['usuario_id'] = $usuarioId;
                    $_SESSION['nome_usuario'] = $nomeUsuario;
                    
                    $sucesso = 'Conta criada com sucesso! Redirecionando para configuração de preferências...';
                    
                    // Redirecionar para configuração de preferências após 2 segundos
                    header("refresh:2;url=configurar-preferencias.php");
                    
                } catch (Exception $e) {
                    $erro = 'Erro ao criar conta. Tente novamente.';
                    
                    // Deletar foto se houve erro no cadastro
                    if ($nomeArquivoFoto) {
                        deletarImagem($nomeArquivoFoto, 'perfil');
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - Eco Bistrô</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .foto-upload {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .foto-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            background-color: rgba(255, 255, 255, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: rgba(110, 186, 90, 1);
            position: relative;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .foto-preview:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
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
        }
        
        .foto-preview:hover .upload-overlay {
            display: flex;
        }
        
        .file-input {
            display: none;
        }
        
        .upload-instructions {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-bottom: 1rem;
        }
        
        .remove-foto {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.8rem;
            cursor: pointer;
            margin-top: 0.5rem;
            display: none;
        }
        
        .remove-foto:hover {
            background: #c82333;
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
                <a href="login.php" class="nav-link">LOGIN</a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container" style="min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 2rem 0;">
        <div class="form-container">
            <h2 style="text-align: center; margin-bottom: 2rem; color: var(--text-dark);">CADASTRO</h2>
            
            <?php if ($erro): ?>
                <div class="alert alert-error"><?php echo $erro; ?></div>
            <?php endif; ?>
            
            <?php if ($sucesso): ?>
                <div class="alert alert-success"><?php echo $sucesso; ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" id="cadastroForm">
                <!-- Foto de perfil -->
                <div class="foto-upload">
                    <div class="foto-preview" onclick="document.getElementById('foto_perfil').click()">
                        <span id="foto-placeholder"><?php echo strtoupper(substr($nomeUsuario ?: 'U', 0, 1)); ?></span>
                        <img id="foto-img" src="" alt="" style="display: none;">
                        <div class="upload-overlay">
                            📷 Clique para escolher
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
                    <div class="upload-instructions">
                        <strong>Foto de perfil (opcional)</strong><br>
                        Clique na imagem para escolher uma foto<br>
                        <small>JPG, PNG ou WEBP - Máximo 5MB</small>
                    </div>
                    <button type="button" class="remove-foto" id="remove-foto" onclick="removerFoto()">
                        🗑️ Remover foto
                    </button>
                </div>
                
                <div class="form-group">
                    <label for="nome_usuario" class="form-label">Nome de usuário *</label>
                    <input 
                        type="text" 
                        id="nome_usuario" 
                        name="nome_usuario" 
                        class="form-control" 
                        value="<?php echo htmlspecialchars($nomeUsuario ?? ''); ?>"
                        placeholder="Ex: maria_silva"
                        pattern="[a-zA-Z0-9_]{3,20}"
                        title="3-20 caracteres: letras, números e underscore"
                        required
                        onchange="atualizarPlaceholder()"
                    >
                    <small style="color: var(--text-light); font-size: 0.8rem;">
                        3-20 caracteres: apenas letras, números e underscore
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="email" class="form-label">E-mail *</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-control" 
                        value="<?php echo htmlspecialchars($email ?? ''); ?>"
                        placeholder="seu@email.com"
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label for="biografia" class="form-label">Biografia</label>
                    <textarea 
                        id="biografia" 
                        name="biografia" 
                        class="form-control" 
                        rows="3"
                        maxlength="200"
                        placeholder="Conte um pouco sobre sua paixão pela culinária..."
                    ><?php echo htmlspecialchars($biografia ?? ''); ?></textarea>
                    <small id="contador-biografia" style="color: var(--text-light); font-size: 0.8rem;">
                        0/200 caracteres
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="senha" class="form-label">Senha *</label>
                    <input 
                        type="password" 
                        id="senha" 
                        name="senha" 
                        class="form-control" 
                        minlength="6"
                        placeholder="Mínimo 6 caracteres"
                        required
                    >
                    <small style="color: var(--text-light); font-size: 0.8rem;">
                        Mínimo 6 caracteres
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="confirmar_senha" class="form-label">Confirmar senha *</label>
                    <input 
                        type="password" 
                        id="confirmar_senha" 
                        name="confirmar_senha" 
                        class="form-control" 
                        minlength="6"
                        placeholder="Digite a senha novamente"
                        required
                    >
                </div>
                <button type="submit" class="btn-form" style="width: 100%; margin-bottom: 1rem;">
                    Criar Conta
                </button>
                
                <div style="text-align: center;">
                    <p>Já tem conta? <a href="login.php" style="color: var(--accent-color); text-decoration: none; font-weight: 600;">Faça login</a></p>
                </div>
            </form>
        </div>
    </div>

    <!-- Elementos visuais de fundo -->
    <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: -1; opacity: 0.1;">
        <div style="position: absolute; top: 15%; right: 10%; width: 120px; height: 120px; background: var(--primary-color); border-radius: 50%; opacity: 0.6;"></div>
        <div style="position: absolute; bottom: 25%; left: 15%; width: 200px; height: 200px; background: var(--secondary-color); border-radius: 50%; opacity: 0.4;"></div>
        <div style="position: absolute; top: 60%; right: 80%; width: 90px; height: 90px; background: var(--accent-color); border-radius: 50%; opacity: 0.5;"></div>
        <div style="position: absolute; top: 30%; left: 5%; width: 60px; height: 60px; background: #C7CEEA; border-radius: 50%; opacity: 0.3;"></div>
        <div style="position: absolute; bottom: 10%; right: 5%; width: 80px; height: 80px; background: #FFB7B2; border-radius: 50%; opacity: 0.4;"></div>
    </div>

    <script>
        // Preview da foto
        function previewFoto(input) {
            const file = input.files[0];
            const preview = document.getElementById('foto-img');
            const placeholder = document.getElementById('foto-placeholder');
            const removeBtn = document.getElementById('remove-foto');
            
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
                    removeBtn.style.display = 'inline-block';
                };
                reader.readAsDataURL(file);
            }
        }
        
        // Remover foto
        function removerFoto() {
            const input = document.getElementById('foto_perfil');
            const preview = document.getElementById('foto-img');
            const placeholder = document.getElementById('foto-placeholder');
            const removeBtn = document.getElementById('remove-foto');
            
            input.value = '';
            preview.style.display = 'none';
            preview.src = '';
            placeholder.style.display = 'block';
            removeBtn.style.display = 'none';
        }
        
        // Atualizar placeholder com primeira letra do nome
        function atualizarPlaceholder() {
            const nomeUsuario = document.getElementById('nome_usuario').value;
            const placeholder = document.getElementById('foto-placeholder');
            const preview = document.getElementById('foto-img');
            
            if (preview.style.display === 'none') {
                placeholder.textContent = nomeUsuario ? nomeUsuario[0].toUpperCase() : 'U';
            }
        }
        
        // Contador de caracteres da biografia
        document.getElementById('biografia').addEventListener('input', function() {
            const contador = document.getElementById('contador-biografia');
            const atual = this.value.length;
            const maximo = 200;
            
            contador.textContent = `${atual}/${maximo} caracteres`;
            contador.style.color = atual > maximo * 0.9 ? 'var(--accent-color)' : 'var(--text-light)';
        });
        
        // Validação do formulário
        document.getElementById('cadastroForm').addEventListener('submit', function(e) {
            const nomeUsuario = document.getElementById('nome_usuario').value.trim();
            const email = document.getElementById('email').value.trim();
            const senha = document.getElementById('senha').value;
            const confirmarSenha = document.getElementById('confirmar_senha').value;
            
            // Validações básicas
            if (!nomeUsuario || !email || !senha || !confirmarSenha) {
                e.preventDefault();
                alert('Por favor, preencha todos os campos obrigatórios.');
                return;
            }
            
            if (senha !== confirmarSenha) {
                e.preventDefault();
                alert('As senhas não coincidem.');
                return;
            }
            
            if (senha.length < 6) {
                e.preventDefault();
                alert('A senha deve ter pelo menos 6 caracteres.');
                return;
            }
            
            if (!/^[a-zA-Z0-9_]+$/.test(nomeUsuario)) {
                e.preventDefault();
                alert('Nome de usuário deve conter apenas letras, números e underscore.');
                return;
            }
            
            // Adicionar loading ao botão
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<div class="loading"></div> Criando conta...';
            submitBtn.disabled = true;
        });

        // Validação de senha em tempo real
        document.getElementById('confirmar_senha').addEventListener('input', function() {
            const senha = document.getElementById('senha').value;
            const confirmarSenha = this.value;
            
            if (confirmarSenha && senha !== confirmarSenha) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#E9ECEF';
            }
        });

        // Validação de nome de usuário
        document.getElementById('nome_usuario').addEventListener('input', function() {
            const valor = this.value;
            const valido = /^[a-zA-Z0-9_]*$/.test(valor);
            
            if (valor && !valido) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#E9ECEF';
            }
            
            atualizarPlaceholder();
        });

        // Animação de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const formContainer = document.querySelector('.form-container');
            formContainer.style.opacity = '0';
            formContainer.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                formContainer.style.transition = 'all 0.6s ease';
                formContainer.style.opacity = '1';
                formContainer.style.transform = 'translateY(0)';
            }, 200);
            
            // Inicializar contador de biografia
            const biografia = document.getElementById('biografia');
            biografia.dispatchEvent(new Event('input'));
        });

        // Focar no primeiro campo
        document.getElementById('nome_usuario').focus();
    </script>
</body>
</html>