<?php
require __DIR__ . '/includes/db.php';
// 1. Validar Sesión
if (!isset($_SESSION['user_id'])) {
    die("Acceso denegado. <a href='index.php'>Iniciar Sesión</a>");
}

// 2. Filtros y datos (misma lógica que reportes_mensuales.php)
require __DIR__ . '/includes/reporte_datos.php';
$mostrar_comision = ($zona_filtro !== 'todas');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <title>Imprimir Reporte - <?= e($meses[$mes_actual]) ?> <?= $anio_actual ?></title>
    <link rel="stylesheet" href="assets/css/organic.css">
    <link rel="stylesheet" href="assets/css/imperio.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <main class="imp-main">
        <div class="sheet-page">
            <div class="sheet-toolbar no-print">
                <div class="imp-page-head">
                    <span class="imp-kicker">Vista de impresión · A4</span>
                    <h2>Reporte de <?= e($meses[$mes_actual]) ?> <?= $anio_actual ?></h2>
                </div>
                <button type="button" onclick="window.print()" class="btn btn-primary">
                    <i data-lucide="printer" style="width:16px; height:16px;"></i> Imprimir / Guardar PDF
                </button>
            </div>

            <?php require __DIR__ . '/includes/reporte_hoja.php'; ?>
        </div>
    </main>

    <script>
        // Auto-imprimir al cargar
        window.onload = function() {
            if (typeof lucide !== 'undefined') lucide.createIcons();
            setTimeout(function() { window.print(); }, 500);
        };
    </script>
</body>
</html>
