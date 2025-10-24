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
$pastaId = $input['pasta_id'] ?? null;
$usuarioLogado = getUsuarioLogado();

if (!$receitaId || !$pastaId) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    if ($pastaId == 'favoritos') {
        // Remover dos favoritos
        $stmt = $db->prepare("DELETE FROM favoritos WHERE receita_id = ? AND usuario_id = ?");
        $stmt->execute([$receitaId, $usuarioLogado['id']]);
        
    } elseif ($pastaId == 'fazer-mais-tarde') {
        // Remover da pasta especial
        $stmt = $db->prepare("DELETE FROM pasta_receitas WHERE receita_id = ? AND pasta_id = ? AND usuario_id = ?");
        $stmt->execute([$receitaId, $pastaId, $usuarioLogado['id']]);
        
    } else {
        // Verificar se a pasta pertence ao usuário
        $stmt = $db->prepare("SELECT id FROM pastas WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$pastaId, $usuarioLogado['id']]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Pasta não encontrada']);
            exit;
        }
        
        // Remover da pasta normal
        $stmt = $db->prepare("DELETE FROM pasta_receitas WHERE receita_id = ? AND pasta_id = ?");
        $stmt->execute([$receitaId, $pastaId]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Receita removida da pasta com sucesso']);
    
} catch (Exception $e) {
    error_log("Erro ao remover receita da pasta: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>