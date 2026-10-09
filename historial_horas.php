<?php
require __DIR__ . '/includes/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

// Liquidaciones de jornales: cierres con valor de hora configurado.
// Un registro por día; el calendario suma las horas trabajadas de cada cierre.
$sql = "
    SELECT
        c.id, c.zona, c.fecha_inicio, c.saldo_favor, c.descuento_creditos, c.valor_hora, c.porcentaje_comision,
        d.dia_semana, d.efectivo, d.transferencia, d.gasto_monto, d.hora_entrada, d.hora_salida
    FROM cierres_semanales c
    LEFT JOIN detalles_diarios d ON c.id = d.cierre_id
    WHERE c.valor_hora > 0
    ORDER BY c.fecha_inicio DESC, c.id
";
$rows = $pdo->query($sql)->fetchAll();

$tipo = 'hor';
require __DIR__ . '/includes/historial_cal.php';
