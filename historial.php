<?php
require __DIR__ . '/includes/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

// FILTRO: Excluir empleados administrativos
$empleados_admin = ['Alejandro', 'Lourdes', 'Maxi'];
$placeholders = implode(',', array_fill(0, count($empleados_admin), '?'));

// Un registro por día de cada cierre (el calendario agrupa y totaliza en PHP)
$sql = "
    SELECT
        c.id, c.zona, c.fecha_inicio, c.saldo_favor, c.descuento_creditos, c.valor_hora,
        d.dia_semana, d.efectivo, d.transferencia, d.gasto_monto, d.hora_entrada, d.hora_salida
    FROM cierres_semanales c
    LEFT JOIN detalles_diarios d ON c.id = d.cierre_id
    WHERE c.zona NOT IN ($placeholders)
    ORDER BY c.fecha_inicio DESC, c.id
";
$stmt = $pdo->prepare($sql);
$stmt->execute($empleados_admin);
$rows = $stmt->fetchAll();

$tipo = 'cob';
require __DIR__ . '/includes/historial_cal.php';
