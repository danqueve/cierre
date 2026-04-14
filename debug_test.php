<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>PHP Version: " . phpversion() . "</h2>";
echo "<h3>Session Status: " . session_status() . "</h3>";

// Test session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "<p>Session started OK. ID: " . session_id() . "</p>";
} else {
    echo "<p>Session already active.</p>";
}

// Test DB connection
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
    echo "<p style='color:green'>✓ Conexión a DB exitosa</p>";
    
    // Test query
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Tablas: " . implode(', ', $tables) . "</p>";
    
} catch (\PDOException $e) {
    echo "<p style='color:red'>✗ Error DB: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p>Done.</p>";
?>
