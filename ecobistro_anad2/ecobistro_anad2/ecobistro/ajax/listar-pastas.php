<?php
// ajax/listar-pastas.php
require_once '../config.php';
iniciarSessao();

header('Content-Type: application/json');

if (!estaLogado()) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$usuarioLogado = getUsuarioLogado();
$db = Database::getInstance()->getConnection();

try {
    // Contar favoritos
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM favoritos WHERE usuario_id = ?");
    $stmt->execute([$usuarioLogado['id']]);
    $totalFavoritos = $stmt->fetch()['total'];
    
    // Contar "fazer mais tarde"
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM pasta_receitas WHERE pasta_id = 'fazer-mais-tarde' AND usuario_id = ?");
    $stmt->execute([$usuarioLogado['id']]);
    $totalFazerMaisTarde = $stmt->fetch()['total'];
    
    // Buscar pastas personalizadas do usuário
    $stmt = $db->prepare("
        SELECT p.*, COUNT(pr.receita_id) as total_receitas
        FROM pastas p
        LEFT JOIN pasta_receitas pr ON p.id = pr.pasta_id
        WHERE p.usuario_id = ?
        GROUP BY p.id, p.nome, p.descricao, p.data_criacao
        ORDER BY p.nome ASC
    ");
    $stmt->execute([$usuarioLogado['id']]);
    $pastas = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'favoritos' => $totalFavoritos,
        'fazerMaisTarde' => $totalFazerMaisTarde,
        'pastas' => $pastas
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao listar pastas: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>