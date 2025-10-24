<?php
// ajax/adicionar-pasta.php
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
$receitaId = (int)($input['receita_id'] ?? 0);
$pastaId = $input['pasta_id'] ?? null;
$usuarioLogado = getUsuarioLogado();

if (!$receitaId || !$pastaId) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    // Verificar se a receita existe e está ativa
    $stmt = $db->prepare("SELECT id FROM receitas WHERE id = ? AND ativo = 1");
    $stmt->execute([$receitaId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Receita não encontrada']);
        exit;
    }
    
    $db->beginTransaction();
    
    if ($pastaId === 'favoritos') {
        // Adicionar aos favoritos
        try {
            // Verificar se já não está nos favoritos
            $stmt = $db->prepare("SELECT 1 FROM favoritos WHERE usuario_id = ? AND receita_id = ?");
            $stmt->execute([$usuarioLogado['id'], $receitaId]);
            
            if ($stmt->fetch()) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Receita já está nos favoritos']);
                exit;
            }
            
            // Adicionar aos favoritos
            $stmt = $db->prepare("INSERT INTO favoritos (usuario_id, receita_id) VALUES (?, ?)");
            $stmt->execute([$usuarioLogado['id'], $receitaId]);
            
            $message = 'Receita adicionada aos favoritos!';
            
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate key
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Receita já está nos favoritos']);
                exit;
            }
            throw $e;
        }
        
    } elseif ($pastaId === 'fazer-mais-tarde') {
        // Adicionar à pasta "Fazer mais tarde"
        try {
            // Verificar se já não está na pasta
            $stmt = $db->prepare("SELECT 1 FROM pasta_receitas WHERE receita_id = ? AND pasta_id = ? AND usuario_id = ?");
            $stmt->execute([$receitaId, $pastaId, $usuarioLogado['id']]);
            
            if ($stmt->fetch()) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Receita já está na pasta "Fazer mais tarde"']);
                exit;
            }
            
            // Adicionar à pasta especial
            $stmt = $db->prepare("INSERT INTO pasta_receitas (receita_id, pasta_id, usuario_id) VALUES (?, ?, ?)");
            $stmt->execute([$receitaId, $pastaId, $usuarioLogado['id']]);
            
            $message = 'Receita adicionada à pasta "Fazer mais tarde"!';
            
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate key
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Receita já está na pasta "Fazer mais tarde"']);
                exit;
            }
            throw $e;
        }
        
    } else {
        // Pasta personalizada - verificar se pertence ao usuário
        $stmt = $db->prepare("SELECT id, nome FROM pastas WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$pastaId, $usuarioLogado['id']]);
        $pasta = $stmt->fetch();
        
        if (!$pasta) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Pasta não encontrada']);
            exit;
        }
        
        try {
            // Verificar se já não está na pasta
            $stmt = $db->prepare("SELECT 1 FROM pasta_receitas WHERE receita_id = ? AND pasta_id = ?");
            $stmt->execute([$receitaId, $pastaId]);
            
            if ($stmt->fetch()) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Receita já está nesta pasta']);
                exit;
            }
            
            // Adicionar à pasta personalizada
            $stmt = $db->prepare("INSERT INTO pasta_receitas (receita_id, pasta_id, usuario_id) VALUES (?, ?, ?)");
            $stmt->execute([$receitaId, $pastaId, $usuarioLogado['id']]);
            
            $message = 'Receita adicionada à pasta "' . $pasta['nome'] . '"!';
            
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate key
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Receita já está nesta pasta']);
                exit;
            }
            throw $e;
        }
    }
    
    $db->commit();
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Erro ao adicionar receita à pasta: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>