<?php
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$id = $_GET['id'] ?? 0;

// Si no se pasa un ID, buscamos el último cierre cargado
if ($id == 0) {
    $stmtLast = $pdo->query("SELECT id FROM cierres_semanales ORDER BY id DESC LIMIT 1");
    $lastEntry = $stmtLast->fetch();
    if ($lastEntry) {
        $id = $lastEntry['id'];
    }
}

// Obtener cabecera
$stmt = $pdo->prepare("SELECT * FROM cierres_semanales WHERE id = ?");
$stmt->execute([$id]);
$cierre = $stmt->fetch();

if (!$cierre) {
    die("
        <div style='font-family: sans-serif; text-align: center; padding-top: 50px; color: #333;'>
            <h2>⚠️ Cierre no encontrado</h2>
            <p>No hay datos cargados en el sistema para mostrar.</p>
            <a href='cargar.php' style='background: #2c3e50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ir a Cargar Datos</a>
        </div>
    ");
}

// Obtener detalles ordenados por día
$stmtDet = $pdo->prepare("
    SELECT * FROM detalles_diarios 
    WHERE cierre_id = ? 
    ORDER BY FIELD(dia_semana, 'LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO')
");
$stmtDet->execute([$id]);
$detalles = $stmtDet->fetchAll();

// --- CÁLCULOS ---
$totalEf = 0; 
$totalTr = 0; 
$totalGasto = 0;

foreach($detalles as $d) {
    $totalEf += $d['efectivo'];
    $totalTr += $d['transferencia'];
    $totalGasto += $d['gasto_monto'];
}

$totalCobrado = $totalEf + $totalTr;
$comision = $totalCobrado * 0.05; 
$saldoFavor = $cierre['saldo_favor'];
$descuentoCreditos = $cierre['descuento_creditos'] ?? 0;

$sueldoCobrador = ($comision + $saldoFavor) - ($totalGasto + $descuentoCreditos);
$netoRendir = $totalCobrado - ($comision + $saldoFavor) + $descuentoCreditos;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Liquidación - <?= htmlspecialchars($cierre['zona']) ?></title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <style>
        /* --- ESTILOS GENERALES DE LA PÁGINA --- */
        body { 
            background-color: #525659; 
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .preview-container { 
            display: flex; 
            justify-content: center; 
            padding: 40px 0; 
            min-height: 100vh; 
        }
        
        .a4-page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 15px rgba(0,0,0,0.3);
            color: #333;
            position: relative;
            box-sizing: border-box;
        }

        /* --- CABECERA DEL DOCUMENTO --- */
        .doc-header { 
            text-align: center; 
            margin-bottom: 25px; 
            border-bottom: 3px solid #2c3e50; 
            padding-bottom: 15px; 
        }
        .doc-header h2 { 
            margin: 0; 
            font-size: 24pt; 
            color: #066bcf; /* Azul como en la captura */
        }
        .doc-header p { 
            color: #333; 
            margin: 8px 0 0 0; 
            font-size: 13pt; 
        }
        
        .doc-info { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 25px; 
            font-size: 11pt; 
            background-color: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
        }

        /* --- ESTILOS DE TABLA (TEXTO NEGRO) --- */
        .main-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 30px; 
            background-color: #fff;
        }

        .main-table th { 
            background-color: #f2f2f2; 
            color: #000; /* Texto Negro */
            border: 1px solid #ccc; 
            padding: 12px 8px; 
            text-transform: uppercase; 
            font-weight: bold;
            text-align: center;
        }

        .main-table td { 
            border: 1px solid #dee2e6; 
            padding: 10px 8px; 
            color: #000; /* Texto Negro */
            vertical-align: middle;
        }

        .main-table tr:nth-child(even) { 
            background-color: #f9f9f9; 
        }

        .main-table tfoot td { 
            background-color: #eeeeee; 
            font-weight: bold; 
            border-top: 2px solid #000; 
            color: #000; /* Texto Negro */
            font-size: 11pt;
        }

        /* --- SECCIÓN DE LIQUIDACIÓN --- */
        .liquidation-section {
            display: flex; 
            justify-content: space-between; 
            gap: 20px; 
            margin-top: 30px;
        }
        
        .liq-box {
            border: 1px solid #ccc;
            border-radius: 8px;
            overflow: hidden;
            width: 48%;
            background: #fff;
        }

        .liq-title {
            padding: 10px;
            font-weight: bold;
            text-align: center;
            color: white;
            text-transform: uppercase;
        }

        .box-cobrador .liq-title { background-color: #6c757d; }
        .box-empresa .liq-title { background-color: #2c3e50; }

        .liq-content { padding: 15px; }

        .liq-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px dashed #eee;
            color: #000;
        }

        .liq-total {
            background-color: #f8f9fa;
            padding: 12px;
            font-weight: bold;
            font-size: 13pt;
            display: flex;
            justify-content: space-between;
            border-top: 2px solid #333;
            color: #000;
        }

        .firma-box { 
            margin-top: 80px; 
            display: flex; 
            justify-content: center; 
            text-align: center; 
        }
        .firma-line { 
            border-top: 1px solid #000; 
            width: 250px; 
            padding-top: 8px; 
        }

        /* --- ESTILOS DE IMPRESIÓN --- */
        @media print {
            body { background: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .preview-container { display: block !important; padding: 0 !important; }
            .a4-page { box-shadow: none !important; border: none !important; width: 100% !important; margin: 0 !important; padding: 10mm !important; }
            .no-print { display: none !important; }
            
            .main-table th { background-color: #e0e0e0 !important; border: 1px solid #000 !important; }
            .main-table td { border: 1px solid #000 !important; }
            .liq-box { border: 1px solid #000 !important; }
        }
    </style>
</head>
<body> 

    <!-- Botones Flotantes -->
    <div class="no-print" style="position: fixed; top: 20px; right: 20px; z-index: 1000;">
        <button onclick="window.close()" style="background: #e74c3c; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; box-shadow: 0 2px 5px rgba(0,0,0,0.3);">
            Cerrar Pestaña
        </button>
        <button onclick="window.print()" style="background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; margin-left: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.3);">
            Imprimir
        </button>
    </div>

    <div class="preview-container">
        <div class="a4-page">
            
            <div class="doc-header">
                <h2>Liquidación Semanal de Cobranza</h2>
                <p>Gestión de Zona: <strong><?= htmlspecialchars($cierre['zona']) ?></strong></p>
            </div>

            <div class="doc-info">
                <div><strong>Semana del:</strong> <?= date('d/m/Y', strtotime($cierre['fecha_inicio'])) ?></div>
                <div><strong>Fecha Emisión:</strong> <?= date('d/m/Y') ?></div>
                <div><strong>Usuario:</strong> <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></div>
            </div>

            <!-- Tabla de Detalles con texto negro -->
            <table class="main-table">
                <thead>
                    <tr>
                        <th style="width: 15%;">Día</th>
                        <th style="width: 15%;">Efectivo</th>
                        <th style="width: 15%;">Transf.</th>
                        <th style="width: 15%;">Total</th>
                        <th style="width: 15%;">Gastos</th>
                        <th>Detalle Gasto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($detalles as $d): 
                        $sub = $d['efectivo'] + $d['transferencia'];
                    ?>
                    <tr>
                        <td style="font-weight: 600;"><?= $d['dia_semana'] ?></td>
                        <td style="text-align: right;"><?= formatCurrency($d['efectivo']) ?></td>
                        <td style="text-align: right;"><?= formatCurrency($d['transferencia']) ?></td>
                        <td style="text-align: right; font-weight: bold;"><?= formatCurrency($sub) ?></td>
                        <td style="text-align: right;"><?= $d['gasto_monto'] > 0 ? formatCurrency($d['gasto_monto']) : '-' ?></td>
                        <td style="font-style: italic; font-size: 9pt;"><?= htmlspecialchars($d['gasto_concepto']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>TOTALES</td>
                        <td style="text-align: right;"><?= formatCurrency($totalEf) ?></td>
                        <td style="text-align: right;"><?= formatCurrency($totalTr) ?></td>
                        <td style="text-align: right;"><?= formatCurrency($totalCobrado) ?></td>
                        <td style="text-align: right;"><?= formatCurrency($totalGasto) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>

            <!-- SECCIÓN DE LIQUIDACIÓN -->
            <div class="liquidation-section">
                
                <!-- 1. LIQUIDACIÓN DEL COBRADOR -->
                <div class="liq-box box-cobrador">
                    <div class="liq-title">Liquidación Cobrador</div>
                    <div class="liq-content">
                        <div class="liq-row">
                            <span>(+) Comisión (5%):</span>
                            <span><?= formatCurrency($comision) ?></span>
                        </div>
                        <div class="liq-row">
                            <span>(+) Saldo a Favor:</span>
                            <span><?= formatCurrency($saldoFavor) ?></span>
                        </div>
                        <?php if($cierre['saldo_concepto']): ?>
                            <div style="font-size: 9pt; color: #666; font-style: italic; text-align: right; margin-top: -5px;">
                                (<?= htmlspecialchars($cierre['saldo_concepto']) ?>)
                            </div>
                        <?php endif; ?>
                        
                        <div class="liq-row">
                            <span>(-) Desc. Gastos:</span>
                            <span>- <?= formatCurrency($totalGasto) ?></span>
                        </div>

                        <div class="liq-row">
                            <span>(-) Desc. Créditos:</span>
                            <span>- <?= formatCurrency($descuentoCreditos) ?></span>
                        </div>
                        <?php if($cierre['descuento_creditos_concepto']): ?>
                            <div style="font-size: 9pt; color: #666; font-style: italic; text-align: right; margin-top: -5px;">
                                (<?= htmlspecialchars($cierre['descuento_creditos_concepto']) ?>)
                            </div>
                        <?php endif; ?>

                        <div class="liq-total" style="margin-top: 10px;">
                            <span>A Cobrar:</span>
                            <span><?= formatCurrency($sueldoCobrador) ?></span>
                        </div>
                    </div>
                </div>

                <!-- 2. NETO A RENDIR EMPRESA -->
                <div class="liq-box box-empresa">
                    <div class="liq-title">Neto a Rendir (Empresa)</div>
                    <div class="liq-content">
                        <div class="liq-row">
                            <span>Total Cobranza Bruta:</span>
                            <strong><?= formatCurrency($totalCobrado) ?></strong>
                        </div>
                        <div class="liq-row">
                            <span>(-) Comisión (5%):</span>
                            <span>- <?= formatCurrency($comision) ?></span>
                        </div>
                        <div class="liq-row">
                            <span>(-) Saldo a Favor:</span>
                            <span>- <?= formatCurrency($saldoFavor) ?></span>
                        </div>
                        <div class="liq-row">
                            <span>(+) Retención Créditos:</span>
                            <span>+ <?= formatCurrency($descuentoCreditos) ?></span>
                        </div>
                        
                        <div style="margin-top: 15px; font-size: 9pt; color: #555; font-style: italic; text-align: center; border: 1px dashed #ccc; padding: 5px;">
                            * Los gastos fueron descontados del sueldo del cobrador.
                        </div>
                    </div>
                    <div class="liq-total">
                        <span>TOTAL FINAL:</span>
                        <span><?= formatCurrency($netoRendir) ?></span>
                    </div>
                </div>

            </div>

            <div class="firma-box">
                <div>
                    <div class="firma-line">Firma Administración</div>
                </div>
            </div>

        </div>
    </div>

</body>
</html>