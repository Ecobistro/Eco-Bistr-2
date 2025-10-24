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
$usuarioLogado = getUsuarioLogado();

if (!$pastaId) {
    echo json_encode(['success' => false, 'message' => 'ID da pasta não fornecido']);
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
    
    $db->beginTransaction();
    
    // Remover todas as receitas da pasta
    $stmt = $db->prepare("DELETE FROM pasta_receitas WHERE pasta_id = ?");
    $stmt->execute([$pastaId]);
    
    // Remover a pasta
    $stmt = $db->prepare("DELETE FROM pastas WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$pastaId, $usuarioLogado['id']]);
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'Pasta deletada com sucesso']);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Erro ao deletar pasta: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>