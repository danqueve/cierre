<?php
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$id = $_GET['id'] ?? 0;

// FIX: Si no se pasa un ID (id=0), intentamos buscar el ULTIMO cierre cargado
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

// Obtener detalles ordenados
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
$comision = $totalCobrado * 0.05; // 5% del Total
$saldoFavor = $cierre['saldo_favor'];
$descuentoCreditos = $cierre['descuento_creditos'] ?? 0; // Nuevo campo

// 1. LIQUIDACIÓN DEL COBRADOR (SUELDO)
// Sueldo = (Comisión + Saldo Favor) - (Gastos + Descuento Créditos)
$sueldoCobrador = ($comision + $saldoFavor) - ($totalGasto + $descuentoCreditos);

// 2. NETO A RENDIR A LA EMPRESA
// La empresa recibe el Total Cobrado MENOS lo que efectivamente se le paga al cobrador (Comisión + Saldo).
// PERO, si hay un descuento de crédito, la empresa RETIENE ese dinero, por lo que SUMA al neto a rendir (o resta menos).
// Matemáticamente: Total - (Comision + Saldo) + DescuentoCredito
$netoRendir = $totalCobrado - ($comision + $saldoFavor) + $descuentoCreditos;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Liquidación - <?= $cierre['zona'] ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Estilos generales */
        html, body {
            margin: 0;
            padding: 0;
            background-color: #525659;
            height: 100%;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .preview-container {
            display: flex;
            justify-content: center;
            padding: 40px 0;
            min-height: 100vh;
        }
        
        /* --- HOJA A4 --- */
        .a4-page {
            position: relative;
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 15px rgba(0,0,0,0.3);
            box-sizing: border-box;
            color: #333;
        }

        /* Encabezado */
        .doc-header { 
            text-align: center; 
            margin-bottom: 25px; 
            border-bottom: 3px solid #2c3e50; 
            padding-bottom: 15px; 
            position: relative; 
            z-index: 2; 
        }
        .doc-header h2 { 
            margin: 0; 
            font-size: 24pt; 
            text-transform: uppercase; 
            color: #2c3e50; 
            letter-spacing: 1px;
        }
        .doc-header p { 
            color: #555; 
            margin: 8px 0 0 0; 
            font-size: 13pt; 
            font-weight: 500;
        }
        
        /* Info Documento */
        .doc-info { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 25px; 
            font-size: 11pt; 
            color: #444; 
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-radius: 6px;
            border: 1px solid #e9ecef;
            position: relative; 
            z-index: 2; 
        }
        
        /* Marca de Agua Centrada */
        .watermark {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.06;
        }
        .watermark img { 
            max-width: 70%; 
            max-height: 70%; 
            object-fit: contain; 
            filter: grayscale(100%);
        }

        /* Tabla Principal */
        .main-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 30px; 
            position: relative; 
            z-index: 2; 
            font-size: 10pt; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .main-table th { 
            background-color: #2c3e50; 
            color: white; 
            border: 1px solid #2c3e50; 
            padding: 10px 8px; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
        }
        .main-table td { 
            border: 1px solid #dee2e6; 
            padding: 10px 8px; 
            color: #333; 
        }
        .main-table tr:nth-child(even) { background-color: #f8f9fa; }
        .main-table tfoot td { 
            background-color: #e9ecef; 
            font-weight: bold; 
            border-top: 2px solid #2c3e50; 
            color: #2c3e50; 
            font-size: 11pt;
        }

        /* Sección de Liquidación */
        .liquidation-section {
            display: flex;
            gap: 25px;
            margin-top: 20px;
            position: relative;
            z-index: 2;
        }

        .liq-box {
            border: 1px solid #ced4da;
            padding: 0;
            flex: 1;
            border-radius: 8px;
            overflow: hidden;
            background-color: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .liq-title {
            text-align: center;
            font-weight: bold;
            padding: 10px;
            text-transform: uppercase;
            font-size: 11pt;
            color: white;
            letter-spacing: 0.5px;
        }

        .liq-content {
            padding: 15px;
        }

        .liq-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 10.5pt;
            color: #444;
            padding-bottom: 5px;
            border-bottom: 1px dashed #e9ecef;
        }
        .liq-row:last-child { border-bottom: none; }
        
        .liq-total {
            background-color: #f1f3f5;
            padding: 12px 15px;
            border-top: 1px solid #ced4da;
            font-weight: bold;
            font-size: 13pt;
            display: flex;
            justify-content: space-between;
            color: #2c3e50;
        }

        /* Estilos específicos para cajas */
        .box-cobrador { border-color: #6c757d; }
        .box-cobrador .liq-title { background-color: #6c757d; }
        
        .box-empresa { border-color: #2c3e50; }
        .box-empresa .liq-title { background-color: #2c3e50; }

        /* Firmas */
        .firma-box { 
            margin-top: 80px; 
            display: flex; 
            justify-content: space-around; 
            text-align: center; 
            color: #333; 
            position: relative; 
            z-index: 2; 
        }
        .firma-line { 
            border-top: 1px solid #333; 
            width: 220px; 
            margin-top: 50px; 
            padding-top: 8px; 
            font-size: 10pt; 
            text-transform: uppercase;
        }
        
        /* IMPRESIÓN */
        @media print {
            body { background-color: white; margin: 0; padding: 0; }
            .preview-container { padding: 0; display: block; }
            .a4-page { box-shadow: none; margin: 0; width: 100%; min-height: 100%; page-break-after: always; }
            .no-print { display: none !important; }
            .main-table th { background-color: #eee !important; color: black !important; border-color: #000; }
            .liq-title { background-color: #eee !important; color: black !important; border-bottom: 1px solid #000; }
            .liq-total { background-color: #eee !important; }
        }
    </style>
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</head>
<body> 

    <div class="no-print" style="position: fixed; top: 20px; right: 20px; z-index: 1000;">
        <button onclick="window.close()" style="background: #e74c3c; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; box-shadow: 0 2px 5px rgba(0,0,0,0.3);">
            Cerrar Pestaña
        </button>
        <button onclick="window.print()" style="background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; margin-left: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.3);">
            Imprimir
        </button>
    </div>

    <div class="preview-container">
        <!-- INICIO HOJA A4 -->
        <div class="a4-page">
            
            <!-- MARCA DE AGUA CENTRADA -->
            <div class="watermark">
                 <img src="img/logo.png" alt="Logo"> 
            </div>

            <div class="doc-header">
                <h2>Liquidación Semanal de Cobranza</h2>
                <p>Gestión de Zona: <strong><?= $cierre['zona'] ?></strong></p>
            </div>

            <div class="doc-info">
                <div><strong>Semana del:</strong> <?= date('d/m/Y', strtotime($cierre['fecha_inicio'])) ?></div>
                <div><strong>Fecha Emisión:</strong> <?= date('d/m/Y') ?></div>
                <div><strong>Usuario:</strong> <?= $_SESSION['username'] ?? 'Admin' ?></div>
            </div>

            <!-- Tabla de Detalles -->
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
                        <td style="text-align: right; font-weight: bold; background-color: rgba(0,0,0,0.03);"><?= formatCurrency($sub) ?></td>
                        <td style="text-align: right; color: #c0392b;"><?= $d['gasto_monto'] > 0 ? formatCurrency($d['gasto_monto']) : '-' ?></td>
                        <td style="font-style: italic; font-size: 9pt;"><?= $d['gasto_concepto'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>TOTALES</td>
                        <td style="text-align: right;"><?= formatCurrency($totalEf) ?></td>
                        <td style="text-align: right;"><?= formatCurrency($totalTr) ?></td>
                        <td style="text-align: right;"><?= formatCurrency($totalCobrado) ?></td>
                        <td style="text-align: right; color: #c0392b;"><?= formatCurrency($totalGasto) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>

            <!-- SECCIÓN NUEVA: CÁLCULOS SEPARADOS -->
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
                                (<?= $cierre['saldo_concepto'] ?>)
                            </div>
                        <?php endif; ?>
                        
                        <div class="liq-row" style="color: #c0392b;">
                            <span>(-) Desc. Gastos:</span>
                            <span>- <?= formatCurrency($totalGasto) ?></span>
                        </div>

                        <!-- NUEVA FILA: DESCUENTO CRÉDITOS -->
                        <div class="liq-row" style="color: #c0392b;">
                            <span>(-) Desc. Créditos:</span>
                            <span>- <?= formatCurrency($descuentoCreditos) ?></span>
                        </div>
                        <?php if($cierre['descuento_creditos_concepto']): ?>
                            <div style="font-size: 9pt; color: #666; font-style: italic; text-align: right; margin-top: -5px;">
                                (<?= $cierre['descuento_creditos_concepto'] ?>)
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
                        <!-- NUEVA FILA: SUMA POR RETENCIÓN DE CRÉDITO -->
                        <div class="liq-row" style="color: #27ae60;">
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

            <div style="position: absolute; bottom: 15mm; left: 20mm; font-size: 9pt; color: #777;">
                Documento generado automáticamente - Uso interno exclusivo.
            </div>

        </div>
        <!-- FIN HOJA A4 -->
    </div>

</body>
</html>