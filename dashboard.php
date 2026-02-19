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

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Cobranzas</title>
    <link rel="stylesheet" href="style.css">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <style>
        /* Estilos específicos para las tarjetas */
        .stat-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0,0,0,0.3);
        }
        .stat-title {
            color: var(--text-muted);
            font-size: 0.9rem;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
        }
        .stat-icon {
            align-self: flex-end;
            margin-bottom: -10px;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    
    <?php include 'header.php'; ?>

    <div class="container">
        
        <!-- Barra de Filtro de Fechas -->
        <div class="card" style="padding: 1rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <div style="color: var(--text-muted);">
                Semana del: <strong style="color: var(--accent-blue);"><?= date('d/m/Y', strtotime($fecha_inicio_semana)) ?></strong> 
                al <strong style="color: var(--accent-blue);"><?= date('d/m/Y', strtotime($fecha_fin_semana)) ?></strong>
            </div>
            
            <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                <label style="color: var(--text-main); margin:0;">Ir a:</label>
                <input type="date" name="fecha_filtro" value="<?= $fecha_input ?>" style="width: auto;" required>
                <button type="submit" class="btn btn-primary" style="padding: 8px 15px; font-size: 0.9rem;">Filtrar</button>
                <?php if(isset($_GET['fecha_filtro'])): ?>
                    <a href="dashboard.php" class="btn" style="background: #333; color: white; padding: 8px 15px; text-decoration: none; font-size: 0.9rem;">Hoy</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Tarjetas de Resumen (KPIs) -->
        <div class="stats-grid">
            
            <!-- 1. Total Recaudado -->
            <div class="stat-card">
                <div class="stat-title">Total Recaudado</div>
                <div class="stat-number text-green"><?= formatCurrency($total_cobrado) ?></div>
            </div>

            <!-- 2. Efectivo -->
            <div class="stat-card">
                <div class="stat-title">Efectivo</div>
                <div class="stat-number text-blue"><?= formatCurrency($total_efectivo) ?></div>
            </div>

            <!-- 3. Transferencias -->
            <div class="stat-card">
                <div class="stat-title">Transferencias</div>
                <div class="stat-number text-yellow"><?= formatCurrency($total_transferencia) ?></div>
            </div>

            <!-- 4. Gastos Reportados (NUEVA TARJETA) -->
            <div class="stat-card">
                <div class="stat-title">Gastos Reportados</div>
                <div class="stat-number text-red">- <?= formatCurrency($total_gastos) ?></div>
            </div>

        </div>

        <!-- GRÁFICOS -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
            
            <!-- Gráfico 1: Barras (Evolución Diaria con Colores) -->
            <div class="card">
                <div class="card-header">Evolución de Cargas Diaria</div>
                <?php if(empty($dataDiaria)): ?>
                    <div style="text-align: center; padding: 3rem; color: var(--text-muted);">Sin datos.</div>
                <?php else: ?>
                    <div id="daily_chart" style="width: 100%; height: 350px;"></div>
                <?php endif; ?>
            </div>

            <!-- Gráfico 2: Circular (Distribución Zonas) -->
            <div class="card">
                <div class="card-header">Distribución por Zona</div>
                <?php if(empty($dataZonas)): ?>
                    <div style="text-align: center; padding: 3rem; color: var(--text-muted);">Sin datos.</div>
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
    </script>
</body>
</html>