<?php
require_once '../config.php';
iniciarSessao();

// Apenas POST é permitido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verificar se usuário está logado
if (!estaLogado()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
    exit;
}

// Receber dados JSON
$input = json_decode(file_get_contents('php://input'), true);
$comentarioId = (int)($input['comentario_id'] ?? 0);
$comentario = sanitizar($input['comentario'] ?? '');
$usuarioId = $_SESSION['usuario_id'];

// Validações
if (!$comentarioId) {
    echo json_encode(['success' => false, 'message' => 'ID do comentário não fornecido']);
    exit;
}

if (empty($comentario)) {
    echo json_encode(['success' => false, 'message' => 'Comentário não pode estar vazio']);
    exit;
}

if (strlen($comentario) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Comentário muito longo (máximo 1000 caracteres)']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar se o comentário existe e pertence ao usuário
    $stmt = $db->prepare("SELECT * FROM comentarios WHERE id = ? AND usuario_id = ? AND ativo = 1");
    $stmt->execute([$comentarioId, $usuarioId]);
    $comentarioExistente = $stmt->fetch();
    
    if (!$comentarioExistente) {
        echo json_encode(['success' => false, 'message' => 'Comentário não encontrado ou você não tem permissão para editá-lo']);
        exit;
    }
    
    // Atualizar o comentário
    $stmt = $db->prepare("UPDATE comentarios SET comentario = ?, data_editado = NOW() WHERE id = ? AND usuario_id = ?");
    $resultado = $stmt->execute([$comentario, $comentarioId, $usuarioId]);
    
    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => 'Comentário atualizado com sucesso!'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar comentário']);
    }
    
} catch (Exception $e) {
    error_log("Erro ao editar comentário: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>