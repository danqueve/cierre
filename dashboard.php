<?php
require 'db.php';
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

// --- Colores para el gráfico circular (Zonas) --- pastel-friendly
function getZoneColor($zonaName) {
    switch($zonaName) {
        case 'Zona 1': return '#7c3aed'; // Purple
        case 'Zona 2': return '#2563eb'; // Blue
        case 'Zona 3': return '#059669'; // Green
        case 'Zona 4a6': return '#b45309'; // Yellow
        default: return '#dc2626';
    }
}
$pieColors = [];
foreach($dataZonas as $z) { $pieColors[] = getZoneColor($z['zona']); }

// --- Colores para las Barras Diarias (pastel-friendly) ---
$barColors = [
    '#7c3aed', // Lunes (Purple)
    '#2563eb', // Martes (Blue)
    '#059669', // Miércoles (Green)
    '#b45309', // Jueves (Amber)
    '#dc2626', // Viernes (Red)
    '#0891b2'  // Sábado (Cyan)
];

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
    return "<svg viewBox='0 0 $width $height' class='sparkline-container'><polyline points='$polyline' class='sparkline-path' fill='none' /></svg>";
}

$sparklineDataGlobal = [];
foreach($dataDiaria as $d) $sparklineDataGlobal[] = $d['total_dia'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Cobranzas</title>
    <link rel="stylesheet" href="style.css">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* ===== TARJETAS KPI PREMIUM ===== */
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 4px 16px rgba(124,58,237,0.07);
            transition: all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }
        /* Línea superior de acento */
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 3px;
            background: var(--card-accent, linear-gradient(to right, var(--accent-purple), var(--accent-blue)));
            border-radius: 20px 20px 0 0;
        }
        /* Brillo de fondo sutil */
        .stat-card::after {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 140px; height: 140px;
            border-radius: 50%;
            background: var(--card-glow, rgba(124,58,237,0.04));
            pointer-events: none;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 32px rgba(124,58,237,0.14);
            border-color: var(--accent-purple);
        }

        /* Variantes de color por tarjeta */
        .card-green  { --card-accent: linear-gradient(90deg,#059669,#34d399); --card-glow: rgba(5,150,105,0.06); }
        .card-blue   { --card-accent: linear-gradient(90deg,#2563eb,#60a5fa); --card-glow: rgba(37,99,235,0.06); }
        .card-yellow { --card-accent: linear-gradient(90deg,#b45309,#fbbf24); --card-glow: rgba(180,83,9,0.06); }
        .card-red    { --card-accent: linear-gradient(90deg,#dc2626,#f87171); --card-glow: rgba(220,38,38,0.06); }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .stat-title {
            color: var(--text-muted);
            font-size: 0.78rem;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 1px;
        }

        /* Ícono con fondo de acento */
        .stat-icon-wrap {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .stat-icon-wrap svg { width: 18px; height: 18px; }
        .icon-green  { background: var(--tint-green);  color: var(--accent-green); }
        .icon-blue   { background: var(--tint-blue);   color: var(--accent-blue); }
        .icon-yellow { background: var(--tint-yellow); color: var(--accent-yellow); }
        .icon-red    { background: var(--tint-red);    color: var(--accent-red); }

        /* Número con gradiente de texto */
        .stat-number {
            font-size: 1.9rem;
            font-weight: 800;
            margin: 4px 0 10px;
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1.1;
        }
        .num-green  { background-image: linear-gradient(135deg, #059669, #34d399); }
        .num-blue   { background-image: linear-gradient(135deg, #2563eb, #60a5fa); }
        .num-yellow { background-image: linear-gradient(135deg, #b45309, #fbbf24); }
        .num-red    { background-image: linear-gradient(135deg, #dc2626, #f87171); }

        /* Barra de progreso */
        .stat-progress-wrap {
            background: var(--border-color);
            border-radius: 99px;
            height: 4px;
            margin-top: 2px;
            overflow: hidden;
        }
        .stat-progress-bar {
            height: 100%;
            border-radius: 99px;
            transition: width 1s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .bar-green  { background: linear-gradient(90deg, #059669, #34d399); }
        .bar-blue   { background: linear-gradient(90deg, #2563eb, #60a5fa); }
        .bar-yellow { background: linear-gradient(90deg, #b45309, #fbbf24); }
        .bar-red    { background: linear-gradient(90deg, #dc2626, #f87171); }

        /* Sparkline coloring by trend */
        .sparkline-trend-up   .sparkline-path { stroke: var(--accent-green); }
        .sparkline-trend-down .sparkline-path { stroke: var(--accent-red); }

        /* Encabezado de sección gráficos */
        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 2rem 0 1.2rem;
        }
        .section-header h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
        }
        .section-divider {
            flex: 1;
            height: 1px;
            background: var(--border-color);
        }

        /* Filtro de fechas */
        .filter-bar {
            padding: 0.9rem 1.2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(124,58,237,0.06);
        }
        .filter-week-label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.92rem;
            color: var(--text-muted);
        }
        .filter-week-label strong { color: var(--accent-purple); }
        .filter-form {
            display: flex;
            gap: 10px;
            align-items: center;
            background: var(--tint-purple);
            padding: 5px 14px;
            border-radius: 50px;
            border: 1px solid var(--border-color);
        }
        .filter-form label { color: var(--text-muted); margin:0; font-size: 0.82rem; }
        .filter-form input[type="date"] {
            background: transparent;
            border: none;
            color: var(--text-main);
            padding: 4px 2px;
            font-size: 0.9rem;
            width: auto;
        }
        .filter-btn {
            background: var(--accent-purple);
            color: white;
            border: none;
            border-radius: 20px;
            padding: 6px 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.82rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .filter-btn:hover { filter: brightness(1.1); transform: translateY(-1px); }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <div class="container">

        <!-- Barra de Filtro de Fechas -->
        <div class="filter-bar">
            <div class="filter-week-label">
                <i data-lucide="calendar-days" style="color: var(--accent-purple); width:18px; height:18px;"></i>
                <span>Semana del <strong><?= date('d/m/Y', strtotime($fecha_inicio_semana)) ?></strong> al <strong><?= date('d/m/Y', strtotime($fecha_fin_semana)) ?></strong></span>
            </div>
            <form method="GET" class="filter-form">
                <label>Ir a:</label>
                <input type="date" name="fecha_filtro" value="<?= $fecha_input ?>" required>
                <button type="submit" class="filter-btn">
                    <i data-lucide="search" style="width:13px; height:13px;"></i> Buscar
                </button>
            </form>
        </div>

        <!-- Tarjetas de Resumen (KPIs) -->
        <?php
            $pct_ef  = $total_cobrado > 0 ? round(($total_efectivo / $total_cobrado) * 100) : 0;
            $pct_tr  = $total_cobrado > 0 ? round(($total_transferencia / $total_cobrado) * 100) : 0;
            $pct_gs  = $total_cobrado > 0 ? min(100, round(($total_gastos / $total_cobrado) * 100)) : 0;
            $sparkClass = $trend_percent >= 0 ? 'sparkline-trend-up' : 'sparkline-trend-down';
        ?>
        <div class="stats-grid">

            <!-- 1. Total Recaudado -->
            <div class="stat-card card-green">
                <div class="stat-header">
                    <span class="stat-title">Total Recaudado</span>
                    <div class="stat-icon-wrap icon-green">
                        <i data-lucide="dollar-sign"></i>
                    </div>
                </div>
                <div class="stat-number num-green"><?= formatCurrency($total_cobrado) ?></div>
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
                    <?php if($trend_percent != 0): ?>
                        <span class="trend-indicator <?= $trend_percent > 0 ? 'trend-up' : 'trend-down' ?>">
                            <?= $trend_percent > 0 ? '▲' : '▼' ?> <?= $trend_percent > 0 ? '+' : '' ?><?= round($trend_percent, 1) ?>%
                        </span>
                        <span style="font-size:0.75rem; color:var(--text-muted);">vs semana anterior</span>
                    <?php else: ?>
                        <span class="trend-indicator trend-neutral">— Sin comparativa</span>
                    <?php endif; ?>
                </div>
                <div class="<?= $sparkClass ?>"><?= generateSparkline($sparklineDataGlobal) ?></div>
            </div>

            <!-- 2. Efectivo -->
            <div class="stat-card card-blue">
                <div class="stat-header">
                    <span class="stat-title">Efectivo</span>
                    <div class="stat-icon-wrap icon-blue">
                        <i data-lucide="banknote"></i>
                    </div>
                </div>
                <div class="stat-number num-blue"><?= formatCurrency($total_efectivo) ?></div>
                <div style="font-size:0.78rem; color:var(--text-muted); margin-bottom:8px;"><?= $pct_ef ?>% del total cobrado</div>
                <div class="stat-progress-wrap">
                    <div class="stat-progress-bar bar-blue" style="width:<?= $pct_ef ?>%;"></div>
                </div>
            </div>

            <!-- 3. Transferencias -->
            <div class="stat-card card-yellow">
                <div class="stat-header">
                    <span class="stat-title">Transferencias</span>
                    <div class="stat-icon-wrap icon-yellow">
                        <i data-lucide="credit-card"></i>
                    </div>
                </div>
                <div class="stat-number num-yellow"><?= formatCurrency($total_transferencia) ?></div>
                <div style="font-size:0.78rem; color:var(--text-muted); margin-bottom:8px;"><?= $pct_tr ?>% del total cobrado</div>
                <div class="stat-progress-wrap">
                    <div class="stat-progress-bar bar-yellow" style="width:<?= $pct_tr ?>%;"></div>
                </div>
            </div>

            <!-- 4. Gastos Reportados -->
            <div class="stat-card card-red">
                <div class="stat-header">
                    <span class="stat-title">Gastos Reportados</span>
                    <div class="stat-icon-wrap icon-red">
                        <i data-lucide="trending-down"></i>
                    </div>
                </div>
                <div class="stat-number num-red">- <?= formatCurrency($total_gastos) ?></div>
                <div style="font-size:0.78rem; color:var(--text-muted); margin-bottom:8px;"><?= $pct_gs ?>% del total cobrado</div>
                <div class="stat-progress-wrap">
                    <div class="stat-progress-bar bar-red" style="width:<?= $pct_gs ?>%;"></div>
                </div>
            </div>

        </div>

        <!-- SECCIÓN GRÁFICOS -->
        <div class="section-header">
            <i data-lucide="bar-chart-2" style="width:18px; height:18px; color:var(--accent-purple);"></i>
            <h3>Análisis Visual</h3>
            <div class="section-divider"></div>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem;">

            <!-- Gráfico 1: Barras (Evolución Diaria) -->
            <div class="card">
                <div class="card-header">Evolución de Cargas Diaria</div>
                <?php if(empty($dataDiaria)): ?>
                    <div class="empty-state">
                        <i data-lucide="bar-chart-2" class="empty-icon"></i>
                        <div>No hay movimientos registrados esta semana.</div>
                    </div>
                <?php else: ?>
                    <div id="daily_chart" style="width: 100%; height: 350px;"></div>
                <?php endif; ?>
            </div>

            <!-- Gráfico 2: Circular (Distribución Zonas) -->
            <div class="card">
                <div class="card-header">Distribución por Zona</div>
                <?php if(empty($dataZonas)): ?>
                     <div class="empty-state">
                        <i data-lucide="pie-chart" class="empty-icon"></i>
                        <div>No hay datos de zonas disponibles.</div>
                    </div>
                <?php else: ?>
                    <div id="piechart_3d" style="width: 100%; height: 350px;"></div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- SCRIPTS DE GRÁFICOS -->
    <script type="text/javascript">
      google.charts.load('current', {'packages':['corechart']});
      google.charts.setOnLoadCallback(drawCharts);

      function drawCharts() {
          drawDailyChart();
          drawPieChart();
      }

      // --- GRÁFICO DE BARRAS (DÍAS) ---
      function drawDailyChart() {
        var data = new google.visualization.DataTable();
        data.addColumn('string', 'Día');
        data.addColumn('number', 'Recaudado');
        data.addColumn({ role: 'style' });

        data.addRows([
          <?php
            $i = 0;
            foreach($dataDiaria as $d):
                $color = $barColors[$i % count($barColors)];
                $i++;
          ?>
          ['<?= $d['dia_semana'] ?>', <?= (float)$d['total_dia'] ?>, 'color: <?= $color ?>'],
          <?php endforeach; ?>
        ]);

        var options = {
            backgroundColor: 'transparent',
            legend: { position: 'none' },
            hAxis: {
                textStyle: { color: '#6b6894', fontSize: 12 }
            },
            vAxis: {
                textStyle: { color: '#6b6894', fontSize: 11 },
                format: 'currency',
                gridlines: { color: '#ddd8f8' },
                baselineColor: '#ddd8f8'
            },
            animation: { startup: true, duration: 800, easing: 'out' },
            bar: { groupWidth: "60%" },
            chartArea: { left: 60, right: 20, top: 20, bottom: 40 }
        };

        if(document.getElementById('daily_chart')) {
            var chart = new google.visualization.ColumnChart(document.getElementById('daily_chart'));
            chart.draw(data, options);
        }
      }

      // --- GRÁFICO CIRCULAR (ZONAS) ---
      function drawPieChart() {
        var data = new google.visualization.DataTable();
        data.addColumn('string', 'Zona');
        data.addColumn('number', 'Recaudación');

        data.addRows([
          <?php foreach($dataZonas as $z): ?>
          ['<?= $z['zona'] ?>', <?= (float)$z['total_zona'] ?>],
          <?php endforeach; ?>
        ]);

        var options = {
          backgroundColor: 'transparent',
          is3D: true,
          legend: { position: 'right', textStyle: { color: '#1e1b4b', fontSize: 12 } },
          titleTextStyle: { color: '#1e1b4b' },
          pieSliceText: 'percentage',
          pieSliceTextStyle: { color: 'white', bold: true, fontSize: 11 },
          colors: <?= json_encode($pieColors) ?>,
          chartArea: { width: '90%', height: '80%' }
        };

        if(document.getElementById('piechart_3d')) {
            var chart = new google.visualization.PieChart(document.getElementById('piechart_3d'));
            chart.draw(data, options);
        }
      }

       window.onresize = drawCharts;
       window.onload = function() {
           lucide.createIcons();
       }
    </script>
</body>
</html>
