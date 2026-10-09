<?php
require __DIR__ . '/includes/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$id = (int)($_GET['id'] ?? 0);

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
$pctComision = (float)($cierre['porcentaje_comision'] ?? 6);
$pctTxt = rtrim(rtrim(number_format($pctComision, 2, ',', ''), '0'), ',');
$comision = $totalCobrado * $pctComision / 100;
$saldoFavor = $cierre['saldo_favor'];
$descuentoCreditos = $cierre['descuento_creditos'] ?? 0;

$sueldoCobrador = ($comision + $saldoFavor) - ($totalGasto + $descuentoCreditos);
$netoRendir = $totalCobrado - ($comision + $saldoFavor) + $descuentoCreditos;

$dias_nom = ['LUNES' => 'Lunes', 'MARTES' => 'Martes', 'MIERCOLES' => 'Miércoles', 'JUEVES' => 'Jueves', 'VIERNES' => 'Viernes', 'SABADO' => 'Sábado'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <title>Liquidación - <?= htmlspecialchars($cierre['zona']) ?></title>
    <link rel="stylesheet" href="assets/css/organic.css">
    <link rel="stylesheet" href="assets/css/imperio.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>window.addEventListener('load', function() { if (typeof lucide !== 'undefined') lucide.createIcons(); });</script>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <main class="imp-main">
        <div class="sheet-page">
            <div class="sheet-toolbar no-print">
                <div class="imp-page-head">
                    <span class="imp-kicker">Vista de impresión · A4</span>
                    <h2>Cierre de <?= htmlspecialchars($cierre['zona']) ?></h2>
                </div>
                <a href="historial.php" class="btn btn-secondary">
                    <i data-lucide="arrow-left" style="width:16px; height:16px;"></i> Volver al historial
                </a>
                <button type="button" onclick="window.print()" class="btn btn-primary">
                    <i data-lucide="printer" style="width:16px; height:16px;"></i> Imprimir / Guardar PDF
                </button>
            </div>

            <div class="sheet-wrap">
                <div class="sheet compact">

                    <div class="sheet-head">
                        <img class="sheet-logo" src="assets/img/logo.png" alt="Imperio">
                        <div class="sheet-brand"><b>Imperio<span>.</span></b><small>Sistema de gestión</small></div>
                        <span class="tag tag-accent" style="font-size:12px; padding:6px 12px;"><?= htmlspecialchars($cierre['zona']) ?></span>
                    </div>

                    <div class="sheet-title">
                        <h1>Liquidación semanal de cobranza</h1>
                        <span class="sheet-sub">Gestión de zona: <strong><?= htmlspecialchars($cierre['zona']) ?></strong></span>
                    </div>

                    <div class="sheet-info">
                        <div><small>Semana del</small><strong><?= date('d/m/Y', strtotime($cierre['fecha_inicio'])) ?></strong></div>
                        <div><small>Fecha emisión</small><strong><?= date('d/m/Y') ?></strong></div>
                        <div><small>Usuario</small><strong><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></strong></div>
                    </div>

                    <div class="sheet-table-wrap">
                        <table class="sheet-table">
                            <thead>
                                <tr>
                                    <th>Día</th>
                                    <th class="r">Efectivo</th>
                                    <th class="r">Transf.</th>
                                    <th class="r">Total</th>
                                    <th class="r">Gastos</th>
                                    <th>Detalle gasto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($detalles as $d):
                                    $sub = $d['efectivo'] + $d['transferencia'];
                                ?>
                                <tr>
                                    <td><strong><?= $dias_nom[$d['dia_semana']] ?? htmlspecialchars($d['dia_semana']) ?></strong></td>
                                    <td class="r"><?= formatCurrency($d['efectivo']) ?></td>
                                    <td class="r"><?= formatCurrency($d['transferencia']) ?></td>
                                    <td class="r"><strong><?= formatCurrency($sub) ?></strong></td>
                                    <td class="r gasto-col"><?= ($d['gasto_monto'] > 0) ? formatCurrency($d['gasto_monto']) : '-' ?></td>
                                    <td class="note"><?= htmlspecialchars($d['gasto_concepto'] ?? '') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>TOTALES</td>
                                    <td class="r"><?= formatCurrency($totalEf) ?></td>
                                    <td class="r"><?= formatCurrency($totalTr) ?></td>
                                    <td class="r"><?= formatCurrency($totalCobrado) ?></td>
                                    <td class="r"><?= formatCurrency($totalGasto) ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="sheet-two">
                        <!-- 1. Liquidación del cobrador -->
                        <div class="sheet-box">
                            <h4>Liquidación cobrador</h4>
                            <div class="sheet-line"><span>(+) Comisión (<?= $pctTxt ?>%)</span><span><?= formatCurrency($comision) ?></span></div>
                            <div class="sheet-line"><span>(+) Saldo a favor</span><span><?= formatCurrency($saldoFavor) ?></span></div>
                            <?php if($cierre['saldo_concepto']): ?>
                                <div class="sheet-line-note">(<?= htmlspecialchars($cierre['saldo_concepto']) ?>)</div>
                            <?php endif; ?>
                            <div class="sheet-line"><span>(−) Desc. gastos</span><span>− <?= formatCurrency($totalGasto) ?></span></div>
                            <div class="sheet-line"><span>(−) Desc. créditos</span><span>− <?= formatCurrency($descuentoCreditos) ?></span></div>
                            <?php if($cierre['descuento_creditos_concepto']): ?>
                                <div class="sheet-line-note">(<?= htmlspecialchars($cierre['descuento_creditos_concepto']) ?>)</div>
                            <?php endif; ?>
                            <div class="sheet-grand"><strong>A COBRAR</strong><span><?= formatCurrency($sueldoCobrador) ?></span></div>
                        </div>

                        <!-- 2. Neto a rendir empresa -->
                        <div class="sheet-box sage">
                            <h4>Neto a rendir (empresa)</h4>
                            <div class="sheet-line"><span>Total cobranza bruta</span><strong><?= formatCurrency($totalCobrado) ?></strong></div>
                            <div class="sheet-line"><span>(−) Comisión (<?= $pctTxt ?>%)</span><span>− <?= formatCurrency($comision) ?></span></div>
                            <div class="sheet-line"><span>(−) Saldo a favor</span><span>− <?= formatCurrency($saldoFavor) ?></span></div>
                            <div class="sheet-line"><span>(+) Retención créditos</span><span>+ <?= formatCurrency($descuentoCreditos) ?></span></div>
                            <div class="sheet-grand"><strong>TOTAL FINAL</strong><span><?= formatCurrency($netoRendir) ?></span></div>
                        </div>
                    </div>

                    <span class="sheet-note">* Los gastos fueron descontados del sueldo del cobrador.</span>

                    <div class="sheet-signs">
                        <div class="sheet-sign"><strong>Firma administración</strong></div>
                    </div>

                    <div class="sheet-foot">Documento generado por el Sistema de Gestión.</div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
