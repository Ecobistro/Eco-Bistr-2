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
    $usuario = sanitizar($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';
    
    if (empty($usuario) || empty($senha)) {
        $erro = 'Por favor, preencha todos os campos.';
    } else {
        $db = Database::getInstance()->getConnection();
        
        // Buscar usuário por nome de usuário ou email
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE (nome_usuario = ? OR email = ?) AND ativo = 1");
        $stmt->execute([$usuario, $usuario]);
        $usuarioDb = $stmt->fetch();
        
        if ($usuarioDb && password_verify($senha, $usuarioDb['senha'])) {
            $_SESSION['usuario_id'] = $usuarioDb['id'];
            $_SESSION['nome_usuario'] = $usuarioDb['nome_usuario'];
            
            // Redirecionar para a página desejada ou home
            $redirect = $_GET['redirect'] ?? 'index.php';
            header('Location: ' . $redirect);
            exit;
        } else {
            $erro = 'Usuário ou senha incorretos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Eco Bistrô</title>
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
                <a href="cadastro.php" class="btn btn-primary">CADASTRE-SE</a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container" style="min-height: 80vh; display: flex; align-items: center; justify-content: center;">
        <div class="form-container">
            <h2 style="text-align: center; margin-bottom: 2rem; color: var(--text-dark);">LOGIN</h2>
            
            <?php if ($erro): ?>
                <div class="alert alert-error"><?php echo $erro; ?></div>
            <?php endif; ?>
            
            <?php if ($sucesso): ?>
                <div class="alert alert-success"><?php echo $sucesso; ?></div>
            <?php endif; ?>
            
            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label for="usuario" class="form-label">Nome de usuário ou e-mail</label>
                    <input 
                        type="text" 
                        id="usuario" 
                        name="usuario" 
                        class="form-control" 
                        value="<?php echo htmlspecialchars($usuario ?? ''); ?>"
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label for="senha" class="form-label">Senha</label>
                    <input type="password" id="senha" name="senha" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-bottom: 1rem;">
                    Entrar
                </button>
                
                <div style="text-align: center;">
                    <p>Não tem conta? <a href="cadastro.php" style="color: var(--accent-color); text-decoration: none; font-weight: 600;">Faça cadastro</a></p>
                </div>
            </form>
        </div>
    </div>

    <!-- Visual adicional inspirado no PDF -->
    <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: -1; opacity: 0.1;">
        <div style="position: absolute; top: 10%; left: 10%; width: 100px; height: 100px; background: var(--primary-color); border-radius: 50%; opacity: 0.5;"></div>
        <div style="position: absolute; bottom: 20%; right: 15%; width: 150px; height: 150px; background: var(--secondary-color); border-radius: 50%; opacity: 0.3;"></div>
        <div style="position: absolute; top: 50%; left: 80%; width: 80px; height: 80px; background: var(--accent-color); border-radius: 50%; opacity: 0.4;"></div>
    </div>

    <script>
        // Validação do formulário
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const usuario = document.getElementById('usuario').value.trim();
            const senha = document.getElementById('senha').value;
            
            if (!usuario || !senha) {
                e.preventDefault();
                alert('Por favor, preencha todos os campos.');
                return;
            }
            
            // Adicionar loading ao botão
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<div class="loading"></div> Entrando...';
            submitBtn.disabled = true;
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
        });

        // Focar no primeiro campo
        document.getElementById('usuario').focus();
    </script>
</body>
</html>