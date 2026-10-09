<?php
require __DIR__ . '/includes/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

// --- 1. Lógica de Filtrado por Fecha ---
$fecha_input = $_GET['fecha_filtro'] ?? date('Y-m-d');

// Calculamos siempre el LUNES y SÁBADO de esa semana
$fecha_inicio_semana = date('Y-m-d', strtotime('monday this week', strtotime($fecha_input)));
$fecha_fin_semana = date('Y-m-d', strtotime('saturday this week', strtotime($fecha_input)));

// --- 2. Totales Globales (Tarjetas) ---
$stmt = $pdo->prepare("
    SELECT
        SUM(d.efectivo) as total_efectivo,
        SUM(d.transferencia) as total_transferencia,
        SUM(d.gasto_monto) as total_gastos
    FROM detalles_diarios d
    JOIN cierres_semanales c ON d.cierre_id = c.id
    WHERE c.fecha_inicio = ?
");
$stmt->execute([$fecha_inicio_semana]);
$totales = $stmt->fetch();

$total_efectivo = $totales['total_efectivo'] ?? 0;
$total_transferencia = $totales['total_transferencia'] ?? 0;
$total_gastos = $totales['total_gastos'] ?? 0;
$total_cobrado = $total_efectivo + $total_transferencia;

// --- 3. Datos para Gráfico Circular (Por Zona) ---
$stmtZona = $pdo->prepare("
    SELECT
        c.zona,
        SUM(d.efectivo + d.transferencia) as total_zona
    FROM cierres_semanales c
    JOIN detalles_diarios d ON c.id = d.cierre_id
    WHERE c.fecha_inicio = ?
    GROUP BY c.zona
");
$stmtZona->execute([$fecha_inicio_semana]);
$dataZonas = $stmtZona->fetchAll();

// --- 4. Datos para Gráfico de Barras (Evolución Diaria) ---
$stmtDia = $pdo->prepare("
    SELECT
        d.dia_semana,
        SUM(d.efectivo + d.transferencia) as total_dia
    FROM detalles_diarios d
    JOIN cierres_semanales c ON d.cierre_id = c.id
    WHERE c.fecha_inicio = ?
    GROUP BY d.dia_semana
    ORDER BY FIELD(d.dia_semana, 'LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO')
");
$stmtDia->execute([$fecha_inicio_semana]);
$dataDiaria = $stmtDia->fetchAll();

// --- 5. Lógica de Tendencias y Sparklines ---
$fecha_inicio_semana_prev = date('Y-m-d', strtotime('-1 week', strtotime($fecha_inicio_semana)));

$stmtPrev = $pdo->prepare("
    SELECT SUM(d.efectivo + d.transferencia) as total_prev
    FROM detalles_diarios d
    JOIN cierres_semanales c ON d.cierre_id = c.id
    WHERE c.fecha_inicio = ?
");
$stmtPrev->execute([$fecha_inicio_semana_prev]);
$total_prev = $stmtPrev->fetchColumn() ?: 0;

$trend_percent = 0;
if($total_prev > 0) {
    $trend_percent = (($total_cobrado - $total_prev) / $total_prev) * 100;
} elseif($total_cobrado > 0) {
    $trend_percent = 100;
}


function generateSparkline($data) {
    if(empty($data)) return '';
    $width = 100; $height = 30;
    $max = max($data) ?: 1;
    $min = min($data);
    $range = $max - $min ?: 1;

    $points = [];
    $step = $width / (count($data) - 1 ?: 1);

    foreach($data as $i => $val) {
        $x = $i * $step;
        $y = $height - (($val - $min) / $range * $height);
        $points[] = "$x,$y";
    }

    $polyline = implode(' ', $points);
    return "<svg viewBox='0 0 $width $height' preserveAspectRatio='none' class='imp-spark' aria-hidden='true'><polyline points='$polyline' class='imp-spark-path' fill='none' /></svg>";
}

$sparklineDataGlobal = [];
foreach($dataDiaria as $d) $sparklineDataGlobal[] = $d['total_dia'];

$pct_ef  = $total_cobrado > 0 ? round(($total_efectivo / $total_cobrado) * 100) : 0;
$pct_tr  = $total_cobrado > 0 ? round(($total_transferencia / $total_cobrado) * 100) : 0;
$pct_gs  = $total_cobrado > 0 ? min(100, round(($total_gastos / $total_cobrado) * 100)) : 0;

// Navegación de semanas
$semana_ant = date('Y-m-d', strtotime('-7 days', strtotime($fecha_inicio_semana)));
$semana_sig = date('Y-m-d', strtotime('+7 days', strtotime($fecha_inicio_semana)));

// Evolución diaria: 6 columnas (Lu a Sa), con 0 si no hay datos
$dias_cols = ['LUNES' => 'Lu', 'MARTES' => 'Ma', 'MIERCOLES' => 'Mi', 'JUEVES' => 'Ju', 'VIERNES' => 'Vi', 'SABADO' => 'Sá'];
$totales_dia = array_fill_keys(array_keys($dias_cols), 0);
foreach ($dataDiaria as $d) { $totales_dia[$d['dia_semana']] = (float)$d['total_dia']; }
$max_dia     = max($totales_dia);
$dia_max_key = $max_dia > 0 ? array_search($max_dia, $totales_dia) : null;

// Distribución por zona: de mayor a menor
usort($dataZonas, fn($a, $b) => $b['total_zona'] <=> $a['total_zona']);
$max_zona   = $dataZonas ? (float)$dataZonas[0]['total_zona'] : 0;
$suma_zonas = array_sum(array_column($dataZonas, 'total_zona'));
$n_zonas    = count($dataZonas);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Cobranzas</title>
    <link rel="stylesheet" href="assets/css/organic.css">
    <link rel="stylesheet" href="assets/css/imperio.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

    <?php include __DIR__ . '/includes/header.php'; ?>

    <main class="imp-main">
        <div class="imp-page">

            <!-- 1. Encabezado + navegación de semana -->
            <div class="imp-dash-head">
                <div class="imp-page-head">
                    <span class="imp-kicker">Dashboard</span>
                    <h1>Resumen semanal</h1>
                    <p>Semana del <?= date('d/m/Y', strtotime($fecha_inicio_semana)) ?> al <?= date('d/m/Y', strtotime($fecha_fin_semana)) ?></p>
                </div>
                <form method="GET" class="imp-weeknav">
                    <a href="?fecha_filtro=<?= $semana_ant ?>" class="imp-circle-btn" title="Semana anterior" aria-label="Semana anterior">‹</a>
                    <input type="date" name="fecha_filtro" value="<?= e($fecha_input) ?>" required class="input on-light" onchange="this.form.submit()">
                    <a href="?fecha_filtro=<?= $semana_sig ?>" class="imp-circle-btn" title="Semana siguiente" aria-label="Semana siguiente">›</a>
                    <a href="dashboard.php" class="btn btn-secondary imp-btn-today">Esta semana</a>
                </form>
            </div>

            <!-- 2. KPIs -->
            <div class="imp-kpis">
                <div class="imp-kpi-main">
                    <span class="imp-kicker">Total recaudado</span>
                    <span class="imp-money-big imp-money-xl"><?= formatCurrency($total_cobrado) ?></span>
                    <div class="imp-trend-row">
                        <?php if($trend_percent != 0): ?>
                            <span class="imp-trend <?= $trend_percent > 0 ? 'up' : 'down' ?>">
                                <?= $trend_percent > 0 ? '▲ +' : '▼ ' ?><?= round($trend_percent, 1) ?>%
                            </span>
                            <span class="imp-trend-note">vs semana anterior (<?= formatCurrency($total_prev) ?>)</span>
                        <?php else: ?>
                            <span class="imp-trend neutral">— Sin comparativa</span>
                        <?php endif; ?>
                    </div>
                    <?= generateSparkline($sparklineDataGlobal) ?>
                </div>

                <div class="imp-kpi-side">
                    <div class="imp-kpi-card">
                        <span class="imp-kpi-label">Efectivo</span>
                        <span class="imp-kpi-value"><?= formatCurrency($total_efectivo) ?></span>
                        <span class="imp-kpi-pct"><?= $pct_ef ?>% del total</span>
                        <div class="imp-bar-track-h"><div class="imp-bar-fill ef" style="--w:<?= $pct_ef ?>%"></div></div>
                    </div>
                    <div class="imp-kpi-card">
                        <span class="imp-kpi-label">Transferencias</span>
                        <span class="imp-kpi-value"><?= formatCurrency($total_transferencia) ?></span>
                        <span class="imp-kpi-pct"><?= $pct_tr ?>% del total</span>
                        <div class="imp-bar-track-h"><div class="imp-bar-fill tr" style="--w:<?= $pct_tr ?>%"></div></div>
                    </div>
                    <div class="imp-kpi-card gasto">
                        <span class="imp-kpi-label">Gastos reportados</span>
                        <span class="imp-kpi-value">− <?= formatCurrency($total_gastos) ?></span>
                        <span class="imp-kpi-pct"><?= $pct_gs ?>% del total</span>
                        <div class="imp-bar-track-h"><div class="imp-bar-fill gs" style="--w:<?= $pct_gs ?>%"></div></div>
                    </div>
                </div>
            </div>

            <!-- 3. Gráficos -->
            <div class="imp-charts">
                <!-- Evolución diaria -->
                <section class="imp-chart-card">
                    <div class="imp-chart-head"><h3>Evolución diaria</h3></div>
                    <?php if(empty($dataDiaria)): ?>
                        <p class="imp-empty">No hay movimientos registrados esta semana.</p>
                    <?php else: ?>
                        <div class="imp-bars">
                            <?php $i = 0; foreach($dias_cols as $key => $abbr):
                                $val = $totales_dia[$key];
                                $h   = $max_dia > 0 ? round($val / $max_dia * 100, 2) : 0;
                                $fch = date('d/m', strtotime("+$i days", strtotime($fecha_inicio_semana)));
                                $i++;
                            ?>
                                <div class="imp-bar-col">
                                    <span class="imp-bar-val"><?= montoCorto($val) ?></span>
                                    <div class="imp-bar-area">
                                        <div class="imp-bar <?= $key === $dia_max_key ? 'max' : '' ?>" style="--h:<?= $h ?>%"></div>
                                    </div>
                                    <span class="imp-bar-day"><?= $abbr ?></span>
                                    <span class="imp-bar-date"><?= $fch ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Distribución por zona -->
                <section class="imp-chart-card">
                    <div class="imp-chart-head">
                        <h3>Distribución por zona</h3>
                        <?php if($n_zonas): ?><span class="imp-chart-meta"><?= $n_zonas ?> <?= $n_zonas === 1 ? 'zona con cobros' : 'zonas con cobros' ?></span><?php endif; ?>
                    </div>
                    <?php if(empty($dataZonas)): ?>
                        <p class="imp-empty">No hay movimientos registrados esta semana.</p>
                    <?php else: ?>
                        <ul class="imp-zones">
                            <?php foreach($dataZonas as $idx => $z):
                                $tz  = (float)$z['total_zona'];
                                $pz  = $suma_zonas > 0 ? round($tz / $suma_zonas * 100) : 0;
                                $wz  = $max_zona > 0 ? round($tz / $max_zona * 100, 2) : 0;
                            ?>
                                <li class="imp-zone">
                                    <div class="imp-zone-top">
                                        <strong><?= e($z['zona']) ?></strong>
                                        <span class="imp-zone-amount"><?= formatCurrency($tz) ?></span>
                                        <span class="imp-zone-pct"><?= $pz ?>%</span>
                                    </div>
                                    <div class="imp-zone-track"><div class="imp-zone-fill <?= $idx === 0 ? 'top' : '' ?>" style="--w:<?= $wz ?>%"></div></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </div>

        </div>
    </main>
</body>
</html>
