<?php
/**
 * Historial en formato calendario (compartido por historial.php y historial_horas.php).
 *
 * Variables esperadas (las define la página que incluye este archivo):
 *   $tipo  'cob' | 'hor'
 *   $rows  filas de cierres_semanales LEFT JOIN detalles_diarios (una por día), con:
 *          id, zona, fecha_inicio, saldo_favor, descuento_creditos, valor_hora,
 *          dia_semana, efectivo, transferencia, gasto_monto, hora_entrada, hora_salida
 */
$es_cob = ($tipo === 'cob');
$es_admin_h = ($_SESSION['role'] ?? '') === 'admin';

$meses_es = [1=>'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$dias_nom = ['LUNES' => 'Lunes', 'MARTES' => 'Martes', 'MIERCOLES' => 'Miércoles', 'JUEVES' => 'Jueves', 'VIERNES' => 'Viernes', 'SABADO' => 'Sábado'];
$dias_off = ['LUNES' => 0, 'MARTES' => 1, 'MIERCOLES' => 2, 'JUEVES' => 3, 'VIERNES' => 4, 'SABADO' => 5];

// ── 1. Agrupar filas por cierre ──────────────────────────────────────────
$items = [];
foreach ($rows as $r) {
    $id = $r['id'];
    if (!isset($items[$id])) {
        $items[$id] = [
            'id' => (int)$id, 'nombre' => $r['zona'], 'inicio' => $r['fecha_inicio'],
            'saldo' => (float)$r['saldo_favor'], 'desc' => (float)$r['descuento_creditos'],
            'vh' => (float)$r['valor_hora'],
            'dias' => array_fill_keys(array_keys($dias_nom), ['ef' => 0.0, 'tr' => 0.0, 'gm' => 0.0, 'min' => 0]),
            'ef' => 0.0, 'tr' => 0.0, 'gm' => 0.0, 'min' => 0,
        ];
    }
    if (empty($r['dia_semana'])) continue;
    $min = 0;
    if ($r['hora_entrada'] && $r['hora_salida']) {
        $min = (int)round((strtotime($r['hora_salida']) - strtotime($r['hora_entrada'])) / 60);
        if ($min < 0) $min += 1440;
    }
    $dd = ['ef' => (float)$r['efectivo'], 'tr' => (float)$r['transferencia'], 'gm' => (float)$r['gasto_monto'], 'min' => $min];
    $items[$id]['dias'][$r['dia_semana']] = $dd;
    foreach (['ef', 'tr', 'gm', 'min'] as $k) $items[$id][$k] += $dd[$k];
}
foreach ($items as &$it) {
    $it['total'] = $es_cob ? $it['ef'] + $it['tr'] : ($it['min'] / 60) * $it['vh'];
    $it['medida'] = $es_cob ? $it['ef'] + $it['tr'] : $it['min'];   // valor usado para intensidad / mejor semana
}
unset($it);

// ── 2. Mes seleccionado y vista ──────────────────────────────────────────
$mes  = isset($_GET['mes'])  ? (int)$_GET['mes']  : (int)date('n');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');
if ($mes < 1 || $mes > 12) $mes = (int)date('n');
if ($anio < 2000 || $anio > 2100) $anio = (int)date('Y');
$vista = (($_GET['vista'] ?? 'mes') === 'semanas') ? 'semanas' : 'mes';

$primer_dia = mktime(0, 0, 0, $mes, 1, $anio);
$ultimo_dia = mktime(0, 0, 0, $mes, (int)date('t', $primer_dia), $anio);
$grid_ini   = strtotime('monday this week', $primer_dia);
$grid_fin   = strtotime('sunday this week', $ultimo_dia);
$hoy_str    = date('Y-m-d');
$mes_key    = sprintf('%04d-%02d', $anio, $mes);

// ── 3. Totales por fecha y cierres por semana ────────────────────────────
$por_fecha = [];     // 'Y-m-d' => medida del día (sumada)
$por_semana = [];    // lunes 'Y-m-d' => [id, ...]
foreach ($items as $id => $it) {
    $por_semana[$it['inicio']][] = $id;
    foreach ($it['dias'] as $dia => $dd) {
        $f = date('Y-m-d', strtotime("+{$dias_off[$dia]} days", strtotime($it['inicio'])));
        $v = $es_cob ? $dd['ef'] + $dd['tr'] : $dd['min'];
        $por_fecha[$f] = ($por_fecha[$f] ?? 0) + $v;
    }
}

$fmt_celda = fn($v) => $es_cob ? montoCorto($v) : formatHoras((int)$v);
$fmt_total = fn($v) => formatCurrency($v);

// ── 4. KPIs del mes (cierres cuya semana empieza en el mes) ──────────────
$del_mes = array_filter($items, fn($it) => substr($it['inicio'], 0, 7) === $mes_key);
$n_mes   = count($del_mes);
if ($es_cob) {
    $suma  = array_sum(array_column($del_mes, 'total'));
    $mejor = $n_mes ? max(array_column($del_mes, 'total')) : 0;
    $kpis = [
        ['Cierres del mes',    (string)$n_mes],
        ['Total recaudado',    formatCurrency($suma)],
        ['Mejor semana',       formatCurrency($mejor)],
        ['Promedio semanal',   formatCurrency($n_mes ? $suma / $n_mes : 0)],
    ];
} else {
    $suma  = array_sum(array_column($del_mes, 'total'));
    $kpis = [
        ['Liquidaciones',   (string)$n_mes],
        ['Horas totales',   formatHoras((int)array_sum(array_column($del_mes, 'min')))],
        ['Total liquidado', formatCurrency($suma)],
        ['Promedio',        formatCurrency($n_mes ? $suma / $n_mes : 0)],
    ];
}

// ── 5. URLs ──────────────────────────────────────────────────────────────
$self = basename($_SERVER['PHP_SELF']);
$qs = function (array $o = []) use ($mes, $anio, $vista) {
    $p = array_merge(['mes' => sprintf('%02d', $mes), 'anio' => $anio, 'vista' => $vista], $o);
    return '?' . http_build_query($p);
};
$prev_ts = strtotime('-1 month', $primer_dia);
$next_ts = strtotime('+1 month', $primer_dia);
$url_prev = $qs(['mes' => date('m', $prev_ts), 'anio' => date('Y', $prev_ts)]);
$url_next = $qs(['mes' => date('m', $next_ts), 'anio' => date('Y', $next_ts)]);
$url_hoy  = '?' . http_build_query(['vista' => $vista]);
$from_param = $es_cob ? '' : '&from=horas';

// ── 6. Datos de detalle para el panel lateral (JSON) ─────────────────────
$js_items = [];
foreach ($items as $id => $it) {
    $dias_l = [];
    foreach ($it['dias'] as $dia => $dd) {
        $f = date('d/m', strtotime("+{$dias_off[$dia]} days", strtotime($it['inicio'])));
        $v = $es_cob ? formatCurrency($dd['ef'] + $dd['tr']) : ($dd['min'] > 0 ? formatHoras($dd['min']) : '–');
        $dias_l[] = [$dias_nom[$dia] . ' ' . $f, $v];
    }
    if ($es_cob) {
        $comision = ($it['ef'] + $it['tr']) * 0.06;
        $lineas = [
            ['Efectivo', formatCurrency($it['ef'])],
            ['Transferencia', formatCurrency($it['tr'])],
            ['Gastos', formatCurrency($it['gm'])],
            ['Comisión 6%', formatCurrency($comision)],
        ];
        $url_ver = 'ver_cierre.php?id=' . $id;
        $url_edit = null;
    } else {
        $lineas = [
            ['Horas totales', formatHoras($it['min'])],
            ['Valor hora', formatCurrency($it['vh'])],
        ];
        $url_ver = 'liquidar_horas.php?id=' . $id . '&export=1';
        $url_edit = 'cargar_horas.php?id=' . $id;
    }
    $js_items[$id] = [
        'id' => $id,
        'kicker' => $es_cob ? 'Cobranza' : 'Jornales',
        'nombre' => $it['nombre'],
        'semana' => date('d/m/Y', strtotime($it['inicio'])),
        'total' => formatCurrency($it['total']),
        'dias' => $dias_l,
        'lineas' => $lineas,
        'ver' => $url_ver,
        'verLbl' => $es_cob ? 'Ver liquidación' : 'Ver recibo',
        'editar' => $url_edit,
        'eliminar' => $es_admin_h ? 'eliminar_cierre.php?id=' . $id . '&token=' . csrf_token() . $from_param : null,
    ];
}

// ── 7. Semanas del mes para ambas vistas ─────────────────────────────────
$semanas = [];
for ($ts = $grid_ini; $ts <= $grid_fin; $ts = strtotime('+7 days', $ts)) {
    $semanas[] = date('Y-m-d', $ts);
}
$nombres_mes = [];
foreach ($semanas as $lunes) {
    foreach ($por_semana[$lunes] ?? [] as $id) $nombres_mes[$items[$id]['nombre']] = true;
}
$nombres_mes = array_keys($nombres_mes);
sort($nombres_mes);
$max_medida = 0;
foreach ($semanas as $lunes) foreach ($por_semana[$lunes] ?? [] as $id) $max_medida = max($max_medida, $items[$id]['medida']);

$titulo_h1 = $es_cob ? 'Historial de cobranzas' : 'Historial de jornales';
$nueva_url = $es_cob ? 'cargar.php' : 'cargar_horas.php';
$item_lbl  = $es_cob ? 'cierre' : 'liquidación';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $es_cob ? 'Historial de Cierres' : 'Historial de Horas' ?></title>
    <link rel="stylesheet" href="assets/css/organic.css">
    <link rel="stylesheet" href="assets/css/imperio.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="assets/js/main.js" defer></script>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>

    <!-- Modal de confirmación de eliminación -->
    <div class="dialog-backdrop no-print" id="deleteModal" hidden>
        <div class="dialog">
            <span class="imp-dialog-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            </span>
            <div class="dialog-title">¿Eliminar liquidación?</div>
            <div class="dialog-body">Esta acción es <strong>permanente</strong> y no se puede deshacer. Se eliminarán todos los detalles asociados a este cierre.</div>
            <div class="dialog-actions">
                <button type="button" class="btn btn-secondary" style="min-height:44px;" onclick="closeModal()">Cancelar</button>
                <a id="confirmDeleteBtn" href="#" class="btn btn-primary" style="min-height:44px;">Sí, eliminar</a>
            </div>
        </div>
    </div>

    <main class="imp-main">
        <div id="toast-container" class="toast-container"></div>

        <div class="imp-page">
            <!-- Encabezado -->
            <div class="imp-dash-head">
                <div class="imp-page-head">
                    <span class="imp-kicker <?= $es_cob ? '' : 'sage' ?>">Historial</span>
                    <h1><?= $titulo_h1 ?></h1>
                    <p><?= $es_cob ? 'Cobranzas registradas en el sistema.' : 'Registro de liquidaciones de jornales.' ?></p>
                </div>
                <div class="imp-weeknav">
                    <div class="seg imp-tabs">
                        <a href="historial.php" class="seg-opt <?= $es_cob ? 'on' : '' ?>">Cobranzas</a>
                        <a href="historial_horas.php" class="seg-opt <?= $es_cob ? '' : 'on' ?>">Jornales</a>
                    </div>
                    <div class="seg imp-tabs">
                        <a href="<?= $qs(['vista' => 'mes']) ?>" class="seg-opt <?= $vista === 'mes' ? 'on' : '' ?>">Mes</a>
                        <a href="<?= $qs(['vista' => 'semanas']) ?>" class="seg-opt <?= $vista === 'semanas' ? 'on' : '' ?>">Semanas</a>
                    </div>
                    <a href="<?= $nueva_url ?>" class="btn btn-primary imp-btn-today" style="color:var(--color-text);">+ Nueva carga</a>
                </div>
            </div>

            <!-- KPIs -->
            <div class="imp-hist-kpis">
                <?php foreach ($kpis as [$lbl, $val]): ?>
                    <div class="imp-hist-kpi">
                        <span><?= e($lbl) ?></span>
                        <strong class="imp-num"><?= e($val) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="imp-layout">
                <section class="imp-cal">
                    <!-- Navegación de mes -->
                    <div class="imp-cal-nav">
                        <a href="<?= $url_prev ?>" class="imp-circle-btn" aria-label="Mes anterior">‹</a>
                        <h2><?= $meses_es[$mes] ?> <?= $anio ?></h2>
                        <a href="<?= $url_next ?>" class="imp-circle-btn" aria-label="Mes siguiente">›</a>
                        <a href="<?= $url_hoy ?>" class="btn btn-secondary imp-btn-today" style="margin-left:auto;">Hoy</a>
                    </div>

                    <!-- Búsqueda en vivo -->
                    <div class="imp-search">
                        <i data-lucide="search" style="width:16px; height:16px;"></i>
                        <input type="search" id="searchInput" class="input on-bg" placeholder="Buscar por <?= $es_cob ? 'zona' : 'empleado' ?> o fecha (dd/mm)…" oninput="filterItems()">
                        <span id="search-count"></span>
                    </div>

                    <?php if ($vista === 'mes'): ?>
                        <div class="imp-cal-weeks">
                            <div class="imp-cal-dow"><span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span><span>Dom</span></div>
                            <?php foreach ($semanas as $lunes): ?>
                                <div class="imp-cal-week" data-week="<?= $lunes ?>">
                                    <div class="imp-cal-days">
                                        <?php for ($d = 0; $d < 7; $d++):
                                            $ts = strtotime("+$d days", strtotime($lunes));
                                            $f  = date('Y-m-d', $ts);
                                            $fuera = date('Y-m', $ts) !== $mes_key;
                                            $val = $por_fecha[$f] ?? 0;
                                        ?>
                                            <div class="imp-cal-day <?= $fuera ? 'out' : '' ?>">
                                                <span class="imp-cal-num <?= $f === $hoy_str ? 'today' : '' ?>"><?= date('j', $ts) ?></span>
                                                <?php if ($val > 0): ?><span class="imp-cal-amt"><?= e($fmt_celda($val)) ?></span><?php endif; ?>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                    <?php if (!empty($por_semana[$lunes])): ?>
                                        <div class="imp-cal-chips">
                                            <?php foreach ($por_semana[$lunes] as $id): $it = $items[$id]; ?>
                                                <button type="button" class="imp-cal-chip <?= $es_cob ? '' : 'sage' ?>" data-id="<?= $id ?>" data-search="<?= e(mb_strtolower($it['nombre'] . ' ' . date('d/m/Y', strtotime($it['inicio'])))) ?>">
                                                    <span class="dot"><?= e(mb_substr($it['nombre'], 0, 1)) ?></span>
                                                    <span class="nm"><?= e($it['nombre']) ?></span>
                                                    <span class="amt"><?= e($es_cob ? montoCorto($it['total']) : formatHoras($it['min'])) ?></span>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table class="imp-sem-table">
                                <thead>
                                    <tr>
                                        <th>Semana</th>
                                        <?php foreach ($nombres_mes as $n): ?><th><?= e($n) ?></th><?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($semanas as $lunes): ?>
                                        <tr data-week="<?= $lunes ?>">
                                            <td class="lbl"><?= date('d/m', strtotime($lunes)) ?></td>
                                            <?php foreach ($nombres_mes as $n):
                                                $celda = null;
                                                foreach ($por_semana[$lunes] ?? [] as $id) if ($items[$id]['nombre'] === $n) { $celda = $items[$id]; break; }
                                                $nivel = ($celda && $max_medida > 0) ? max(1, min(4, (int)ceil($celda['medida'] / $max_medida * 4))) : 0;
                                            ?>
                                                <td>
                                                    <?php if ($celda): ?>
                                                        <button type="button" class="imp-sem-cell lv<?= $nivel ?>" data-id="<?= $celda['id'] ?>" data-search="<?= e(mb_strtolower($celda['nombre'] . ' ' . date('d/m/Y', strtotime($celda['inicio'])))) ?>">
                                                            <b><?= e($es_cob ? montoCorto($celda['total']) : formatHoras($celda['min'])) ?></b>
                                                            <small><?= e($es_cob ? 'cobrado' : montoCorto($celda['total'])) ?></small>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="imp-sem-empty">–</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if ($n_mes === 0): ?>
                        <p class="imp-empty" style="text-align:left;">No hay liquidaciones registradas en este mes.</p>
                    <?php endif; ?>
                </section>

                <!-- Panel lateral -->
                <aside class="imp-summary" id="selPanel">
                    <div id="selNone" class="imp-sel-none">
                        <span class="imp-sel-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path></svg>
                        </span>
                        <h3>Elegí un cierre</h3>
                        <p>Tocá cualquier <?= $item_lbl ?> del calendario para ver el detalle, abrir el PDF o eliminarlo.</p>
                    </div>
                    <div id="selHas" class="imp-sel-has" hidden>
                        <span class="imp-kicker" id="selKicker"></span>
                        <div>
                            <h3 id="selNombre"></h3>
                            <span class="imp-sel-sub" id="selSemana"></span>
                        </div>
                        <span class="imp-money-big imp-money-md" id="selTotal"></span>
                        <div class="imp-sel-days" id="selDias"></div>
                        <hr>
                        <div class="imp-sel-lines" id="selLineas"></div>
                        <div class="imp-sel-actions">
                            <a id="selVer" href="#" target="_blank" class="btn btn-primary" style="color:var(--color-text);"></a>
                            <a id="selEditar" href="#" class="btn imp-btn-ghost-dark" hidden>Editar</a>
                            <button type="button" id="selEliminar" class="btn imp-btn-danger-dark" hidden>Eliminar</button>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </main>

    <script>
        var ITEMS = <?= json_encode($js_items, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
        var seleccionado = null;

        function fila(clase, k, v) {
            var d = document.createElement('div');
            if (clase) d.className = clase;
            var a = document.createElement('span'); a.textContent = k;
            var b = document.createElement('strong'); b.textContent = v;
            d.appendChild(a); d.appendChild(b);
            return d;
        }

        function seleccionar(id) {
            var it = ITEMS[id];
            if (!it) return;
            seleccionado = id;
            document.querySelectorAll('[data-id]').forEach(function(el) { el.classList.toggle('active', el.getAttribute('data-id') == id); });
            document.querySelectorAll('[data-week]').forEach(function(el) {
                el.classList.toggle('sel', !!el.querySelector('[data-id="' + id + '"]'));
            });
            document.getElementById('selNone').hidden = true;
            document.getElementById('selHas').hidden = false;
            document.getElementById('selKicker').textContent = it.kicker;
            document.getElementById('selNombre').textContent = it.nombre;
            document.getElementById('selSemana').textContent = 'Semana del ' + it.semana;
            document.getElementById('selTotal').textContent = it.total;
            var dias = document.getElementById('selDias'); dias.textContent = '';
            it.dias.forEach(function(d) { dias.appendChild(fila('', d[0], d[1])); });
            var lin = document.getElementById('selLineas'); lin.textContent = '';
            it.lineas.forEach(function(l) { lin.appendChild(fila('', l[0], l[1])); });
            var ver = document.getElementById('selVer'); ver.href = it.ver; ver.textContent = it.verLbl;
            var ed = document.getElementById('selEditar'); ed.hidden = !it.editar; if (it.editar) ed.href = it.editar;
            var del = document.getElementById('selEliminar'); del.hidden = !it.eliminar;
        }

        document.addEventListener('click', function(e) {
            var b = e.target.closest('[data-id]');
            if (b) seleccionar(b.getAttribute('data-id'));
        });

        document.getElementById('selEliminar').addEventListener('click', function() {
            if (seleccionado && ITEMS[seleccionado].eliminar) openModal(ITEMS[seleccionado].eliminar);
        });

        // Notificaciones de estado desde URL
        document.addEventListener('DOMContentLoaded', function() {
            var p = new URLSearchParams(window.location.search);
            var status = p.get('status');
            if (status === 'deleted') showToast('Liquidación eliminada correctamente.', 'success');
            if (status === 'error') showToast('Error: ' + (p.get('msg') || 'Acción fallida'), 'error');
        });

        // --- Búsqueda en vivo (atenúa los cierres que no coinciden) ---
        function filterItems() {
            var q = document.getElementById('searchInput').value.toLowerCase().trim();
            var n = 0;
            document.querySelectorAll('[data-search]').forEach(function(el) {
                var ok = !q || el.getAttribute('data-search').indexOf(q) !== -1;
                el.classList.toggle('dim', !ok);
                if (ok) n++;
            });
            document.getElementById('search-count').textContent = q ? n + (n === 1 ? ' resultado' : ' resultados') : '';
        }

        // --- Modal de confirmación ---
        function openModal(url) {
            document.getElementById('confirmDeleteBtn').href = url;
            document.getElementById('deleteModal').hidden = false;
            document.body.style.overflow = 'hidden';
        }
        function closeModal() {
            document.getElementById('deleteModal').hidden = true;
            document.body.style.overflow = '';
        }
        document.getElementById('deleteModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeModal(); });

        window.addEventListener('load', function() { if (typeof lucide !== 'undefined') lucide.createIcons(); });
    </script>
</body>
</html>
