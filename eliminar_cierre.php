<?php
require 'db.php';

// Solo administradores pueden eliminar
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Protección CSRF: el token debe venir en la URL
$token = $_GET['token'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    header("Location: historial.php?status=error&msg=Token_Invalido");
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM detalles_diarios WHERE cierre_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM cierres_semanales WHERE id = ?")->execute([$id]);
        $pdo->commit();
        header("Location: historial.php?status=deleted");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: historial.php?status=error&msg=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    header("Location: historial.php?status=error&msg=ID_Invalido");
    exit;
}

