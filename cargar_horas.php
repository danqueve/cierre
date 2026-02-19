<?php
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$mensaje = '';
$dias = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];
$empleados = ['Alejandro', 'Emilia', 'Luz', 'Maxi'];

// Procesar Guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $empleado = $_POST['zona'];
        $fecha = $_POST['fecha_inicio'];
        $valor_hora = $_POST['valor_hora'] ?: 0;
        
        // Nuevos campos de ajuste
        $saldo_favor = $_POST['saldo_favor'] ?: 0;
        $saldo_concepto = $_POST['saldo_concepto'];
        $descuento = $_POST['descuento'] ?: 0;
        $descuento_concepto = $_POST['descuento_concepto'];

        // 1. Verificar/Crear Cabecera (Tabla cierres_semanales)
        $stmtCheck = $pdo->prepare("SELECT id FROM cierres_semanales WHERE zona = ? AND fecha_inicio = ?");
        $stmtCheck->execute([$empleado, $fecha]);
        $existe = $stmtCheck->fetch();

        if ($existe) {
            $cierre_id = $existe['id'];
            // Actualizamos valor hora y los nuevos ajustes
            $stmtUpd = $pdo->prepare("UPDATE cierres_semanales SET valor_hora=?, saldo_favor=?, saldo_concepto=?, descuento_creditos=?, descuento_creditos_concepto=? WHERE id=?");
            $stmtUpd->execute([$valor_hora, $saldo_favor, $saldo_concepto, $descuento, $descuento_concepto, $cierre_id]);
        } else {
            $stmtIns = $pdo->prepare("INSERT INTO cierres_semanales (zona, fecha_inicio, valor_hora, saldo_favor, saldo_concepto, descuento_creditos, descuento_creditos_concepto) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtIns->execute([$empleado, $fecha, $valor_hora, $saldo_favor, $saldo_concepto, $descuento, $descuento_concepto]);
            $cierre_id = $pdo->lastInsertId();
        }

        // 2. Guardar Horarios (Mañana y Tarde)
        $stmtCheckDia = $pdo->prepare("SELECT id FROM detalles_diarios WHERE cierre_id=? AND dia_semana=?");
        $stmtInsDia = $pdo->prepare("INSERT INTO detalles_diarios (cierre_id, dia_semana, hora_entrada, hora_salida, hora_entrada_tarde, hora_salida_tarde) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtUpdDia = $pdo->prepare("UPDATE detalles_diarios SET hora_entrada=?, hora_salida=?, hora_entrada_tarde=?, hora_salida_tarde=? WHERE id=?");

        foreach ($dias as $dia) {
            $e1 = !empty($_POST['horas'][$dia]['entrada']) ? $_POST['horas'][$dia]['entrada'] : null;
            $s1 = !empty($_POST['horas'][$dia]['salida']) ? $_POST['horas'][$dia]['salida'] : null;
            $e2 = !empty($_POST['horas'][$dia]['entrada_tarde']) ? $_POST['horas'][$dia]['entrada_tarde'] : null;
            $s2 = !empty($_POST['horas'][$dia]['salida_tarde']) ? $_POST['horas'][$dia]['salida_tarde'] : null;

            $stmtCheckDia->execute([$cierre_id, $dia]);
            $diaExistente = $stmtCheckDia->fetch();

            if ($diaExistente) {
                $stmtUpdDia->execute([$e1, $s1, $e2, $s2, $diaExistente['id']]);
            } else {
                if($e1 || $s1 || $e2 || $s2) {
                    $stmtInsDia->execute([$cierre_id, $dia, $e1, $s1, $e2, $s2]);
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
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cargar Horarios</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="main.js" defer></script>
    <style>
        /* Nuevos estilos para la grilla de horarios */
        .grid-dias {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card-dia {
            background-color: #2b2b2b;
            border: 1px solid #444;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
            transition: transform 0.2s;
        }
        .card-dia:hover {
            transform: translateY(-2px);
            border-color: var(--accent-purple);
        }

        .card-dia-header {
            background: linear-gradient(135deg, #1f1f1f, #252525);
            padding: 10px 15px;
            border-bottom: 1px solid #333;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .dia-nombre {
            font-weight: bold;
            color: var(--accent-blue);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .dia-total {
            font-size: 0.9rem;
            color: var(--accent-yellow);
            font-weight: bold;
            background: rgba(224, 175, 104, 0.1);
            padding: 4px 8px;
            border-radius: 4px;
        }

        .card-dia-body {
            padding: 15px;
        }

        .turno-row {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            gap: 10px;
            transition: all 0.3s ease;
        }
        .turno-row:last-child { margin-bottom: 0; }
        
        .turno-label {
            width: 70px;
            font-size: 0.8rem;
            color: #888;
            font-weight: 600;
        }
        
        /* Inputs mejorados */
        .time-input-group {
            flex: 1;
            display: flex;
            gap: 5px;
            align-items: center;
        }
        .time-input-group input {
            width: 100%;
            background-color: #1a1a1a;
            border: 1px solid #555;
            color: white;
            padding: 8px;
            border-radius: 6px;
            text-align: center;
            font-family: monospace;
            font-size: 1rem;
        }
        .time-input-group input:focus {
            border-color: var(--accent-green);
            background-color: #000;
        }
        
        .ajustes-section {
            background: rgba(0,0,0,0.2); 
            border: 1px dashed #555; 
            padding: 20px; 
            border-radius: 12px; 
            margin-top: 20px;
        }
    </style>
    <script>
        function checkTurnos() {
            const empleadoSelect = document.querySelector('select[name="zona"]');
            const empleado = empleadoSelect.value;
            const rowsTarde = document.querySelectorAll('.row-tarde');
            const labelsManana = document.querySelectorAll('.label-manana');
            
            // Lógica de negocio: Solo Luz tiene horario cortado
            const esHorarioCortado = (empleado === 'Luz');

            rowsTarde.forEach(row => {
                if(esHorarioCortado) {
                    row.style.display = 'flex';
                } else {
                    row.style.display = 'none';
                    // Opcional: Limpiar valores si se oculta para evitar errores
                    const inputs = row.querySelectorAll('input');
                    inputs.forEach(i => i.value = '');
                }
            });

            // Actualizar etiquetas visuales
            labelsManana.forEach(label => {
                label.innerText = esHorarioCortado ? 'MAÑANA' : 'HORARIO';
            });

            // Recalcular totales por si había datos que se ocultaron
            calcularHoras();
        }

        function calcularHoras() {
            let totalMinutosSemana = 0;
            const valorHora = parseFloat(document.getElementById('valor_hora').value) || 0;
            const saldoFavor = parseFloat(document.getElementById('saldo_favor').value) || 0;
            const descuento = parseFloat(document.getElementById('descuento').value) || 0;
            
            const dias = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];

            dias.forEach(dia => {
                // Inputs Turno 1
                const e1 = document.getElementsByName(`horas[${dia}][entrada]`)[0].value;
                const s1 = document.getElementsByName(`horas[${dia}][salida]`)[0].value;
                // Inputs Turno 2 (pueden estar ocultos)
                const e2 = document.getElementsByName(`horas[${dia}][entrada_tarde]`)[0].value;
                const s2 = document.getElementsByName(`horas[${dia}][salida_tarde]`)[0].value;
                
                const spanTotal = document.getElementById(`total_${dia}`);
                let minutosDia = 0;

                // Calcular T1
                if (e1 && s1) {
                    const d1 = calcularDiferencia(e1, s1);
                    minutosDia += d1;
                }
                // Calcular T2
                if (e2 && s2) {
                    const d2 = calcularDiferencia(e2, s2);
                    minutosDia += d2;
                }

                if (minutosDia > 0) {
                    const h = Math.floor(minutosDia / 60);
                    const m = minutosDia % 60;
                    spanTotal.innerText = `${h}h ${m}m`;
                    spanTotal.style.opacity = '1';
                    spanTotal.style.color = '#e0af68';
                    totalMinutosSemana += minutosDia;
                } else {
                    spanTotal.innerText = '0h 0m';
                    spanTotal.style.opacity = '0.5';
                    spanTotal.style.color = '#ccc';
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
            document.getElementById('t_neto').innerText = formatter.format(totalNeto);
        }

        function calcularDiferencia(inicio, fin) {
            const start = new Date(`2000-01-01T${inicio}`);
            const end = new Date(`2000-01-01T${fin}`);
            let diff = (end - start) / 1000 / 60; 
            if (diff < 0) diff += 24 * 60;
            return diff;
        }

        // Ejecutar al cargar para configurar la vista inicial
        window.addEventListener('load', function() {
            checkTurnos();
            lucide.createIcons();
        });
    </script>
</head>
</head>
<body class="fade-in">
    
    <?php include 'header.php'; ?>

    <div class="container">
        
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

        <form method="POST">
            
            <!-- 1. Cabecera de Configuración -->
            <div class="card">
                <div class="card-header">Nueva Liquidación de Jornales</div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; align-items: end;">
                    <div class="input-group">
                        <label>Empleado</label>
                        <i data-lucide="user" class="input-icon"></i>
                        <select name="zona" onchange="checkTurnos()" required class="input-with-icon" style="background-color: #2b2b2b;">
                            <?php foreach($empleados as $emp): ?>
                                <option value="<?= $emp ?>"><?= $emp ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Inicio Semana (Lunes)</label>
                        <i data-lucide="calendar" class="input-icon"></i>
                        <input type="date" name="fecha_inicio" required value="<?= date('Y-m-d', strtotime('monday this week')) ?>" class="input-with-icon">
                    </div>
                    <div class="input-group">
                        <label style="color: var(--accent-green); font-weight: bold;">Valor Hora ($)</label>
                        <i data-lucide="dollar-sign" class="input-icon" style="color: var(--accent-green);"></i>
                        <input type="number" id="valor_hora" name="valor_hora" step="0.01" class="calculable input-with-icon" oninput="calcularHoras()" placeholder="0.00" style="font-size: 1.2em; font-weight: bold; color: var(--accent-green); border-bottom-color: var(--accent-green);">
                    </div>
                </div>
            </div>

            <!-- 2. Grilla de Días (Nuevo Diseño Visual) -->
            <div class="grid-dias">
                <?php foreach($dias as $dia): ?>
                <div class="card-dia">
                    <div class="card-dia-header">
                        <span class="dia-nombre"><?= $dia ?></span>
                        <span id="total_<?= $dia ?>" class="dia-total">0h 0m</span>
                    </div>
                    <div class="card-dia-body">
                        <!-- Turno Mañana (O Único) -->
                        <div class="turno-row">
                            <div class="turno-label label-manana">HORARIO</div>
                            <div class="time-input-group input-group" style="margin-bottom: 0;">
                                <i data-lucide="clock" class="input-icon" style="width:14px;"></i>
                                <input type="time" name="horas[<?= $dia ?>][entrada]" oninput="calcularHoras()" title="Entrada" class="input-with-icon">
                                <span style="color:#555">-</span>
                                <input type="time" name="horas[<?= $dia ?>][salida]" oninput="calcularHoras()" title="Salida" class="input-with-icon">
                            </div>
                        </div>
                        <!-- Turno Tarde (Solo para Luz - controlado por JS) -->
                        <div class="turno-row row-tarde">
                            <div class="turno-label">TARDE</div>
                            <div class="time-input-group input-group" style="margin-bottom: 0;">
                                <i data-lucide="clock" class="input-icon" style="width:14px;"></i>
                                <input type="time" name="horas[<?= $dia ?>][entrada_tarde]" oninput="calcularHoras()" title="Entrada Tarde" class="input-with-icon">
                                <span style="color:#555">-</span>
                                <input type="time" name="horas[<?= $dia ?>][salida_tarde]" oninput="calcularHoras()" title="Salida Tarde" class="input-with-icon">
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- 3. Ajustes y Totales -->
            <div class="card">
                <div class="card-header">Ajustes y Liquidación Final</div>
                
                <div class="ajustes-section">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
                        
                        <!-- Columna: Sumas (Saldo a Favor) -->
                        <div class="input-group">
                            <h4 style="margin-top: 0; color: var(--accent-green);">Ingresos Extra / Saldo a Favor (+)</h4>
                            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                                <div class="input-group" style="width: 140px; margin-bottom: 0;">
                                    <i data-lucide="plus" class="input-icon" style="color: var(--accent-green);"></i>
                                    <input type="number" id="saldo_favor" name="saldo_favor" step="0.01" oninput="calcularHoras()" placeholder="0.00" class="input-with-icon" style="font-weight: bold; color: var(--accent-green); border-bottom-color: var(--accent-green);">
                                </div>
                                <div class="input-group" style="flex: 1; margin-bottom: 0;">
                                    <i data-lucide="tag" class="input-icon"></i>
                                    <input type="text" name="saldo_concepto" placeholder="Concepto" class="input-with-icon">
                                </div>
                            </div>
                        </div>

                        <!-- Columna: Restas (Descuentos) -->
                        <div class="input-group">
                            <h4 style="margin-top: 0; color: var(--accent-red);">Descuentos / Adelantos (-)</h4>
                            <div style="display: flex; gap: 10px;">
                                <div class="input-group" style="width: 140px; margin-bottom: 0;">
                                    <i data-lucide="minus" class="input-icon" style="color: var(--accent-red);"></i>
                                    <input type="number" id="descuento" name="descuento" step="0.01" oninput="calcularHoras()" placeholder="0.00" class="input-with-icon" style="font-weight: bold; color: var(--accent-red); border-bottom-color: var(--accent-red);">
                                </div>
                                <div class="input-group" style="flex: 1; margin-bottom: 0;">
                                    <i data-lucide="tag" class="input-icon"></i>
                                    <input type="text" name="descuento_concepto" placeholder="Concepto" class="input-with-icon">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Totales -->
                <div style="margin-top: 30px; border-top: 1px solid #444; padding-top: 20px; display: flex; flex-direction: column; align-items: flex-end; gap: 10px;">
                    
                    <div style="display: flex; justify-content: space-between; width: 100%; max-width: 400px; color: #888;">
                        <span>Total Horas:</span>
                        <span id="t_horas" style="color: #fff; font-weight: bold;">0h 0m</span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; width: 100%; max-width: 400px; color: #aaa;">
                        <span>Subtotal (Horas x Valor):</span>
                        <span id="t_bruto" style="color: #fff;">$ 0,00</span>
                    </div>

                    <div style="display: flex; justify-content: space-between; width: 100%; max-width: 400px; font-size: 1.5rem; font-weight: bold; margin-top: 10px; padding-top: 10px; border-top: 1px dashed #555;">
                        <span style="color: var(--accent-blue);">TOTAL A PAGAR:</span>
                        <span id="t_neto" style="color: var(--accent-green);">$ 0,00</span>
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top: 20px; font-size: 1.1em; padding: 12px 40px; width: 100%; max-width: 400px;">
                        Guardar Liquidación
                    </button>
                </div>

            </div>
        </form>
    </div>
</body>
</html>