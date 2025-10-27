<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS - CONFIGURAÇÃO
 * Versão Comercial 2.0
 * Desenvolvido para uso profissional
 */

// Prevenir acesso direto
if (!defined('SYSTEM_INIT')) {
    define('SYSTEM_INIT', true);
}

// ============================================
// CONFIGURAÇÕES DO BANCO DE DADOS
// ============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'gestao_eventos_pro');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ============================================
// CONFIGURAÇÕES DO SISTEMA
// ============================================
define('SITE_URL', 'http://localhost/exdigital');
define('SITE_NAME', 'Gestão Eventos Pro');
define('SITE_VERSION', '2.0.0');
define('ADMIN_EMAIL', 'extensangola@gmailcom.com');

// ============================================
// CONFIGURAÇÕES DE SEGURANÇA
// ============================================
define('SESSION_LIFETIME', 7200); // 2 horas
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 900); // 15 minutos

// ============================================
// CONFIGURAÇÕES DE UPLOAD
// ============================================
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

// ============================================
// CONFIGURAÇÕES DE TIMEZONE
// ============================================
date_default_timezone_set('Africa/Luanda');

// ============================================
// CONFIGURAÇÕES DE ERRO
// ============================================
if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// ============================================
// CLASSE DE CONEXÃO COM BANCO DE DADOS
// ============================================
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch(PDOException $e) {
            $this->logError("Erro de conexão: " . $e->getMessage());
            die("Erro ao conectar ao banco de dados. Por favor, tente novamente mais tarde.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    private function logError($message) {
        $logFile = __DIR__ . '/logs/db_errors.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    }
    
    // Prevenir clonagem
    private function __clone() {}
    
    // Prevenir unserialize
    public function __wakeup() {
        throw new Exception("Não é permitido unserialize");
    }
}

// ============================================
// CLASSE DE SEGURANÇA
// ============================================
class Security {
    
    /**
     * Sanitizar string
     */
    public static function clean($data) {
        if (is_array($data)) {
            return array_map([self::class, 'clean'], $data);
        }
        
        // Retornar string vazia se for null ou false
        if ($data === null || $data === false) {
            return '';
        }
        
        // Converter para string se necessário
        $data = (string) $data;
        
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validar email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Hash de senha
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * Verificar senha
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Gerar token único
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Gerar código único
     */
    public static function generateCode($prefix = '', $length = 6) {
        $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, $length));
        return $prefix ? $prefix . '-' . $code : $code;
    }
    
    /**
     * Proteger contra CSRF
     */
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = self::generateToken();
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validar token CSRF
     */
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Obter IP do cliente
     */
    public static function getClientIP() {
        $ip = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
    
    /**
     * Obter User Agent
     */
    public static function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    /**
     * Validar senha forte
     */
    public static function isStrongPassword($password) {
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            return false;
        }
        
        // Deve conter pelo menos uma letra maiúscula, minúscula e número
        if (!preg_match('/[A-Z]/', $password) || 
            !preg_match('/[a-z]/', $password) || 
            !preg_match('/[0-9]/', $password)) {
            return false;
        }
        
        return true;
    }
}

// ============================================
// CLASSE DE SESSÃO
// ============================================
class Session {
    
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
            
            session_start();
            
            // Regenerar ID de sessão periodicamente
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } else if (time() - $_SESSION['created'] > 1800) {
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }
    
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
    
    public static function has($key) {
        return isset($_SESSION[$key]);
    }
    
    public static function delete($key) {
        unset($_SESSION[$key]);
    }
    
    public static function destroy() {
        session_unset();
        session_destroy();
    }
    
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
    }
    
    public static function getUserType() {
        return $_SESSION['user_type'] ?? null;
    }
    
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    public static function setFlash($type, $message) {
        $_SESSION['flash'][$type] = $message;
    }
    
    public static function getFlash($type) {
        if (isset($_SESSION['flash'][$type])) {
            $message = $_SESSION['flash'][$type];
            unset($_SESSION['flash'][$type]);
            return $message;
        }
        return null;
    }
}

// ============================================
// CLASSE DE VALIDAÇÃO
// ============================================
class Validator {
    
    private $errors = [];
    
    public function validate($data, $rules) {
        foreach ($rules as $field => $ruleSet) {
            $value = $data[$field] ?? null;
            $ruleList = explode('|', $ruleSet);
            
            foreach ($ruleList as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }
        
        return empty($this->errors);
    }
    
    private function applyRule($field, $value, $rule) {
        $parts = explode(':', $rule);
        $ruleName = $parts[0];
        $ruleValue = $parts[1] ?? null;
        
        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->addError($field, "O campo é obrigatório");
                }
                break;
                
            case 'email':
                if (!empty($value) && !Security::validateEmail($value)) {
                    $this->addError($field, "Email inválido");
                }
                break;
                
            case 'min':
                if (!empty($value) && strlen($value) < $ruleValue) {
                    $this->addError($field, "Mínimo de $ruleValue caracteres");
                }
                break;
                
            case 'max':
                if (!empty($value) && strlen($value) > $ruleValue) {
                    $this->addError($field, "Máximo de $ruleValue caracteres");
                }
                break;
                
            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, "Deve ser um número");
                }
                break;
                
            case 'date':
                if (!empty($value) && !strtotime($value)) {
                    $this->addError($field, "Data inválida");
                }
                break;
                
            case 'strong_password':
                if (!empty($value) && !Security::isStrongPassword($value)) {
                    $this->addError($field, "Senha fraca. Use letras maiúsculas, minúsculas e números");
                }
                break;
        }
    }
    
    private function addError($field, $message) {
        $this->errors[$field][] = $message;
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function getFirstError($field) {
        return $this->errors[$field][0] ?? null;
    }
}

// ============================================
// FUNÇÕES AUXILIARES
// ============================================

/**
 * Redirecionar para URL
 */
function redirect($url) {
    header("Location: " . SITE_URL . $url);
    exit;
}

/**
 * Obter URL do sistema
 */
function url($path = '') {
    return SITE_URL . '/' . ltrim($path, '/');
}

/**
 * Obter URL de asset
 */
function asset($path) {
    return SITE_URL . '/assets/' . ltrim($path, '/');
}

/**
 * Formatar moeda
 */
function formatMoney($value, $currency = 'AOA') {
    if ($currency === 'AOA') {
        return number_format($value, 2, ',', '.') . ' Kz';
    } elseif ($currency === 'USD') {
        return '$ ' . number_format($value, 2, '.', ',');
    }
    return number_format($value, 2, ',', '.');
}

/**
 * Formatar data
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '-';
    return date($format, strtotime($date));
}

/**
 * Formatar data e hora
 */
function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    if (empty($datetime)) return '-';
    return date($format, strtotime($datetime));
}

/**
 * Tempo decorrido
 */
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'agora mesmo';
    if ($diff < 3600) return floor($diff / 60) . ' min atrás';
    if ($diff < 86400) return floor($diff / 3600) . ' h atrás';
    if ($diff < 604800) return floor($diff / 86400) . ' dias atrás';
    
    return formatDate($datetime);
}

/**
 * Obter tipo de evento formatado
 */
function getEventType($type) {
    $types = [
        'casamento' => 'Casamento',
        'aniversario' => 'Aniversário',
        'noivado' => 'Noivado',
        'corporativo' => 'Corporativo',
        'batizado' => 'Batizado',
        'formatura' => 'Formatura',
        'outro' => 'Outro'
    ];
    return $types[$type] ?? $type;
}

/**
 * Obter status formatado
 */
function getStatusLabel($status, $type = 'evento') {
    $labels = [
        'evento' => [
            'rascunho' => '<span class="badge badge-secondary">Rascunho</span>',
            'ativo' => '<span class="badge badge-success">Ativo</span>',
            'em_andamento' => '<span class="badge badge-primary">Em Andamento</span>',
            'concluido' => '<span class="badge badge-info">Concluído</span>',
            'cancelado' => '<span class="badge badge-danger">Cancelado</span>'
        ],
        'pagamento' => [
            'pendente' => '<span class="badge badge-warning">Pendente</span>',
            'processando' => '<span class="badge badge-info">Processando</span>',
            'aprovado' => '<span class="badge badge-success">Aprovado</span>',
            'rejeitado' => '<span class="badge badge-danger">Rejeitado</span>',
            'cancelado' => '<span class="badge badge-secondary">Cancelado</span>',
            'expirado' => '<span class="badge badge-dark">Expirado</span>'
        ],
        'despesa' => [
            'pendente' => '<span class="badge badge-warning">Pendente</span>',
            'pago' => '<span class="badge badge-success">Pago</span>',
            'atrasado' => '<span class="badge badge-danger">Atrasado</span>',
            'cancelado' => '<span class="badge badge-secondary">Cancelado</span>'
        ]
    ];
    
    return $labels[$type][$status] ?? $status;
}

/**
 * Registrar log de acesso
 */
function logAccess($userType, $userId, $action, $description = null) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO logs_acesso (usuario_tipo, usuario_id, acao, descricao, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userType,
            $userId,
            $action,
            $description,
            Security::getClientIP(),
            Security::getUserAgent()
        ]);
    } catch (PDOException $e) {
        error_log("Erro ao registrar log: " . $e->getMessage());
    }
}

/**
 * Criar notificação
 */
function createNotification($userType, $userId, $title, $message, $type = 'info', $link = null) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO notificacoes (usuario_tipo, usuario_id, titulo, mensagem, tipo, link)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userType, $userId, $title, $message, $type, $link]);
    } catch (PDOException $e) {
        error_log("Erro ao criar notificação: " . $e->getMessage());
    }
}

/**
 * Truncar texto
 */
function truncate($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Verificar se é método POST
 */
function isPost() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Verificar se é método GET
 */
function isGet() {
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}

/**
 * Obter valor POST
 */
function post($key, $default = null) {
    $value = $_POST[$key] ?? $default;
    return $value !== null ? Security::clean($value) : $default;
}

/**
 * Obter valor GET
 */
function get($key, $default = null) {
    $value = $_GET[$key] ?? $default;
    return $value !== null ? Security::clean($value) : $default;
}

/**
 * Gerar QR Code (usando API externa)
 */
function generateQRCode($data, $size = 300) {
    $encoded = urlencode($data);
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encoded}";
}

/**
 * Upload de arquivo
 */
function uploadFile($file, $folder = 'outros') {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Nenhum arquivo enviado'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Erro no upload do arquivo'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'Arquivo muito grande'];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'message' => 'Tipo de arquivo não permitido'];
    }
    
    $uploadDir = UPLOAD_PATH . $folder . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'filename' => $filename,
            'path' => $folder . '/' . $filename
        ];
    }
    
    return ['success' => false, 'message' => 'Falha ao mover arquivo'];
}

/**
 * Deletar arquivo
 */
function deleteFile($path) {
    $fullPath = UPLOAD_PATH . $path;
    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }
    return false;
}

/**
 * Enviar email (configurar SMTP)
 */
function sendEmail($to, $subject, $body, $fromName = null) {
    // Implementar com PHPMailer ou biblioteca similar
    // Por enquanto, usando mail() nativo
    $fromName = $fromName ?? SITE_NAME;
    $headers = "From: {$fromName} <" . ADMIN_EMAIL . ">\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $body, $headers);
}

// ============================================
// INICIALIZAÇÃO
// ============================================
Session::start();

// Verificar manutenção
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT valor FROM configuracoes WHERE chave = 'manutencao_ativo'");
$stmt->execute();
$manutencao = $stmt->fetchColumn();

if ($manutencao == '1' && !isset($_SESSION['is_admin'])) {
    // Exibir página de manutenção
    if (!defined('MAINTENANCE_MODE')) {
        include __DIR__ . '/maintenance.php';
        exit;
    }
}