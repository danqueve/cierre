<?php
require 'db.php';
// 1. Validar Sesión
// session_start(); // Eliminado porque db.php ya inicia la sesión
if (!isset($_SESSION['user_id'])) { 
    die("Acceso denegado. <a href='index.php'>Iniciar Sesión</a>"); 
}

// 2. Obtener Filtros
$mes_actual = $_GET['mes'] ?? date('m');
$anio_actual = $_GET['anio'] ?? date('Y');
$zona_filtro = $_GET['zona'] ?? 'todas';

$meses = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
];

// 3. Lógica de Consulta (Idéntica a reportes_mensuales.php)
if ($zona_filtro === 'todas') {
    // MODO GENERAL
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
    // MODO DETALLADO
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

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Imprimir Reporte - <?= $meses[$mes_actual] ?> <?= $anio_actual ?></title>
    <!-- Incluimos style.css para reusar las clases @media print -->
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <style>
        /* Forzar estilos de impresión en pantalla para esta vista dedicada */
        body { 
            background: #525659; 
            margin: 0; 
            display: flex; 
            justify-content: center; 
            min-height: 100vh;
        }
        .a4-preview {
            width: 210mm;
            min-height: 297mm;
            background: white;
            padding: 10mm;
            margin: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
        }
        
        /* Forzar BLANCO Y NEGRO estricto */
        * {
            color: black !important;
            border-color: black !important;
            background: transparent !important; /* Reseteamos fondos */
        }
        /* Restaurar fondos específicos para impresión si es necesario */
        @media print {
            body { 
                background: white !important; 
            }
            .a4-preview { 
                background: white !important; 
                width: 100%; margin: 0; box-shadow: none; padding: 0; 
            }
            .no-print-btn { display: none !important; }

            /* Re-aplicar fondo gris suave SOLO a cabeceras de tabla */
            th {
                background-color: #f0f0f0 !important; 
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact;
            }
            /* Asegurar fondo blanco en celdas de datos */
            td {
                background-color: white !important;
            }
        }
    </style>
</head>
<body>

    <!-- Botón flotante para incitar la impresión si no sale automática -->
    <div class="no-print-btn" style="position: fixed; top: 20px; right: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; background: #3498db; color: white; border: none; font-weight: bold; border-radius: 5px;">
            🖨️ Imprimir / Guardar PDF
        </button>
    </div>

    <!-- Contenedor que simula la hoja A4 -->
    <div class="a4-preview">
        <!-- Reutilizamos las clases print-* definidas en style.css -->
        
        <div class="print-header">
            <h2>Reporte Mensual de Cobranzas</h2>
            <p>Periodo: <?= $meses[$mes_actual] ?> <?= $anio_actual ?></p>
            <p style="font-size: 0.9em; color: black;"><?= $titulo_reporte ?></p>
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

                        // Acumular
                        $g_efectivo += $fila['total_efectivo'];
                        $g_transf += $fila['total_transferencia'];
                        $g_bruto += $bruto;
                        $g_comision += $comision;
                        $g_gastos += $fila['total_gastos'];
                        $g_neto += $neto;
                    ?>
                    <tr>
                        <td><?= $titulo ?></td>
                        <td style="text-align: right;"><?= number_format($fila['total_efectivo'], 2, ',', '.') ?></td>
                        <td style="text-align: right;"><?= number_format($fila['total_transferencia'], 2, ',', '.') ?></td>
                        <td style="text-align: right; font-weight: bold;"><?= number_format($bruto, 2, ',', '.') ?></td>
                        <td style="text-align: right;"><?= number_format($fila['total_gastos'], 2, ',', '.') ?></td>
                        <td style="text-align: right; font-weight: bold;"><?= number_format($neto, 2, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center; padding: 20px;">Sin registros para este periodo.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td style="text-align: right;">TOTALES</td>
                    <td style="text-align: right;"><?= number_format($g_efectivo, 2, ',', '.') ?></td>
                    <td style="text-align: right;"><?= number_format($g_transf, 2, ',', '.') ?></td>
                    <td style="text-align: right;"><?= number_format($g_bruto, 2, ',', '.') ?></td>
                    <td style="text-align: right;"><?= number_format($g_gastos, 2, ',', '.') ?></td>
                    <td style="text-align: right;"><?= number_format($g_neto, 2, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>
        
        <div style="margin-top: 30px; border-top: 1px solid #000; padding-top: 5px; font-size: 8pt; text-align: center;">
            Generado el <?= date('d/m/Y H:i') ?> por <?= $_SESSION['username'] ?? 'Sistema' ?>
        </div>
    </div>

    <script>
        // Auto-imprimir al cargar
        window.onload = function() {
            setTimeout(() => {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
