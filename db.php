<?php
// Buffer de salida: evita problemas de BOM en cualquier archivo PHP
ob_start();

// Iniciar sesión antes de cualquier output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar credenciales desde config.php (no commiteado al repo)
$_config = file_exists(__DIR__ . '/config.php')
    ? require __DIR__ . '/config.php'
    : [];

$host    = $_config['db_host']    ?? 'localhost';
$db      = $_config['db_name']    ?? 'c2881399_cierres';
$user    = $_config['db_user']    ?? 'c2881399_cierres';
$pass    = $_config['db_pass']    ?? '';
$charset = $_config['db_charset'] ?? 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// --- Funciones de utilidad ---

function formatCurrency($val) {
    $valor = $val ?? 0;
    return '$ ' . number_format((float)$valor, 0, ',', '.');
}

function h($val) {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}

function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit;
    }
}

function requireAdmin() {
    requireAuth();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        header("Location: dashboard.php");
        exit;
    }
}

// --- Protección CSRF ---

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf($token = null) {
    $token = $token ?? ($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Token CSRF inválido. Recargá la página e intentá de nuevo.');
    }
}
