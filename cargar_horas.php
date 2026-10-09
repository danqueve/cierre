<?php
require __DIR__ . '/includes/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$mensaje = '';
$dias = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];
$empleados = ['Alejandro', 'Lourdes', 'Maxi', 'Cristian', 'Nahuel'];

// Modo edición: cargar datos existentes si se pasa ?id=X
$editando    = false;
$cierre_edit = null;
$horas_edit  = [];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $stmtE = $pdo->prepare("SELECT * FROM cierres_semanales WHERE id = ?");
    $stmtE->execute([$_GET['id']]);
    $cierre_edit = $stmtE->fetch();
    if ($cierre_edit) {
        $editando = true;
        $stmtH = $pdo->prepare("SELECT * FROM detalles_diarios WHERE cierre_id = ?");
        $stmtH->execute([$cierre_edit['id']]);
        foreach ($stmtH->fetchAll() as $row) {
            $horas_edit[$row['dia_semana']] = $row;
        }
    }
}

// Procesar Guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $pdo->beginTransaction();

        $empleado           = $_POST['zona'];
        $fecha              = $_POST['fecha_inicio'];
        $valor_hora         = $_POST['valor_hora'] ?: 0;
        $saldo_favor        = $_POST['saldo_favor'] ?: 0;
        $saldo_concepto     = $_POST['saldo_concepto'];
        $descuento          = $_POST['descuento'] ?: 0;
        $descuento_concepto = $_POST['descuento_concepto'];
        $cierre_id_post     = isset($_POST['cierre_id']) && is_numeric($_POST['cierre_id']) ? (int)$_POST['cierre_id'] : null;

        // 1. Crear o actualizar cabecera
        if ($cierre_id_post) {
            // Edición directa por ID
            $cierre_id = $cierre_id_post;
            $stmtUpd = $pdo->prepare("UPDATE cierres_semanales SET zona=?, fecha_inicio=?, valor_hora=?, saldo_favor=?, saldo_concepto=?, descuento_creditos=?, descuento_creditos_concepto=? WHERE id=?");
            $stmtUpd->execute([$empleado, $fecha, $valor_hora, $saldo_favor, $saldo_concepto, $descuento, $descuento_concepto, $cierre_id]);
        } else {
            $stmtCheck = $pdo->prepare("SELECT id FROM cierres_semanales WHERE zona = ? AND fecha_inicio = ?");
            $stmtCheck->execute([$empleado, $fecha]);
            $existe = $stmtCheck->fetch();
            if ($existe) {
                $cierre_id = $existe['id'];
                $stmtUpd = $pdo->prepare("UPDATE cierres_semanales SET valor_hora=?, saldo_favor=?, saldo_concepto=?, descuento_creditos=?, descuento_creditos_concepto=? WHERE id=?");
                $stmtUpd->execute([$valor_hora, $saldo_favor, $saldo_concepto, $descuento, $descuento_concepto, $cierre_id]);
            } else {
                $stmtIns = $pdo->prepare("INSERT INTO cierres_semanales (zona, fecha_inicio, valor_hora, saldo_favor, saldo_concepto, descuento_creditos, descuento_creditos_concepto) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmtIns->execute([$empleado, $fecha, $valor_hora, $saldo_favor, $saldo_concepto, $descuento, $descuento_concepto]);
                $cierre_id = $pdo->lastInsertId();
            }
        }

        // 2. Guardar Horarios (Turno Único)
        $stmtCheckDia = $pdo->prepare("SELECT id FROM detalles_diarios WHERE cierre_id=? AND dia_semana=?");
        $stmtInsDia = $pdo->prepare("INSERT INTO detalles_diarios (cierre_id, dia_semana, hora_entrada, hora_salida) VALUES (?, ?, ?, ?)");
        $stmtUpdDia = $pdo->prepare("UPDATE detalles_diarios SET hora_entrada=?, hora_salida=? WHERE id=?");

        foreach ($dias as $dia) {
            $e1 = !empty($_POST['horas'][$dia]['entrada']) ? $_POST['horas'][$dia]['entrada'] : null;
            $s1 = !empty($_POST['horas'][$dia]['salida']) ? $_POST['horas'][$dia]['salida'] : null;

            $stmtCheckDia->execute([$cierre_id, $dia]);
            $diaExistente = $stmtCheckDia->fetch();

            if ($diaExistente) {
                $stmtUpdDia->execute([$e1, $s1, $diaExistente['id']]);
            } else {
                if($e1 || $s1) {
                    $stmtInsDia->execute([$cierre_id, $dia, $e1, $s1]);
                }
            }
        }

        $pdo->commit();
        $mensaje = "¡Liquidación guardada correctamente!";
        header("Location: liquidar_horas.php?id=" . $cierre_id);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = "Error: " . $e->getMessage();
    }
}
?>
<?php
$dias_lbl   = ['LUNES' => 'Lunes', 'MARTES' => 'Martes', 'MIERCOLES' => 'Miércoles', 'JUEVES' => 'Jueves', 'VIERNES' => 'Viernes', 'SABADO' => 'Sábado'];
$dias_short = ['LUNES' => 'Lu', 'MARTES' => 'Ma', 'MIERCOLES' => 'Mi', 'JUEVES' => 'Ju', 'VIERNES' => 'Vi', 'SABADO' => 'Sá'];
$emp_sel = $editando ? $cierre_edit['zona'] : $empleados[0];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cargar Horarios</title>
    <link rel="stylesheet" href="assets/css/organic.css">
    <link rel="stylesheet" href="assets/css/imperio.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="assets/js/main.js" defer></script>
    <script>
        var DIAS = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];

        function calcularHoras() {
            let totalMinutosSemana = 0;
            const valorHora = parseFloat(document.getElementById('valor_hora').value) || 0;
            const saldoFavor = parseFloat(document.getElementById('saldo_favor').value) || 0;
            const descuento = parseFloat(document.getElementById('descuento').value) || 0;

            DIAS.forEach(dia => {
                const e1 = document.getElementsByName(`horas[${dia}][entrada]`)[0].value;
                const s1 = document.getElementsByName(`horas[${dia}][salida]`)[0].value;

                const spanTotal = document.getElementById(`total_${dia}`);
                const barra = document.getElementById(`barra_${dia}`);
                let minutosDia = 0;

                if (e1 && s1) {
                    minutosDia += calcularDiferencia(e1, s1);
                }

                if (minutosDia > 0) {
                    const h = Math.floor(minutosDia / 60);
                    const m = minutosDia % 60;
                    spanTotal.innerText = `${h}h ${m}m`;
                    spanTotal.classList.add('on');
                    totalMinutosSemana += minutosDia;

                    // Barra de turno: left = inicio/24h, width = duración/24h
                    const partes = e1.split(':');
                    const inicio = parseInt(partes[0]) * 60 + parseInt(partes[1]);
                    const ancho = Math.min(minutosDia, 1440 - inicio);
                    barra.style.left = (inicio / 1440 * 100) + '%';
                    barra.style.width = (ancho / 1440 * 100) + '%';
                } else {
                    spanTotal.innerText = '0h 0m';
                    spanTotal.classList.remove('on');
                    barra.style.left = '0';
                    barra.style.width = '0';
                }
            });

            // Totales Generales
            const totalHorasDecimal = totalMinutosSemana / 60;
            const sueldoBruto = totalHorasDecimal * valorHora;
            const totalNeto = sueldoBruto + saldoFavor - descuento;

            const horasTotales = Math.floor(totalMinutosSemana / 60);
            const minutosTotales = totalMinutosSemana % 60;

            // Actualizar UI
            document.getElementById('t_horas').innerText = `${horasTotales}h ${minutosTotales}m`;

            const formatter = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' });

            document.getElementById('t_bruto').innerText = formatter.format(sueldoBruto);
            document.getElementById('t_saldo').innerText = formatter.format(saldoFavor);
            document.getElementById('t_desc').innerText = formatter.format(descuento);
            document.getElementById('t_neto').innerText = formatter.format(totalNeto);
        }

        function calcularDiferencia(inicio, fin) {
            const start = new Date(`2000-01-01T${inicio}`);
            const end = new Date(`2000-01-01T${fin}`);
            let diff = (end - start) / 1000 / 60;
            if (diff < 0) diff += 24 * 60;
            return diff;
        }

        // Copia entrada/salida del lunes a martes–viernes (no al sábado)
        function repetirLunes() {
            const e = document.getElementsByName('horas[LUNES][entrada]')[0].value;
            const s = document.getElementsByName('horas[LUNES][salida]')[0].value;
            ['MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES'].forEach(dia => {
                document.getElementsByName(`horas[${dia}][entrada]`)[0].value = e;
                document.getElementsByName(`horas[${dia}][salida]`)[0].value = s;
            });
            calcularHoras();
        }

        function sumarDias(d, n) { const r = new Date(d); r.setDate(d.getDate() + n); return r; }
        function dm(d) { return String(d.getDate()).padStart(2,'0') + '/' + String(d.getMonth()+1).padStart(2,'0'); }

        function actualizarFechas() {
            const v = document.querySelector('input[name="fecha_inicio"]').value;
            const p = v ? v.split('-') : null;
            const lunes = p ? new Date(parseInt(p[0]), parseInt(p[1]) - 1, parseInt(p[2])) : null;
            document.querySelectorAll('[data-dia-idx]').forEach(el => {
                el.innerText = lunes ? dm(sumarDias(lunes, parseInt(el.getAttribute('data-dia-idx')))) : '';
            });
        }

        function actualizarEmpleado() {
            const z = document.querySelector('input[name="zona"]:checked');
            if (z) document.getElementById('r_emp').innerText = z.value;
        }

        window.addEventListener('load', function() {
            lucide.createIcons();
            actualizarFechas();
            actualizarEmpleado();
            calcularHoras();
            document.querySelector('input[name="fecha_inicio"]').addEventListener('change', actualizarFechas);
            document.querySelectorAll('input[name="zona"]').forEach(r => r.addEventListener('change', actualizarEmpleado));
        });
    </script>
</head>
<body>

    <?php include __DIR__ . '/includes/header.php'; ?>

    <main class="imp-main">

        <div id="toast-container" class="toast-container"></div>

        <?php if($mensaje): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const msg = "<?= addslashes($mensaje) ?>";
                    const isError = "<?= strpos($mensaje, 'Error') !== false ? 'true' : 'false' ?>";
                    showToast(msg, isError === 'true' ? 'error' : 'success');
                });
            </script>
        <?php endif; ?>

        <div class="imp-page">
            <div class="imp-dash-head">
                <div class="imp-page-head">
                    <span class="imp-kicker sage">Jornales</span>
                    <h1><?= $editando ? 'Editar liquidación de jornales' : 'Nueva liquidación de jornales' ?></h1>
                    <p>Cargá entrada y salida de cada día; las horas y el total se calculan solos.</p>
                </div>
                <?php if($editando): ?>
                    <a href="historial_horas.php" class="btn btn-secondary imp-btn-today">&larr; Volver al historial</a>
                <?php endif; ?>
            </div>

            <form method="POST" class="imp-layout">
                <?= csrf_field() ?>
                <?php if($editando): ?>
                    <input type="hidden" name="cierre_id" value="<?= $cierre_edit['id'] ?>">
                <?php endif; ?>

                <div class="imp-col">

                    <!-- 1. Configuración -->
                    <section class="imp-section">
                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <span class="imp-label">Empleado</span>
                            <div class="imp-chips">
                                <?php foreach($empleados as $emp): ?>
                                    <label class="imp-chip avatar">
                                        <input type="radio" name="zona" value="<?= e($emp) ?>" required <?= $emp_sel === $emp ? 'checked' : '' ?>>
                                        <span><i class="ini"><?= e(mb_substr($emp, 0, 1)) ?></i><?= e($emp) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="imp-row">
                            <div class="field" style="flex:0 1 220px;">
                                <label for="fecha_inicio">Inicio de semana (lunes)</label>
                                <input type="date" id="fecha_inicio" name="fecha_inicio" required class="input on-light"
                                       value="<?= $editando ? e($cierre_edit['fecha_inicio']) : date('Y-m-d', strtotime('monday this week')) ?>">
                            </div>
                            <div class="field" style="flex:0 1 200px;">
                                <label for="valor_hora" style="color:var(--color-accent-2-700); font-weight:700;">Valor hora</label>
                                <div class="imp-money sage"><i>$</i>
                                    <input type="number" id="valor_hora" name="valor_hora" step="0.01" inputmode="decimal"
                                           class="input calculable" oninput="calcularHoras()" placeholder="0.00"
                                           value="<?= $editando && $cierre_edit['valor_hora'] > 0 ? e($cierre_edit['valor_hora']) : '' ?>">
                                </div>
                            </div>
                            <button type="button" class="btn btn-secondary imp-btn-today" style="margin-left:auto;" onclick="repetirLunes()">
                                <i data-lucide="copy" style="width:16px; height:16px;"></i> Repetir horario del lunes
                            </button>
                        </div>
                    </section>

                    <!-- 2. Grilla de días -->
                    <div class="imp-hgrid">
                        <?php foreach($dias as $i => $dia):
                            $valEntrada = ($editando && isset($horas_edit[$dia])) ? substr($horas_edit[$dia]['hora_entrada'] ?? '', 0, 5) : '';
                            $valSalida  = ($editando && isset($horas_edit[$dia])) ? substr($horas_edit[$dia]['hora_salida']  ?? '', 0, 5) : '';
                        ?>
                        <div class="imp-hcard">
                            <div class="imp-hcard-head">
                                <div class="imp-day-id">
                                    <span class="imp-day-badge sage"><?= $dias_short[$dia] ?></span>
                                    <div>
                                        <strong><?= $dias_lbl[$dia] ?></strong>
                                        <small data-dia-idx="<?= $i ?>"></small>
                                    </div>
                                </div>
                                <span id="total_<?= $dia ?>" class="imp-hpill">0h 0m</span>
                            </div>
                            <div class="imp-time-grid">
                                <div class="field">
                                    <label>Entrada</label>
                                    <input type="time" name="horas[<?= $dia ?>][entrada]" oninput="calcularHoras()" class="input" value="<?= e($valEntrada) ?>">
                                </div>
                                <div class="field">
                                    <label>Salida</label>
                                    <input type="time" name="horas[<?= $dia ?>][salida]" oninput="calcularHoras()" class="input" value="<?= e($valSalida) ?>">
                                </div>
                            </div>
                            <div class="imp-shift">
                                <div class="imp-shift-track"><div class="imp-shift-fill" id="barra_<?= $dia ?>"></div></div>
                                <div class="imp-shift-marks"><span>0h</span><span>6h</span><span>12h</span><span>18h</span><span>24h</span></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- 3. Ajustes -->
                    <div class="imp-h3">
                        <h3>Ajustes</h3><small>Opcional</small>
                    </div>
                    <div class="imp-adjust-grid">
                        <div class="imp-adjust plus">
                            <div class="imp-adjust-title"><span class="imp-adjust-sign">+</span> Ingresos extra</div>
                            <div class="field">
                                <label>Monto</label>
                                <input type="number" id="saldo_favor" name="saldo_favor" step="0.01" oninput="calcularHoras()" placeholder="0.00" class="input on-light"
                                       value="<?= $editando && $cierre_edit['saldo_favor'] > 0 ? e($cierre_edit['saldo_favor']) : '' ?>">
                            </div>
                            <div class="field">
                                <label>Concepto</label>
                                <input type="text" name="saldo_concepto" placeholder="Concepto" class="input on-light"
                                       value="<?= $editando ? e($cierre_edit['saldo_concepto']) : '' ?>">
                            </div>
                        </div>
                        <div class="imp-adjust minus">
                            <div class="imp-adjust-title"><span class="imp-adjust-sign">−</span> Descuentos / adelantos</div>
                            <div class="field">
                                <label>Monto</label>
                                <input type="number" id="descuento" name="descuento" step="0.01" oninput="calcularHoras()" placeholder="0.00" class="input on-light"
                                       value="<?= $editando && $cierre_edit['descuento_creditos'] > 0 ? e($cierre_edit['descuento_creditos']) : '' ?>">
                            </div>
                            <div class="field">
                                <label>Concepto</label>
                                <input type="text" name="descuento_concepto" placeholder="Concepto" class="input on-light"
                                       value="<?= $editando ? e($cierre_edit['descuento_creditos_concepto']) : '' ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel resumen -->
                <aside class="imp-summary">
                    <span class="imp-kicker sage">Liquidación · <span id="r_emp"><?= e($emp_sel) ?></span></span>
                    <div class="imp-summary-total">
                        <small>Total a pagar</small>
                        <span class="imp-money-big" id="t_neto">$ 0,00</span>
                    </div>
                    <div class="imp-summary-rows">
                        <div><span>Total horas</span><strong class="imp-num" id="t_horas">0h 0m</strong></div>
                        <div><span>Subtotal (horas × valor)</span><strong class="imp-num" id="t_bruto">$ 0,00</strong></div>
                        <hr>
                        <div class="good"><span>Ingresos extra (+)</span><strong class="imp-num" id="t_saldo">$ 0,00</strong></div>
                        <div class="warn"><span>Descuentos (−)</span><strong class="imp-num" id="t_desc">$ 0,00</strong></div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="save" style="width:18px; height:18px;"></i>
                        <?= $editando ? 'Actualizar y ver liquidación' : 'Guardar y ver liquidación' ?>
                    </button>
                </aside>
            </form>
        </div>
    </main>
</body>
</html>
