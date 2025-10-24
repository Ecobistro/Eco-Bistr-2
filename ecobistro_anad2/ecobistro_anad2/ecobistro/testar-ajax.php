<?php
require_once 'config.php';
iniciarSessao();

// Verificar se usuário está logado
if (!estaLogado()) {
    echo "ERRO: Usuário não está logado. Faça login primeiro.";
    exit;
}

$usuarioLogado = getUsuarioLogado();
$db = Database::getInstance()->getConnection();

echo "<h1>🧪 Teste de Funcionalidades AJAX - Eco Bistrô</h1>";
echo "<h2>Usuário logado: " . $usuarioLogado['nome_usuario'] . " (ID: " . $usuarioLogado['id'] . ")</h2>";

// Buscar uma receita para testar
$stmt = $db->query("SELECT id, titulo FROM receitas WHERE ativo = 1 LIMIT 1");
$receitaTeste = $stmt->fetch();

if (!$receitaTeste) {
    echo "<p style='color: red;'>❌ Nenhuma receita encontrada. Crie uma receita primeiro para testar as funcionalidades.</p>";
    echo "<a href='nova-receita.php' class='btn btn-primary'>Criar Receita de Teste</a>";
    exit;
}

echo "<p>✅ Receita de teste: <strong>" . $receitaTeste['titulo'] . "</strong> (ID: " . $receitaTeste['id'] . ")</p>";

?>

<div style="max-width: 800px; margin: 0 auto; padding: 2rem;">
    
    <h3>1. 👍 Teste de Curtidas</h3>
    <div style="background: #f8f9fa; padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
        <p>Testando sistema de curtidas na receita: <strong><?php echo $receitaTeste['titulo']; ?></strong></p>
        <button id="testar-curtida" class="btn btn-primary">Testar Curtida</button>
        <div id="resultado-curtida" style="margin-top: 1rem; padding: 0.5rem; border-radius: 5px;"></div>
    </div>

    <h3>2. ❤️ Teste de Favoritos</h3>
    <div style="background: #f8f9fa; padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
        <p>Testando sistema de favoritos na receita: <strong><?php echo $receitaTeste['titulo']; ?></strong></p>
        <button id="testar-favorito" class="btn btn-primary">Testar Favorito</button>
        <div id="resultado-favorito" style="margin-top: 1rem; padding: 0.5rem; border-radius: 5px;"></div>
    </div>

    <h3>3. 💬 Teste de Comentários</h3>
    <div style="background: #f8f9fa; padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
        <p>Testando sistema de comentários na receita: <strong><?php echo $receitaTeste['titulo']; ?></strong></p>
        <textarea id="comentario-teste" placeholder="Digite um comentário de teste..." style="width: 100%; padding: 0.5rem; margin-bottom: 0.5rem; border-radius: 5px; border: 1px solid #ddd;"></textarea>
        <button id="testar-comentario" class="btn btn-primary">Testar Comentário</button>
        <div id="resultado-comentario" style="margin-top: 1rem; padding: 0.5rem; border-radius: 5px;"></div>
    </div>

    <h3>4. 👥 Teste de Seguidores</h3>
    <div style="background: #f8f9fa; padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
        <p>Testando sistema de seguidores (seguir outro usuário)</p>
        <select id="usuario-seguir" style="width: 100%; padding: 0.5rem; margin-bottom: 0.5rem; border-radius: 5px; border: 1px solid #ddd;">
            <option value="">Selecione um usuário para seguir</option>
            <?php
            $stmt = $db->prepare("SELECT id, nome_usuario FROM usuarios WHERE id != ? AND ativo = 1 LIMIT 5");
            $stmt->execute([$usuarioLogado['id']]);
            $outrosUsuarios = $stmt->fetchAll();
            foreach ($outrosUsuarios as $usuario) {
                echo "<option value='" . $usuario['id'] . "'>" . $usuario['nome_usuario'] . "</option>";
            }
            ?>
        </select>
        <button id="testar-seguir" class="btn btn-primary">Testar Seguir</button>
        <div id="resultado-seguir" style="margin-top: 1rem; padding: 0.5rem; border-radius: 5px;"></div>
    </div>

    <h3>5. 📁 Teste de Pastas</h3>
    <div style="background: #f8f9fa; padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
        <p>Testando sistema de pastas</p>
        <input type="text" id="nome-pasta" placeholder="Nome da pasta de teste" style="width: 100%; padding: 0.5rem; margin-bottom: 0.5rem; border-radius: 5px; border: 1px solid #ddd;">
        <button id="testar-criar-pasta" class="btn btn-primary">Criar Pasta</button>
        <div id="resultado-pasta" style="margin-top: 1rem; padding: 0.5rem; border-radius: 5px;"></div>
    </div>

    <h3>📊 Status Atual</h3>
    <div style="background: #e9ecef; padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
        <div id="status-atual">
            <p>Carregando status...</p>
        </div>
    </div>

    <div style="text-align: center; margin-top: 2rem;">
        <a href="index.php" class="btn btn-secondary">Voltar ao Início</a>
        <a href="testar-funcionalidades.php" class="btn btn-primary">Ver Teste Completo</a>
    </div>

</div>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f8f9fa;
    margin: 0;
    padding: 2rem;
}

h1, h2, h3 {
    color: #2c3e50;
}

.btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    margin: 0.25rem;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn:hover {
    opacity: 0.8;
}

.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.info {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}
</style>

<script>
const receitaId = <?php echo $receitaTeste['id']; ?>;

// Função para fazer requisições AJAX
async function fazerRequisicao(url, dados) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(dados)
        });
        
        const resultado = await response.json();
        return resultado;
    } catch (error) {
        return { success: false, message: 'Erro de conexão: ' + error.message };
    }
}

// Teste de curtidas
document.getElementById('testar-curtida').addEventListener('click', async function() {
    const resultado = document.getElementById('resultado-curtida');
    resultado.innerHTML = 'Testando...';
    
    const resposta = await fazerRequisicao('ajax/curtir.php', { receita_id: receitaId });
    
    if (resposta.success) {
        resultado.innerHTML = `<div class="success">✅ ${resposta.message}</div>`;
    } else {
        resultado.innerHTML = `<div class="error">❌ ${resposta.message}</div>`;
    }
    
    atualizarStatus();
});

// Teste de favoritos
document.getElementById('testar-favorito').addEventListener('click', async function() {
    const resultado = document.getElementById('resultado-favorito');
    resultado.innerHTML = 'Testando...';
    
    const resposta = await fazerRequisicao('ajax/favoritar.php', { receita_id: receitaId });
    
    if (resposta.success) {
        resultado.innerHTML = `<div class="success">✅ ${resposta.message}</div>`;
    } else {
        resultado.innerHTML = `<div class="error">❌ ${resposta.message}</div>`;
    }
    
    atualizarStatus();
});

// Teste de comentários
document.getElementById('testar-comentario').addEventListener('click', async function() {
    const resultado = document.getElementById('resultado-comentario');
    const comentario = document.getElementById('comentario-teste').value;
    
    if (!comentario.trim()) {
        resultado.innerHTML = `<div class="error">❌ Digite um comentário primeiro</div>`;
        return;
    }
    
    resultado.innerHTML = 'Testando...';
    
    const resposta = await fazerRequisicao('ajax/editar-comentario.php', { 
        comentario_id: 0, // Novo comentário
        comentario: comentario 
    });
    
    if (resposta.success) {
        resultado.innerHTML = `<div class="success">✅ ${resposta.message}</div>`;
        document.getElementById('comentario-teste').value = '';
    } else {
        resultado.innerHTML = `<div class="error">❌ ${resposta.message}</div>`;
    }
    
    atualizarStatus();
});

// Teste de seguidores
document.getElementById('testar-seguir').addEventListener('click', async function() {
    const resultado = document.getElementById('resultado-seguir');
    const usuarioId = document.getElementById('usuario-seguir').value;
    
    if (!usuarioId) {
        resultado.innerHTML = `<div class="error">❌ Selecione um usuário primeiro</div>`;
        return;
    }
    
    resultado.innerHTML = 'Testando...';
    
    const resposta = await fazerRequisicao('ajax/seguir.php', { usuario_id: usuarioId });
    
    if (resposta.success) {
        resultado.innerHTML = `<div class="success">✅ ${resposta.message}</div>`;
    } else {
        resultado.innerHTML = `<div class="error">❌ ${resposta.message}</div>`;
    }
    
    atualizarStatus();
});

// Teste de pastas
document.getElementById('testar-criar-pasta').addEventListener('click', async function() {
    const resultado = document.getElementById('resultado-pasta');
    const nomePasta = document.getElementById('nome-pasta').value;
    
    if (!nomePasta.trim()) {
        resultado.innerHTML = `<div class="error">❌ Digite um nome para a pasta</div>`;
        return;
    }
    
    resultado.innerHTML = 'Testando...';
    
    const resposta = await fazerRequisicao('ajax/criar-pasta.php', { nome: nomePasta });
    
    if (resposta.success) {
        resultado.innerHTML = `<div class="success">✅ ${resposta.message}</div>`;
        document.getElementById('nome-pasta').value = '';
    } else {
        resultado.innerHTML = `<div class="error">❌ ${resposta.message}</div>`;
    }
    
    atualizarStatus();
});

// Função para atualizar status
async function atualizarStatus() {
    const statusDiv = document.getElementById('status-atual');
    statusDiv.innerHTML = 'Atualizando status...';
    
    try {
        const response = await fetch('ajax/listar-pastas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({})
        });
        
        const resultado = await response.json();
        
        if (resultado.success) {
            statusDiv.innerHTML = `
                <div class="info">
                    <h4>Status Atual:</h4>
                    <p>✅ Sistema AJAX funcionando</p>
                    <p>📁 Pastas: ${resultado.pastas ? resultado.pastas.length : 0}</p>
                    <p>🍽️ Receita de teste: ${receitaId}</p>
                </div>
            `;
        } else {
            statusDiv.innerHTML = `<div class="error">❌ Erro ao carregar status: ${resultado.message}</div>`;
        }
    } catch (error) {
        statusDiv.innerHTML = `<div class="error">❌ Erro de conexão: ${error.message}</div>`;
    }
}

// Carregar status inicial
document.addEventListener('DOMContentLoaded', function() {
    atualizarStatus();
});
</script>
