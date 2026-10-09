<?php
/**
 * Hoja A4 del reporte mensual. Requiere includes/reporte_datos.php ya incluido.
 * $mostrar_comision (opcional): agrega la columna "Comisión".
 */
$mostrar_comision = $mostrar_comision ?? false;
$periodo = $meses[$mes_actual] . ' ' . $anio_actual;
?>
<div class="sheet-wrap">
    <div class="sheet compact">
        <div class="sheet-head">
            <img class="sheet-logo" src="assets/img/logo.png" alt="Imperio">
            <div class="sheet-brand"><b>Imperio<span>.</span></b><small>Sistema de gestión</small></div>
            <span class="tag tag-accent" style="font-size:12px; padding:6px 12px;"><?= e($periodo) ?></span>
        </div>

        <div class="sheet-title">
            <h1>Reporte mensual de cobranzas</h1>
            <span class="sheet-sub">Periodo: <?= e($periodo) ?> · <?= e($titulo_reporte) ?></span>
        </div>

        <div class="sheet-info">
            <div><small>Total bruto</small><strong class="imp-num sheet-kpi"><?= formatCurrency($g_bruto) ?></strong></div>
            <div><small>Gastos</small><strong class="imp-num sheet-kpi"><?= formatCurrency($g_gastos) ?></strong></div>
            <div class="sage"><small>Neto final</small><strong class="imp-num sheet-kpi"><?= formatCurrency($g_neto) ?></strong></div>
        </div>

        <div class="sheet-table-wrap">
            <table class="sheet-table">
                <thead>
                    <tr>
                        <th><?= e($columna_principal) ?></th>
                        <th class="r">Efectivo</th>
                        <th class="r">Transf.</th>
                        <th class="r">Total bruto</th>
                        <?php if ($mostrar_comision): ?><th class="r">Comisión</th><?php endif; ?>
                        <th class="r">Gastos</th>
                        <th class="r">Neto final</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($filas) > 0): ?>
                        <?php foreach ($filas as $f): ?>
                        <tr>
                            <td><strong><?= e($f['titulo']) ?></strong></td>
                            <td class="r"><?= formatCurrency($f['efectivo']) ?></td>
                            <td class="r"><?= formatCurrency($f['transf']) ?></td>
                            <td class="r"><strong><?= formatCurrency($f['bruto']) ?></strong></td>
                            <?php if ($mostrar_comision): ?><td class="r"><?= formatCurrency($f['comision']) ?></td><?php endif; ?>
                            <td class="r gasto-col"><?= formatCurrency($f['gastos']) ?></td>
                            <td class="r total-col"><?= formatCurrency($f['neto']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="<?= $mostrar_comision ? 7 : 6 ?>" class="c" style="padding:24px;">Sin registros para este periodo.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>TOTALES</td>
                        <td class="r"><?= formatCurrency($g_efectivo) ?></td>
                        <td class="r"><?= formatCurrency($g_transf) ?></td>
                        <td class="r"><?= formatCurrency($g_bruto) ?></td>
                        <?php if ($mostrar_comision): ?><td class="r"><?= formatCurrency($g_comision) ?></td><?php endif; ?>
                        <td class="r"><?= formatCurrency($g_gastos) ?></td>
                        <td class="r"><?= formatCurrency($g_neto) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="sheet-foot sheet-foot-row">
            <span>Generado el <?= date('d/m/Y H:i') ?> por <?= e($_SESSION['username'] ?? 'Sistema') ?></span>
            <span>Neto = bruto − (comisión + saldo a favor)</span>
        </div>
    </div>
</div>
