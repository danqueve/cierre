# Handoff: Rediseño Imperio Cierres (estilo Organic)

Repo destino: `danqueve/cierre` (PHP + PDO, sin framework, CSS en `assets/css/style.css`).

## Cómo usar este paquete con Claude Code (VS Code)

1. Descomprimí esta carpeta dentro de la raíz de tu proyecto local (`cierre/design_handoff_imperio_cierres/`).
2. Abrí VS Code en la raíz del proyecto y abrí Claude Code.
3. Pegale este pedido:

   > Leé `design_handoff_imperio_cierres/README.md` y aplicá el rediseño a las páginas PHP existentes, pantalla por pantalla, sin cambiar la lógica de base de datos ni los nombres de los campos `name=""` de los formularios. Empezá por `includes/header.php` y `cargar.php`, y mostrame el diff antes de seguir con la siguiente.

4. Para ver el prototipo de referencia: abrí `Imperio Cierres.dc.html` con Live Server (extensión de VS Code) o cualquier servidor local. Es interactivo.

## Sobre los archivos de diseño

`Imperio Cierres.dc.html` es una **referencia de diseño hecha en HTML** (prototipo con datos de ejemplo), no código para copiar tal cual. La tarea es **recrear este diseño en los archivos PHP existentes**, manteniendo:
- Toda la lógica PHP/SQL, `csrf_field()` / `csrf_verify()`, sesiones y redirecciones.
- Los `name` de los inputs (`data[LUNES][efectivo]`, `horas[LUNES][entrada]`, `saldo_favor`, `zona`, `fecha_inicio`, etc.).
- Los cálculos JS existentes (`calcularTotales()`, `calcularHoras()`), adaptando solo los IDs/markup que cambien.

En el prototipo los estilos están inline (por la herramienta de diseño). En PHP conviene pasarlos a **clases** en una hoja nueva `assets/css/imperio.css`.

## Fidelidad

**Alta fidelidad.** Colores, tipografía, radios y espaciados son finales. Los datos son de ejemplo.

## Setup de estilos

1. Copiar `assets/css/organic.css` (tokens + componentes base) al proyecto.
2. En cada `<head>`, **reemplazar** `assets/css/style.css` por:
   ```html
   <link rel="stylesheet" href="assets/css/organic.css">
   <link rel="stylesheet" href="assets/css/imperio.css">
   ```
   (`organic.css` ya importa Google Fonts: Caprasimo + Figtree 400/600/700.)
3. Eliminar los bloques `<style>` por página de `cargar.php`, `cargar_horas.php`, `historial.php`, etc. (tema oscuro/violeta viejo).
4. Lucide se sigue usando (`<i data-lucide>` + `lucide.createIcons()`); agregar `stroke-width: 2.75` vía CSS: `.lucide{stroke-width:2.75}`.

## Design tokens (de organic.css)

Colores:
- Fondo `--color-bg` #f5ead8 · Superficie `--color-surface` #ebddc5 · Texto `--color-text` #201e1d
- Acento (terracota) `--color-accent` #c67139 · 100 #fff2eb · 200 #ffe1d0 · 300 #ffc6a5 · 600 #b2622d · 700 #8c491a · 800 #643312
- Acento 2 (salvia) `--color-accent-2` #7a8a5e · 100 #f0fae1 · 200 #e1eecc · 300 #ccdbb2 · 400 #aebf92 · 500 #8fa073 · 600 #728157 · 700 #56633f · 800 #3d472b · 900 #272e1b
- Neutros 100 #f9f4ed · 200 #eee7db · 300 #dcd3c4 · 400 #c0b6a5 · 600 #82796a · 700 #645c50 · 800 #474238

Tipografía:
- Títulos: Caprasimo 400 (`--font-heading`). H1 de página 44px; H2 32px; H3 24–28px.
- Cuerpo: Figtree (`--font-body`) 15px base.
- **Montos/totales: Figtree 700, `font-variant-numeric: tabular-nums`, letter-spacing -0.02em** (NO Caprasimo). Total grande 44px; panel de historial 42px; KPIs 28px; totales en PDF 22–26px.
- Kickers: 12px, 700, uppercase, letter-spacing .1em, color accent-700 (o accent-2-700 en Jornales).
- Labels de campo: 12px, color texto al 70% (`.field > label`).

Radios: inputs/botones/chips `999px`; tarjetas de sección 32px; paneles resumen 36px; tarjetas pequeñas 18–28px; filas de días 999px.
Sombras: `--shadow-sm` filas/tarjetas, `--shadow-lg` hoja A4.
Espaciado: `--space-2` 8.8px · `--space-3` 13.2px · `--space-4` 17.6px · `--space-6` 26.4px. Padding horizontal de página `clamp(16px,4vw,48px)`, contenedor máx. 1240px.

## Pantallas

### Header (`includes/header.php`)
- Fila flex con wrap, padding 13px × clamp(16,4vw,48).
- Marca: círculo 44px fondo neutral-100 con `logo.png` 36px + "Imperio" Caprasimo 22px y punto en `--color-accent`.
- Nav: botones pill (padding 8×16, 14px/600). Activo: fondo `--color-text`, texto `--color-bg`. Inactivo: transparente.
- Usuario: nombre 14px/700 + rol 12px neutral-700, avatar círculo 38px accent-2-300 con inicial Caprasimo.
- Mantener el menú hamburguesa mobile existente, re-estilado con los mismos tokens.

### 1. Cargar cobranzas (`cargar.php`)
Layout: encabezado (kicker "Cobranzas", H1 "Carga de cobranzas", subtítulo) + dos columnas flex-wrap: principal `flex:1 1 620px`, resumen `flex:1 1 300px; max-width:400px; position:sticky; top:16px`.
- **Tarjeta de configuración** (surface, radio 32, padding 26): 
  - Zona como **chips pill** (reemplaza el `<select>`; implementarlo con `<input type="radio" name="zona">` ocultos + `<label>`). Activo: fondo accent, borde accent. Inactivo: neutral-100, borde neutral-300.
  - `fecha_inicio` (input date `.input`, min-height 44) + tag salvia "Semana del dd/mm al dd/mm/aaaa".
  - Segmentado `.seg` "Semana completa / Día por día" (los radios `modo_carga` existentes).
- **Modo semanal**: encabezado de columnas (11px uppercase) y una **fila pill por día** (fondo neutral-100, radio 999, shadow-sm), grid `150px repeat(3,1fr) 1.3fr`: círculo 40px accent-200 con "Lu/Ma…" + nombre y fecha del día; inputs Efectivo, Transferencia (prefijo "$"), Gasto (fondo accent-100, texto accent-800), Concepto.
- **Modo diario**: tarjeta neutral-100; selector de día como **círculos de 72px** (activo accent) con abreviatura y fecha; "Fecha del pago: …" en accent-2-700; 4 campos en grid auto-fit 180px.
- **Ajustes finales**: dos tarjetas radio 32: "Saldo a favor" (accent-2-100, ícono + en círculo accent-2-600) y "Descuento créditos" (accent-100, ícono − accent-600).
- **Panel resumen** (fondo `--color-text`, texto `--color-bg`, radio 36): kicker "Resumen · {zona}", Total cobrado (44px), filas Efectivo / Transferencia / Gastos (accent-300) / Saldo a favor (accent-2-300) / Descuento (accent-300), botón primario "Guardar datos" min-height 52 (submit).
- Toast de éxito: pill fija abajo-centro, fondo accent-2-700, check en círculo accent-2-200.

### 2. Cargar horarios (`cargar_horas.php`)
Misma estructura (kicker "Jornales", H1 "Nueva/Editar liquidación de jornales").
- Empleado como chips con avatar inicial (radio `name="zona"`), fecha, **Valor hora** (fondo accent-2-100, 18px/700).
- Botón secundario "Repetir horario del lunes": copia entrada/salida del lunes a Ma–Vi (no al sábado). **Funcionalidad nueva**, JS simple.
- Grilla de días `repeat(auto-fill,minmax(270px,1fr))`: tarjeta neutral-100 radio 32 con círculo del día, nombre/fecha, **pill de total** (con horas: accent-2-600 / texto neutral-100; vacío: neutral-200), inputs time Entrada/Salida en 2 columnas, y **barra de turno** 10px (fondo neutral-300, segmento accent-2-500 posicionado `left = inicio/24h`, `width = duración/24h`) con marcas 0/6/12/18/24h.
- Ajustes: "Ingresos extra" / "Descuentos / adelantos" (mismo estilo que cobranzas).
- Resumen oscuro: Total a pagar, Total horas, Subtotal, extras, descuentos, botón "Guardar y ver liquidación".

### 3. Historial como calendario (`historial.php` y `historial_horas.php`)
- Encabezado + segmentados "Cobranzas / Jornales" (cada uno lleva a su página) y "Mes / Semanas" (`?vista=mes|semanas`), botón "+ Nueva carga".
- Navegación de mes con `?mes=MM&anio=AAAA`: botones círculo ‹ ›, título "Octubre 2026" Caprasimo 30px, botón "Hoy".
- KPIs (grid auto-fit 200px, surface radio 28): Cierres del mes, Total recaudado, Mejor semana, Promedio semanal (jornales: Liquidaciones, Horas totales, Total liquidado, Promedio). Mismos cálculos que el `historial.php` actual, filtrados por mes.
- **Vista Mes**: encabezado Lun…Dom; cada semana es un bloque (surface, radio 28; si contiene el seleccionado: accent-100) con 7 celdas (fondo bg, radio 20, min-height 62; fuera de mes opacidad .4; hoy = número en círculo accent) mostrando el total del día (Σ efectivo+transferencia de `detalles_diarios`, formato corto "$ 324 mil"; en jornales horas). Debajo, **chips por cierre** de esa semana (`fecha_inicio` = lunes): inicial en círculo, nombre de zona/empleado, total corto.
- **Vista Semanas**: tabla filas = semanas, columnas = zonas/empleados; celda botón radio 20 con intensidad salvia (100/200/300/400 según total/máximo).
- **Panel lateral oscuro** al elegir un cierre: tipo, nombre, semana, total, desglose por día, efectivo/transferencia/gastos/comisión 6% (o horas/valor hora), botones "Ver liquidación" (→ `ver_cierre.php?id=`), en jornales "Ver recibo" (→ `liquidar_horas.php?id=`) y "Editar" (→ `cargar_horas.php?id=`), y "Eliminar" (abre el modal).
- Sin selección: ícono de calendario + "Elegí un cierre".
- Modal eliminar: `.dialog-backdrop` + `.dialog`, mismo texto actual, "Cancelar" / "Sí, eliminar" → `eliminar_cierre.php?id=…&token=…`.
- Mantener el filtro existente que excluye `['Alejandro','Lourdes','Maxi']` en historial de cobranzas.

### 4. PDF / impresión (`liquidar_horas.php`, `ver_cierre.php`, `ver_impresion_reporte.php`)
Hoja común: 794×1123px (A4) fondo neutral-100 en pantalla (blanco al imprimir), padding ~60/56px, flex column gap 24–28, contenedor gris neutral-300 radio 36 alrededor.
- Cabecera: logo 56px en círculo blanco + "Imperio." + "Sistema de gestión"; tag a la derecha (semana / zona / periodo).
- H1 Caprasimo 34–38px + subtítulo 14px.
- Tres cajas de info (surface, radio 18): etiqueta 10px uppercase + valor 15px/700.
- Tabla con contenedor radio 20 y borde neutral-300; encabezado oscuro (`--color-text`; en jornales accent-2-700) 10–11px uppercase; filas con separador neutral-300; columna total resaltada accent-2-100/accent-2-800; tfoot sobre surface con borde superior 2px.
- **Liquidación jornales**: caja de totales a la derecha (360px, surface, radio 24); TOTAL A PAGAR 26px accent-700; conceptos en itálica 11px; firmas "Firma empleado" / "Firma responsable".
- **Cierre de cobranza**: dos cajas lado a lado: "Liquidación cobrador" (surface) y "Neto a rendir (empresa)" (accent-2-100); mismas fórmulas actuales; nota de gastos; "Firma administración".
- **Reporte mensual**: filtros (mes, año, chips de zona "Todas las zonas" + zonas) arriba, fuera de la hoja; 3 KPIs (Total bruto, Gastos, Neto final); columna opcional "Comisión 6%"; pie "Generado el … por …".
- Toolbar fuera de la hoja: "Volver/Editar" (secundario) + "Imprimir / Guardar PDF" (primario, `window.print()`; mantener html2pdf si se usa `?export=1`).
- CSS de impresión:
  ```css
  @media print{
    @page{size:A4 portrait;margin:0}
    body{background:#fff!important}
    .no-print{display:none!important}
    .sheet-wrap{padding:0!important;background:none!important;border-radius:0!important}
    .sheet{box-shadow:none!important;margin:0!important;border-radius:0!important;background:#fff!important;
           -webkit-print-color-adjust:exact;print-color-adjust:exact}
  }
  ```
  Ocultar el header del sitio al imprimir.

## Interacciones
- Hovers/pressed/focus vienen de `organic.css` (`.btn-primary:hover` accent-600, `:focus-visible` anillo 2px accent).
- Totales en vivo: reutilizar `calcularTotales()` / `calcularHoras()` apuntando a los nuevos IDs.
- Responsive: las columnas usan flex-wrap; el panel resumen baja debajo del formulario en pantallas angostas. En mobile, las filas de días de cobranzas pasan a tarjeta (grid de 2 columnas) por debajo de ~700px.

## Archivos
- `Imperio Cierres.dc.html`: prototipo interactivo de todas las pantallas (necesita `support.js` al lado). La barra superior navega entre Cargar, Jornales, Historial, Hist. Horas y Reportes.
- `assets/css/organic.css`: tokens y componentes (`.btn`, `.input`, `.field`, `.seg`, `.tag`, `.dialog`…).
- `assets/img/logo.png`: logo existente del repo.
