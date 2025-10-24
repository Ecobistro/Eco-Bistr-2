<?php
require_once '../config.php';
iniciarSessao();

header('Content-Type: application/json');

if (!estaLogado()) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$nome = trim($input['nome'] ?? '');
$descricao = trim($input['descricao'] ?? '');
$usuarioLogado = getUsuarioLogado();

if (!$nome) {
    echo json_encode(['success' => false, 'message' => 'Nome da pasta é obrigatório']);
    exit;
}

if (strlen($nome) > 100) {
    echo json_encode(['success' => false, 'message' => 'Nome muito longo (máximo 100 caracteres)']);
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    // Verificar se já existe uma pasta com o mesmo nome
    $stmt = $db->prepare("SELECT id FROM pastas WHERE nome = ? AND usuario_id = ?");
    $stmt->execute([$nome, $usuarioLogado['id']]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Já existe uma pasta com este nome']);
        exit;
    }
    
    // Criar nova pasta
    $stmt = $db->prepare("INSERT INTO pastas (nome, descricao, usuario_id, data_criacao) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$nome, $descricao, $usuarioLogado['id']]);
    
    $pastaId = $db->lastInsertId();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Pasta criada com sucesso',
        'pasta_id' => $pastaId
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao criar pasta: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>