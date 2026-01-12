<?php
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$id = $_GET['id'] ?? 0;

// Obtener datos de la cabecera
$stmt = $pdo->prepare("SELECT * FROM cierres_semanales WHERE id = ?");
$stmt->execute([$id]);
$cierre = $stmt->fetch();

if (!$cierre) die("Liquidación no encontrada");

// Obtener detalles ordenados por día
$stmtDet = $pdo->prepare("SELECT * FROM detalles_diarios WHERE cierre_id = ? ORDER BY FIELD(dia_semana, 'LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO')");
$stmtDet->execute([$id]);
$detalles = $stmtDet->fetchAll();

// --- CÁLCULOS GLOBALES Y VERIFICACIÓN DE TURNO TARDE ---
$totalMinutos = 0;
$hayTurnoTarde = false; // Bandera para controlar la visualización

foreach($detalles as $d) {
    // Verificar si hay datos en turno tarde en algún día
    if (!empty($d['hora_entrada_tarde']) || !empty($d['hora_salida_tarde'])) {
        $hayTurnoTarde = true;
    }

    // Calcular Turno Mañana
    if($d['hora_entrada'] && $d['hora_salida']) {
        $start = strtotime($d['hora_entrada']);
        $end = strtotime($d['hora_salida']);
        $diff = ($end - $start) / 60; // Diferencia en minutos
        if($diff < 0) $diff += 24 * 60; // Ajuste si cruza medianoche
        $totalMinutos += $diff;
    }
    
    // Calcular Turno Tarde
    if(!empty($d['hora_entrada_tarde']) && !empty($d['hora_salida_tarde'])) {
        $start = strtotime($d['hora_entrada_tarde']);
        $end = strtotime($d['hora_salida_tarde']);
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
    <title>Liquidación Horas - <?= $cierre['zona'] ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Estilos A4 Exclusivos - Minimalista B&N */
        html, body { background-color: #525659; min-height: 100%; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 0; }
        .preview-container { display: flex; justify-content: center; padding: 40px 0; }
        
        /* Hoja A4 */
        .a4-page {
            width: 210mm; min-height: 297mm; padding: 20mm; background: white; 
            color: black; position: relative; box-shadow: 0 0 15px rgba(0,0,0,0.3);
            box-sizing: border-box;
        }

        /* Encabezado */
        .doc-header { text-align: center; border-bottom: 2px solid black; padding-bottom: 15px; margin-bottom: 25px; }
        .doc-header h2 { margin: 0; text-transform: uppercase; font-size: 18pt; font-weight: bold; }
        .doc-header h4 { margin: 5px 0; color: black; font-weight: normal; font-size: 12pt; }

        /* Información Principal */
        .info-box { 
            display: flex; justify-content: space-between; 
            margin-bottom: 30px; font-size: 11pt; border-bottom: 1px solid #ccc; padding-bottom: 15px;
        }
        .info-item strong { font-weight: bold; }

        /* Tabla de Horarios */
        .hours-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 10pt; }
        
        .hours-table th { 
            background-color: #f8f5f5; /* Gris Claro */
            color: black; 
            padding: 8px; 
            text-transform: uppercase; 
            border: 1px solid black; 
            vertical-align: middle;
            font-weight: bold;
        }
        
        .hours-table td { 
            border: 1px solid black; 
            padding: 8px; 
            text-align: center; 
            color: black;
        }
        
        .hours-table tr:nth-child(even) { background-color: #f9f9f9; }
        .th-group { background-color: #d0d0d0 !important; font-size: 9pt; }

        /* Caja de Totales - Estilo Recibo Detallado */
        .total-box { 
            float: right; width: 45%; border: 2px solid black; padding: 15px; margin-top: 10px;
        }
        .total-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 11pt; }
        .sub-detail { font-size: 9pt; color: #444; font-style: italic; margin-top: -5px; margin-bottom: 8px; text-align: right; }
        
        .grand-total { border-top: 2px solid black; padding-top: 10px; font-weight: bold; font-size: 14pt; margin-top: 10px; background-color: #f0f0f0; padding: 10px; }

        /* Firmas */
        .firma-box { margin-top: 80px; display: flex; justify-content: space-around; text-align: center; clear: both; }
        .firma-line { border-top: 1px solid black; width: 200px; margin-top: 60px; font-size: 10pt; }

        /* Ajustes de Impresión */
        @media print {
            body { background: white; margin: 0; }
            .preview-container { padding: 0; }
            .a4-page { box-shadow: none; margin: 0; width: 100%; }
            .no-print { display: none !important; }
            .hours-table th, .grand-total { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="position: fixed; top: 20px; right: 20px; z-index: 1000;">
        <a href="cargar_horas.php" class="btn" style="background: #333; color: white; margin-right: 10px; border: 1px solid #fff;">&larr; Volver</a>
        <button onclick="window.print()" class="btn" style="background: white; color: black; font-weight: bold; border: 2px solid black;">Imprimir Liquidación</button>
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
                        <th colspan="2" class="th-group">Turno Mañana</th>
                        <?php if($hayTurnoTarde): ?>
                            <th colspan="2" class="th-group">Turno Tarde</th>
                        <?php endif; ?>
                        <th rowspan="2" style="width: 15%;">Total Horas</th>
                    </tr>
                    <tr>
                        <th style="width: 15%;">Entrada</th>
                        <th style="width: 15%;">Salida</th>
                        <?php if($hayTurnoTarde): ?>
                            <th style="width: 15%;">Entrada</th>
                            <th style="width: 15%;">Salida</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($detalles as $d): 
                        $minutosDia = 0;
                        // Turno 1
                        if($d['hora_entrada'] && $d['hora_salida']) {
                            $start = strtotime($d['hora_entrada']);
                            $end = strtotime($d['hora_salida']);
                            $diff = ($end - $start) / 60;
                            if($diff < 0) $diff += 24 * 60;
                            $minutosDia += $diff;
                        }
                        // Turno 2
                        if(!empty($d['hora_entrada_tarde']) && !empty($d['hora_salida_tarde'])) {
                            $start = strtotime($d['hora_entrada_tarde']);
                            $end = strtotime($d['hora_salida_tarde']);
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
                        <?php if($hayTurnoTarde): ?>
                            <td><?= !empty($d['hora_entrada_tarde']) ? date('H:i', strtotime($d['hora_entrada_tarde'])) : '-' ?></td>
                            <td><?= !empty($d['hora_salida_tarde']) ? date('H:i', strtotime($d['hora_salida_tarde'])) : '-' ?></td>
                        <?php endif; ?>
                        <td style="font-weight: bold; background-color: #e8e8e8;"><?= $horasStr ?></td>
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