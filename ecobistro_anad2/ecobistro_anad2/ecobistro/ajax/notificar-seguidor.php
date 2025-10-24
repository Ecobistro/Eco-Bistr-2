<?php
// ajax/get-estatisticas-seguidor.php
require_once '../config.php';
iniciarSessao();

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !estaLogado()) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

$usuarioId = $_SESSION['usuario_id'];

try {
    $db = Database::getInstance()->getConnection();
    
    // Buscar estatísticas do usuário
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM seguidores WHERE seguido_id = ?");
    $stmt->execute([$usuarioId]);
    $totalSeguidores = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM seguidores WHERE seguidor_id = ?");
    $stmt->execute([$usuarioId]);
    $totalSeguindo = $stmt->fetch()['total'];
    
    // Buscar seguidores recentes (últimos 7 dias)
    $stmt = $db->prepare("
        SELECT u.nome_usuario, s.data_seguimento
        FROM seguidores s
        JOIN usuarios u ON s.seguidor_id = u.id
        WHERE s.seguido_id = ? 
        AND s.data_seguimento >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY s.data_seguimento DESC
        LIMIT 5
    ");
    $stmt->execute([$usuarioId]);
    $seguidoresRecentes = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'total_seguidores' => $totalSeguidores,
        'total_seguindo' => $totalSeguindo,
        'seguidores_recentes' => $seguidoresRecentes
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao buscar estatísticas: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>

<?php
// ajax/sugerir-usuarios.php
require_once '../config.php';
iniciarSessao();

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !estaLogado()) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

$usuarioId = $_SESSION['usuario_id'];
$limite = intval($_GET['limite'] ?? 5);

try {
    $db = Database::getInstance()->getConnection();
    
    // Buscar usuários sugeridos (que o usuário não segue e que são ativos)
    $stmt = $db->prepare("
        SELECT u.id, u.nome_usuario, u.biografia, u.foto_perfil,
               (SELECT COUNT(*) FROM receitas WHERE usuario_id = u.id AND ativo = 1) as total_receitas,
               (SELECT COUNT(*) FROM seguidores WHERE seguido_id = u.id) as total_seguidores
        FROM usuarios u
        WHERE u.id != ? 
        AND u.ativo = 1
        AND u.id NOT IN (
            SELECT seguido_id FROM seguidores WHERE seguidor_id = ?
        )
        ORDER BY total_receitas DESC, total_seguidores DESC, RAND()
        LIMIT ?
    ");
    $stmt->execute([$usuarioId, $usuarioId, $limite]);
    $usuariosSugeridos = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'usuarios' => $usuariosSugeridos
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao buscar sugestões: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>

<?php
// functions/seguidores-helper.php
require_once '../config.php';

/**
 * Função para verificar se um usuário segue outro
 */
function verificarSeguindo($seguidorId, $seguidoId) {
    if (!$seguidorId || !$seguidoId || $seguidorId == $seguidoId) {
        return false;
    }
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT COUNT(*) as seguindo FROM seguidores WHERE seguidor_id = ? AND seguido_id = ?");
    $stmt->execute([$seguidorId, $seguidoId]);
    
    return $stmt->fetch()['seguindo'] > 0;
}

/**
 * Função para obter lista de usuários que o usuário segue
 */
function obterListaSeguindo($usuarioId) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT seguido_id FROM seguidores WHERE seguidor_id = ?");
    $stmt->execute([$usuarioId]);
    
    return array_column($stmt->fetchAll(), 'seguido_id');
}

/**
 * Função para obter estatísticas de seguidores de um usuário
 */
function obterEstatisticasUsuario($usuarioId) {
    $db = Database::getInstance()->getConnection();
    
    // Total de seguidores
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM seguidores WHERE seguido_id = ?");
    $stmt->execute([$usuarioId]);
    $totalSeguidores = $stmt->fetch()['total'];
    
    // Total seguindo
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM seguidores WHERE seguidor_id = ?");
    $stmt->execute([$usuarioId]);
    $totalSeguindo = $stmt->fetch()['total'];
    
    // Total de receitas
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM receitas WHERE usuario_id = ? AND ativo = 1");
    $stmt->execute([$usuarioId]);
    $totalReceitas = $stmt->fetch()['total'];
    
    return [
        'seguidores' => $totalSeguidores,
        'seguindo' => $totalSeguindo,
        'receitas' => $totalReceitas
    ];
}

/**
 * Função para buscar usuários por termo
 */
function buscarUsuarios($termo, $limite = 20, $usuarioExcluir = null) {
    $db = Database::getInstance()->getConnection();
    
    $sql = "
        SELECT u.*, 
               (SELECT COUNT(*) FROM receitas WHERE usuario_id = u.id AND ativo = 1) as total_receitas,
               (SELECT COUNT(*) FROM seguidores WHERE seguido_id = u.id) as total_seguidores
        FROM usuarios u 
        WHERE u.ativo = 1 
        AND (u.nome_usuario LIKE ? OR u.biografia LIKE ?)
    ";
    
    $params = ["%$termo%", "%$termo%"];
    
    if ($usuarioExcluir) {
        $sql .= " AND u.id != ?";
        $params[] = $usuarioExcluir;
    }
    
    $sql .= " ORDER BY u.nome_usuario LIMIT ?";
    $params[] = $limite;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Função para obter feed de atividades dos usuários seguidos
 */
function obterFeedSeguindo($usuarioId, $limite = 10) {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        SELECT 'receita' as tipo, r.id, r.titulo, r.data_criacao, u.nome_usuario, u.id as usuario_id
        FROM receitas r
        JOIN usuarios u ON r.usuario_id = u.id
        JOIN seguidores s ON s.seguido_id = u.id
        WHERE s.seguidor_id = ? AND r.ativo = 1
        
        UNION ALL
        
        SELECT 'seguidor' as tipo, u2.id, 
               CONCAT(u1.nome_usuario, ' começou a seguir ', u2.nome_usuario) as titulo,
               seg.data_seguimento as data_criacao, u1.nome_usuario, u1.id as usuario_id
        FROM seguidores seg
        JOIN usuarios u1 ON seg.seguidor_id = u1.id
        JOIN usuarios u2 ON seg.seguido_id = u2.id
        JOIN seguidores s ON s.seguido_id = u1.id
        WHERE s.seguidor_id = ?
        
        ORDER BY data_criacao DESC
        LIMIT ?
    ");
    
    $stmt->execute([$usuarioId, $usuarioId, $limite]);
    return $stmt->fetchAll();
}
?>