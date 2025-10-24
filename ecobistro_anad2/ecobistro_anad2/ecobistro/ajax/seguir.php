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
$usuarioId = $input['usuario_id'] ?? null;
$usuarioLogado = getUsuarioLogado();

if (!$usuarioId) {
    echo json_encode(['success' => false, 'message' => 'ID do usuário não fornecido']);
    exit;
}

if ($usuarioId == $usuarioLogado['id']) {
    echo json_encode(['success' => false, 'message' => 'Você não pode seguir a si mesmo']);
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    // Verificar se o usuário existe
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE id = ? AND ativo = 1");
    $stmt->execute([$usuarioId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        exit;
    }
    
    // Verificar se já está seguindo
    $stmt = $db->prepare("SELECT COUNT(*) as seguindo FROM seguidores WHERE seguidor_id = ? AND seguido_id = ?");
    $stmt->execute([$usuarioLogado['id'], $usuarioId]);
    $jaSeguindo = $stmt->fetch()['seguindo'] > 0;
    
    if ($jaSeguindo) {
        // Parar de seguir
        $stmt = $db->prepare("DELETE FROM seguidores WHERE seguidor_id = ? AND seguido_id = ?");
        $stmt->execute([$usuarioLogado['id'], $usuarioId]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Você parou de seguir este usuário',
            'seguindo' => false
        ]);
    } else {
        // Começar a seguir
        $stmt = $db->prepare("INSERT INTO seguidores (seguidor_id, seguido_id, data_seguimento) VALUES (?, ?, NOW())");
        $stmt->execute([$usuarioLogado['id'], $usuarioId]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Você agora está seguindo este usuário',
            'seguindo' => true
        ]);
    }
    
} catch (Exception $e) {
    error_log("Erro ao seguir usuário: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>