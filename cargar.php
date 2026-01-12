<?php
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$mensaje = '';
$dias = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];
$zonas = ['Zona 1', 'Zona 2', 'Zona 3', 'Zona 4a6'];

// Procesar Guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $modo_carga = $_POST['modo_carga']; // 'semanal' o 'diario'
        $zona = $_POST['zona'];
        $fecha = $_POST['fecha_inicio'];
        
        // Datos de Ajustes
        $saldo_favor = $_POST['saldo_favor'] ?: 0;
        $saldo_concepto = $_POST['saldo_concepto'];
        $descuento_creditos = $_POST['descuento_creditos'] ?: 0;
        $descuento_creditos_concepto = $_POST['descuento_creditos_concepto'];

        // 1. GESTIÓN DE CABECERA (Cierre Semanal)
        // Verificar si ya existe un cierre para esa zona y fecha
        $stmtCheck = $pdo->prepare("SELECT id FROM cierres_semanales WHERE zona = ? AND fecha_inicio = ?");
        $stmtCheck->execute([$zona, $fecha]);
        $existe = $stmtCheck->fetch();

        if ($existe) {
            $cierre_id = $existe['id'];
            // Actualizamos cabecera (saldo a favor y descuentos) solo si estamos en modo semanal o si se enviaron datos
            if ($modo_carga === 'semanal' || ($saldo_favor > 0 || $descuento_creditos > 0)) {
                 $stmtUpd = $pdo->prepare("UPDATE cierres_semanales SET saldo_favor=?, saldo_concepto=?, descuento_creditos=?, descuento_creditos_concepto=? WHERE id=?");
                 $stmtUpd->execute([$saldo_favor, $saldo_concepto, $descuento_creditos, $descuento_creditos_concepto, $cierre_id]);
            }
        } else {
            // Insertar nuevo cierre cabecera
            $stmtIns = $pdo->prepare("INSERT INTO cierres_semanales (zona, fecha_inicio, saldo_favor, saldo_concepto, descuento_creditos, descuento_creditos_concepto) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtIns->execute([$zona, $fecha, $saldo_favor, $saldo_concepto, $descuento_creditos, $descuento_creditos_concepto]);
            $cierre_id = $pdo->lastInsertId();
        }

        // 2. GESTIÓN DE DETALLES (Días)
        $stmtInsertarDetalle = $pdo->prepare("INSERT INTO detalles_diarios (cierre_id, dia_semana, efectivo, transferencia, gasto_monto, gasto_concepto) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtUpdateDetalle = $pdo->prepare("UPDATE detalles_diarios SET efectivo=?, transferencia=?, gasto_monto=?, gasto_concepto=? WHERE cierre_id=? AND dia_semana=?");

        if ($modo_carga === 'semanal') {
            // MODO SEMANAL: Borrar todo lo anterior de esta semana y reescribir
            $pdo->prepare("DELETE FROM detalles_diarios WHERE cierre_id=?")->execute([$cierre_id]);
            
            foreach ($dias as $dia) {
                $ef = $_POST['data'][$dia]['efectivo'] ?: 0;
                $tr = $_POST['data'][$dia]['transferencia'] ?: 0;
                $gm = $_POST['data'][$dia]['gasto_monto'] ?: 0;
                $gc = $_POST['data'][$dia]['gasto_concepto'];
                
                $stmtInsertarDetalle->execute([$cierre_id, $dia, $ef, $tr, $gm, $gc]);
            }

        } else {
            // MODO DIARIO: Solo afectar el día seleccionado
            $dia_seleccionado = $_POST['dia_seleccionado'];
            
            // Datos del formulario diario
            $ef = $_POST['dia_efectivo'] ?: 0;
            $tr = $_POST['dia_transferencia'] ?: 0;
            $gm = $_POST['dia_gasto_monto'] ?: 0;
            $gc = $_POST['dia_gasto_concepto'];

            // Verificar si este día ya existía
            $stmtCheckDia = $pdo->prepare("SELECT id FROM detalles_diarios WHERE cierre_id=? AND dia_semana=?");
            $stmtCheckDia->execute([$cierre_id, $dia_seleccionado]);
            
            if ($stmtCheckDia->fetch()) {
                // Actualizar
                $stmtUpdateDetalle->execute([$ef, $tr, $gm, $gc, $cierre_id, $dia_seleccionado]);
            } else {
                // Insertar
                $stmtInsertarDetalle->execute([$cierre_id, $dia_seleccionado, $ef, $tr, $gm, $gc]);
            }
        }

        $pdo->commit();
        // CAMBIO: Mensaje de éxito simple sin redirección
        $mensaje = "¡Datos guardados correctamente!";
        
        // Opcional: Si quieres limpiar el formulario después de guardar, podrías redirigir a la misma página
        // header("Location: cargar.php?status=success"); 
        // Pero para mantener los datos en pantalla o simplemente mostrar el mensaje, lo dejamos así.

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
    <title>Cargar Cierre Semanal</title>
    <link rel="stylesheet" href="style.css">
    <script>
        function toggleModo() {
            const modo = document.querySelector('input[name="modo_carga"]:checked').value;
            const contenedorSemanal = document.getElementById('contenedor-semanal');
            const contenedorDiario = document.getElementById('contenedor-diario');
            
            if (modo === 'semanal') {
                contenedorSemanal.style.display = 'block';
                contenedorDiario.style.display = 'none';
                enableInputs(contenedorSemanal, true);
                enableInputs(contenedorDiario, false);
            } else {
                contenedorSemanal.style.display = 'none';
                contenedorDiario.style.display = 'block';
                enableInputs(contenedorSemanal, false);
                enableInputs(contenedorDiario, true);
                actualizarFechaDia();
            }
        }

        function enableInputs(container, enable) {
            const inputs = container.querySelectorAll('input, select');
            inputs.forEach(input => {
                input.disabled = !enable;
            });
        }

        function calcularTotales() {
            let inputs = document.querySelectorAll('.calculable:not(:disabled)');
            let totalEf = 0, totalTr = 0, totalGasto = 0;

            inputs.forEach(input => {
                let val = parseFloat(input.value) || 0;
                if(input.name.includes('[efectivo]') || input.name == 'dia_efectivo') totalEf += val;
                if(input.name.includes('[transferencia]') || input.name == 'dia_transferencia') totalTr += val;
                if(input.name.includes('[gasto_monto]') || input.name == 'dia_gasto_monto') totalGasto += val;
            });

            const formatter = new Intl.NumberFormat('es-AR', {
                style: 'currency',
                currency: 'ARS',
                minimumFractionDigits: 0
            });

            const elEf = document.getElementById('t_efectivo');
            const elTr = document.getElementById('t_transf');
            const elTo = document.getElementById('t_total');
            
            if(elEf) elEf.innerText = formatter.format(totalEf);
            if(elTr) elTr.innerText = formatter.format(totalTr);
            if(elTo) elTo.innerText = formatter.format(totalEf + totalTr);
        }

        function actualizarFechaDia() {
            const fechaInicioInput = document.querySelector('input[name="fecha_inicio"]');
            const diaSelect = document.querySelector('select[name="dia_seleccionado"]');
            const fechaInfo = document.getElementById('fecha-dia-info');

            if (!fechaInicioInput.value || !diaSelect.value) {
                fechaInfo.innerText = '';
                return;
            }

            const partes = fechaInicioInput.value.split('-');
            const anio = parseInt(partes[0]);
            const mes = parseInt(partes[1]) - 1; 
            const dia = parseInt(partes[2]);
            
            const fechaLunes = new Date(anio, mes, dia);

            const diasOffset = {
                'LUNES': 0, 'MARTES': 1, 'MIERCOLES': 2, 'JUEVES': 3, 'VIERNES': 4, 'SABADO': 5
            };

            const offset = diasOffset[diaSelect.value] || 0;
            const fechaResultado = new Date(fechaLunes);
            fechaResultado.setDate(fechaLunes.getDate() + offset);

            const diaRes = String(fechaResultado.getDate()).padStart(2, '0');
            const mesRes = String(fechaResultado.getMonth() + 1).padStart(2, '0');
            const anioRes = fechaResultado.getFullYear();

            fechaInfo.innerHTML = `Fecha del pago: <strong style="color: var(--accent-green);">${diaRes}/${mesRes}/${anioRes}</strong>`;
        }

        window.onload = function() {
            toggleModo();
            document.querySelector('input[name="fecha_inicio"]').addEventListener('change', actualizarFechaDia);
            document.querySelector('select[name="dia_seleccionado"]').addEventListener('change', actualizarFechaDia);
        };
    </script>
</head>
<body>
    
    <?php include 'header.php'; ?>

    <div class="container">
        
        <?php if($mensaje): ?>
            <!-- Mensaje de éxito visible -->
            <div class="card" style="border-color: var(--accent-green); color: var(--accent-green); text-align: center; background-color: rgba(16, 185, 129, 0.1);">
                <?= $mensaje ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="card">
                <div class="card-header">Carga de Cobranzas</div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label>Zona de Cobranza</label>
                        <select name="zona" required style="font-size: 1.1em; padding: 12px;">
                            <?php foreach($zonas as $z): ?>
                                <option value="<?= $z ?>"><?= $z ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Inicio de Semana (Lunes)</label>
                        <input type="date" name="fecha_inicio" required value="<?= date('Y-m-d', strtotime('monday this week')) ?>" style="font-size: 1.1em; padding: 12px;">
                    </div>
                </div>

                <div style="margin-bottom: 25px; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 12px; display: flex; gap: 30px; align-items: center; justify-content: center;">
                    <label style="color: var(--text-main); font-weight: bold; margin: 0; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <input type="radio" name="modo_carga" value="semanal" checked onchange="toggleModo()" style="width: 20px; height: 20px;">
                        Carga Semana Completa
                    </label>
                    <label style="color: var(--text-main); font-weight: bold; margin: 0; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <input type="radio" name="modo_carga" value="diario" onchange="toggleModo()" style="width: 20px; height: 20px;">
                        Carga Día por Día
                    </label>
                </div>

                <!-- 1. CONTENEDOR CARGA SEMANAL -->
                <div id="contenedor-semanal">
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Día</th>
                                    <th>Efectivo</th>
                                    <th>Transferencia</th>
                                    <th style="color: var(--accent-red);">Gasto Monto</th>
                                    <th>Gasto Concepto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($dias as $dia): ?>
                                <tr>
                                    <td style="font-weight: 600; color: var(--accent-blue);"><?= $dia ?></td>
                                    <td><input type="number" step="0.01" name="data[<?= $dia ?>][efectivo]" class="calculable" oninput="calcularTotales()" placeholder="$ 0"></td>
                                    <td><input type="number" step="0.01" name="data[<?= $dia ?>][transferencia]" class="calculable" oninput="calcularTotales()" placeholder="$ 0"></td>
                                    <td><input type="number" step="0.01" name="data[<?= $dia ?>][gasto_monto]" class="calculable" oninput="calcularTotales()" style="color: var(--accent-red); border-color: rgba(247, 118, 142, 0.3);" placeholder="$ 0"></td>
                                    <td><input type="text" name="data[<?= $dia ?>][gasto_concepto]" placeholder="Detalle..."></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="font-weight: bold; background-color: rgba(255,255,255,0.05);">
                                    <td style="text-align: right; padding-right: 20px;">TOTALES</td>
                                    <td id="t_efectivo" class="text-green">$ 0</td>
                                    <td id="t_transf" class="text-blue">$ 0</td>
                                    <td colspan="2" id="t_total" style="text-align: left; color: white;">$ 0</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- 2. CONTENEDOR CARGA DIARIA -->
                <div id="contenedor-diario" style="display: none;">
                    <div style="background: rgba(0,0,0,0.2); padding: 25px; border-radius: 12px; border: 1px solid var(--accent-blue);">
                        <h3 style="margin-top: 0; color: var(--accent-blue); margin-bottom: 20px;">Cargar Día Individual</h3>
                        
                        <div style="margin-bottom: 20px;">
                            <label>Seleccione el Día a Cargar</label>
                            <select name="dia_seleccionado" style="font-size: 1.1em; padding: 12px; border-color: var(--accent-blue);">
                                <?php foreach($dias as $dia): ?>
                                    <option value="<?= $dia ?>"><?= $dia ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div id="fecha-dia-info" style="margin-top: 10px; font-size: 1rem; color: var(--text-muted);"></div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                            <div>
                                <label>Monto Efectivo</label>
                                <input type="number" step="0.01" name="dia_efectivo" class="calculable" placeholder="$ 0.00">
                            </div>
                            <div>
                                <label>Monto Transferencia</label>
                                <input type="number" step="0.01" name="dia_transferencia" class="calculable" placeholder="$ 0.00">
                            </div>
                            <div>
                                <label style="color: var(--accent-red);">Gasto (Monto)</label>
                                <input type="number" step="0.01" name="dia_gasto_monto" class="calculable" placeholder="$ 0.00" style="border-color: var(--accent-red); color: var(--accent-red);">
                            </div>
                            <div>
                                <label>Concepto Gasto</label>
                                <input type="text" name="dia_gasto_concepto" placeholder="Ej. Nafta">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN AJUSTES FINALES -->
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-color); background: rgba(0,0,0,0.2); padding: 20px; border-radius: 8px;">
                    <h3 style="margin-top: 0; font-size: 1rem; color: var(--accent-yellow); margin-bottom: 15px;">Ajustes Finales (Opcional)</h3>
                    
                    <!-- Fila 1: Saldo a Favor -->
                    <div style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 15px;">
                        <div style="flex: 1; min-width: 150px;">
                            <label class="text-green" style="font-weight: bold;">Saldo a Favor (+)</label>
                            <input type="number" step="0.01" name="saldo_favor" placeholder="0.00" style="border-color: var(--accent-green);">
                        </div>
                        <div style="flex: 3; min-width: 250px;">
                            <label>Concepto del Saldo a Favor</label>
                            <input type="text" name="saldo_concepto" placeholder="Ej. Bono por objetivos ventas">
                        </div>
                    </div>

                    <!-- Fila 2: Descuento Créditos -->
                    <div style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 150px;">
                            <label class="text-red" style="font-weight: bold;">Descuento Créditos (-)</label>
                            <input type="number" step="0.01" name="descuento_creditos" placeholder="0.00" style="border-color: var(--accent-red); color: #ff6b6b;">
                        </div>
                        <div style="flex: 3; min-width: 250px;">
                            <label>Concepto Descuento</label>
                            <input type="text" name="descuento_creditos_concepto" placeholder="Ej. Cuota 1/3 Préstamo">
                        </div>
                    </div>
                </div>

                <div style="margin-top: 30px; text-align: right;">
                    <button type="submit" class="btn btn-primary" style="font-size: 1.1em; padding: 12px 30px;">
                        Guardar Datos
                    </button>
                </div>
            </div>
        </form>
    </div>
</body>
</html>