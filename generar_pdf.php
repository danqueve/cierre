<?php
require 'db.php';
requireAuth();
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$tipo = $_GET['tipo'] ?? '';

if (!in_array($tipo, ['cierre', 'mensual'])) {
    die("Tipo de reporte no válido.");
}

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('chroot', __DIR__);

$dompdf = new Dompdf($options);

ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte PDF</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11pt; color: #000; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #2c3e50; padding-bottom: 10px; }
        .header h2 { margin: 0; color: #2c3e50; font-size: 18pt; }
        .header p { margin: 5px 0 0 0; color: #555; }
        .info-box { background: #f8f9fa; padding: 10px; border-radius: 4px; margin-bottom: 20px; font-size: 10pt; }
        .info-box div { margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background-color: #f2f2f2; color: #000; padding: 8px; border: 1px solid #ccc; font-weight: bold; text-align: center; font-size: 10pt; }
        td { padding: 8px; border: 1px solid #ccc; font-size: 10pt; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }
        tfoot td { background-color: #eeeeee; font-weight: bold; border-top: 2px solid #000; }
        .liq-section { width: 100%; margin-top: 20px; }
        .liq-box { width: 48%; display: inline-block; vertical-align: top; border: 1px solid #ccc; border-radius: 4px; background: #fff; }
        .liq-box:first-child { margin-right: 2%; } /* spacing */
        .liq-title { padding: 8px; text-align: center; font-weight: bold; color: white; border-bottom: 1px solid #ccc; }
        .box-cob { background-color: #6c757d; }
        .box-emp { background-color: #2c3e50; }
        .liq-content { padding: 10px; }
        .liq-row { padding: 4px 0; border-bottom: 1px dashed #eee; font-size: 9.5pt; display:table; width:100%; }
        .liq-row > span { display:table-cell; }
        .liq-row > span:last-child { text-align:right; }
        .liq-total { background-color: #f8f9fa; padding: 10px; font-weight: bold; font-size: 11pt; border-top: 2px solid #333; margin-top: 10px; display:table; width:100%; }
        .liq-total > span { display:table-cell; }
        .liq-total > span:last-child { text-align:right; }
        .footer { position: absolute; bottom: 0; width: 100%; text-align: center; font-size: 8pt; color: #555; border-top: 1px solid #ccc; padding-top: 10px; }
        .firma-box { margin-top: 60px; text-align: center; }
        .firma-line { border-top: 1px solid #000; width: 200px; display: inline-block; padding-top: 5px; }
    </style>
</head>
<body>

<?php
if ($tipo === 'cierre') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM cierres_semanales WHERE id = ?");
    $stmt->execute([$id]);
    $cierre = $stmt->fetch();

    if (!$cierre) {
        echo "Cierre no encontrado.";
        exit;
    }

    $stmtDet = $pdo->prepare("SELECT * FROM detalles_diarios WHERE cierre_id = ? ORDER BY FIELD(dia_semana, 'LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO')");
    $stmtDet->execute([$id]);
    $detalles = $stmtDet->fetchAll();

    $totalEf = 0; $totalTr = 0; $totalGasto = 0;
    foreach($detalles as $d) {
        $totalEf += $d['efectivo'];
        $totalTr += $d['transferencia'];
        $totalGasto += $d['gasto_monto'];
    }

    $totalCobrado = $totalEf + $totalTr;
    $comision = $totalCobrado * ($cierre['porcentaje_comision'] / 100); 
    $saldoFavor = $cierre['saldo_favor'];
    $descuentoCreditos = $cierre['descuento_creditos'] ?? 0;

    $sueldoCobrador = ($comision + $saldoFavor) - ($totalGasto + $descuentoCreditos);
    $netoRendir = $totalCobrado - ($comision + $saldoFavor) + $descuentoCreditos;
    ?>

    <div class="header">
        <h2>Liquidación Semanal de Cobranza</h2>
        <p>Gestión de Zona: <strong><?= htmlspecialchars($cierre['zona']) ?></strong></p>
    </div>

    <div class="info-box">
        <div><strong>Semana del:</strong> <?= date('d/m/Y', strtotime($cierre['fecha_inicio'])) ?></div>
        <div><strong>Fecha Emisión:</strong> <?= date('d/m/Y') ?></div>
        <div><strong>Generado por:</strong> <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="15%">Día</th>
                <th width="15%">Efectivo</th>
                <th width="15%">Transf.</th>
                <th width="15%">Total</th>
                <th width="15%">Gastos</th>
                <th width="25%">Detalle Gasto</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($detalles as $d): 
                $sub = $d['efectivo'] + $d['transferencia'];
            ?>
            <tr>
                <td class="fw-bold text-center"><?= $d['dia_semana'] ?></td>
                <td class="text-right"><?= formatCurrency($d['efectivo']) ?></td>
                <td class="text-right"><?= formatCurrency($d['transferencia']) ?></td>
                <td class="text-right fw-bold"><?= formatCurrency($sub) ?></td>
                <td class="text-right"><?= ($d['gasto_monto'] > 0) ? formatCurrency($d['gasto_monto']) : '-' ?></td>
                <td style="font-style: italic; font-size: 8pt;"><?= htmlspecialchars($d['gasto_concepto']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td>TOTALES</td>
                <td class="text-right"><?= formatCurrency($totalEf) ?></td>
                <td class="text-right"><?= formatCurrency($totalTr) ?></td>
                <td class="text-right"><?= formatCurrency($totalCobrado) ?></td>
                <td class="text-right"><?= formatCurrency($totalGasto) ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="liq-section">
        <div class="liq-box">
            <div class="liq-title box-cob">Liquidación Cobrador</div>
            <div class="liq-content">
                <div class="liq-row"><span>(+) Comisión (<?= floatval($cierre['porcentaje_comision']) ?>%):</span><span><?= formatCurrency($comision) ?></span></div>
                <div class="liq-row"><span>(+) Saldo a Favor:</span><span><?= formatCurrency($saldoFavor) ?></span></div>
                <?php if($cierre['saldo_concepto']): ?><div style="font-size:8pt;color:#666;text-align:right;">(<?= htmlspecialchars($cierre['saldo_concepto']) ?>)</div><?php endif; ?>
                <div class="liq-row"><span>(-) Desc. Gastos:</span><span>- <?= formatCurrency($totalGasto) ?></span></div>
                <div class="liq-row"><span>(-) Desc. Créditos:</span><span>- <?= formatCurrency($descuentoCreditos) ?></span></div>
                <?php if($cierre['descuento_creditos_concepto']): ?><div style="font-size:8pt;color:#666;text-align:right;">(<?= htmlspecialchars($cierre['descuento_creditos_concepto']) ?>)</div><?php endif; ?>
                <div class="liq-total"><span>A Cobrar:</span><span><?= formatCurrency($sueldoCobrador) ?></span></div>
            </div>
        </div>

        <div class="liq-box">
            <div class="liq-title box-emp">Neto a Rendir (Empresa)</div>
            <div class="liq-content">
                <div class="liq-row"><span>Total Cobranza Bruta:</span><span class="fw-bold"><?= formatCurrency($totalCobrado) ?></span></div>
                <div class="liq-row"><span>(-) Comisión (<?= floatval($cierre['porcentaje_comision']) ?>%):</span><span>- <?= formatCurrency($comision) ?></span></div>
                <div class="liq-row"><span>(-) Saldo a Favor:</span><span>- <?= formatCurrency($saldoFavor) ?></span></div>
                <div class="liq-row"><span>(+) Retención Créditos:</span><span>+ <?= formatCurrency($descuentoCreditos) ?></span></div>
                <div style="margin-top:10px; font-size:8pt; text-align:center; color:#555;">* Los gastos fueron descontados del sueldo del cobrador.</div>
                <div class="liq-total"><span>TOTAL FINAL:</span><span><?= formatCurrency($netoRendir) ?></span></div>
            </div>
        </div>
    </div>

    <div class="firma-box">
        <div class="firma-line">Firma Administración</div>
    </div>
    <title>Liquidacion_<?= htmlspecialchars($cierre['zona']) ?></title>

<?php
} elseif ($tipo === 'mensual') {
    $mes_actual = $_GET['mes'] ?? date('m');
    $anio_actual = $_GET['anio'] ?? date('Y');
    $zona_filtro = $_GET['zona'] ?? 'todas';

    $meses = [
        '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
        '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
        '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
    ];

    if ($zona_filtro === 'todas') {
        $sql = "
            SELECT 
                c.zona,
                COUNT(DISTINCT c.id) as cantidad_items,
                SUM(c.saldo_favor) as total_saldo_favor,
                SUM(d.efectivo) as total_efectivo,
                SUM(d.transferencia) as total_transferencia,
                SUM(d.gasto_monto) as total_gastos
            FROM cierres_semanales c
            JOIN detalles_diarios d ON c.id = d.cierre_id
            WHERE MONTH(c.fecha_inicio) = ? AND YEAR(c.fecha_inicio) = ?
            GROUP BY c.zona
            ORDER BY c.zona ASC
        ";
        $params = [$mes_actual, $anio_actual];
        $titulo_reporte = "Consolidado General - Todas las Zonas";
        $columna_principal = "Zona";
        $es_detalle = false;
    } else {
        $sql = "
            SELECT 
                c.fecha_inicio as zona,
                1 as cantidad_items,
                c.saldo_favor as total_saldo_favor,
                SUM(d.efectivo) as total_efectivo,
                SUM(d.transferencia) as total_transferencia,
                SUM(d.gasto_monto) as total_gastos
            FROM cierres_semanales c
            JOIN detalles_diarios d ON c.id = d.cierre_id
            WHERE MONTH(c.fecha_inicio) = ? AND YEAR(c.fecha_inicio) = ? AND c.zona = ?
            GROUP BY c.id
            ORDER BY c.fecha_inicio ASC
        ";
        $params = [$mes_actual, $anio_actual, $zona_filtro];
        $titulo_reporte = "Reporte Detallado - " . $zona_filtro;
        $columna_principal = "Semana del";
        $es_detalle = true;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reporte = $stmt->fetchAll();

    $g_efectivo = 0; $g_transf = 0; $g_bruto = 0; 
    $g_comision = 0; $g_gastos = 0; $g_neto = 0;
    ?>
    <title>ReporteMensual_<?= $meses[$mes_actual] ?>_<?= $anio_actual ?></title>
    <div class="header">
        <h2>Reporte Mensual de Cobranzas</h2>
        <p>Periodo: <?= $meses[$mes_actual] ?> <?= $anio_actual ?></p>
        <p style="font-size:0.9em;"><?= $titulo_reporte ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th width="25%"><?= $columna_principal ?></th>
                <th width="15%">Efectivo</th>
                <th width="15%">Transf.</th>
                <th width="15%">Total Bruto</th>
                <th width="15%">Gastos</th>
                <th width="15%">Neto Final</th>
            </tr>
        </thead>
        <tbody>
            <?php if(count($reporte) > 0): ?>
                <?php foreach($reporte as $fila): 
                    $bruto = $fila['total_efectivo'] + $fila['total_transferencia'];
                    $comision = $bruto * 0.06;
                    $neto = $bruto - ($comision + $fila['total_saldo_favor']);
                    $titulo = $es_detalle ? date('d/m/Y', strtotime($fila['zona'])) : $fila['zona'];

                    $g_efectivo += $fila['total_efectivo'];
                    $g_transf += $fila['total_transferencia'];
                    $g_bruto += $bruto;
                    $g_comision += $comision;
                    $g_gastos += $fila['total_gastos'];
                    $g_neto += $neto;
                ?>
                <tr>
                    <td><?= $titulo ?></td>
                    <td class="text-right"><?= formatCurrency($fila['total_efectivo']) ?></td>
                    <td class="text-right"><?= formatCurrency($fila['total_transferencia']) ?></td>
                    <td class="text-right fw-bold"><?= formatCurrency($bruto) ?></td>
                    <td class="text-right" style="color:red;"><?= formatCurrency($fila['total_gastos']) ?></td>
                    <td class="text-right fw-bold"><?= formatCurrency($neto) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-center" style="padding:20px;">Sin registros para este periodo.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td class="text-right">TOTALES</td>
                <td class="text-right"><?= formatCurrency($g_efectivo) ?></td>
                <td class="text-right"><?= formatCurrency($g_transf) ?></td>
                <td class="text-right"><?= formatCurrency($g_bruto) ?></td>
                <td class="text-right"><?= formatCurrency($g_gastos) ?></td>
                <td class="text-right"><?= formatCurrency($g_neto) ?></td>
            </tr>
        </tfoot>
    </table>
<?php
}
?>

    <div class="footer">
        Generado el <?= date('d/m/Y H:i') ?> por <?= $_SESSION['username'] ?? 'Sistema' ?>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Generate dynamic filename based on title extracted from HTML or variables.
$filename = "reporte.pdf";
if ($tipo === 'cierre' && isset($cierre)) {
    $filename = "Liquidacion_" . preg_replace('/[^a-zA-Z0-9]/', '_', $cierre['zona']) . "_" . date('Ymd', strtotime($cierre['fecha_inicio'])) . ".pdf";
} elseif ($tipo === 'mensual' && isset($mes_actual) && isset($anio_actual)) {
    $filename = "Reporte_" . str_replace(" ", "_", $titulo_reporte) . "_" . $anio_actual . $mes_actual . ".pdf";
}

$dompdf->stream($filename, ["Attachment" => false]);
