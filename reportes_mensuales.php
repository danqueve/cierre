<?php
require __DIR__ . '/includes/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

require __DIR__ . '/includes/reporte_datos.php';

$anios = range((int)date('Y') + 1, 2024);
if (!in_array($anio_actual, $anios)) $anios[] = $anio_actual;
rsort($anios);
$mostrar_comision = ($zona_filtro !== 'todas');   // columna opcional en el detalle por zona
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reporte Mensual - <?= e($meses[$mes_actual]) ?> <?= $anio_actual ?></title>
    <link rel="stylesheet" href="assets/css/organic.css">
    <link rel="stylesheet" href="assets/css/imperio.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>window.addEventListener('load', function() { if (typeof lucide !== 'undefined') lucide.createIcons(); });</script>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <main class="imp-main">
        <div class="sheet-page">

            <!-- Filtros (fuera de la hoja) -->
            <form method="GET" class="rep-filters no-print" id="repForm">
                <div class="sheet-toolbar">
                    <div class="imp-page-head">
                        <span class="imp-kicker">Reportes · Exportar PDF</span>
                        <h2>Reporte mensual de cobranzas</h2>
                    </div>
                    <div class="field" style="width:170px;">
                        <label for="mes">Mes</label>
                        <select name="mes" id="mes" class="input" onchange="this.form.submit()">
                            <?php foreach ($meses as $num => $nom): ?>
                                <option value="<?= $num ?>" <?= $num == $mes_actual ? 'selected' : '' ?>><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field" style="width:120px;">
                        <label for="anio">Año</label>
                        <select name="anio" id="anio" class="input" onchange="this.form.submit()">
                            <?php foreach ($anios as $a): ?>
                                <option value="<?= $a ?>" <?= $a == $anio_actual ? 'selected' : '' ?>><?= $a ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="window.print()">
                        <i data-lucide="printer" style="width:16px; height:16px;"></i> Imprimir / Guardar PDF
                    </button>
                </div>

                <div class="imp-chips">
                    <label class="imp-chip">
                        <input type="radio" name="zona" value="todas" <?= $zona_filtro === 'todas' ? 'checked' : '' ?> onchange="this.form.submit()">
                        <span>Todas las zonas</span>
                    </label>
                    <?php foreach ($zonas_lista as $z): ?>
                        <label class="imp-chip">
                            <input type="radio" name="zona" value="<?= e($z) ?>" <?= $z === $zona_filtro ? 'checked' : '' ?> onchange="this.form.submit()">
                            <span><?= e($z) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </form>

            <?php require __DIR__ . '/includes/reporte_hoja.php'; ?>
        </div>
    </main>
</body>
</html>
