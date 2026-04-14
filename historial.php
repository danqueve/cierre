<?php
require 'db.php';
requireAuth();

// FILTRO: Excluir empleados administrativos
$empleados_admin = ['Alejandro', 'Emilia', 'Luz', 'Maxi'];
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
    WHERE c.zona NOT IN ($placeholders)
    GROUP BY c.id
    ORDER BY c.fecha_inicio DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($empleados_admin);
$cierres = $stmt->fetchAll();

// --- KPIs de resumen ---
$total_cierres  = count($cierres);
$gran_total     = array_sum(array_column($cierres, 'total_recaudado'));
$max_recaudado  = $total_cierres > 0 ? max(array_column($cierres, 'total_recaudado')) : 1;
$promedio       = $total_cierres > 0 ? $gran_total / $total_cierres : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Cierres</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* ===== KPI STRIP ===== */
        .kpi-strip {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .kpi-pill {
            flex: 1;
            min-width: 160px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 14px;
            padding: 1rem 1.2rem;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: border-color 0.3s;
        }
        .kpi-pill:hover { border-color: rgba(255,255,255,0.15); }
        .kpi-pill-icon {
            width: 40px; height: 40px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .kpi-pill-icon svg { width: 18px; height: 18px; }
        .kpi-pill-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600; }
        .kpi-pill-value { font-size: 1.3rem; font-weight: 800; line-height: 1.1; margin-top: 2px; }

        /* ===== BARRA DE BÚSQUEDA ===== */
        .search-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 0.6rem 1rem;
            margin-bottom: 1.2rem;
            transition: border-color 0.3s;
        }
        .search-bar:focus-within { border-color: var(--accent-purple); }
        .search-bar svg { color: var(--text-muted); width: 16px; height: 16px; flex-shrink:0; }
        .search-bar input {
            background: transparent;
            border: none;
            outline: none;
            color: var(--text-main);
            font-size: 0.95rem;
            width: 100%;
            border-radius: 0;
        }
        .search-bar input::placeholder { color: var(--text-muted); }
        #search-count { font-size: 0.8rem; color: var(--text-muted); white-space: nowrap; }

        /* ===== TABLA MEJORADA ===== */
        .hist-table { width: 100%; border-collapse: collapse; }
        .hist-table thead tr {
            background: rgba(255,255,255,0.02);
            border-bottom: 1px solid var(--border-color);
        }
        .hist-table th {
            padding: 12px 16px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-muted);
            font-weight: 700;
            border-bottom: none;
            background: transparent;
        }
        .hist-table td {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            vertical-align: middle;
        }
        .hist-table tbody tr {
            transition: background 0.2s ease, transform 0.15s ease;
        }
        .hist-table tbody tr:hover {
            background: rgba(187,154,247,0.06);
            transform: translateX(4px);
        }
        .hist-table tbody tr:hover td { border-bottom-color: rgba(187,154,247,0.1); }
        .hist-table tbody tr.hidden-row { display: none; }


        /* ===== MODAL DE CONFIRMACIÓN ===== */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            z-index: 9000;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-box {
            background: #1e1e24;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 2rem;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 24px 60px rgba(0,0,0,0.6);
            transform: scale(0.92) translateY(10px);
            transition: transform 0.3s cubic-bezier(0.175,0.885,0.32,1.275);
        }
        .modal-overlay.active .modal-box { transform: scale(1) translateY(0); }
        .modal-icon-wrap {
            width: 60px; height: 60px;
            border-radius: 50%;
            background: rgba(247,118,142,0.12);
            border: 1px solid rgba(247,118,142,0.2);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.2rem;
        }
        .modal-icon-wrap svg { width: 26px; height: 26px; color: var(--accent-red); }
        .modal-title { font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem; }
        .modal-desc { font-size: 0.9rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 1.5rem; }
        .modal-actions { display: flex; gap: 10px; justify-content: center; }
        .modal-btn {
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }
        .modal-btn-cancel {
            background: rgba(255,255,255,0.07);
            color: var(--text-muted);
            border: 1px solid rgba(255,255,255,0.08);
        }
        .modal-btn-cancel:hover { background: rgba(255,255,255,0.12); color: var(--text-main); }
        .modal-btn-confirm {
            background: linear-gradient(135deg, #f7768e, #e04060);
            color: white;
            box-shadow: 0 4px 15px rgba(247,118,142,0.3);
        }
        .modal-btn-confirm:hover { filter: brightness(1.15); transform: translateY(-1px); }

        /* ===== BOTONES DE ACCIÓN ===== */
        .act-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 15px;
            border-radius: 99px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            white-space: nowrap;
        }
        .act-btn svg { width: 13px; height: 13px; flex-shrink: 0; }
        .act-btn:hover { transform: translateY(-2px); }
        .act-btn:active { transform: scale(0.96); }

        /* Ver — gradiente azul/morado */
        .act-btn-view {
            background: linear-gradient(135deg, rgba(122,162,247,0.12), rgba(187,154,247,0.12));
            color: var(--accent-blue);
            border: 1px solid rgba(122,162,247,0.28);
        }
        .act-btn-view:hover {
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            color: #fff;
            border-color: transparent;
            box-shadow: 0 6px 20px rgba(122,162,247,0.35);
        }

        /* Eliminar — rojo */
        .act-btn-delete {
            background: rgba(247,118,142,0.1);
            color: var(--accent-red);
            border: 1px solid rgba(247,118,142,0.25);
        }
        .act-btn-delete:hover {
            background: linear-gradient(135deg, #f7768e, #e04060);
            color: #fff;
            border-color: transparent;
            box-shadow: 0 6px 20px rgba(247,118,142,0.35);
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <!-- Modal de Confirmación de Eliminación -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-box">
            <div class="modal-icon-wrap">
                <i data-lucide="trash-2"></i>
            </div>
            <div class="modal-title">¿Eliminar liquidación?</div>
            <div class="modal-desc">Esta acción es <strong>permanente</strong> y no se puede deshacer. Se eliminarán todos los detalles asociados a este cierre.</div>
            <div class="modal-actions">
                <button class="modal-btn modal-btn-cancel" onclick="closeModal()">Cancelar</button>
                <form id="deleteForm" method="POST" action="eliminar_cierre.php" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" id="deleteIdInput" value="">
                    <button type="submit" class="modal-btn modal-btn-confirm">Sí, eliminar</button>
                </form>
            </div>
        </div>
    </div>

    <div class="container" id="main-content">
        <div class="card">

            <!-- Encabezado con título y botón de nueva carga -->
            <div class="page-title-row">
                <div>
                    <h2 class="page-title">Historial de Liquidaciones</h2>
                    <p class="page-subtitle">Cobranzas registradas en el sistema</p>
                </div>
                <a href="cargar.php" class="btn btn-primary btn-with-icon" title="Nueva Carga">
                    <i data-lucide="plus-circle" style="width:16px; height:16px;"></i> Nueva Carga
                </a>
            </div>

            <?php if($total_cierres > 0): ?>

            <!-- KPIs de resumen -->
            <div class="kpi-strip">
                <div class="kpi-pill">
                    <div class="kpi-pill-icon" style="background:rgba(122,162,247,0.15); color:var(--accent-blue);">
                        <i data-lucide="list-checks"></i>
                    </div>
                    <div>
                        <div class="kpi-pill-label">Cierres Totales</div>
                        <div class="kpi-pill-value" style="color:var(--accent-blue);"><?= $total_cierres ?></div>
                    </div>
                </div>
                <div class="kpi-pill">
                    <div class="kpi-pill-icon" style="background:rgba(158,206,106,0.15); color:var(--accent-green);">
                        <i data-lucide="dollar-sign"></i>
                    </div>
                    <div>
                        <div class="kpi-pill-label">Total Recaudado</div>
                        <div class="kpi-pill-value" style="color:var(--accent-green);"><?= formatCurrency($gran_total) ?></div>
                    </div>
                </div>
                <div class="kpi-pill">
                    <div class="kpi-pill-icon" style="background:rgba(187,154,247,0.15); color:var(--accent-purple);">
                        <i data-lucide="trending-up"></i>
                    </div>
                    <div>
                        <div class="kpi-pill-label">Mejor Semana</div>
                        <div class="kpi-pill-value" style="color:var(--accent-purple);"><?= formatCurrency($max_recaudado) ?></div>
                    </div>
                </div>
                <div class="kpi-pill">
                    <div class="kpi-pill-icon" style="background:rgba(224,175,104,0.15); color:var(--accent-yellow);">
                        <i data-lucide="bar-chart"></i>
                    </div>
                    <div>
                        <div class="kpi-pill-label">Promedio Semanal</div>
                        <div class="kpi-pill-value" style="color:var(--accent-yellow);"><?= formatCurrency($promedio) ?></div>
                    </div>
                </div>
            </div>

            <!-- Barra de búsqueda -->
            <div class="search-bar">
                <i data-lucide="search"></i>
                <input type="text" id="searchInput" placeholder="Buscar por zona o fecha…" oninput="filterTable()">
                <span id="search-count"><?= $total_cierres ?> registros</span>
            </div>

            <!-- Tabla -->
            <div style="overflow-x: auto;">
                <table class="hist-table" id="histTable">
                    <thead>
                        <tr>
                            <th>Fecha Semana</th>
                            <th>Zona</th>
                            <th>Total Recaudado</th>
            <th style="text-align:center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($cierres as $c):
                            $horas_desde_creacion = (time() - strtotime($c['fecha_creacion'])) / 3600;
                        ?>
                        <tr>
                            <td class="col-date fw-600">
                                <?= date('d/m/Y', strtotime($c['fecha_inicio'])) ?>
                                <span class="date-day-label">(Lunes)</span>
                                <?php if($horas_desde_creacion < 24): ?>
                                    <span class="badge-new">Nuevo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge-zona"><?= htmlspecialchars($c['zona']) ?></span>
                            </td>
                            <td class="col-amount text-green fw-700">
                                <?= formatCurrency($c['total_recaudado']) ?>
                            </td>
                            <td class="col-actions">
                                <div class="action-buttons">
                                    <a href="ver_cierre.php?id=<?= $c['id'] ?>" target="_blank" class="act-btn act-btn-view" title="Ver Pantalla">
                                        <i data-lucide="monitor"></i> Ver
                                    </a>
                                    <a href="editar_cierre.php?id=<?= $c['id'] ?>" class="act-btn act-btn-edit" title="Editar Liquidación" style="color: #e2b93b; background: rgba(226,185,59,0.1);">
                                        <i data-lucide="edit"></i> Editar
                                    </a>
                                    <a href="generar_pdf.php?tipo=cierre&id=<?= $c['id'] ?>" target="_blank" class="act-btn act-btn-view" title="Descargar PDF" style="color: #3498db; background: rgba(52,152,219,0.1);">
                                        <i data-lucide="file-down"></i> PDF
                                    </a>
                                    <button
                                        class="act-btn act-btn-delete"
                                        onclick="openModal(<?= (int)$c['id'] ?>)"
                                        title="Eliminar cierre"
                                        aria-label="Eliminar cierre del <?= date('d/m/Y', strtotime($c['fecha_inicio'])) ?>">
                                        <i data-lucide="trash-2"></i> Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php else: ?>
                <div class="empty-state">
                    <i data-lucide="folder-open" class="empty-icon"></i>
                    <div class="empty-state-title">No hay liquidaciones registradas</div>
                    <div class="empty-state-description">
                        Aún no se han cargado cierres de cobranza en el sistema.<br>
                        Comenzá cargando una nueva liquidación semanal.
                    </div>
                    <a href="cargar.php" class="empty-state-button">
                        <i data-lucide="plus-circle" style="width:18px; height:18px; vertical-align:text-bottom; margin-right:5px;"></i>
                        Nueva Carga
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
        window.onload = function() { lucide.createIcons(); }

        // --- Búsqueda en tiempo real ---
        function filterTable() {
            const q = document.getElementById('searchInput').value.toLowerCase().trim();
            const rows = document.querySelectorAll('#histTable tbody tr');
            let visible = 0;
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const match = text.includes(q);
                row.classList.toggle('hidden-row', !match);
                if (match) visible++;
            });
            document.getElementById('search-count').textContent = visible + ' registro' + (visible !== 1 ? 's' : '');
        }

        // --- Modal de confirmación ---
        function openModal(id) {
            document.getElementById('deleteIdInput').value = id;
            document.getElementById('deleteModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('deleteModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Cerrar modal al hacer clic en el fondo
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // Cerrar modal con Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });
    </script>
</body>
</html>