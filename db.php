<?php
$host = 'localhost';
$db   = 'c2881399_cierres';
$user = 'c2881399_cierres';
$pass = 'PIvubafi71'; 
$charset = 'utf8mb4';

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

// Iniciar sesión solo si no está iniciada (evita errores si se llama múltiples veces)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function formatCurrency($val) {
    // FIX: Si el valor es null, usar 0. Convertir siempre a float explícitamente.
    // Esto soluciona el error "Deprecated: Passing null to parameter #1" en PHP 8.1+
    $valor = $val ?? 0;
    return '$ ' . number_format((float)$valor, 0, ',', '.');
}
?>