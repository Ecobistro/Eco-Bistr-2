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
$receitaId = $input['receita_id'] ?? null;
$usuarioLogado = getUsuarioLogado();

if (!$receitaId) {
    echo json_encode(['success' => false, 'message' => 'ID da receita não fornecido']);
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    // Verificar se a receita existe
    $stmt = $db->prepare("SELECT id FROM receitas WHERE id = ? AND ativo = 1");
    $stmt->execute([$receitaId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Receita não encontrada']);
        exit;
    }
    
    // Verificar se já curtiu
    $stmt = $db->prepare("SELECT COUNT(*) as curtida FROM curtidas WHERE receita_id = ? AND usuario_id = ?");
    $stmt->execute([$receitaId, $usuarioLogado['id']]);
    $jaCurtida = $stmt->fetch()['curtida'] > 0;
    
    if ($jaCurtida) {
        // Remover curtida
        $stmt = $db->prepare("DELETE FROM curtidas WHERE receita_id = ? AND usuario_id = ?");
        $stmt->execute([$receitaId, $usuarioLogado['id']]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Curtida removida',
            'curtida' => false
        ]);
    } else {
        // Adicionar curtida
        $stmt = $db->prepare("INSERT INTO curtidas (receita_id, usuario_id, data_curtida) VALUES (?, ?, NOW())");
        $stmt->execute([$receitaId, $usuarioLogado['id']]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Receita curtida',
            'curtida' => true
        ]);
    }
    
} catch (Exception $e) {
    error_log("Erro ao curtir receita: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>