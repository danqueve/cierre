<?php
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

// --- CONFIGURACIÓN DE FILTROS ---
$mes_actual = $_GET['mes'] ?? date('m');
$anio_actual = $_GET['anio'] ?? date('Y');
$zona_filtro = $_GET['zona'] ?? 'todas';

$meses = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
];

// Lista de zonas (Idealmente vendría de DB)
$zonas_lista = ['Zona 1', 'Zona 2', 'Zona 3', 'Zona 4a6'];

// --- LÓGICA DE CONSULTA ---
if ($zona_filtro === 'todas') {
    // MODO GENERAL: Resumen agrupado por ZONA
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
    // MODO DETALLADO: Filtro por ZONA, agrupado por SEMANA
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

// Inicializar acumuladores
$g_efectivo = 0; $g_transf = 0; $g_bruto = 0; 
$g_comision = 0; $g_gastos = 0; $g_neto = 0;

// Datos para el Gráfico
$chart_labels = [];
$chart_data = [];

foreach($reporte as $fila) {
    $bruto = $fila['total_efectivo'] + $fila['total_transferencia'];
    $comision = $bruto * 0.05;
    $neto = $bruto - ($comision + $fila['total_saldo_favor']);
    
    // Acumular totales
    $g_efectivo += $fila['total_efectivo'];
    $g_transf += $fila['total_transferencia'];
    $g_bruto += $bruto;
    $g_comision += $comision;
    $g_gastos += $fila['total_gastos'];
    $g_neto += $neto;

    // Preparar datos para chart
    $label = $es_detalle ? date('d/m', strtotime($fila['zona'])) : $fila['zona'];
    $chart_labels[] = $label;
    $chart_data[] = $bruto;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Mensual - Moderno</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* Ajustes específicos para reporte */
        body { background-color: #0a0a0c; color: #e0e0e0; }
    </style>
</head>
<body>

    <div class="no-print">
        <?php include 'header.php'; ?>
    </div>

    <div class="report-wrapper">
        
        <!-- BARRA DE FILTROS -->
        <form method="GET" class="filter-bar no-print">
            <div style="flex-grow: 1; display: flex; gap: 10px; align-items: center;">
                <i data-lucide="filter" style="color: var(--accent-purple);"></i>
                
                <select name="zona" class="form-select" style="background: rgba(0,0,0,0.3); color: white; border: 1px solid #444; padding: 8px; border-radius: 6px;">
                    <option value="todas">Todas las Zonas</option>
                    <?php foreach($zonas_lista as $z): ?>
                        <option value="<?= $z ?>" <?= $z == $zona_filtro ? 'selected' : '' ?>><?= $z ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="mes" style="background: rgba(0,0,0,0.3); color: white; border: 1px solid #444; padding: 8px; border-radius: 6px;">
                    <?php foreach($meses as $num => $nom): ?>
                        <option value="<?= $num ?>" <?= $num == $mes_actual ? 'selected' : '' ?>><?= $nom ?></option>
                    <?php endforeach; ?>
                </select>
                
                <input type="number" name="anio" value="<?= $anio_actual ?>" style="width: 70px; background: rgba(0,0,0,0.3); color: white; border: 1px solid #444; padding: 8px; border-radius: 6px;">
                
                <button type="submit" class="btn btn-primary" style="padding: 8px 15px; font-size: 0.9rem;">Filtrar</button>
            </div>
            
            <a href="ver_impresion_reporte.php?zona=<?= $zona_filtro ?>&mes=<?= $mes_actual ?>&anio=<?= $anio_actual ?>" 
               target="_blank" 
               class="btn" 
               style="background: var(--accent-yellow); color: #1e1e1e; font-weight: bold; text-decoration: none; display: flex; align-items: center; padding: 8px 15px;">
                <i data-lucide="printer" style="width: 16px; margin-right: 5px;"></i> Exportar PDF / Imprimir
            </a>
        </form>

        <!-- KPI CARDS -->
        <div class="kpi-grid no-print">
            <div class="kpi-card">
                <div class="kpi-icon" style="background: rgba(122, 162, 247, 0.2); color: #7aa2f7;">
                    <i data-lucide="dollar-sign"></i>
                </div>
                <div class="kpi-content">
                    <div>Total Bruto</div>
                    <div><?= formatCurrency($g_bruto) ?></div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon" style="background: rgba(158, 206, 106, 0.2); color: #9ece6a;">
                    <i data-lucide="trending-up"></i>
                </div>
                <div class="kpi-content">
                    <div>Neto Empresa</div>
                    <div><?= formatCurrency($g_neto) ?></div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon" style="background: rgba(247, 118, 142, 0.2); color: #f7768e;">
                    <i data-lucide="credit-card"></i>
                </div>
                <div class="kpi-content">
                    <div>Gastos Totales</div>
                    <div><?= formatCurrency($g_gastos) ?></div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon" style="background: rgba(187, 154, 247, 0.2); color: #bb9af7;">
                    <i data-lucide="percent"></i>
                </div>
                <div class="kpi-content">
                    <div>Comisiones</div>
                    <div><?= formatCurrency($g_comision) ?></div>
                </div>
            </div>
        </div>

        <!-- CHART SECTION -->
        <?php if(count($reporte) > 0): ?>
        <div class="chart-container no-print">
            <canvas id="revenueChart"></canvas>
        </div>
        <?php endif; ?>

        <!-- VISTA DE PANTALLA (TABLA) -->
        <div class="card no-print">
            <div class="card-header" style="border: none; padding-bottom: 0;">
                <i data-lucide="table" style="vertical-align: middle; margin-right: 8px;"></i>
                Detalle de Registros
            </div>
            <div style="overflow-x: auto; margin-top: 15px;">
                <table class="screen-table">
                    <thead>
                        <tr>
                            <th><?= $columna_principal ?></th>
                            <th style="text-align: right;">Efectivo</th>
                            <th style="text-align: right;">Transferencia</th>
                            <th style="text-align: right;">Total Bruto</th>
                            <th style="text-align: right;">Neto Empresa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($reporte as $fila): 
                            $bruto = $fila['total_efectivo'] + $fila['total_transferencia'];
                            $comision = $bruto * 0.05;
                            $neto = $bruto - ($comision + $fila['total_saldo_favor']);
                            $txt = $es_detalle ? date('d/m/Y', strtotime($fila['zona'])) : $fila['zona'];
                        ?>
                        <tr>
                            <td><?= $txt ?></td>
                            <td style="text-align: right; color: #aaa;"><?= formatCurrency($fila['total_efectivo']) ?></td>
                            <td style="text-align: right; color: #aaa;"><?= formatCurrency($fila['total_transferencia']) ?></td>
                            <td style="text-align: right; font-weight: bold; color: white;"><?= formatCurrency($bruto) ?></td>
                            <td style="text-align: right; font-weight: bold; color: var(--accent-green);"><?= formatCurrency($neto) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- VISTA DE IMPRESIÓN (OCULTA EN PANTALLA) -->
        <div class="print-only a4-page">
            <div class="print-header">
                <h2>Reporte Mensual de Cobranzas</h2>
                <p>Periodo: <?= $meses[$mes_actual] ?> <?= $anio_actual ?> | Filtro: <?= $titulo_reporte ?></p>
            </div>
            
            <table class="print-table">
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
                            $comision = $bruto * 0.05;
                            $neto = $bruto - ($comision + $fila['total_saldo_favor']);
                            $titulo = $es_detalle ? date('d/m/Y', strtotime($fila['zona'])) : $fila['zona'];
                        ?>
                        <tr>
                            <td><?= $titulo ?></td>
                            <td style="text-align: right;"><?= formatCurrency($fila['total_efectivo']) ?></td>
                            <td style="text-align: right;"><?= formatCurrency($fila['total_transferencia']) ?></td>
                            <td style="text-align: right; font-weight: bold;"><?= formatCurrency($bruto) ?></td>
                            <td style="text-align: right; color: #c0392b;"><?= formatCurrency($fila['total_gastos']) ?></td>
                            <td style="text-align: right; font-weight: bold;"><?= formatCurrency($neto) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center; padding: 20px;">Sin registros para este periodo.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td style="text-align: right;">TOTALES</td>
                        <td style="text-align: right;"><?= formatCurrency($g_efectivo) ?></td>
                        <td style="text-align: right;"><?= formatCurrency($g_transf) ?></td>
                        <td style="text-align: right;"><?= formatCurrency($g_bruto) ?></td>
                        <td style="text-align: right;"><?= formatCurrency($g_gastos) ?></td>
                        <td style="text-align: right;"><?= formatCurrency($g_neto) ?></td>
                    </tr>
                </tfoot>
            </table>
            
            <div style="margin-top: 30px; border-top: 1px solid #000; padding-top: 5px; font-size: 8pt; text-align: center;">
                Generado el <?= date('d/m/Y H:i') ?> por <?= $_SESSION['username'] ?? 'Sistema' ?>
            </div>
        </div>

    </div>

    <!-- Scripts -->
    <script>
        lucide.createIcons();

        // Chart.js Setup
        <?php if(count($reporte) > 0): ?>
        const ctx = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [{
                    label: 'Recaudación Bruta',
                    data: <?= json_encode($chart_data) ?>,
                    backgroundColor: 'rgba(122, 162, 247, 0.5)',
                    borderColor: '#7aa2f7',
                    borderWidth: 2,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    title: { display: true, text: 'Tendencia de Ingresos', color: '#ccc', font: { size: 16 } }
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        border: { color: '#333' },
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { color: '#888' }
                    },
                    x: {
                        border: { color: '#333' },
                        grid: { display: false },
                        ticks: { color: '#888' }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>