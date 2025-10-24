<?php
require_once 'config.php';
iniciarSessao();

if (!estaLogado()) {
    echo "ERRO: Usuário não está logado. Faça login primeiro.";
    exit;
}

$usuarioLogado = getUsuarioLogado();
$db = Database::getInstance()->getConnection();

echo "<h1>Diagnóstico do Sistema - Eco Bistrô</h1>";
echo "<h2>Status do Usuário</h2>";
echo "Usuário logado: " . $usuarioLogado['nome_usuario'] . " (ID: " . $usuarioLogado['id'] . ")<br>";

echo "<h2>Verificação das Tabelas</h2>";

// Verificar se tabelas existem
$tabelas = ['usuarios', 'receitas', 'pastas', 'pasta_receitas', 'favoritos', 'curtidas', 'comentarios', 'seguidores', 'categorias'];

foreach ($tabelas as $tabela) {
    try {
        $stmt = $db->query("SELECT COUNT(*) as total FROM $tabela");
        $total = $stmt->fetch()['total'];
        echo "✓ Tabela '$tabela': $total registros<br>";
    } catch (Exception $e) {
        echo "✗ ERRO na tabela '$tabela': " . $e->getMessage() . "<br>";
    }
}

echo "<h2>Estrutura da Tabela pasta_receitas</h2>";
try {
    $stmt = $db->query("DESCRIBE pasta_receitas");
    $colunas = $stmt->fetchAll();
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Chave</th><th>Padrão</th><th>Extra</th></tr>";
    foreach ($colunas as $coluna) {
        echo "<tr>";
        echo "<td>" . $coluna['Field'] . "</td>";
        echo "<td>" . $coluna['Type'] . "</td>";
        echo "<td>" . $coluna['Null'] . "</td>";
        echo "<td>" . $coluna['Key'] . "</td>";
        echo "<td>" . $coluna['Default'] . "</td>";
        echo "<td>" . $coluna['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "ERRO ao verificar estrutura: " . $e->getMessage();
}

echo "<h2>Dados de Exemplo</h2>";

// Mostrar receitas disponíveis
echo "<h3>Receitas Disponíveis:</h3>";
try {
    $stmt = $db->query("SELECT id, titulo FROM receitas WHERE ativo = 1 LIMIT 5");
    $receitas = $stmt->fetchAll();
    if (empty($receitas)) {
        echo "Nenhuma receita encontrada. <a href='#' onclick='criarReceitaTeste()'>Criar receita de teste</a><br>";
    } else {
        foreach ($receitas as $receita) {
            echo "ID: " . $receita['id'] . " - " . $receita['titulo'] . "<br>";
        }
    }
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage();
}

// Mostrar pastas do usuário
echo "<h3>Minhas Pastas:</h3>";
try {
    $stmt = $db->prepare("SELECT id, nome FROM pastas WHERE usuario_id = ?");
    $stmt->execute([$usuarioLogado['id']]);
    $pastas = $stmt->fetchAll();
    if (empty($pastas)) {
        echo "Nenhuma pasta encontrada.<br>";
    } else {
        foreach ($pastas as $pasta) {
            echo "ID: " . $pasta['id'] . " - " . $pasta['nome'] . "<br>";
        }
    }
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage();
}

echo "<h2>Arquivos PHP Necessários</h2>";

$arquivos = [
    'ajax/criar-pasta.php',
    'ajax/editar-pasta.php', 
    'ajax/deletar-pasta.php',
    'ajax/adicionar-pasta.php',
    'ajax/remover-pasta.php',
    'ajax/listar-pastas.php',
    'ajax/favoritar.php',
    'ajax/curtir.php',
    'ajax/seguir.php',
    'ajax/editar-comentario.php',
    'ajax/excluir-comentario.php'
];

foreach ($arquivos as $arquivo) {
    if (file_exists($arquivo)) {
        echo "✓ $arquivo existe<br>";
    } else {
        echo "✗ $arquivo NÃO existe<br>";
    }
}

echo "<h2>Teste de Conectividade AJAX</h2>";
?>

<script>
// Função para criar receita de teste
async function criarReceitaTeste() {
    const resposta = confirm('Deseja criar uma receita de teste?');
    if (!resposta) return;
    
    try {
        const response = await fetch('?acao=criar_receita_teste');
        const result = await response.text();
        alert('Resultado: ' + result);
        location.reload();
    } catch (error) {
        alert('Erro: ' + error.message);
    }
}

// Teste básico de conectividade
async function testarAjax() {
    const resultado = document.getElementById('resultado-ajax');
    resultado.innerHTML = 'Testando...';
    
    try {
        const response = await fetch('ajax/listar-pastas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({})
        });
        
        const result = await response.json();
        resultado.innerHTML = 'Teste AJAX: ' + (result.success ? 'SUCESSO' : 'FALHA - ' + result.message);
        resultado.style.color = result.success ? 'green' : 'red';
    } catch (error) {
        resultado.innerHTML = 'ERRO: ' + error.message;
        resultado.style.color = 'red';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    testarAjax();
});
</script>

<button onclick="testarAjax()">Testar Conectividade AJAX</button>
<div id="resultado-ajax" style="margin: 10px 0; font-weight: bold;"></div>

<?php
// Processar ações especiais
if (isset($_GET['acao']) && $_GET['acao'] == 'criar_receita_teste') {
    try {
        // Verificar se já existe receita de teste
        $stmt = $db->prepare("SELECT id FROM receitas WHERE titulo = 'Receita de Teste' AND usuario_id = ?");
        $stmt->execute([$usuarioLogado['id']]);
        
        if (!$stmt->fetch()) {
            $stmt = $db->prepare("
                INSERT INTO receitas (titulo, descricao, tempo_preparo, porcoes, usuario_id, categoria_id, ingredientes, modo_preparo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                'Receita de Teste',
                'Esta é uma receita criada automaticamente para testes',
                30,
                4,
                $usuarioLogado['id'],
                1, // Primeira categoria
                'Ingrediente 1;Ingrediente 2;Ingrediente 3',
                'Passo 1;Passo 2;Passo 3'
            ]);
            echo "Receita de teste criada com sucesso! ID: " . $db->lastInsertId();
        } else {
            echo "Receita de teste já existe!";
        }
    } catch (Exception $e) {
        echo "Erro ao criar receita de teste: " . $e->getMessage();
    }
    exit;
}
?>

<h2>Problemas Mais Comuns e Soluções</h2>
<ol>
    <li><strong>Arquivos PHP não encontrados:</strong> Certifique-se de que todos os arquivos AJAX estão na pasta 'ajax/' do seu projeto</li>
    <li><strong>Erro de permissão:</strong> Verifique se as pastas têm permissão de escrita</li>
    <li><strong>Erro de banco de dados:</strong> Execute o script SQL completo para criar todas as tabelas</li>
    <li><strong>Sessão não iniciada:</strong> Certifique-se de que está logado no sistema</li>
    <li><strong>Caminho incorreto:</strong> Verifique se os caminhos dos arquivos estão corretos</li>
</ol>