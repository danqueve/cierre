<?php
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

// --- CONSULTA HISTORIAL DE HORAS ---
// Buscamos cierres que tengan valor de hora configurado, lo que indica que son liquidaciones de jornales.
// Sumamos las horas trabajadas de cada cierre para mostrar un resumen en la tabla.
$sql = "
    SELECT 
        c.id, 
        c.zona, -- Nombre del empleado
        c.fecha_inicio, 
        c.valor_hora,
        SUM(
            (IFNULL(TIME_TO_SEC(TIMEDIFF(d.hora_salida, d.hora_entrada)), 0) + 
             IFNULL(TIME_TO_SEC(TIMEDIFF(d.hora_salida_tarde, d.hora_entrada_tarde)), 0)) / 3600
        ) as total_horas_decimal
    FROM cierres_semanales c
    LEFT JOIN detalles_diarios d ON c.id = d.cierre_id
    WHERE c.valor_hora > 0 -- Solo mostrar si se cargó valor hora
    GROUP BY c.id
    ORDER BY c.fecha_inicio DESC
";

$stmt = $pdo->query($sql);
$historial = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Horas</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Estilos específicos para esta tabla */
        table { text-align: center; }
        th, td { vertical-align: middle; }
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: bold;
            background-color: rgba(255,255,255,0.1);
        }
    </style>
</head>
<body>
    
    <?php include 'header.php'; ?>

    <div class="container">
        
        <div class="card" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h2 style="margin: 0; color: var(--accent-purple);">Historial de Horas</h2>
                <p style="margin: 5px 0; color: var(--text-muted);">Registro de liquidaciones de jornales</p>
            </div>
            <a href="cargar_horas.php" class="btn btn-primary" style="display: flex; align-items: center; gap: 5px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Nueva Liquidación
            </a>
        </div>

        <div class="card">
            <?php if(count($historial) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Fecha Semana</th>
                        <th>Empleado</th>
                        <th>Total Horas</th>
                        <th>Valor Hora</th>
                        <th>Total Liquidado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($historial as $h): 
                        // Cálculos de presentación
                        $horasDecimal = $h['total_horas_decimal'] ?: 0;
                        $totalPagar = $horasDecimal * $h['valor_hora'];
                        
                        // Convertir decimal a formato visual (Ej: 40.5 -> 40h 30m)
                        $horasEnteras = floor($horasDecimal);
                        $minutosRestantes = round(($horasDecimal - $horasEnteras) * 60);
                    ?>
                    <tr>
                        <td style="font-weight: bold; color: var(--text-main);">
                            <?= date('d/m/Y', strtotime($h['fecha_inicio'])) ?>
                            <div style="color: var(--text-muted); font-size: 0.8em; font-weight: normal;">(Lunes)</div>
                        </td>
                        <td>
                            <span style="color: var(--accent-blue); font-weight: 600;"><?= $h['zona'] ?></span>
                        </td>
                        <td>
                            <span class="badge" style="color: #fff;">
                                <?= $horasEnteras ?>h <?= $minutosRestantes > 0 ? $minutosRestantes.'m' : '' ?>
                            </span>
                        </td>
                        <td style="color: var(--text-muted);">
                            <?= formatCurrency($h['valor_hora']) ?>
                        </td>
                        <td style="font-weight: bold; color: var(--accent-green); font-size: 1.1em;">
                            <?= formatCurrency($totalPagar) ?>
                        </td>
                        <td>
                            <a href="liquidar_horas.php?id=<?= $h['id'] ?>" target="_blank" class="btn" style="background-color: #333; border: 1px solid #555; padding: 6px 12px; font-size: 0.85rem; color: #ccc;">
                                Ver Recibo
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div style="padding: 50px; text-align: center; color: var(--text-muted);">
                    <div style="font-size: 3rem; margin-bottom: 10px; opacity: 0.3;">🕰️</div>
                    <p>No hay liquidaciones de horas registradas aún.</p>
                    <a href="cargar_horas.php" style="color: var(--accent-blue);">Crear la primera ahora</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>