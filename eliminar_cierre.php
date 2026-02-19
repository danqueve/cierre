<?php
require 'db.php';
session_start();

// 1. Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// 2. Obtener y validar el ID
$id = $_GET['id'] ?? 0;

if ($id > 0) {
    try {
        // Iniciar transacción para asegurar integridad
        $pdo->beginTransaction();

        // 3. Eliminar los detalles diarios asociados
        $stmtDetalles = $pdo->prepare("DELETE FROM detalles_diarios WHERE cierre_id = ?");
        $stmtDetalles->execute([$id]);

        // 4. Eliminar el registro principal de cierre
        $stmtCierre = $pdo->prepare("DELETE FROM cierres_semanales WHERE id = ?");
        $stmtCierre->execute([$id]);

        // Confirmar transacción
        $pdo->commit();

        // Redirigir con éxito
        header("Location: historial.php?status=deleted");
        exit;

    } catch (Exception $e) {
        // Si hay error, revertir cambios
        $pdo->rollBack();
        header("Location: historial.php?status=error&msg=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    // ID inválido
    header("Location: historial.php?status=error&msg=ID_Invalido");
    exit;
}
?>
