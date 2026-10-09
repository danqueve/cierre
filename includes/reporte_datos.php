<?php
// Datos del reporte mensual (compartido por reportes_mensuales.php y ver_impresion_reporte.php)

$mes_actual = sprintf('%02d', min(12, max(1, (int)($_GET['mes'] ?? date('m')))));
$anio_actual = (int)($_GET['anio'] ?? date('Y'));
$zona_filtro = $_GET['zona'] ?? 'todas';

$meses = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
];

// Lista de zonas (Idealmente vendría de DB)
$zonas_lista = ['Zona Este', 'Zona Norte', 'Zona Sur', 'Zona 4a6', 'Zona Sgo', 'Leales', 'Catamarca', 'Tafi del Valle'];

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
            SUM(d.gasto_monto) as total_gastos,
            SUM((d.efectivo + d.transferencia) * c.porcentaje_comision / 100) as total_comision
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
            SUM(d.gasto_monto) as total_gastos,
            SUM((d.efectivo + d.transferencia) * c.porcentaje_comision / 100) as total_comision
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

// Filas ya calculadas para la vista (bruto, comisión y neto = bruto − (comisión + saldo a favor))
$filas = [];
foreach ($reporte as $fila) {
    $bruto    = $fila['total_efectivo'] + $fila['total_transferencia'];
    $comision = (float)$fila['total_comision'];
    $neto     = $bruto - ($comision + $fila['total_saldo_favor']);

    $g_efectivo += $fila['total_efectivo'];
    $g_transf   += $fila['total_transferencia'];
    $g_bruto    += $bruto;
    $g_comision += $comision;
    $g_gastos   += $fila['total_gastos'];
    $g_neto     += $neto;

    $filas[] = [
        'titulo'   => $es_detalle ? date('d/m/Y', strtotime($fila['zona'])) : $fila['zona'],
        'efectivo' => $fila['total_efectivo'],
        'transf'   => $fila['total_transferencia'],
        'bruto'    => $bruto,
        'comision' => $comision,
        'gastos'   => $fila['total_gastos'],
        'neto'     => $neto,
    ];
}
