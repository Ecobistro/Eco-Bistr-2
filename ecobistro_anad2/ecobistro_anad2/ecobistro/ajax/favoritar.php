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
    
    // Verificar se já está favoritada
    $stmt = $db->prepare("SELECT COUNT(*) as favoritada FROM favoritos WHERE receita_id = ? AND usuario_id = ?");
    $stmt->execute([$receitaId, $usuarioLogado['id']]);
    $jaFavoritada = $stmt->fetch()['favoritada'] > 0;
    
    if ($jaFavoritada) {
        // Remover dos favoritos
        $stmt = $db->prepare("DELETE FROM favoritos WHERE receita_id = ? AND usuario_id = ?");
        $stmt->execute([$receitaId, $usuarioLogado['id']]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Receita removida dos favoritos',
            'favoritada' => false
        ]);
    } else {
        // Adicionar aos favoritos
        $stmt = $db->prepare("INSERT INTO favoritos (receita_id, usuario_id, data_favoritado) VALUES (?, ?, NOW())");
        $stmt->execute([$receitaId, $usuarioLogado['id']]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Receita adicionada aos favoritos',
            'favoritada' => true
        ]);
    }
    
} catch (Exception $e) {
    error_log("Erro ao favoritar receita: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>