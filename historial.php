<?php
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

// Obtener todos los cierres con su total recaudado
// FILTRO APLICADO: Excluir a los empleados administrativos (Alejandro, Emilia, Luz, Maxi)
// O bien, filtrar aquellos que NO tienen valor hora configurado (asumiendo que los cobradores son por comisión)
// Usamos una lista negra de nombres para ser específicos según tu pedido.

$empleados_admin = ['Alejandro', 'Emilia', 'Luz', 'Maxi'];
// Creamos una cadena de marcadores para la consulta IN (?, ?, ?, ?)
$placeholders = implode(',', array_fill(0, count($empleados_admin), '?'));

$sql = "
    SELECT 
        c.id, 
        c.zona, 
        c.fecha_inicio, 
        c.fecha_creacion,
        SUM(d.efectivo + d.transferencia) as total_recaudado
    FROM cierres_semanales c
    LEFT JOIN detalles_diarios d ON c.id = d.cierre_id
    WHERE c.zona NOT IN ($placeholders) -- Excluir administrativos
    GROUP BY c.id
    ORDER BY c.fecha_inicio DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($empleados_admin);
$cierres = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Cierres</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Estilos específicos para centrar la tabla en historial */
        table {
            text-align: center; /* Centra el contenido general */
        }
        th, td {
            text-align: center !important; /* Fuerza el centrado en celdas y cabeceras */
            vertical-align: middle; /* Centrado vertical */
        }
        /* Ajuste para que la columna de acciones se vea bien centrada */
        td .btn {
            display: inline-block;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    
    <?php include 'header.php'; ?>

    <div class="container">
        <div class="card">
            <div class="card-header" style="text-align: center;">Historial de Liquidaciones (Cobranzas)</div>
            
            <?php if(count($cierres) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Fecha Semana</th>
                        <th>Zona</th>
                        <th>Total Recaudado</th>
                        <th>Fecha Carga</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($cierres as $c): ?>
                    <tr>
                        <td style="font-weight: bold; color: var(--text-main);">
                            <?= date('d/m/Y', strtotime($c['fecha_inicio'])) ?> 
                            <div style="color: var(--text-muted); font-size: 0.8em; font-weight: normal;">(Lunes)</div>
                        </td>
                        <td>
                            <span style="color: var(--accent-blue);"><?= $c['zona'] ?></span>
                        </td>
                        <td class="text-green" style="font-weight: bold;">
                            <?= formatCurrency($c['total_recaudado']) ?>
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.9em;">
                            <?= date('d/m/Y H:i', strtotime($c['fecha_creacion'])) ?>
                        </td>
                        <td>
                            <!-- Agregado target="_blank" para abrir en nueva pestaña -->
                            <a href="ver_cierre.php?id=<?= $c['id'] ?>" target="_blank" class="btn btn-primary" style="padding: 6px 15px; font-size: 0.9rem;">
                                Ver Liquidación
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                    Aún no hay cierres de cobranza cargados en el sistema.
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>