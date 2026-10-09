<?php
require __DIR__ . '/includes/db.php';
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


$dias_nom = ['LUNES' => 'Lunes', 'MARTES' => 'Martes', 'MIERCOLES' => 'Miércoles', 'JUEVES' => 'Jueves', 'VIERNES' => 'Viernes', 'SABADO' => 'Sábado'];
$dias_off = ['LUNES' => 0, 'MARTES' => 1, 'MIERCOLES' => 2, 'JUEVES' => 3, 'VIERNES' => 4, 'SABADO' => 5];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <title>Liquidación Horas - <?= e($cierre['zona']) ?></title>
    <link rel="stylesheet" href="assets/css/organic.css">
    <link rel="stylesheet" href="assets/css/imperio.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        window.addEventListener('load', function() {
            lucide.createIcons();
            <?php if($autoExport): ?>
            const element = document.querySelector('.sheet');
            const nombre = '<?= addslashes($cierre['zona']) ?>';
            const fecha  = '<?= date('d-m-Y', strtotime($cierre['fecha_inicio'])) ?>';
            const opt = {
                margin:     0,
                filename:   'Liquidacion-' + nombre + '-' + fecha + '.pdf',
                image:      { type: 'jpeg', quality: 0.98 },
                html2canvas:{ scale: 2, useCORS: true, letterRendering: true },
                jsPDF:      { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            element.classList.add('exporting');
            html2pdf().set(opt).from(element).save().then(function() { element.classList.remove('exporting'); });
            <?php endif; ?>
        });
    </script>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <main class="imp-main">
        <div class="sheet-page">
            <div class="sheet-toolbar no-print">
                <div class="imp-page-head">
                    <span class="imp-kicker sage">Vista de impresión · A4</span>
                    <h2>Liquidación de <?= e($cierre['zona']) ?></h2>
                </div>
                <a href="cargar_horas.php?id=<?= (int)$cierre['id'] ?>" class="btn btn-secondary">
                    <i data-lucide="arrow-left" style="width:16px; height:16px;"></i> Editar horarios
                </a>
                <button type="button" onclick="window.print()" class="btn btn-primary">
                    <i data-lucide="printer" style="width:16px; height:16px;"></i> Imprimir / Guardar PDF
                </button>
            </div>

            <div class="sheet-wrap">
                <div class="sheet">

                    <div class="sheet-head">
                        <img class="sheet-logo" src="assets/img/logo.png" alt="Imperio">
                        <div class="sheet-brand"><b>Imperio<span>.</span></b><small>Sistema de gestión</small></div>
                        <span class="tag tag-accent-2" style="font-size:12px; padding:6px 12px;">Semana del <?= date('d/m/Y', strtotime($cierre['fecha_inicio'])) ?></span>
                    </div>

                    <div class="sheet-title">
                        <h1>Liquidación de jornales</h1>
                        <span class="sheet-sub">Control de horarios semanal</span>
                    </div>

                    <div class="sheet-info">
                        <div><small>Empleado</small><strong><?= e($cierre['zona']) ?></strong></div>
                        <div><small>Semana del</small><strong><?= date('d/m/Y', strtotime($cierre['fecha_inicio'])) ?></strong></div>
                        <div><small>Fecha emisión</small><strong><?= date('d/m/Y') ?></strong></div>
                    </div>

                    <div class="sheet-table-wrap">
                        <table class="sheet-table sage">
                            <thead>
                                <tr>
                                    <th>Día</th>
                                    <th class="c">Entrada</th>
                                    <th class="c">Salida</th>
                                    <th class="r">Total horas</th>
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
                                    $off = $dias_off[$d['dia_semana']] ?? 0;
                                    $fechaDia = date('d/m', strtotime("+$off days", strtotime($cierre['fecha_inicio'])));
                                ?>
                                <tr>
                                    <td><strong><?= $dias_nom[$d['dia_semana']] ?? e($d['dia_semana']) ?></strong> <span style="color:var(--color-neutral-700);"><?= $fechaDia ?></span></td>
                                    <td class="c"><?= $d['hora_entrada'] ? date('H:i', strtotime($d['hora_entrada'])) : '-' ?></td>
                                    <td class="c"><?= $d['hora_salida'] ? date('H:i', strtotime($d['hora_salida'])) : '-' ?></td>
                                    <td class="r total-col"><?= $horasStr ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="sheet-totals">
                        <div class="sheet-line"><span>Horas trabajadas</span><strong><?= $horasReales ?>h <?= $minutosReales ?>m</strong></div>
                        <div class="sheet-line"><span>Valor hora</span><span><?= formatCurrency($cierre['valor_hora']) ?></span></div>
                        <div class="sheet-line"><span>Subtotal horas</span><span><?= formatCurrency($subtotalHoras) ?></span></div>

                        <?php if($saldoFavor > 0): ?>
                        <div class="sheet-line"><span>(+) Ingresos extra</span><span><?= formatCurrency($saldoFavor) ?></span></div>
                        <?php if($cierre['saldo_concepto']): ?>
                            <div class="sheet-line-note">(<?= e($cierre['saldo_concepto']) ?>)</div>
                        <?php endif; ?>
                        <?php endif; ?>

                        <?php if($descuento > 0): ?>
                        <div class="sheet-line"><span>(−) Descuentos / adelantos</span><span>− <?= formatCurrency($descuento) ?></span></div>
                        <?php if($cierre['descuento_creditos_concepto']): ?>
                            <div class="sheet-line-note">(<?= e($cierre['descuento_creditos_concepto']) ?>)</div>
                        <?php endif; ?>
                        <?php endif; ?>

                        <div class="sheet-grand"><strong>TOTAL A PAGAR</strong><span><?= formatCurrency($totalPagar) ?></span></div>
                    </div>

                    <div class="sheet-signs">
                        <div class="sheet-sign"><strong>Firma empleado</strong><small><?= e($cierre['zona']) ?></small></div>
                        <div class="sheet-sign"><strong>Firma responsable</strong></div>
                    </div>

                    <div class="sheet-foot">Documento generado por el Sistema de Gestión.</div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
