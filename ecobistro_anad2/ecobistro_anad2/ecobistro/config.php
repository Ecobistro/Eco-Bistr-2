<?php
// cspell:disable
// Configurações da aplicação
define('DB_HOST', 'localhost');
define('DB_NAME', 'eco_bistro');
define('DB_USER', 'root');
define('DB_PASS', '');

// Classe para conexão com banco
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            die("Erro na conexão: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }
}

// Funções auxiliares
function iniciarSessao() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function estaLogado() {
    return isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id']);
}

function getUsuarioLogado() {
    if (!estaLogado()) {
        return null;
    }

    static $usuario = null;
    
    if ($usuario === null) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ? AND ativo = 1");
        $stmt->execute([$_SESSION['usuario_id']]);
        $usuario = $stmt->fetch();
    }

    return $usuario;
}

function sanitizar($texto) {
    return htmlspecialchars(trim($texto), ENT_QUOTES, 'UTF-8');
}

function formatarTempo($minutos) {
    if ($minutos < 60) {
        return $minutos . ' min';
    } else {
        $horas = floor($minutos / 60);
        $minutosRestantes = $minutos % 60;
        
        if ($minutosRestantes == 0) {
            return $horas . 'h';
        } else {
            return $horas . 'h ' . $minutosRestantes . 'min';
        }
    }
}

function redimensionarImagem($origem, $destino, $larguraMax, $alturaMax, $qualidade = 80) {
    // Obter informações da imagem original
    $info = getimagesize($origem);
    if ($info === false) {
        return false;
    }

    $larguraOriginal = $info[0];
    $alturaOriginal = $info[1];
    $tipoOriginal = $info[2];

    // Calcular novas dimensões mantendo proporção
    $proporcao = min($larguraMax / $larguraOriginal, $alturaMax / $alturaOriginal);
    $novaLargura = (int) ($larguraOriginal * $proporcao);
    $novaAltura = (int) ($alturaOriginal * $proporcao);

    // Criar imagem a partir do tipo
    switch ($tipoOriginal) {
        case IMAGETYPE_JPEG:
            $imagemOriginal = imagecreatefromjpeg($origem);
            break;
        case IMAGETYPE_PNG:
            $imagemOriginal = imagecreatefrompng($origem);
            break;
        case IMAGETYPE_GIF:
            $imagemOriginal = imagecreatefromgif($origem);
            break;
        case IMAGETYPE_WEBP:
            $imagemOriginal = imagecreatefromwebp($origem);
            break;
        default:
            return false;
    }

    if ($imagemOriginal === false) {
        return false;
    }

    // Criar nova imagem redimensionada
    $novaImagem = imagecreatetruecolor($novaLargura, $novaAltura);
    
    // Preservar transparência para PNG e GIF
    if ($tipoOriginal == IMAGETYPE_PNG || $tipoOriginal == IMAGETYPE_GIF) {
        imagealphablending($novaImagem, false);
        imagesavealpha($novaImagem, true);
        $transparente = imagecolorallocatealpha($novaImagem, 255, 255, 255, 127);
        imagefilledrectangle($novaImagem, 0, 0, $novaLargura, $novaAltura, $transparente);
    }

    // Redimensionar
    imagecopyresampled(
        $novaImagem, $imagemOriginal,
        0, 0, 0, 0,
        $novaLargura, $novaAltura,
        $larguraOriginal, $alturaOriginal
    );

    // Salvar imagem redimensionada
    $resultado = false;
    switch ($tipoOriginal) {
        case IMAGETYPE_JPEG:
            $resultado = imagejpeg($novaImagem, $destino, $qualidade);
            break;
        case IMAGETYPE_PNG:
            $resultado = imagepng($novaImagem, $destino);
            break;
        case IMAGETYPE_GIF:
            $resultado = imagegif($novaImagem, $destino);
            break;
        case IMAGETYPE_WEBP:
            $resultado = imagewebp($novaImagem, $destino, $qualidade);
            break;
    }

    // Limpar memória
    imagedestroy($imagemOriginal);
    imagedestroy($novaImagem);

    return $resultado;
}

function criarDiretorioSeNaoExistir($caminho) {
    if (!is_dir($caminho)) {
        return mkdir($caminho, 0755, true);
    }
    return true;
}

function gerarNomeArquivoUnico($diretorio, $nomeOriginal) {
    $info = pathinfo($nomeOriginal);
    $nome = sanitizar($info['filename']);
    $extensao = strtolower($info['extension']);
    
    // Remover caracteres especiais
    $nome = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nome);
    
    $contador = 0;
    do {
        $nomeArquivo = $nome . ($contador > 0 ? "_$contador" : "") . ".$extensao";
        $caminhoCompleto = $diretorio . $nomeArquivo;
        $contador++;
    } while (file_exists($caminhoCompleto));
    
    return $nomeArquivo;
}

function formatarDataBrasil($data) {
    return date('d/m/Y H:i', strtotime($data));
}

function timeAgo($data) {
    $agora = new DateTime();
    $dataObj = new DateTime($data);
    $diff = $agora->diff($dataObj);
    
    if ($diff->d >= 30) {
        return date('d/m/Y', strtotime($data));
    } elseif ($diff->d >= 1) {
        return $diff->d . ' dia' . ($diff->d > 1 ? 's' : '') . ' atrás';
    } elseif ($diff->h >= 1) {
        return $diff->h . ' hora' . ($diff->h > 1 ? 's' : '') . ' atrás';
    } elseif ($diff->i >= 1) {
        return $diff->i . ' minuto' . ($diff->i > 1 ? 's' : '') . ' atrás';
    } else {
        return 'Agora há pouco';
    }
}

// Função para verificar se usuário está seguindo outro
function estaSeguindo($seguidorId, $seguidoId) {
    if (!$seguidorId || !$seguidoId || $seguidorId == $seguidoId) {
        return false;
    }
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT COUNT(*) as seguindo FROM seguidores WHERE seguidor_id = ? AND seguido_id = ?");
    $stmt->execute([$seguidorId, $seguidoId]);
    
    return $stmt->fetch()['seguindo'] > 0;
}

// ======================================
// CONSTANTES PARA UPLOADS
// ======================================
define('UPLOAD_DIR_RECEITAS', 'uploads/receitas/');
define('UPLOAD_DIR_PERFIL', 'uploads/perfil/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/webp']);

// Criar diretórios de upload se não existirem
criarDiretorioSeNaoExistir(UPLOAD_DIR_RECEITAS);
criarDiretorioSeNaoExistir(UPLOAD_DIR_PERFIL);

// ======================================
// FUNÇÕES DE UPLOAD DE IMAGEM
// ======================================

/**
 * Função principal para fazer upload de imagens de forma segura
 * @param array $arquivo - $_FILES['nome_campo']
 * @param string $pasta - 'receitas' ou 'perfil'
 * @return string|false - Nome do arquivo salvo ou false se erro
 */
function uploadImagem($arquivo, $pasta = 'receitas') {
    // Verificar se houve erro no upload
    if (!isset($arquivo['error']) || $arquivo['error'] !== UPLOAD_ERR_OK) {
        error_log("Erro no upload: " . ($arquivo['error'] ?? 'arquivo não definido'));
        return false;
    }
    
    // Verificar se arquivo foi realmente enviado
    if (!isset($arquivo['tmp_name']) || !is_uploaded_file($arquivo['tmp_name'])) {
        error_log("Arquivo não foi enviado via POST");
        return false;
    }
    
    // Configurações
    $tamanhoMaximo = MAX_FILE_SIZE;
    $tiposPermitidos = ALLOWED_IMAGE_TYPES;
    $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    // Verificar tamanho
    if ($arquivo['size'] > $tamanhoMaximo) {
        error_log("Arquivo muito grande: " . $arquivo['size'] . " bytes (máximo: $tamanhoMaximo)");
        return false;
    }
    
    // Verificar tipo MIME
    $tipoArquivo = mime_content_type($arquivo['tmp_name']);
    if (!$tipoArquivo || !in_array($tipoArquivo, $tiposPermitidos)) {
        error_log("Tipo de arquivo não permitido: " . ($tipoArquivo ?? 'desconhecido'));
        return false;
    }
    
    // Verificar extensão
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extensao, $extensoesPermitidas)) {
        error_log("Extensão não permitida: $extensao");
        return false;
    }
    
    // Definir diretório de destino
    $diretorioUpload = ($pasta === 'perfil') ? UPLOAD_DIR_PERFIL : UPLOAD_DIR_RECEITAS;
    
    // Criar pasta se não existir
    if (!criarDiretorioSeNaoExistir($diretorioUpload)) {
        error_log("Erro ao criar diretório: $diretorioUpload");
        return false;
    }
    
    // Gerar nome único
    $nomeArquivo = uniqid('img_') . '_' . time() . '.' . $extensao;
    $caminhoCompleto = $diretorioUpload . $nomeArquivo;
    
    // Mover arquivo
    if (move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
        // Redimensionar imagem
        $redimensionado = redimensionarImagemUpload($caminhoCompleto, 800, 600);
        
        if ($redimensionado) {
            return $nomeArquivo;
        } else {
            error_log("Aviso: Falha ao redimensionar $caminhoCompleto, mantendo original");
            return $nomeArquivo;
        }
    } else {
        error_log("Erro ao mover arquivo para: $caminhoCompleto");
        return false;
    }
}

/**
 * Função para redimensionar imagem após upload
 */
function redimensionarImagemUpload($caminho, $larguraMax = 800, $alturaMax = 600) {
    if (!file_exists($caminho)) {
        return false;
    }
    
    $info = getimagesize($caminho);
    if (!$info) {
        return false;
    }
    
    $larguraOriginal = $info[0];
    $alturaOriginal = $info[1];
    
    // Se a imagem já está no tamanho adequado, não fazer nada
    if ($larguraOriginal <= $larguraMax && $alturaOriginal <= $alturaMax) {
        return true;
    }
    
    // Usar a função existente redimensionarImagem
    return redimensionarImagem($caminho, $caminho, $larguraMax, $alturaMax, 85);
}

/**
 * Função para deletar imagem
 */
function deletarImagem($nomeArquivo, $pasta = 'receitas') {
    if (empty($nomeArquivo)) {
        return true;
    }
    
    $diretorio = ($pasta === 'perfil') ? UPLOAD_DIR_PERFIL : UPLOAD_DIR_RECEITAS;
    $caminho = $diretorio . $nomeArquivo;
    
    if (file_exists($caminho)) {
        return unlink($caminho);
    }
    
    return true;
}

/**
 * Função para obter URL da imagem
 */
function getImagemUrl($nomeArquivo, $pasta = 'receitas') {
    if (empty($nomeArquivo)) {
        return 'assets/images/default-recipe.jpg';
    }
    
    $diretorio = ($pasta === 'perfil') ? 'uploads/perfil/' : 'uploads/receitas/';
    return $diretorio . $nomeArquivo;
}

/**
 * Função para obter URL da imagem de receita
 */
function getImagemReceitaUrl($nomeArquivo) {
    if (empty($nomeArquivo)) {
        return 'img/default-recipe.jpg';
    }
    
    $caminho = 'uploads/receitas/' . $nomeArquivo;
    return file_exists($caminho) ? $caminho : 'img/default-recipe.jpg';
}

/**
 * Função para verificar se imagem existe
 */
function imagemExiste($nomeArquivo, $pasta = 'receitas') {
    if (empty($nomeArquivo)) {
        return false;
    }
    
    $diretorio = ($pasta === 'perfil') ? UPLOAD_DIR_PERFIL : UPLOAD_DIR_RECEITAS;
    $caminho = $diretorio . $nomeArquivo;
    return file_exists($caminho);
}

/**
 * Função para validar imagem antes do upload (uso opcional)
 */
function validarImagemUpload($arquivo) {
    $erros = [];
    
    // Verificar se arquivo foi enviado
    if (!isset($arquivo) || $arquivo['error'] === UPLOAD_ERR_NO_FILE) {
        return $erros;
    }
    
    // Verificar erro no upload
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        switch ($arquivo['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $erros[] = 'Arquivo muito grande (máximo 5MB)';
                break;
            case UPLOAD_ERR_PARTIAL:
                $erros[] = 'Upload incompleto, tente novamente';
                break;
            default:
                $erros[] = 'Erro no upload da imagem';
                break;
        }
        return $erros;
    }
    
    // Verificar tamanho
    if ($arquivo['size'] > MAX_FILE_SIZE) {
        $erros[] = 'Imagem muito grande (máximo 5MB)';
    }
    
    // Verificar tipo
    $tipoArquivo = mime_content_type($arquivo['tmp_name']);
    if (!in_array($tipoArquivo, ALLOWED_IMAGE_TYPES)) {
        $erros[] = 'Formato não suportado. Use JPG, PNG, GIF ou WEBP';
    }
    
    return $erros;
}

// ======================================
// FUNÇÕES DE USUÁRIOS
// ======================================

function getUsuariosSugeridos($usuarioId, $limite = 5) {
    if (!$usuarioId) return [];
    
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        SELECT u.id, u.nome_usuario, u.biografia, u.foto_perfil,
               COUNT(r.id) as total_receitas,
               (SELECT COUNT(*) FROM seguidores s WHERE s.seguido_id = u.id) as total_seguidores
        FROM usuarios u
        LEFT JOIN receitas r ON u.id = r.usuario_id AND r.ativo = 1
        WHERE u.id != ? 
        AND u.ativo = 1
        AND u.id NOT IN (
            SELECT seguido_id FROM seguidores WHERE seguidor_id = ?
        )
        GROUP BY u.id, u.nome_usuario, u.biografia, u.foto_perfil
        ORDER BY total_receitas DESC, total_seguidores DESC, u.data_criacao DESC
        LIMIT ?
    ");
    $stmt->execute([$usuarioId, $usuarioId, $limite]);
    return $stmt->fetchAll();
}

// ======================================
// FUNÇÕES DE PREFERÊNCIAS
// ======================================

/**
 * Obter preferências de um usuário
 */
function getPreferenciasUsuario($usuarioId) {
    if (!$usuarioId) return [];
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT pr.*, tp.descricao, tp.icone 
        FROM preferencias_receitas pr
        JOIN tipos_preferencias tp ON pr.tipo_preferencia = tp.tipo AND pr.valor = tp.valor
        WHERE pr.usuario_id = ? AND pr.ativo = 1
        ORDER BY pr.tipo_preferencia, pr.valor
    ");
    $stmt->execute([$usuarioId]);
    return $stmt->fetchAll();
}

/**
 * Verificar se usuário tem preferência específica
 */
function usuarioTemPreferencia($usuarioId, $tipo, $valor) {
    if (!$usuarioId) return false;
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM preferencias_receitas 
        WHERE usuario_id = ? AND tipo_preferencia = ? AND valor = ? AND ativo = 1
    ");
    $stmt->execute([$usuarioId, $tipo, $valor]);
    return $stmt->fetch()['total'] > 0;
}

/**
 * Obter receitas recomendadas baseadas nas preferências do usuário
 */
function getReceitasRecomendadas($usuarioId, $limite = 10) {
    if (!$usuarioId) return getReceitasPopulares($limite);
    
    $preferencias = getPreferenciasUsuario($usuarioId);
    if (empty($preferencias)) {
        return getReceitasPopulares($limite);
    }
    
    // Se tem preferências, retornar todas as receitas
    // (a filtragem por compatibilidade pode ser feita posteriormente se necessário)
    return getReceitasPopulares($limite);
}

/**
 * Obter receitas populares (fallback quando não há preferências)
 */
function getReceitasPopulares($limite = 10) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT r.*, u.nome_usuario, c.nome as categoria_nome,
               (SELECT COUNT(*) FROM favoritos f WHERE f.receita_id = r.id) as total_favoritos,
               (SELECT COUNT(*) FROM curtidas cu WHERE cu.receita_id = r.id) as total_curtidas
        FROM receitas r
        JOIN usuarios u ON r.usuario_id = u.id
        JOIN categorias c ON r.categoria_id = c.id
        WHERE r.ativo = 1
        ORDER BY r.visualizacoes DESC, r.data_criacao DESC
        LIMIT ?
    ");
    $stmt->execute([$limite]);
    return $stmt->fetchAll();
}

/**
 * Verificar se receita é compatível com preferências do usuário
 */
function receitaCompativelComPreferencias($receitaId, $usuarioId) {
    if (!$usuarioId) return true;
    
    $preferencias = getPreferenciasUsuario($usuarioId);
    if (empty($preferencias)) return true;
    
    // Por enquanto, todas as receitas são consideradas compatíveis
    // Ajuste conforme os campos reais da tabela receitas
    return true;
}

?>