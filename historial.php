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
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* Ajustes específicos para esta página si fuera necesario */
    </style>
    <script>
        window.onload = function() { lucide.createIcons(); }
    </script>
</head>
<body>
    
    <?php include 'header.php'; ?>

    <div class="container">
        <div class="card">
            <div class="card-header" style="text-align: center;">Historial de Liquidaciones (Cobranzas)</div>
            
            <?php if(count($cierres) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Fecha Semana</th>
                            <th>Zona</th>
                            <th>Total Recaudado</th>
                            <th>Fecha Carga</th>
                            <th style="text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($cierres as $c): ?>
                        <tr>
                            <td style="font-weight: 600;">
                                <?= date('d/m/Y', strtotime($c['fecha_inicio'])) ?> 
                                <span style="color: var(--text-muted); font-size: 0.85em; font-weight: normal; margin-left: 5px;">(Lunes)</span>
                                
                                <?php 
                                    // Lógica para badge "NUEVO"
                                    $horas_desde_creacion = (time() - strtotime($c['fecha_creacion'])) / 3600;
                                    if($horas_desde_creacion < 24): 
                                ?>
                                    <span class="badge-new">Nuevo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge-zona"><?= $c['zona'] ?></span>
                            </td>
                            <td class="text-green" style="font-weight: 700; font-size: 1.05rem;">
                                <?= formatCurrency($c['total_recaudado']) ?>
                            </td>
                            <td style="color: var(--text-muted); font-size: 0.9em;">
                                <i data-lucide="clock" style="width: 14px; height: 14px; vertical-align: middle; margin-right: 4px;"></i>
                                <?= date('d/m/Y H:i', strtotime($c['fecha_creacion'])) ?>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 5px; justify-content: center;">
                                    <a href="ver_cierre.php?id=<?= $c['id'] ?>" target="_blank" class="btn-action" title="Ver Detalle">
                                        <i data-lucide="external-link" style="width: 16px; height: 16px;"></i>
                                    </a>
                                    
                                    <a href="eliminar_cierre.php?id=<?= $c['id'] ?>" 
                                       class="btn-action" 
                                       style="color: var(--accent-red); border-color: rgba(247, 118, 142, 0.2); background: rgba(247, 118, 142, 0.1);"
                                       onclick="return confirm('¿Estás seguro de que deseas eliminar esta liquidación permanentemente?');"
                                       title="Eliminar">
                                        <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state">
                    <i data-lucide="folder-open" class="empty-state-icon"></i>
                    <div class="empty-state-title">No hay liquidaciones registradas</div>
                    <div class="empty-state-description">
                        Aún no se han cargado cierres de cobranza en el sistema.<br>
                        Comienza cargando una nueva liquidación semanal.
                    </div>
                    <a href="cargar.php" class="empty-state-button">
                        <i data-lucide="plus-circle" style="width: 18px; height: 18px; vertical-align: text-bottom; margin-right: 5px;"></i>
                        Nueva Carga
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>