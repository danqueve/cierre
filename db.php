<?php
// Buffer de salida: evita problemas de BOM en cualquier archivo PHP
ob_start();

// Iniciar sesión antes de cualquier output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ═══════════════════════════════════════════════════════
//  CARGA DE CONFIGURACIÓN DESDE .env
// ═══════════════════════════════════════════════════════
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

$host    = $_ENV['DB_HOST']    ?? 'localhost';
$db      = $_ENV['DB_NAME']    ?? 'c2881399_cierres';
$user    = $_ENV['DB_USER']    ?? 'c2881399_cierres';
$pass    = $_ENV['DB_PASS']    ?? '';
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

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

// ═══════════════════════════════════════════════════════
//  HELPERS DE SEGURIDAD
// ═══════════════════════════════════════════════════════

/**
 * Escapa salida HTML para prevenir XSS.
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Genera (o recupera) el token CSRF de la sesión actual.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Devuelve el campo hidden HTML con el token CSRF.
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Valida el token CSRF enviado en el POST.
 * Lanza una excepción si es inválido.
 */
function csrf_verify(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('<h2>Error de seguridad: token CSRF inválido. Volvé atrás y reintentá.</h2>');
    }
}

// ═══════════════════════════════════════════════════════
//  HELPER DE FORMATO
// ═══════════════════════════════════════════════════════
function formatCurrency($val): string {
    $valor = $val ?? 0;
    return '$ ' . number_format((float)$valor, 0, ',', '.');
}