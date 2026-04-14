<?php
require 'db.php';
requireAuth();

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Cobranzas</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* ===== TARJETAS KPI PREMIUM ===== */
        .stat-card {
            background: linear-gradient(145deg, var(--card-bg), #1a1a20);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 20px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 4px 24px rgba(0,0,0,0.3);
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
            background: var(--card-glow, rgba(122,162,247,0.06));
            pointer-events: none;
        }
        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 40px rgba(0,0,0,0.5);
            border-color: rgba(255,255,255,0.1);
        }

        /* Variantes de color por tarjeta */
        .card-green  { --card-accent: linear-gradient(90deg,#9ece6a,#73b842); --card-glow: rgba(158,206,106,0.08); }
        .card-blue   { --card-accent: linear-gradient(90deg,#7aa2f7,#4a7ef7); --card-glow: rgba(122,162,247,0.08); }
        .card-yellow { --card-accent: linear-gradient(90deg,#e0af68,#c89040); --card-glow: rgba(224,175,104,0.08); }
        .card-red    { --card-accent: linear-gradient(90deg,#f7768e,#e04060); --card-glow: rgba(247,118,142,0.08); }

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
        .icon-green  { background: rgba(158,206,106,0.18); color: var(--accent-green); }
        .icon-blue   { background: rgba(122,162,247,0.18); color: var(--accent-blue); }
        .icon-yellow { background: rgba(224,175,104,0.18); color: var(--accent-yellow); }
        .icon-red    { background: rgba(247,118,142,0.18); color: var(--accent-red); }

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
        .num-green  { background-image: linear-gradient(135deg, #9ece6a, #c3e88d); }
        .num-blue   { background-image: linear-gradient(135deg, #7aa2f7, #b4c9fb); }
        .num-yellow { background-image: linear-gradient(135deg, #e0af68, #f0cc90); }
        .num-red    { background-image: linear-gradient(135deg, #f7768e, #ffa0b0); }

        /* Barra de progreso */
        .stat-progress-wrap {
            background: rgba(255,255,255,0.06);
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
        .bar-green  { background: linear-gradient(90deg, #9ece6a, #c3e88d); }
        .bar-blue   { background: linear-gradient(90deg, #7aa2f7, #b4c9fb); }
        .bar-yellow { background: linear-gradient(90deg, #e0af68, #f0cc90); }
        .bar-red    { background: linear-gradient(90deg, #f7768e, #ffa0b0); }

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
            background: linear-gradient(to right, rgba(255,255,255,0.08), transparent);
        }

        /* Filtro de fechas mejorado */
        .filter-bar {
            padding: 0.9rem 1.2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            background: rgba(22,22,26,0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px;
            margin-bottom: 1.5rem;
        }
        .filter-week-label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.92rem;
            color: var(--text-muted);
        }
        .filter-week-label strong { color: var(--accent-blue); }
        .filter-form {
            display: flex;
            gap: 10px;
            align-items: center;
            background: rgba(0,0,0,0.25);
            padding: 5px 14px;
            border-radius: 50px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .filter-form label { color: var(--text-muted); margin:0; font-size: 0.82rem; }
        .filter-form input[type="date"] {
            background: transparent;
            border: none;
            color: white;
            padding: 4px 2px;
            font-size: 0.9rem;
            width: auto;
        }
        .filter-btn {
            background: linear-gradient(135deg, var(--accent-purple), #7c3aed);
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
        .filter-btn:hover { filter: brightness(1.15); transform: translateY(-1px); }
    </style>
</head>
<body>
    
    <?php include 'header.php'; ?>

    <div class="container" id="main-content">
        
        <!-- Barra de Filtro de Fechas -->
        <div class="filter-bar">
            <div class="filter-week-label">
                <i data-lucide="calendar-days" style="color: var(--accent-blue); width:18px; height:18px;"></i>
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
        <div class="charts-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
            
            <!-- Gráfico 1: Barras (Evolución Diaria con Colores) -->
            <div class="card">
                <div class="card-header">Evolución de Cargas Diaria</div>
                <?php if(empty($dataDiaria)): ?>
                    <div class="empty-state">
                        <i data-lucide="bar-chart-2" class="empty-icon"></i>
                        <div>No hay movimientos registrados esta semana.</div>
                    </div>
                <?php else: ?>
                    <div style="position:relative; height:300px;"><canvas id="daily_chart"></canvas></div>
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
                    <div style="position:relative; height:300px;"><canvas id="piechart_3d"></canvas></div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- SCRIPTS DE GRÁFICOS (Chart.js) -->
    <script>
      // Datos desde PHP
      const dailyLabels  = <?= json_encode(array_column($dataDiaria, 'dia_semana')) ?>;
      const dailyValues  = <?= json_encode(array_map(fn($d) => (float)$d['total_dia'], $dataDiaria)) ?>;
      const dailyColors  = <?= json_encode(array_slice($barColors, 0, count($dataDiaria))) ?>;
      const pieLabels    = <?= json_encode(array_column($dataZonas, 'zona')) ?>;
      const pieValues    = <?= json_encode(array_map(fn($z) => (float)$z['total_zona'], $dataZonas)) ?>;
      const pieColors    = <?= json_encode($pieColors) ?>;

      // Configuración global Chart.js
      Chart.defaults.color = '#94a3b8';
      Chart.defaults.borderColor = 'rgba(255,255,255,0.06)';
      Chart.defaults.font.family = "'Inter', 'Segoe UI', sans-serif";

      // --- Gráfico de Barras (Evolución Diaria) ---
      const dailyCtx = document.getElementById('daily_chart');
      if (dailyCtx && dailyLabels.length > 0) {
          new Chart(dailyCtx, {
              type: 'bar',
              data: {
                  labels: dailyLabels,
                  datasets: [{
                      label: 'Recaudado',
                      data: dailyValues,
                      backgroundColor: dailyColors,
                      borderRadius: 8,
                      borderSkipped: false,
                  }]
              },
              options: {
                  responsive: true,
                  maintainAspectRatio: false,
                  plugins: {
                      legend: { display: false },
                      tooltip: {
                          callbacks: {
                              label: ctx => '$ ' + ctx.parsed.y.toLocaleString('es-AR')
                          }
                      }
                  },
                  scales: {
                      x: { grid: { display: false } },
                      y: {
                          grid: { color: 'rgba(255,255,255,0.06)' },
                          ticks: {
                              callback: val => '$ ' + (val/1000).toFixed(0) + 'k'
                          }
                      }
                  },
                  animation: { duration: 800, easing: 'easeOutCubic' }
              }
          });
      }

      // --- Gráfico de Dona (Zonas) ---
      const pieCtx = document.getElementById('piechart_3d');
      if (pieCtx && pieLabels.length > 0) {
          new Chart(pieCtx, {
              type: 'doughnut',
              data: {
                  labels: pieLabels,
                  datasets: [{
                      data: pieValues,
                      backgroundColor: pieColors,
                      borderColor: 'rgba(10,10,12,0.8)',
                      borderWidth: 3,
                      hoverOffset: 8
                  }]
              },
              options: {
                  responsive: true,
                  maintainAspectRatio: false,
                  cutout: '62%',
                  plugins: {
                      legend: {
                          position: 'right',
                          labels: { padding: 16, usePointStyle: true, pointStyleWidth: 10 }
                      },
                      tooltip: {
                          callbacks: {
                              label: ctx => ' $ ' + ctx.parsed.toLocaleString('es-AR')
                          }
                      }
                  },
                  animation: { duration: 900, easing: 'easeOutCubic' }
              }
          });
      }

      // Íconos Lucide
      window.addEventListener('load', () => lucide.createIcons());
    </script>
</body>
</html>