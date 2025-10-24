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
$comentarioId = $input['comentario_id'] ?? null;
$usuarioLogado = getUsuarioLogado();

if (!$comentarioId) {
    echo json_encode(['success' => false, 'message' => 'ID do comentário não fornecido']);
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    // Verificar se o comentário pertence ao usuário
    $stmt = $db->prepare("SELECT id, receita_id FROM comentarios WHERE id = ? AND usuario_id = ? AND ativo = 1");
    $stmt->execute([$comentarioId, $usuarioLogado['id']]);
    $comentarioExistente = $stmt->fetch();
    
    if (!$comentarioExistente) {
        echo json_encode(['success' => false, 'message' => 'Comentário não encontrado']);
        exit;
    }
    
    // Marcar comentário como inativo (soft delete)
    $stmt = $db->prepare("UPDATE comentarios SET ativo = 0 WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$comentarioId, $usuarioLogado['id']]);
    
    echo json_encode(['success' => true, 'message' => 'Comentário excluído com sucesso']);
    
} catch (Exception $e) {
    error_log("Erro ao excluir comentário: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>