<?php
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$id = $_GET['id'] ?? 0;
$autoExport = isset($_GET['export']) && $_GET['export'] === '1';

// Obtener datos de la cabecera
$stmt = $pdo->prepare("SELECT * FROM cierres_semanales WHERE id = ?");
$stmt->execute([$id]);
$cierre = $stmt->fetch();

if (!$cierre) die("Liquidación no encontrada");

// Obtener detalles ordenados por día
$stmtDet = $pdo->prepare("SELECT * FROM detalles_diarios WHERE cierre_id = ? ORDER BY FIELD(dia_semana, 'LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO')");
$stmtDet->execute([$id]);
$detalles = $stmtDet->fetchAll();

// --- CÁLCULOS GLOBALES ---
$totalMinutos = 0;

foreach($detalles as $d) {
    if($d['hora_entrada'] && $d['hora_salida']) {
        $start = strtotime($d['hora_entrada']);
        $end = strtotime($d['hora_salida']);
        $diff = ($end - $start) / 60;
        if($diff < 0) $diff += 24 * 60;
        $totalMinutos += $diff;
    }
}

// Convertir minutos totales a formato Horas y Decimales
$horasReales = floor($totalMinutos / 60);
$minutosReales = $totalMinutos % 60;
$totalHorasDecimal = $totalMinutos / 60;

// --- CÁLCULO MONETARIO FINAL ---
$subtotalHoras = $totalHorasDecimal * $cierre['valor_hora'];
$saldoFavor = $cierre['saldo_favor'] ?? 0;
$descuento = $cierre['descuento_creditos'] ?? 0; // Usamos el campo que guardamos como 'descuento_creditos'

$totalPagar = $subtotalHoras + $saldoFavor - $descuento;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="img/logo.png">
    <title>Liquidación Horas - <?= $cierre['zona'] ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background-color: #525659; }
        .preview-container { display: flex; justify-content: center; padding: 40px 0; min-height: 100vh; }

        .a4-page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 0 15px rgba(0,0,0,0.4);
            color: #000;
            position: relative;
            font-family: Arial, sans-serif;
            font-size: 10pt;
        }

        /* Encabezado del documento */
        .doc-header {
            text-align: center;
            border-bottom: 3px solid #1a1a2e;
            padding-bottom: 14px;
            margin-bottom: 22px;
        }
        .doc-header h2 {
            margin: 0;
            text-transform: uppercase;
            font-size: 18pt;
            font-weight: bold;
            color: #1a1a2e;
            letter-spacing: 1px;
        }
        .doc-header h4 {
            margin: 4px 0 0;
            color: #444;
            font-weight: normal;
            font-size: 11pt;
        }

        /* Info box (empleado, semana, fecha) */
        .a4-page .info-box {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
            padding: 10px 15px;
            background: #f0f4ff;
            border: 1px solid #c0cce8;
            border-radius: 6px;
        }
        .a4-page .info-item {
            font-size: 10pt;
            color: #222;
        }
        .a4-page .info-item strong {
            color: #1a1a2e;
        }

        /* Tabla de horas */
        .a4-page .hours-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 22px;
        }
        .a4-page .hours-table th {
            background: #1a1a2e;
            color: #fff;
            padding: 8px 10px;
            text-align: center;
            font-size: 10pt;
            border: 1px solid #1a1a2e;
        }
        .a4-page .hours-table .th-group {
            background: #2d3561;
            color: #fff;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .a4-page .hours-table td {
            padding: 7px 10px;
            border: 1px solid #ccc;
            text-align: center;
            font-size: 10pt;
            color: #000;
            background: #fff;
        }
        .a4-page .hours-table tbody tr:nth-child(even) td {
            background: #f7f8fc;
        }
        .a4-page .hours-table tbody tr:hover td {
            background: #eef1fb;
        }

        /* Caja de totales */
        .a4-page .total-box {
            margin-top: 18px;
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 14px 18px;
            background: #fafafa;
            max-width: 380px;
            margin-left: auto;
        }
        .a4-page .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            color: #222;
            font-size: 10pt;
            border-bottom: 1px solid #eee;
        }
        .a4-page .total-row:last-child { border-bottom: none; }
        .a4-page .total-row strong { color: #000; }
        .a4-page .grand-total {
            font-size: 13pt;
            font-weight: bold;
            border-top: 2px solid #1a1a2e !important;
            margin-top: 8px;
            padding-top: 10px;
            color: #1a1a2e;
            border-bottom: none !important;
        }
        .a4-page .sub-detail {
            font-size: 8.5pt;
            color: #555;
            font-style: italic;
            padding-left: 8px;
            margin-top: -2px;
        }

        /* Firmas */
        .a4-page .firma-box {
            display: flex;
            justify-content: space-around;
            margin-top: 50px;
        }
        .a4-page .firma-line {
            border-top: 1.5px solid #333;
            padding-top: 8px;
            text-align: center;
            width: 160px;
            color: #222;
            font-size: 9pt;
            font-weight: bold;
        }

        @media print {
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            body { background: #fff; }
            .no-print { display: none !important; }
            .preview-container { display: block; padding: 0; }
            .a4-page { box-shadow: none; margin: 0; width: 100%; min-height: 100%; }
        }
    </style>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        window.addEventListener('load', function() {
            lucide.createIcons();
            <?php if($autoExport): ?>
            const element = document.querySelector('.a4-page');
            const nombre = '<?= addslashes($cierre['zona']) ?>';
            const fecha  = '<?= date('d-m-Y', strtotime($cierre['fecha_inicio'])) ?>';
            const opt = {
                margin:     0,
                filename:   'Liquidacion-' + nombre + '-' + fecha + '.pdf',
                image:      { type: 'jpeg', quality: 0.98 },
                html2canvas:{ scale: 2, useCORS: true, letterRendering: true },
                jsPDF:      { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
            <?php endif; ?>
        });
    </script>
</head>
<body>

    <div class="no-print" style="position: fixed; top: 20px; right: 20px; z-index: 1000;">
        <a href="cargar_horas.php" class="btn" style="background: white; color: var(--text-main); margin-right: 10px; border: 1px solid var(--border-color); box-shadow:0 2px 8px rgba(0,0,0,0.08);">&larr; Volver</a>
        <button onclick="window.print()" class="btn" style="background: var(--accent-purple); color: white; font-weight: bold; border: none; box-shadow:0 4px 12px rgba(124,58,237,0.3);">
            Imprimir <i data-lucide="printer" style="width:16px; height:16px; vertical-align:middle;"></i>
        </button>
    </div>

    <div class="preview-container">
        <div class="a4-page">
            
            <div class="doc-header">
                <h2>Liquidación de Jornales</h2>
                <h4>Control de Horarios Semanal</h4>
            </div>

            <div class="info-box">
                <div class="info-item">Empleado: <strong><?= $cierre['zona'] ?></strong></div>
                <div class="info-item">Semana del: <strong><?= date('d/m/Y', strtotime($cierre['fecha_inicio'])) ?></strong></div>
                <div class="info-item">Fecha Emisión: <strong><?= date('d/m/Y') ?></strong></div>
            </div>

            <table class="hours-table">
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 15%;">Día</th>
                        <th colspan="2" class="th-group">Horario</th>
                        <th rowspan="2" style="width: 15%;">Total Horas</th>
                    </tr>
                    <tr>
                        <th style="width: 15%;">Entrada</th>
                        <th style="width: 15%;">Salida</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($detalles as $d): 
                        $minutosDia = 0;
                        if($d['hora_entrada'] && $d['hora_salida']) {
                            $start = strtotime($d['hora_entrada']);
                            $end = strtotime($d['hora_salida']);
                            $diff = ($end - $start) / 60;
                            if($diff < 0) $diff += 24 * 60;
                            $minutosDia += $diff;
                        }

                        $horasStr = "-";
                        if($minutosDia > 0) {
                            $h = floor($minutosDia / 60);
                            $m = $minutosDia % 60;
                            $horasStr = "{$h}h {$m}m";
                        }
                    ?>
                    <tr>
                        <td style="font-weight: bold; text-align: left; padding-left: 15px;"><?= $d['dia_semana'] ?></td>
                        <td><?= $d['hora_entrada'] ? date('H:i', strtotime($d['hora_entrada'])) : '-' ?></td>
                        <td><?= $d['hora_salida'] ? date('H:i', strtotime($d['hora_salida'])) : '-' ?></td>
                        <td style="font-weight: bold; background-color: #dde3f5; color: #1a1a2e;"><?= $horasStr ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="total-box">
                <!-- 1. Horas Trabajadas -->
                <div class="total-row">
                    <span>Horas Trabajadas:</span>
                    <strong><?= $horasReales ?>h <?= $minutosReales ?>m</strong>
                </div>
                <div class="total-row" style="border-bottom: 1px solid #ccc; padding-bottom: 5px;">
                    <span>Valor Hora:</span>
                    <span><?= formatCurrency($cierre['valor_hora']) ?></span>
                </div>
                <div class="total-row" style="margin-top: 5px;">
                    <span>Subtotal Horas:</span>
                    <span><?= formatCurrency($subtotalHoras) ?></span>
                </div>

                <!-- 2. Ingresos Extra -->
                <?php if($saldoFavor > 0): ?>
                <div class="total-row" style="color: black;">
                    <span>(+) Ingresos Extra:</span>
                    <span><?= formatCurrency($saldoFavor) ?></span>
                </div>
                <?php if($cierre['saldo_concepto']): ?>
                    <div class="sub-detail">(<?= $cierre['saldo_concepto'] ?>)</div>
                <?php endif; ?>
                <?php endif; ?>

                <!-- 3. Descuentos -->
                <?php if($descuento > 0): ?>
                <div class="total-row" style="color: black;">
                    <span>(-) Descuentos/Adelantos:</span>
                    <span>- <?= formatCurrency($descuento) ?></span>
                </div>
                <?php if($cierre['descuento_creditos_concepto']): ?>
                    <div class="sub-detail">(<?= $cierre['descuento_creditos_concepto'] ?>)</div>
                <?php endif; ?>
                <?php endif; ?>

                <!-- 4. TOTAL FINAL -->
                <div class="total-row grand-total">
                    <span>TOTAL A PAGAR:</span>
                    <span><?= formatCurrency($totalPagar) ?></span>
                </div>
            </div>

            <div class="firma-box">
                <div>
                    <div class="firma-line">Firma Empleado<br><span style="font-weight:normal; font-size:9pt;"><?= $cierre['zona'] ?></span></div>
                </div>
                <div>
                    <div class="firma-line">Firma Responsable</div>
                </div>
            </div>
            
            <div style="position: absolute; bottom: 15mm; left: 20mm; font-size: 9pt; color: #555;">
                Documento generado por el Sistema de Gestión.
            </div>

        </div>
    </div>

</body>
</html>