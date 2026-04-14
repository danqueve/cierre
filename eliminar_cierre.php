<?php
require 'db.php';
requireAuth();

// Solo acepta POST para evitar eliminación accidental via GET/links
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: historial.php");
    exit;
}

verify_csrf();

$id = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    try {
        $pdo->beginTransaction();

        $stmtDetalles = $pdo->prepare("DELETE FROM detalles_diarios WHERE cierre_id = ?");
        $stmtDetalles->execute([$id]);

        $stmtCierre = $pdo->prepare("DELETE FROM cierres_semanales WHERE id = ?");
        $stmtCierre->execute([$id]);

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
