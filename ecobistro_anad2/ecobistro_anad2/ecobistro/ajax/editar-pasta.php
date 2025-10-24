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
$pastaId = $input['pasta_id'] ?? null;
$nome = trim($input['nome'] ?? '');
$descricao = trim($input['descricao'] ?? '');
$usuarioLogado = getUsuarioLogado();

if (!$pastaId || !$nome) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

if (strlen($nome) > 100) {
    echo json_encode(['success' => false, 'message' => 'Nome muito longo (máximo 100 caracteres)']);
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    // Verificar se a pasta pertence ao usuário
    $stmt = $db->prepare("SELECT id FROM pastas WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$pastaId, $usuarioLogado['id']]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Pasta não encontrada']);
        exit;
    }
    
    // Verificar se já existe outra pasta com o mesmo nome
    $stmt = $db->prepare("SELECT id FROM pastas WHERE nome = ? AND usuario_id = ? AND id != ?");
    $stmt->execute([$nome, $usuarioLogado['id'], $pastaId]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Já existe uma pasta com este nome']);
        exit;
    }
    
    // Atualizar pasta
    $stmt = $db->prepare("UPDATE pastas SET nome = ?, descricao = ? WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$nome, $descricao, $pastaId, $usuarioLogado['id']]);
    
    echo json_encode(['success' => true, 'message' => 'Pasta editada com sucesso']);
    
} catch (Exception $e) {
    error_log("Erro ao editar pasta: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>