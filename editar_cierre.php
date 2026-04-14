<?php
require 'db.php';
requireAuth();

$mensaje = '';
$dias = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];
$zonas = ['Zona 1', 'Zona 2', 'Zona 3', 'Zona 4a6'];

$edit_id = $_GET['id'] ?? null;
if (!$edit_id) { die("ID inválido"); }

// Fetch actual data
$stmtD = $pdo->prepare("SELECT * FROM cierres_semanales WHERE id = ?");
$stmtD->execute([$edit_id]);
$cEdit = $stmtD->fetch();
if (!$cEdit) { die("Cierre no encontrado"); }

$stmtDet = $pdo->prepare("SELECT * FROM detalles_diarios WHERE cierre_id = ?");
$stmtDet->execute([$edit_id]);
$detRow = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

$dEdit = [];
foreach($detRow as $r) {
    $dEdit[$r['dia_semana']] = $r;
}

// Procesar Guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $pdo->beginTransaction();
        
        $modo_carga = $_POST['modo_carga'];
        $zona  = $_POST['zona'];
        $fecha = $_POST['fecha_inicio'];
        
        $saldo_favor                   = $_POST['saldo_favor'] ?: 0;
        $saldo_concepto                = $_POST['saldo_concepto'];
        $descuento_creditos            = $_POST['descuento_creditos'] ?: 0;
        $descuento_creditos_concepto   = $_POST['descuento_creditos_concepto'];
        $porcentaje_comision           = $_POST['porcentaje_comision'] ?: 6.00;

        $stmtCheck = $pdo->prepare("SELECT id FROM cierres_semanales WHERE zona = ? AND fecha_inicio = ?");
        $stmtCheck->execute([$zona, $fecha]);
        $existe = $stmtCheck->fetch();

        if ($existe) {
            $cierre_id = $existe['id'];
            if ($modo_carga === 'semanal' || ($saldo_favor > 0 || $descuento_creditos > 0 || $porcentaje_comision != 6.00)) {
                $stmtUpd = $pdo->prepare("UPDATE cierres_semanales SET saldo_favor=?, saldo_concepto=?, descuento_creditos=?, descuento_creditos_concepto=?, porcentaje_comision=? WHERE id=?");
                $stmtUpd->execute([$saldo_favor, $saldo_concepto, $descuento_creditos, $descuento_creditos_concepto, $porcentaje_comision, $cierre_id]);
            }
        } else {
            $stmtIns = $pdo->prepare("INSERT INTO cierres_semanales (zona, fecha_inicio, saldo_favor, saldo_concepto, descuento_creditos, descuento_creditos_concepto, porcentaje_comision) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtIns->execute([$zona, $fecha, $saldo_favor, $saldo_concepto, $descuento_creditos, $descuento_creditos_concepto, $porcentaje_comision]);
            $cierre_id = $pdo->lastInsertId();
        }

        $stmtInsertarDetalle = $pdo->prepare("INSERT INTO detalles_diarios (cierre_id, dia_semana, efectivo, transferencia, gasto_monto, gasto_concepto) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtUpdateDetalle   = $pdo->prepare("UPDATE detalles_diarios SET efectivo=?, transferencia=?, gasto_monto=?, gasto_concepto=? WHERE cierre_id=? AND dia_semana=?");

        if ($modo_carga === 'semanal') {
            $pdo->prepare("DELETE FROM detalles_diarios WHERE cierre_id=?")->execute([$cierre_id]);
            foreach ($dias as $dia) {
                $ef = $_POST['data'][$dia]['efectivo'] ?: 0;
                $tr = $_POST['data'][$dia]['transferencia'] ?: 0;
                $gm = $_POST['data'][$dia]['gasto_monto'] ?: 0;
                $gc = $_POST['data'][$dia]['gasto_concepto'];
                $stmtInsertarDetalle->execute([$cierre_id, $dia, $ef, $tr, $gm, $gc]);
            }
        } else {
            $dia_seleccionado = $_POST['dia_seleccionado'];
            $ef = $_POST['dia_efectivo'] ?: 0;
            $tr = $_POST['dia_transferencia'] ?: 0;
            $gm = $_POST['dia_gasto_monto'] ?: 0;
            $gc = $_POST['dia_gasto_concepto'];
            $stmtCheckDia = $pdo->prepare("SELECT id FROM detalles_diarios WHERE cierre_id=? AND dia_semana=?");
            $stmtCheckDia->execute([$cierre_id, $dia_seleccionado]);
            if ($stmtCheckDia->fetch()) {
                $stmtUpdateDetalle->execute([$ef, $tr, $gm, $gc, $cierre_id, $dia_seleccionado]);
            } else {
                $stmtInsertarDetalle->execute([$cierre_id, $dia_seleccionado, $ef, $tr, $gm, $gc]);
            }
        }

        $pdo->commit();
        $mensaje = "¡Datos guardados correctamente!";

    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cierre Semanal</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="main.js" defer></script>
    <style>
        /* ===== ENCABEZADO DE PÁGINA ===== */
        .page-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 1.8rem;
        }
        .page-header-icon {
            width: 52px; height: 52px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(187,154,247,0.2), rgba(122,162,247,0.2));
            border: 1px solid rgba(187,154,247,0.3);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .page-header-icon svg { width: 24px; height: 24px; color: var(--accent-purple); }
        .page-header h2 { margin: 0; font-size: 1.4rem; font-weight: 800; }
        .page-header p  { margin: 3px 0 0; font-size: 0.85rem; color: var(--text-muted); }

        /* ===== TABLA DE DÍAS ===== */
        .days-table { width: 100%; border-collapse: separate; border-spacing: 0 6px; }
        .days-table thead th {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-muted);
            font-weight: 700;
            padding: 0 12px 8px;
            border: none;
            background: transparent;
        }
        .days-table tbody tr {
            background: rgba(255,255,255,0.025);
            border-radius: 12px;
            transition: background 0.2s;
        }
        .days-table tbody tr:hover { background: rgba(255,255,255,0.05); }
        .days-table tbody td {
            padding: 8px 10px;
            border: none;
        }
        .days-table tbody td:first-child {
            border-radius: 12px 0 0 12px;
            padding-left: 16px;
        }
        .days-table tbody td:last-child { border-radius: 0 12px 12px 0; }
        .day-label {
            font-weight: 700;
            font-size: 0.82rem;
            color: var(--accent-blue);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .days-table tfoot td {
            padding: 14px 10px 4px;
            border-top: 1px solid rgba(255,255,255,0.06);
            font-weight: 700;
            font-size: 0.95rem;
        }
        .days-table tfoot td:first-child { padding-left: 16px; color: var(--text-muted); font-size: 0.8rem; }
        .days-table .input-group { margin-bottom: 0; }
        .days-table input[type="number"],
        .days-table input[type="text"] { padding: 9px 9px 9px 34px; font-size: 0.88rem; }

        .form-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:20px; margin-bottom:1.5rem; }
        /* ===== SEPARADOR CON LABEL ===== */
        .section-sep {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 1.6rem 0 1.2rem;
        }
        .section-sep span {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            white-space: nowrap;
        }
        .section-sep::before,
        .section-sep::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255,255,255,0.07);
        }

        /* ===== CONTENEDOR CARGA DIARIA ===== */
        .daily-box {
            background: rgba(122,162,247,0.04);
            border: 1px solid rgba(122,162,247,0.2);
            border-radius: 16px;
            padding: 1.5rem;
        }
        .daily-box h3 {
            margin: 0 0 1.2rem;
            font-size: 0.95rem;
            color: var(--accent-blue);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .daily-box h3 svg { width: 16px; height: 16px; }

        /* ===== AJUSTES FINALES ===== */
        .adjustments-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 1.5rem;
        }
        @media (max-width: 640px) { .adjustments-grid { grid-template-columns: 1fr; } }
        .adjust-card {
            border-radius: 14px;
            padding: 1.2rem;
            border: 1px solid;
        }
        .adjust-card-green { background: rgba(158,206,106,0.05); border-color: rgba(158,206,106,0.2); }
        .adjust-card-red   { background: rgba(247,118,142,0.05); border-color: rgba(247,118,142,0.2); }
        .adjust-card-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 12px;
        }
        .adjust-card-title svg { width: 14px; height: 14px; }

        /* ===== BOTÓN GUARDAR ===== */
        .submit-row {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            margin-top: 1.8rem;
            padding-top: 1.2rem;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        .btn-save {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 13px 32px;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            background: linear-gradient(135deg, var(--accent-purple), #7c3aed);
            color: white;
            box-shadow: 0 6px 24px rgba(124,58,237,0.35);
            transition: all 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .btn-save svg { width: 18px; height: 18px; }
        .btn-save:hover  { filter: brightness(1.15); transform: translateY(-2px); box-shadow: 0 10px 30px rgba(124,58,237,0.45); }
        .btn-save:active { transform: scale(0.97); }
    </style>
    <script>
        function toggleModo() {
            var modo = document.querySelector('input[name="modo_carga"]:checked').value;
            var contenedorSemanal = document.getElementById('contenedor-semanal');
            var contenedorDiario  = document.getElementById('contenedor-diario');
            if (modo === 'semanal') {
                contenedorSemanal.style.display = 'block';
                contenedorSemanal.classList.add('slide-up');
                contenedorDiario.style.display = 'none';
                contenedorDiario.classList.remove('slide-up');
                enableInputs(contenedorSemanal, true);
                enableInputs(contenedorDiario, false);
            } else {
                contenedorSemanal.style.display = 'none';
                contenedorSemanal.classList.remove('slide-up');
                contenedorDiario.style.display = 'block';
                contenedorDiario.classList.add('slide-up');
                enableInputs(contenedorSemanal, false);
                enableInputs(contenedorDiario, true);
                actualizarFechaDia();
            }
        }

        function enableInputs(container, enable) {
            container.querySelectorAll('input, select').forEach(function(i) { i.disabled = !enable; });
        }

        function calcularTotales() {
            var totalEf = 0, totalTr = 0, totalGasto = 0;
            document.querySelectorAll('.calculable:not(:disabled)').forEach(function(input) {
                var val = parseFloat(input.value) || 0;
                if (input.name.indexOf('[efectivo]') !== -1      || input.name === 'dia_efectivo')     totalEf    += val;
                if (input.name.indexOf('[transferencia]') !== -1 || input.name === 'dia_transferencia') totalTr    += val;
                if (input.name.indexOf('[gasto_monto]') !== -1   || input.name === 'dia_gasto_monto')   totalGasto += val;
            });
            var fmt = function(v) { return new Intl.NumberFormat('es-AR', { style:'currency', currency:'ARS', minimumFractionDigits:0 }).format(v); };
            var elEf = document.getElementById('t_efectivo');
            var elTr = document.getElementById('t_transf');
            var elTo = document.getElementById('t_total');
            if (elEf) elEf.innerText = fmt(totalEf);
            if (elTr) elTr.innerText = fmt(totalTr);
            if (elTo) elTo.innerText = fmt(totalEf + totalTr);
        }

        function actualizarFechaDia() {
            var fechaInicioInput = document.querySelector('input[name="fecha_inicio"]');
            var diaSelect  = document.querySelector('select[name="dia_seleccionado"]');
            var fechaInfo  = document.getElementById('fecha-dia-info');
            if (!fechaInicioInput.value || !diaSelect.value) { fechaInfo.innerText = ''; return; }
            var parts = fechaInicioInput.value.split('-');
            var anio = parseInt(parts[0]), mes = parseInt(parts[1]), dia = parseInt(parts[2]);
            var fechaLunes = new Date(anio, mes - 1, dia);
            var diasOffset = { LUNES:0, MARTES:1, MIERCOLES:2, JUEVES:3, VIERNES:4, SABADO:5 };
            var fechaRes = new Date(fechaLunes);
            fechaRes.setDate(fechaLunes.getDate() + (diasOffset[diaSelect.value] || 0));
            var d = String(fechaRes.getDate()).padStart(2,'0');
            var m = String(fechaRes.getMonth()+1).padStart(2,'0');
            fechaInfo.innerHTML = 'Fecha del pago: <strong style="color:var(--accent-green);">' + d + '/' + m + '/' + fechaRes.getFullYear() + '</strong>';
        }

        window.onload = function() {
            toggleModo();
            document.querySelector('input[name="fecha_inicio"]').addEventListener('change', actualizarFechaDia);
            document.querySelector('select[name="dia_seleccionado"]').addEventListener('change', actualizarFechaDia);
            if (typeof lucide !== 'undefined') lucide.createIcons();
            calcularTotales();
        };
    </script>
</head>
<body class="fade-in">

    <?php include 'header.php'; ?>

    <div class="container" id="main-content">
        <div id="toast-container" class="toast-container"></div>

        <?php if($mensaje): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var msg = "<?= addslashes($mensaje) ?>";
                    var isError = <?= strpos($mensaje, 'Error') !== false ? 'true' : 'false' ?>;
                    showToast(msg, isError ? 'error' : 'success');
                });
            </script>
        <?php endif; ?>

        <form method="POST" action="editar_cierre.php?id=<?= $edit_id ?>">
            <?= csrf_field() ?>
            <div class="card">

                <!-- Encabezado -->
                <div class="page-header">
                    <div class="page-header-icon">
                        <i data-lucide="clipboard-list"></i>
                    </div>
                    <div>
                        <h2>Editar Cobranzas</h2>
                        <p>Modificá los montos cobrados por zona y semana</p>
                    </div>
                </div>

                <!-- Zona y Fecha -->
                <div class="form-grid">
                    <div class="input-group">
                        <label>Zona de Cobranza</label>
                        <i data-lucide="map-pin" class="input-icon"></i>
                        <select name="zona" required class="input-with-icon">
                            <?php foreach($zonas as $z): ?>
                                <option value="<?= $z ?>" <?= $cEdit["zona"] === $z ? "selected" : "" ?>><?= $z ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Inicio de Semana (Lunes)</label>
                        <i data-lucide="calendar" class="input-icon"></i>
                        <input type="date" name="fecha_inicio" required
                               value="<?= $cEdit['fecha_inicio'] ?>" readonly style="background: rgba(255,255,255,0.05); color: #888; border-color: transparent;"
                               class="input-with-icon">
                    </div>
                </div>

                <!-- Toggle de modo -->
                <div class="segmented-control">
                    <input type="radio" name="modo_carga" id="modo_semanal" value="semanal" checked onchange="toggleModo()">
                    <label for="modo_semanal">
                        <i data-lucide="calendar-days" style="width:15px;"></i> Semana Completa
                    </label>
                    <input type="radio" name="modo_carga" id="modo_diario" value="diario" onchange="toggleModo()">
                    <label for="modo_diario">
                        <i data-lucide="calendar" style="width:15px;"></i> Día por Día
                    </label>
                </div>

                <!-- ═══ MODO SEMANAL ═══ -->
                <div id="contenedor-semanal">
                    <div class="section-sep"><span>Detalle por Día</span></div>
                    <div style="overflow-x:auto;">
                        <table class="days-table">
                            <thead>
                                <tr>
                                    <th style="text-align:left; width:100px;">Día</th>
                                    <th>Efectivo</th>
                                    <th>Transferencia</th>
                                    <th style="color:var(--accent-red);">Gasto</th>
                                    <th>Concepto Gasto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($dias as $dia): ?>
                                <tr>
                                    <td><span class="day-label"><?= $dia ?></span></td>
                                    <td>
                                        <div class="input-group" style="margin-bottom:0;">
                                            <i data-lucide="banknote" class="input-icon" style="width:15px;"></i>
                                            <input type="number" step="0.01"
                                                   name="data[<?= $dia ?>][efectivo]" value="<?= htmlspecialchars($dEdit[$dia]["efectivo"] ?? "") ?>" 
                                                   class="calculable input-with-icon"
                                                   oninput="calcularTotales()" placeholder="0">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="input-group" style="margin-bottom:0;">
                                            <i data-lucide="credit-card" class="input-icon" style="width:15px;"></i>
                                            <input type="number" step="0.01"
                                                   name="data[<?= $dia ?>][transferencia]" value="<?= htmlspecialchars($dEdit[$dia]["transferencia"] ?? "") ?>" 
                                                   class="calculable input-with-icon"
                                                   oninput="calcularTotales()" placeholder="0">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="input-group" style="margin-bottom:0;">
                                            <i data-lucide="trending-down" class="input-icon" style="width:15px; color:var(--accent-red);"></i>
                                            <input type="number" step="0.01"
                                                   name="data[<?= $dia ?>][gasto_monto]" value="<?= htmlspecialchars($dEdit[$dia]["gasto_monto"] ?? "") ?>" 
                                                   class="calculable input-with-icon"
                                                   oninput="calcularTotales()"
                                                   style="color:var(--accent-red); border-bottom-color:rgba(247,118,142,0.35);"
                                                   placeholder="0">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="input-group" style="margin-bottom:0;">
                                            <i data-lucide="tag" class="input-icon" style="width:15px;"></i>
                                            <input type="text"
                                                   name="data[<?= $dia ?>][gasto_concepto]" value="<?= htmlspecialchars($dEdit[$dia]["gasto_concepto"] ?? "") ?>" 
                                                   class="input-with-icon" placeholder="Detalle…">
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>TOTALES</td>
                                    <td id="t_efectivo" class="text-green">$ 0</td>
                                    <td id="t_transf"   class="text-blue">$ 0</td>
                                    <td colspan="2" id="t_total" style="color:white;">$ 0</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- ═══ MODO DIARIO ═══ -->
                <div id="contenedor-diario" style="display:none;">
                    <div class="section-sep"><span>Día Individual</span></div>
                    <div class="daily-box">
                        <h3><i data-lucide="calendar-check"></i> Cargar Día Individual</h3>
                        <div class="input-group" style="max-width:340px;">
                            <label>Seleccione el Día a Cargar</label>
                            <i data-lucide="calendar" class="input-icon"></i>
                            <select name="dia_seleccionado" class="input-with-icon"
                                    style="border-bottom-color:rgba(122,162,247,0.4);">
                                <?php foreach($dias as $dia): ?>
                                    <option value="<?= $dia ?>"><?= $dia ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div id="fecha-dia-info" style="margin-top:8px; font-size:0.9rem; color:var(--text-muted);"></div>
                        </div>
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); gap:16px; margin-top:8px;">
                            <div class="input-group">
                                <label>Monto Efectivo</label>
                                <i data-lucide="banknote" class="input-icon"></i>
                                <input type="number" step="0.01" name="dia_efectivo"
                                       class="calculable input-with-icon" placeholder="0.00">
                            </div>
                            <div class="input-group">
                                <label>Monto Transferencia</label>
                                <i data-lucide="credit-card" class="input-icon"></i>
                                <input type="number" step="0.01" name="dia_transferencia"
                                       class="calculable input-with-icon" placeholder="0.00">
                            </div>
                            <div class="input-group">
                                <label style="color:var(--accent-red);">Gasto (Monto)</label>
                                <i data-lucide="trending-down" class="input-icon" style="color:var(--accent-red);"></i>
                                <input type="number" step="0.01" name="dia_gasto_monto"
                                       class="calculable input-with-icon" placeholder="0.00"
                                       style="border-bottom-color:var(--accent-red); color:var(--accent-red);">
                            </div>
                            <div class="input-group">
                                <label>Concepto Gasto</label>
                                <i data-lucide="tag" class="input-icon"></i>
                                <input type="text" name="dia_gasto_concepto"
                                       class="input-with-icon" placeholder="Ej. Nafta">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ AJUSTES FINALES ═══ -->
                <div class="section-sep">
                    <span>Ajustes Finales <span style="font-weight:400; text-transform:none; letter-spacing:0;">(Opcional)</span></span>
                </div>

                <div class="adjustments-grid">
                    <!-- Saldo a Favor -->
                    <div class="adjust-card adjust-card-green">
                        <div class="adjust-card-title" style="color:var(--accent-green);">
                            <i data-lucide="plus-circle"></i> Saldo a Favor (+)
                        </div>
                        <div class="input-group" style="margin-bottom:12px;">
                            <label>Monto</label>
                            <i data-lucide="dollar-sign" class="input-icon" style="color:var(--accent-green);"></i>
                            <input type="number" step="0.01" name="saldo_favor" value="<?= htmlspecialchars($cEdit["saldo_favor"]) ?>" 
                                   placeholder="0.00" class="input-with-icon"
                                   style="border-bottom-color:rgba(158,206,106,0.4);">
                        </div>
                        <div class="input-group" style="margin-bottom:0;">
                            <label>Concepto</label>
                            <i data-lucide="tag" class="input-icon"></i>
                            <input type="text" name="saldo_concepto" class="input-with-icon" value="<?= htmlspecialchars($cEdit["saldo_concepto"]) ?>" 
                                   placeholder="Ej. Bono por objetivos">
                        </div>
                    </div>

                    <!-- Descuento Créditos -->
                    <div class="adjust-card adjust-card-red">
                        <div class="adjust-card-title" style="color:var(--accent-red);">
                            <i data-lucide="minus-circle"></i> Descuento Créditos (-)
                        </div>
                        <div class="input-group" style="margin-bottom:12px;">
                            <label>Monto</label>
                            <i data-lucide="dollar-sign" class="input-icon" style="color:var(--accent-red);"></i>
                            <input type="number" step="0.01" name="descuento_creditos" value="<?= htmlspecialchars($cEdit["descuento_creditos"]) ?>" 
                                   placeholder="0.00" class="input-with-icon"
                                   style="border-bottom-color:rgba(247,118,142,0.4); color:var(--accent-red);">
                        </div>
                        <div class="input-group" style="margin-bottom:0;">
                            <label>Concepto</label>
                            <i data-lucide="tag" class="input-icon"></i>
                            <input type="text" name="descuento_creditos_concepto" class="input-with-icon" value="<?= htmlspecialchars($cEdit["descuento_creditos_concepto"] ?? "") ?>" 
                                   placeholder="Ej. Cuota 1/3 Préstamo">
                        </div>
                    </div>

                    <!-- Ajuste de Comisión -->
                    <div class="adjust-card" style="border-left-color:var(--accent-blue);">
                        <div class="adjust-card-title" style="color:var(--accent-blue);">
                            <i data-lucide="percent"></i> Comisión (%)
                        </div>
                        <div class="input-group" style="margin-bottom:0;">
                            <label>Porcentaje</label>
                            <i data-lucide="settings" class="input-icon" style="color:var(--accent-blue);"></i>
                            <input type="number" step="0.5" name="porcentaje_comision"
                                   value="<?= htmlspecialchars($cEdit["porcentaje_comision"]) ?>" class="input-with-icon"
                                   style="border-bottom-color:rgba(122,162,247,0.4);">
                        </div>
                    </div>
                </div>

                <!-- Botón Guardar -->
                <div class="submit-row">
                    <button type="submit" class="btn-save">
                        <i data-lucide="save"></i> Guardar Datos
                    </button>
                </div>

            </div>
        </form>
    </div>
</body>
</html>