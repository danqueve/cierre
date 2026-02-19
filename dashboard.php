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
// Agrupamos por día y ordenamos cronológicamente (no alfabéticamente)
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

// --- Colores para el gráfico circular (Zonas) ---
function getZoneColor($zonaName) {
    switch($zonaName) {
        case 'Zona 1': return '#9d7cd8'; // Purple
        case 'Zona 2': return '#7aa2f7'; // Blue
        case 'Zona 3': return '#9ece6a'; // Green
        case 'Zona 4a6': return '#e0af68'; // Yellow
        default: return '#f7768e';
    }
}
$pieColors = [];
foreach($dataZonas as $z) { $pieColors[] = getZoneColor($z['zona']); }

// --- Colores Cíclicos para las Barras Diarias (NUEVOS COLORES) ---
$barColors = [
    '#ff79c6', // Lunes (Rosa Neón)
    '#bd93f9', // Martes (Violeta)
    '#8be9fd', // Miércoles (Celeste Cian)
    '#50fa7b', // Jueves (Verde Brillante)
    '#ffb86c', // Viernes (Naranja)
    '#ff5555'  // Sábado (Rojo)
];

// --- 5. Lógica de Tendencias y Sparklines (NUEVO) ---
$fecha_inicio_semana_prev = date('Y-m-d', strtotime('-1 week', strtotime($fecha_inicio_semana)));

// Totales Semana Anterior
$stmtPrev = $pdo->prepare("
    SELECT SUM(d.efectivo + d.transferencia) as total_prev
    FROM detalles_diarios d
    JOIN cierres_semanales c ON d.cierre_id = c.id
    WHERE c.fecha_inicio = ?
");
$stmtPrev->execute([$fecha_inicio_semana_prev]);
$total_prev = $stmtPrev->fetchColumn() ?: 0;

// Cálculo de Tendencia Global
$trend_percent = 0;
if($total_prev > 0) {
    $trend_percent = (($total_cobrado - $total_prev) / $total_prev) * 100;
} elseif($total_cobrado > 0) {
    $trend_percent = 100; // Si antes era 0 y ahora hay, es 100% crecimiento
}

// Generador de Sparkline SVG
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

// Datos simulados para Sparklines (En producción idealmente serían datos reales de los últimos 7 días)
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
        /* Estilos específicos para las tarjetas - Actualizado */
        .stat-card {
            background: linear-gradient(145deg, var(--card-bg), #222); /* Sutil gradiente */
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
             content: '';
             position: absolute;
             top: 0; left: 0; width: 100%; height: 4px;
             background: linear-gradient(to right, var(--accent-purple), var(--accent-blue));
             opacity: 0;
             transition: opacity 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.4);
            border-color: rgba(255,255,255,0.1);
        }
        .stat-card:hover::before { opacity: 1; }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 5px;
        }
        .stat-title {
            color: var(--text-muted);
            font-size: 0.85rem;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .stat-icon {
            color: var(--text-muted);
            opacity: 0.5;
            width: 20px; height: 20px;
        }
        .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            color: white;
            margin: 5px 0;
        }
    </style>
</head>
<body>
    
    <?php include 'header.php'; ?>

    <div class="container">
        
        <!-- Barra de Filtro de Fechas (Glassmorphism) -->
        <div class="card" style="padding: 1rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; background: rgba(30, 30, 30, 0.6); backdrop-filter: blur(10px);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <i data-lucide="calendar-days" style="color: var(--accent-blue);"></i>
                <div style="color: var(--text-main); font-size: 0.95rem;">
                    Semana del: <strong style="color: var(--accent-blue);"><?= date('d/m/Y', strtotime($fecha_inicio_semana)) ?></strong> 
                    al <strong style="color: var(--accent-blue);"><?= date('d/m/Y', strtotime($fecha_fin_semana)) ?></strong>
                </div>
            </div>
            
            <form method="GET" style="display: flex; gap: 10px; align-items: center; background: rgba(0,0,0,0.2); padding: 5px 15px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.05);">
                <label style="color: var(--text-muted); margin:0; font-size: 0.85rem;">Ir a:</label>
                <input type="date" name="fecha_filtro" value="<?= $fecha_input ?>" style="width: auto; background: transparent; border: none; color: white; padding: 5px;" required>
                <button type="submit" class="btn" style="padding: 6px 15px; font-size: 0.85rem; background: var(--accent-purple); color: white; border-radius: 20px; border: none;"><i data-lucide="search" style="width: 14px; height: 14px;"></i></button>
            </form>
        </div>

        <!-- Tarjetas de Resumen (KPIs) -->
        <div class="stats-grid">
            
            <!-- 1. Total Recaudado -->
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Total Recaudado</span>
                    <i data-lucide="dollar-sign" class="stat-icon"></i>
                </div>
                <div class="stat-number text-green"><?= formatCurrency($total_cobrado) ?></div>
                <div style="display: flex; align-items: center; margin-top: 5px;">
                    <?php if($trend_percent != 0): ?>
                        <span class="trend-indicator <?= $trend_percent > 0 ? 'trend-up' : 'trend-down' ?>">
                            <?= $trend_percent > 0 ? '+' : '' ?><?= round($trend_percent, 1) ?>%
                        </span>
                        <span style="font-size: 0.75rem; color: var(--text-muted); margin-left: 5px;">vs semana anterior</span>
                    <?php else: ?>
                        <span class="trend-indicator trend-neutral">0%</span>
                    <?php endif; ?>
                </div>
                
                <!-- Sparkline SVG -->
                <div style="margin-top: 10px;">
                    <?= generateSparkline($sparklineDataGlobal) ?>
                </div>
            </div>
 
            <!-- 2. Efectivo -->
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Efectivo</span>
                    <i data-lucide="banknote" class="stat-icon"></i>
                </div>
                <div class="stat-number text-blue"><?= formatCurrency($total_efectivo) ?></div>
                 <div style="height: 5px;"></div>
            </div>
 
            <!-- 3. Transferencias -->
            <div class="stat-card">
                 <div class="stat-header">
                    <span class="stat-title">Transferencias</span>
                    <i data-lucide="credit-card" class="stat-icon"></i>
                </div>
                <div class="stat-number text-yellow"><?= formatCurrency($total_transferencia) ?></div>
            </div>
 
            <!-- 4. Gastos Reportados -->
            <div class="stat-card">
                 <div class="stat-header">
                    <span class="stat-title">Gastos Reportados</span>
                    <i data-lucide="trending-down" class="stat-icon"></i>
                </div>
                <div class="stat-number text-red">- <?= formatCurrency($total_gastos) ?></div>
            </div>

        </div>

        <!-- GRÁFICOS -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
            
            <!-- Gráfico 1: Barras (Evolución Diaria con Colores) -->
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
      
      // Llamamos a ambas funciones de dibujo
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
        data.addColumn({ role: 'style' }); // Columna para el color individual

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
            legend: { position: 'none' }, // Ocultamos leyenda (cada barra tiene su color)
            hAxis: { textStyle: { color: '#e0e0e0' } },
            vAxis: { 
                textStyle: { color: '#e0e0e0' },
                format: 'currency',
                gridlines: { color: '#333' }
            },
            animation: { startup: true, duration: 800, easing: 'out' },
            bar: { groupWidth: "60%" }
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
          legend: { position: 'right', textStyle: { color: '#e0e0e0' } },
          titleTextStyle: { color: '#e0e0e0' },
          pieSliceText: 'percentage',
          pieSliceTextStyle: { color: 'black', bold: true },
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