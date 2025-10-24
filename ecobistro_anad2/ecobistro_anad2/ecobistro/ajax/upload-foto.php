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
$usuarioParaSeguirId = intval($input['usuario_id'] ?? 0);
$usuarioLogadoId = $_SESSION['usuario_id'];

// Validações
if (!$usuarioParaSeguirId) {
    echo json_encode(['success' => false, 'message' => 'ID de usuário inválido']);
    exit;
}

if ($usuarioParaSeguirId === $usuarioLogadoId) {
    echo json_encode(['success' => false, 'message' => 'Você não pode seguir a si mesmo']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar se o usuário para seguir existe e está ativo
    $stmt = $db->prepare("SELECT id, nome_usuario FROM usuarios WHERE id = ? AND ativo = 1");
    $stmt->execute([$usuarioParaSeguirId]);
    $usuarioParaSeguir = $stmt->fetch();
    
    if (!$usuarioParaSeguir) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        exit;
    }
    
    // Verificar se já está seguindo
    $stmt = $db->prepare("SELECT COUNT(*) as seguindo FROM seguidores WHERE seguidor_id = ? AND seguido_id = ?");
    $stmt->execute([$usuarioLogadoId, $usuarioParaSeguirId]);
    $jaSeguindo = $stmt->fetch()['seguindo'] > 0;
    
    if ($jaSeguindo) {
        // Parar de seguir (unfollow)
        $stmt = $db->prepare("DELETE FROM seguidores WHERE seguidor_id = ? AND seguido_id = ?");
        $stmt->execute([$usuarioLogadoId, $usuarioParaSeguirId]);
        
        echo json_encode([
            'success' => true, 
            'seguindo' => false,
            'message' => 'Você parou de seguir @' . $usuarioParaSeguir['nome_usuario']
        ]);
    } else {
        // Começar a seguir (follow)
        $stmt = $db->prepare("INSERT INTO seguidores (seguidor_id, seguido_id) VALUES (?, ?)");
        $stmt->execute([$usuarioLogadoId, $usuarioParaSeguirId]);
        
        echo json_encode([
            'success' => true, 
            'seguindo' => true,
            'message' => 'Você agora segue @' . $usuarioParaSeguir['nome_usuario']
        ]);
    }
    
} catch (Exception $e) {
    error_log("Erro ao seguir usuário: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>