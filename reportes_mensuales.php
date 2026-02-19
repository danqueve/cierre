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

// Lista de zonas (Idealmente vendría de DB, pero usamos estática por ahora)
$zonas_lista = ['Zona 1', 'Zona 2', 'Zona 3', 'Zona 4a6'];

// --- LÓGICA DE CONSULTA INTELIGENTE ---
if ($zona_filtro === 'todas') {
    // MODO GENERAL: Resumen agrupado por ZONA
    $sql = "
        SELECT 
            c.zona,
            COUNT(DISTINCT c.id) as cantidad_items, -- Cantidad de semanas
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
    // MODO DETALLADO: Filtro por ZONA, agrupado por SEMANA (Cierre ID)
    // Esto mostrará cada cierre individual dentro del mes para esa zona
    $sql = "
        SELECT 
            c.fecha_inicio as zona, -- Usamos 'zona' como alias para reutilizar la vista
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

// Inicializar acumuladores para el pie de página
$g_efectivo = 0;
$g_transf = 0;
$g_bruto = 0;
$g_comision = 0;
$g_gastos = 0;
$g_neto = 0;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Mensual</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* --- ESTILOS VISUALES MEJORADOS (Estética A4) --- */
        html, body {
            margin: 0;
            padding: 0;
            background-color: #525659; /* Fondo oscuro para resaltar la hoja */
            min-height: 100%;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-header { position: sticky; top: 0; z-index: 1000; background: rgba(30,30,30,0.95); width: 100%; }

        .report-container {
            display: flex;
            justify-content: center;
            padding: 40px 0;
        }

        /* Simulación Hoja A4 */
        .a4-sheet {
            width: 210mm;
            min-height: 297mm;
            background: white;
            padding: 15mm 20mm;
            box-shadow: 0 0 15px rgba(0,0,0,0.3);
            box-sizing: border-box;
            position: relative;
            color: #333;
        }

        /* Cabecera del Documento */
        .doc-header {
            text-align: center;
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .doc-header h2 { margin: 0; color: #2c3e50; text-transform: uppercase; font-size: 22pt; }
        .doc-header p { margin: 5px 0 0; color: #555; font-size: 12pt; }

        /* Información del Filtro */
        .filter-info {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            font-size: 11pt;
            color: #444;
        }

        /* Tabla de Datos - Estilo Profesional */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            margin-bottom: 30px;
        }
        .data-table th {
            background-color: #2c3e50;
            color: white;
            padding: 10px 5px;
            text-transform: uppercase;
            font-weight: 600;
            border: 1px solid #2c3e50;
        }
        .data-table td {
            padding: 8px 5px;
            border: 1px solid #dee2e6;
            text-align: right; /* Números a la derecha */
        }
        .data-table td:first-child { text-align: left; font-weight: 600; color: #2c3e50; }
        .data-table tr:nth-child(even) { background-color: #f8f9fa; }
        
        .data-table tfoot td {
            background-color: #e9ecef;
            font-weight: bold;
            border-top: 2px solid #2c3e50;
            color: #2c3e50;
            font-size: 11pt;
        }

        /* Marca de Agua */
        .watermark {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            pointer-events: none; z-index: 0;
            display: flex; align-items: center; justify-content: center;
            opacity: 0.05;
        }
        .watermark img { max-width: 60%; }

        /* Controles de Filtro (No imprimibles) */
        .filter-controls {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            width: fit-content;
            margin: 0 auto 20px auto;
        }

        /* --- IMPRESIÓN --- */
        @media print {
            body { background-color: white; margin: 0; }
            .main-header, .no-print { display: none !important; }
            .report-container { padding: 0; }
            .a4-sheet { box-shadow: none; margin: 0; width: 100%; min-height: 100%; }
            /* Ajustes de tinta */
            .data-table th { background-color: #eee !important; color: black !important; border-color: #000; }
            .data-table td { border-color: #000; }
        }
    </style>
</head>
<body>
    
    <!-- Header del Sistema (Solo pantalla) -->
    <div class="no-print">
        <?php include 'header.php'; ?>
    </div>

    <div class="report-container">
        
        <!-- Contenedor Principal -->
        <div style="width: 210mm;">
            
            <!-- Barra de Filtros (Flotante sobre la hoja) -->
            <div class="filter-controls no-print">
                <form method="GET" style="display: flex; gap: 10px; align-items: center; margin: 0;">
                    <label style="font-weight: bold; color: #333;">Filtrar:</label>
                    
                    <select name="zona" class="form-select" style="padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                        <option value="todas">Todas las Zonas</option>
                        <?php foreach($zonas_lista as $z): ?>
                            <option value="<?= $z ?>" <?= $z == $zona_filtro ? 'selected' : '' ?>><?= $z ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="mes" style="padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                        <?php foreach($meses as $num => $nom): ?>
                            <option value="<?= $num ?>" <?= $num == $mes_actual ? 'selected' : '' ?>><?= $nom ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="number" name="anio" value="<?= $anio_actual ?>" style="width: 70px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                    
                    <button type="submit" class="btn btn-primary" style="padding: 8px 15px;">Ver Reporte</button>
                </form>
                
                <div style="width: 1px; height: 30px; background: #ddd; margin: 0 10px;"></div>
                
                <button onclick="window.print()" class="btn" style="background-color: #e0af68; color: #1e1e1e; font-weight: bold; padding: 8px 15px;">
                    🖨️ Exportar PDF
                </button>
            </div>

            <!-- HOJA A4 -->
            <div class="a4-sheet">
                
                <!-- Marca de Agua -->
                <div class="watermark">
                     <img src="img/logo.png" alt="Logo"> 
                </div>

                <!-- Cabecera Documento -->
                <div class="doc-header">
                    <h2>Reporte Mensual de Cierres</h2>
                    <p>Resumen de Recaudación y Liquidaciones</p>
                </div>

                <!-- Info Contexto -->
                <div class="filter-info">
                    <div><strong>Reporte:</strong> <?= $titulo_reporte ?></div>
                    <div><strong>Periodo:</strong> <?= $meses[$mes_actual] ?> <?= $anio_actual ?></div>
                    <div><strong>Fecha Emisión:</strong> <?= date('d/m/Y') ?></div>
                </div>

                <!-- Tabla de Datos -->
                <?php if(count($reporte) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th width="20%"><?= $columna_principal ?></th>
                            <th width="13%">Efectivo</th>
                            <th width="13%">Transf.</th>
                            <th width="14%"> Bruto</th>
                            <th width="13%">(5%)</th>
                            <th width="10%">Gastos</th>
                            <th width="14%">Neto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($reporte as $fila): 
                            // Cálculos
                            $bruto = $fila['total_efectivo'] + $fila['total_transferencia'];
                            $comision = $bruto * 0.05;
                            $gastos = $fila['total_gastos'];
                            $saldoFavor = $fila['total_saldo_favor'];
                            
                           
                            $netoEmpresa = $bruto - ($comision + $saldoFavor);

                            // Acumuladores Globales
                            $g_efectivo += $fila['total_efectivo'];
                            $g_transf += $fila['total_transferencia'];
                            $g_bruto += $bruto;
                            $g_comision += $comision;
                            $g_gastos += $gastos;
                            $g_neto += $netoEmpresa;

                            // Formateo de la primera columna (Fecha o Nombre Zona)
                            $texto_principal = $es_detalle 
                                ? date('d/m/Y', strtotime($fila['zona'])) . " <span style='font-size:0.8em; font-weight:normal; color:#666;'>(Semana)</span>"
                                : $fila['zona'];
                        ?>
                        <tr>
                            <td><?= $texto_principal ?></td>
                            <td><?= formatCurrency($fila['total_efectivo']) ?></td>
                            <td><?= formatCurrency($fila['total_transferencia']) ?></td>
                            <td style="font-weight: bold; background-color: #fcfcfc;"><?= formatCurrency($bruto) ?></td>
                            <td style="color: #666;"><?= formatCurrency($comision) ?></td>
                            <td style="color: #c0392b;"><?= formatCurrency($gastos) ?></td>
                            <td style="font-weight: bold; color: #2c3e50; border-left: 2px solid #eee;"><?= formatCurrency($netoEmpresa) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td style="text-align: right;">TOTALES</td>
                            <td><?= formatCurrency($g_efectivo) ?></td>
                            <td><?= formatCurrency($g_transf) ?></td>
                            <td><?= formatCurrency($g_bruto) ?></td>
                            <td><?= formatCurrency($g_comision) ?></td>
                            <td style="color: #c0392b;"><?= formatCurrency($g_gastos) ?></td>
                            <td style="border-left: 2px solid #2c3e50;"><?= formatCurrency($g_neto) ?></td>
                        </tr>
                    </tfoot>
                </table>
                
                <div style="margin-top: 30px; font-size: 10pt; color: #666; border-top: 1px solid #ccc; padding-top: 10px;">
                    <p style="margin: 0;"><strong>Nota:</strong> El "Neto Empresa" representa el monto final disponible tras deducir las comisiones y saldos a favor de los cobradores.</p>
                </div>

                <?php else: ?>
                    <div style="text-align: center; padding: 50px; color: #777; border: 2px dashed #ddd; border-radius: 8px;">
                        <h3>Sin datos disponibles</h3>
                        <p>No se encontraron registros para la selección actual.</p>
                    </div>
                <?php endif; ?>

                <!-- Pie de Página Fijo -->
                <div style="position: absolute; bottom: 15mm; left: 20mm; right: 20mm; text-align: center; font-size: 9pt; color: #999; border-top: 1px solid #eee; padding-top: 5px;">
                    Documento generado por Sistema de Cobranzas - Uso Interno
                </div>

            </div>
            <!-- Fin Hoja A4 -->
            
        </div>
    </div>

</body>
</html>