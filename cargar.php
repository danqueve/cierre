<?php
require __DIR__ . '/includes/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$mensaje = '';
$dias = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];
$zonas = ['Zona Este', 'Zona Norte', 'Zona Sur', 'Zona Sgo', 'Leales','Catamarca', 'Tafi del Valle'];

// Procesar Guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $pdo->beginTransaction();
        
        $modo_carga = $_POST['modo_carga'];
        $zona  = $_POST['zona'];
        $fecha = $_POST['fecha_inicio'];
        
        $saldo_favor                   = $_POST['saldo_favor'] ?: 0;
        $saldo_concepto                = $_POST['saldo_concepto'];
        $descuento_creditos            = $_POST['descuento_creditos'] ?: 0;
        $descuento_creditos_concepto   = $_POST['descuento_creditos_concepto'];
        $porcentaje_comision           = is_numeric($_POST['porcentaje_comision'] ?? '') ? max(0, min(100, (float)$_POST['porcentaje_comision'])) : 6;

        $stmtCheck = $pdo->prepare("SELECT id FROM cierres_semanales WHERE zona = ? AND fecha_inicio = ?");
        $stmtCheck->execute([$zona, $fecha]);
        $existe = $stmtCheck->fetch();

        if ($existe) {
            $cierre_id = $existe['id'];
            if ($modo_carga === 'semanal' || ($saldo_favor > 0 || $descuento_creditos > 0)) {
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
<?php
$dias_lbl   = ['LUNES' => 'Lunes', 'MARTES' => 'Martes', 'MIERCOLES' => 'Miércoles', 'JUEVES' => 'Jueves', 'VIERNES' => 'Viernes', 'SABADO' => 'Sábado'];
$dias_short = ['LUNES' => 'Lu', 'MARTES' => 'Ma', 'MIERCOLES' => 'Mi', 'JUEVES' => 'Ju', 'VIERNES' => 'Vi', 'SABADO' => 'Sá'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cargar Cierre Semanal</title>
    <link rel="stylesheet" href="assets/css/organic.css">
    <link rel="stylesheet" href="assets/css/imperio.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="assets/js/main.js" defer></script>
    <script>
        var DIAS_IDX = { LUNES:0, MARTES:1, MIERCOLES:2, JUEVES:3, VIERNES:4, SABADO:5 };
        var DIAS_NOM = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];

        function toggleModo() {
            var modo = document.querySelector('input[name="modo_carga"]:checked').value;
            var contenedorSemanal = document.getElementById('contenedor-semanal');
            var contenedorDiario  = document.getElementById('contenedor-diario');
            if (modo === 'semanal') {
                contenedorSemanal.style.display = '';
                contenedorDiario.style.display = 'none';
                enableInputs(contenedorSemanal, true);
                enableInputs(contenedorDiario, false);
            } else {
                contenedorSemanal.style.display = 'none';
                contenedorDiario.style.display = '';
                enableInputs(contenedorSemanal, false);
                enableInputs(contenedorDiario, true);
                actualizarFechaDia();
            }
            calcularTotales();
        }

        function enableInputs(container, enable) {
            container.querySelectorAll('input, select').forEach(function(i) { i.disabled = !enable; });
        }

        function fmtMoneda(v) {
            return new Intl.NumberFormat('es-AR', { style:'currency', currency:'ARS', minimumFractionDigits:0 }).format(v);
        }

        function calcularTotales() {
            var totalEf = 0, totalTr = 0, totalGasto = 0;
            document.querySelectorAll('.calculable:not(:disabled)').forEach(function(input) {
                var val = parseFloat(input.value) || 0;
                if (input.name.indexOf('[efectivo]') !== -1      || input.name === 'dia_efectivo')     totalEf    += val;
                if (input.name.indexOf('[transferencia]') !== -1 || input.name === 'dia_transferencia') totalTr    += val;
                if (input.name.indexOf('[gasto_monto]') !== -1   || input.name === 'dia_gasto_monto')   totalGasto += val;
            });
            var saldo = parseFloat(document.querySelector('input[name="saldo_favor"]').value) || 0;
            var desc  = parseFloat(document.querySelector('input[name="descuento_creditos"]').value) || 0;
            var set = function(id, txt) { var el = document.getElementById(id); if (el) el.innerText = txt; };
            set('t_efectivo', fmtMoneda(totalEf));
            set('t_transf',   fmtMoneda(totalTr));
            set('t_total',    fmtMoneda(totalEf + totalTr));
            var pctEl = document.getElementById('porcentaje_comision');
            var pct = pctEl && pctEl.value !== '' ? (parseFloat(pctEl.value) || 0) : 6;
            set('r_pct',      String(pct).replace('.', ','));
            set('t_comision', fmtMoneda((totalEf + totalTr) * pct / 100));
            set('t_gastos',   fmtMoneda(totalGasto));
            set('t_saldo',    fmtMoneda(saldo));
            set('t_desc',     fmtMoneda(desc));
        }

        function fechaLunes() {
            var v = document.querySelector('input[name="fecha_inicio"]').value;
            if (!v) return null;
            var p = v.split('-');
            return new Date(parseInt(p[0]), parseInt(p[1]) - 1, parseInt(p[2]));
        }
        function sumarDias(d, n) { var r = new Date(d); r.setDate(d.getDate() + n); return r; }
        function dm(d)  { return String(d.getDate()).padStart(2,'0') + '/' + String(d.getMonth()+1).padStart(2,'0'); }
        function dmy(d) { return dm(d) + '/' + d.getFullYear(); }

        function actualizarFechas() {
            var lunes = fechaLunes();
            document.querySelectorAll('[data-dia-idx]').forEach(function(el) {
                el.innerText = lunes ? dm(sumarDias(lunes, parseInt(el.getAttribute('data-dia-idx')))) : '';
            });
            var tag = document.getElementById('semana-label');
            if (tag) tag.innerText = lunes ? 'Semana del ' + dm(lunes) + ' al ' + dmy(sumarDias(lunes, 5)) : '';
            actualizarFechaDia();
        }

        function actualizarFechaDia() {
            var lunes = fechaLunes();
            var sel   = document.querySelector('input[name="dia_seleccionado"]:checked');
            var fechaInfo = document.getElementById('fecha-dia-info');
            if (!lunes || !sel) { fechaInfo.innerHTML = ''; return; }
            var idx = DIAS_IDX[sel.value] || 0;
            fechaInfo.innerHTML = 'Fecha del pago: <strong>' + DIAS_NOM[idx] + ' ' + dmy(sumarDias(lunes, idx)) + '</strong>';
        }

        function actualizarZona() {
            var z = document.querySelector('input[name="zona"]:checked');
            var el = document.getElementById('r_zona');
            if (el && z) el.innerText = z.value;
        }

        window.onload = function() {
            toggleModo();
            actualizarFechas();
            actualizarZona();
            document.querySelector('input[name="fecha_inicio"]').addEventListener('change', actualizarFechas);
            document.querySelectorAll('input[name="dia_seleccionado"]').forEach(function(r) { r.addEventListener('change', actualizarFechaDia); });
            document.querySelectorAll('input[name="zona"]').forEach(function(r) { r.addEventListener('change', actualizarZona); });
            if (typeof lucide !== 'undefined') lucide.createIcons();
        };
    </script>
</head>
<body>

    <?php include __DIR__ . '/includes/header.php'; ?>

    <main class="imp-main">
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

        <div class="imp-page">
            <!-- Encabezado -->
            <div class="imp-page-head">
                <span class="imp-kicker">Cobranzas</span>
                <h1>Carga de cobranzas</h1>
                <p>Registrá los montos cobrados por zona y semana.</p>
            </div>

            <form method="POST" class="imp-layout">
                <?= csrf_field() ?>

                <div class="imp-col">

                    <!-- Configuración: zona, fecha y modo -->
                    <section class="imp-section">
                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <span class="imp-label">Zona de cobranza</span>
                            <div class="imp-chips">
                                <?php foreach($zonas as $i => $z): ?>
                                    <label class="imp-chip">
                                        <input type="radio" name="zona" value="<?= e($z) ?>" required <?= $i === 0 ? 'checked' : '' ?>>
                                        <span><?= e($z) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="imp-row">
                            <div class="field" style="flex:0 1 240px;">
                                <label for="fecha_inicio">Inicio de semana (lunes)</label>
                                <input type="date" id="fecha_inicio" name="fecha_inicio" required class="input on-light"
                                       value="<?= date('Y-m-d', strtotime('monday this week')) ?>">
                            </div>
                            <div class="field" style="flex:0 1 150px;">
                                <label for="porcentaje_comision" style="color:var(--color-accent-2-700); font-weight:700;">% de cobranza</label>
                                <div class="imp-money sage"><i>%</i>
                                    <input type="number" id="porcentaje_comision" name="porcentaje_comision" step="0.01" min="0" max="100"
                                           inputmode="decimal" class="input" value="6" oninput="calcularTotales()">
                                </div>
                            </div>
                            <span class="tag tag-accent-2 imp-tag-semana" id="semana-label"></span>

                            <div class="seg" style="margin-left:auto;">
                                <label class="seg-opt">
                                    <input type="radio" name="modo_carga" id="modo_semanal" value="semanal" checked onchange="toggleModo()">Semana completa
                                </label>
                                <label class="seg-opt">
                                    <input type="radio" name="modo_carga" id="modo_diario" value="diario" onchange="toggleModo()">Día por día
                                </label>
                            </div>
                        </div>
                    </section>

                    <!-- ═══ MODO SEMANAL ═══ -->
                    <section id="contenedor-semanal" class="imp-days">
                        <div class="imp-days-head">
                            <span>Día</span><span>Efectivo</span><span>Transferencia</span><span class="gasto">Gasto</span><span>Concepto gasto</span>
                        </div>
                        <?php foreach($dias as $i => $dia): ?>
                        <div class="imp-day">
                            <div class="imp-day-id">
                                <span class="imp-day-badge"><?= $dias_short[$dia] ?></span>
                                <div>
                                    <strong><?= $dias_lbl[$dia] ?></strong>
                                    <small data-dia-idx="<?= $i ?>"></small>
                                </div>
                            </div>
                            <div>
                                <span class="mlabel">Efectivo</span>
                                <div class="imp-money"><i>$</i>
                                    <input type="number" step="0.01" inputmode="decimal"
                                           name="data[<?= $dia ?>][efectivo]"
                                           class="input on-bg calculable"
                                           oninput="calcularTotales()" placeholder="0">
                                </div>
                            </div>
                            <div>
                                <span class="mlabel">Transferencia</span>
                                <div class="imp-money"><i>$</i>
                                    <input type="number" step="0.01" inputmode="decimal"
                                           name="data[<?= $dia ?>][transferencia]"
                                           class="input on-bg calculable"
                                           oninput="calcularTotales()" placeholder="0">
                                </div>
                            </div>
                            <div>
                                <span class="mlabel">Gasto</span>
                                <div class="imp-money gasto"><i>$</i>
                                    <input type="number" step="0.01" inputmode="decimal"
                                           name="data[<?= $dia ?>][gasto_monto]"
                                           class="input calculable"
                                           oninput="calcularTotales()" placeholder="0">
                                </div>
                            </div>
                            <div class="imp-concepto">
                                <span class="mlabel">Concepto gasto</span>
                                <input type="text" name="data[<?= $dia ?>][gasto_concepto]"
                                       class="input on-bg" placeholder="Detalle…">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </section>

                    <!-- ═══ MODO DIARIO ═══ -->
                    <section id="contenedor-diario" class="imp-section light" style="display:none;">
                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <span class="imp-label">Seleccioná el día a cargar</span>
                            <div class="imp-daypick">
                                <?php foreach($dias as $i => $dia): ?>
                                    <label>
                                        <input type="radio" name="dia_seleccionado" value="<?= $dia ?>" <?= $i === 0 ? 'checked' : '' ?>>
                                        <span><b><?= $dias_short[$dia] ?></b><small data-dia-idx="<?= $i ?>"></small></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div id="fecha-dia-info" class="imp-fechapago"></div>
                        </div>

                        <div class="imp-grid-fit">
                            <div class="field">
                                <label>Monto efectivo</label>
                                <input type="number" step="0.01" name="dia_efectivo" class="input calculable"
                                       oninput="calcularTotales()" placeholder="0.00">
                            </div>
                            <div class="field">
                                <label>Monto transferencia</label>
                                <input type="number" step="0.01" name="dia_transferencia" class="input calculable"
                                       oninput="calcularTotales()" placeholder="0.00">
                            </div>
                            <div class="field">
                                <label style="color:var(--color-accent-700);">Gasto (monto)</label>
                                <input type="number" step="0.01" name="dia_gasto_monto" class="input calculable"
                                       oninput="calcularTotales()" placeholder="0.00"
                                       style="background:var(--color-accent-100); color:var(--color-accent-800);">
                            </div>
                            <div class="field">
                                <label>Concepto gasto</label>
                                <input type="text" name="dia_gasto_concepto" class="input" placeholder="Ej. Nafta">
                            </div>
                        </div>
                    </section>

                    <!-- ═══ AJUSTES FINALES ═══ -->
                    <div class="imp-h3">
                        <h3>Ajustes finales</h3><small>Opcional</small>
                    </div>

                    <div class="imp-adjust-grid">
                        <!-- Saldo a favor -->
                        <div class="imp-adjust plus">
                            <div class="imp-adjust-title"><span class="imp-adjust-sign">+</span> Saldo a favor</div>
                            <div class="field">
                                <label>Monto</label>
                                <input type="number" step="0.01" name="saldo_favor" class="input on-light"
                                       oninput="calcularTotales()" placeholder="0.00">
                            </div>
                            <div class="field">
                                <label>Concepto</label>
                                <input type="text" name="saldo_concepto" class="input on-light"
                                       placeholder="Ej. Bono por objetivos">
                            </div>
                        </div>

                        <!-- Descuento créditos -->
                        <div class="imp-adjust minus">
                            <div class="imp-adjust-title"><span class="imp-adjust-sign">−</span> Descuento créditos</div>
                            <div class="field">
                                <label>Monto</label>
                                <input type="number" step="0.01" name="descuento_creditos" class="input on-light"
                                       oninput="calcularTotales()" placeholder="0.00">
                            </div>
                            <div class="field">
                                <label>Concepto</label>
                                <input type="text" name="descuento_creditos_concepto" class="input on-light"
                                       placeholder="Ej. Cuota 1/3 Préstamo">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ PANEL RESUMEN ═══ -->
                <aside class="imp-summary">
                    <span class="imp-kicker">Resumen · <span id="r_zona"><?= e($zonas[0]) ?></span></span>
                    <div class="imp-summary-total">
                        <small>Total cobrado</small>
                        <span class="imp-money-big" id="t_total">$ 0</span>
                    </div>
                    <div class="imp-summary-rows">
                        <div><span>Efectivo</span><strong class="imp-num" id="t_efectivo">$ 0</strong></div>
                        <div><span>Transferencia</span><strong class="imp-num" id="t_transf">$ 0</strong></div>
                        <div class="good"><span>Comisión (<span id="r_pct">6</span>%)</span><strong class="imp-num" id="t_comision">$ 0</strong></div>
                        <div class="warn"><span>Gastos</span><strong class="imp-num" id="t_gastos">$ 0</strong></div>
                        <hr>
                        <div class="good"><span>Saldo a favor (+)</span><strong class="imp-num" id="t_saldo">$ 0</strong></div>
                        <div class="warn"><span>Descuento créditos (−)</span><strong class="imp-num" id="t_desc">$ 0</strong></div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="save" style="width:18px; height:18px;"></i> Guardar datos
                    </button>
                </aside>
            </form>
        </div>
    </main>
</body>
</html>
