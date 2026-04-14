# Plan: Mejora Integral del Sistema "Cierre"

## Contexto

El sistema "Cierre" es una aplicacion PHP para gestion de cierres semanales de cobranzas de "Gestion Imperio". Gestiona cobros por zona, calcula comisiones (5%), controla gastos, y genera reportes de liquidacion. Actualmente la app esta **rota** por 42 conflictos de merge sin resolver en 12 archivos, tiene vulnerabilidades de seguridad, no es responsive, y genera PDFs solo via `window.print()`.

**Skills a utilizar**: `php-pro`, `ui-ux-pro-max`, `mobile-design`, `database-design`

---

## FASE 1: Reparar la App (Conflictos de Merge + Schema)

**Prioridad**: CRITICA - sin esto nada funciona

### 1.1 Resolver conflictos de merge en 12 archivos
Estrategia: **Mantener HEAD** en todos los casos (es la version mas completa con soporte de horas, turnos tarde, descuento creditos).

| Archivo | Accion |
|---------|--------|
| `style.css` | Mantener HEAD (~1134 lineas), eliminar version duplicada |
| `main.js` | Mantener HEAD (3 conflictos): DOMContentLoaded, setupValidation, showToast |
| `header.php` | Mantener HEAD: nav con dropdown Historiales, Lucide includes |
| `dashboard.php` | Mantener HEAD: sparklines, trend calculations |
| `cargar.php` | Mantener HEAD: modo semanal/diario, descuento_creditos |
| `cargar_horas.php` | Mantener HEAD: turno manana/tarde |
| `historial.php` | Mantener HEAD: filtro empleados, table-modern |
| `eliminar_cierre.php` | Mantener HEAD |
| `liquidar_horas.php` | Mantener HEAD: calculo turno tarde |
| `reportes_mensuales.php` | Mantener HEAD: Chart.js |
| `ver_cierre.php` | Mantener HEAD: descuento_creditos, layout 2 columnas |

### 1.2 Actualizar schema SQL (`cobranzas_db.sql`)
Agregar columnas faltantes que el codigo ya usa:
- `cierres_semanales`: `descuento_creditos`, `descuento_creditos_concepto`, `valor_hora`
- `detalles_diarios`: `hora_entrada`, `hora_salida`, `hora_entrada_tarde`, `hora_salida_tarde`

### 1.3 Fix HTML roto en `index.php`
- Eliminar `</style></head>` duplicado
- Eliminar carga duplicada de `main.js`

### Verificacion Fase 1
- `grep -rl '<<<<<<' *.php *.css *.js` = 0 resultados
- Navegar cada pagina sin errores PHP en browser

---

## FASE 2: Seguridad

**Skill**: `php-pro`

### 2.1 Credenciales DB fuera del codigo
- Crear `config.php` con credenciales, agregar a `.gitignore`
- `db.php` importa de `config.php`

### 2.2 Proteccion CSRF
- Agregar funciones `csrf_token()`, `csrf_field()`, `verify_csrf()` en `db.php`
- Insertar token en TODOS los formularios: `index.php`, `cargar.php`, `cargar_horas.php`, `crear_usuario.php`
- Verificar token en cada POST handler

### 2.3 Eliminar via POST (no GET)
- `eliminar_cierre.php`: cambiar a `$_POST['id']` + CSRF
- `historial.php` y `historial_horas.php`: cambiar links de eliminar a mini-forms con POST

### 2.4 Fix XSS
Envolver toda salida de datos de usuario con `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')` en:
- `ver_cierre.php`: zona, gasto_concepto, saldo_concepto, descuento_creditos_concepto
- `historial.php`, `historial_horas.php`: zona
- `liquidar_horas.php`: zona, conceptos
- `dashboard.php`: variables inyectadas en JS

### 2.5 Crear `.gitignore`
```
config.php
ssl
*.log
.env
vendor/
```

### 2.6 Eliminar archivo SSH key (`ssl`)
- Borrar `ssl` del directorio web

### Verificacion Fase 2
- Inyectar `<script>alert(1)</script>` como nombre de zona → debe escaparse
- Intentar DELETE via GET → debe fallar
- Forms sin CSRF token → debe rechazar

---

## FASE 3: UI/UX y Responsividad Movil

**Skills**: `ui-ux-pro-max`, `mobile-design`

### 3.1 Limpieza de `style.css`
- Eliminar reglas duplicadas post-merge
- Agregar `<meta name="viewport">` a TODAS las paginas (solo `index.php` lo tiene)

### 3.2 Menu hamburguesa para movil (`header.php` + `style.css` + `main.js`)
- Boton hamburguesa visible solo en `max-width: 768px`
- Nav como overlay/slide-in en movil
- Dropdown "Historiales" funcional con touch

### 3.3 Breakpoints responsive en `style.css`
```css
@media (max-width: 768px) {
  /* Stats grid: 2 columnas en tablet, 1 en movil */
  /* Tablas: scroll horizontal */
  /* Forms: inputs full-width */
  /* Cards: padding reducido */
}
@media (max-width: 480px) {
  /* Ajustes finos para pantallas pequenas */
}
```

### 3.4 Extraer inline styles a clases CSS
Prioridad por cantidad de inline styles:
1. `cargar.php` (92 inline) → clases: `.form-grid`, `.section-divider`, `.text-right`, `.mode-toggle`
2. `cargar_horas.php` (76 inline) → reutilizar clases de cargar.php
3. `reportes_mensuales.php` (72 inline) → `.filter-bar`, `.chart-container`, `.report-grid`
4. `ver_cierre.php` (52 inline) → estilos de print en `@media print`
5. `historial.php` (33 inline) → `.action-buttons`, `.badge-zone`
6. `dashboard.php` (31 inline) → `.kpi-grid`, `.chart-wrapper`, `.filter-row`

### 3.5 Unificar libreria de charts
- Reemplazar Google Charts en `dashboard.php` por Chart.js
- Eliminar dependencia de `gstatic.com/charts/loader.js`
- Resultado: una sola libreria (Chart.js) en toda la app

### 3.6 Accesibilidad basica
- `<label for="...">` en todos los inputs
- `aria-label` en botones de solo-icono (eliminar, ver, imprimir)
- Link "Saltar al contenido" en `header.php`
- `prefers-reduced-motion` para deshabilitar animaciones

### Verificacion Fase 3
- Chrome DevTools a 375px de ancho → nav funcional, forms apilados, tablas scrolleables
- Lighthouse Accessibility > 70
- No errores CSS en W3C validator

---

## FASE 4: Generacion de PDF Server-Side

**Skill**: `php-pro`

### 4.1 Instalar Dompdf via Composer
```bash
composer require dompdf/dompdf
```
Agregar `vendor/` a `.gitignore`

### 4.2 Crear endpoint de generacion PDF (`generar_pdf.php`)
- Acepta `?id=X&type=cierre|horas|reporte`
- Reutiliza la misma logica de datos de `ver_cierre.php` / `liquidar_horas.php`
- Renderiza HTML a PDF con Dompdf
- Output: PDF inline en browser o descarga

### 4.3 Crear templates PDF
- `templates/pdf_cierre.php` — liquidacion de cobranzas (A4, tabla, watermark, firmas)
- `templates/pdf_horas.php` — liquidacion de horas (A4, turnos, totales)
- `templates/pdf_reporte.php` — reporte mensual consolidado
- CSS inline (requerido por Dompdf), layout basado en tablas (mejor soporte)
- Logo watermark con path absoluto: `file:///c:/wamp64/www/cierre/img/logo.png`

### 4.4 Actualizar UI con botones de PDF
- `historial.php`: icono PDF en cada fila → link a `generar_pdf.php?id=X&type=cierre`
- `historial_horas.php`: idem con `type=horas`
- `ver_cierre.php` y `liquidar_horas.php`: boton "Descargar PDF" junto a "Imprimir"
- `reportes_mensuales.php`: boton "Exportar PDF" para reporte mensual
- Mantener `window.print()` como opcion alternativa

### Verificacion Fase 4
- Generar PDF de un cierre → layout correcto A4, caracteres espanoles (tildes), watermark visible
- Generar PDF de horas → turnos manana/tarde correctos
- PDF se abre en browser y se puede descargar

---

## FASE 5: Calidad de Codigo y Arquitectura

**Skills**: `php-pro`, `database-design`

### 5.1 Extraer reglas de negocio a config (`app_config.php`)
```php
return [
    'commission_rate' => 0.05,
    'zones' => ['Zona 1', 'Zona 2', 'Zona 3', 'Zona 4a6'],
    'employees' => ['Alejandro', 'Emilia', 'Lourdes', 'Maxi'],
    'months' => ['01'=>'Enero', '02'=>'Febrero', ...],
    'days' => ['LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO'],
];
```

### 5.2 Extraer funciones reutilizables (`functions.php`)
- `requireAuth()` — reemplaza el if/redirect repetido en CADA archivo
- `requireAdmin()` — para `crear_usuario.php`
- `formatCurrency()` — mover de `db.php`
- `h()` — shortcut para `htmlspecialchars()`

### 5.3 Mejoras de base de datos
- Indice individual en `cierres_semanales.fecha_inicio` (queries de dashboard/reportes)
- Considerar tablas `zonas` y `empleados` para gestion dinamica desde la UI

### 5.4 Limpiar archivos innecesarios
- Eliminar 7 archivos `test_*.html` (son demos estaticos sin uso)
- Eliminar archivo `ssl` (ya en Fase 2)

### Verificacion Fase 5
- Cambiar `commission_rate` en config → reflejado en ver_cierre.php
- Todas las paginas siguen funcionando post-refactor
- No hay funciones/variables duplicadas

---

## Orden de Dependencias

```
FASE 1 (Reparar) ──obligatorio──> FASE 2 (Seguridad)
                                      │
                              ┌───────┴───────┐
                              v               v
                    FASE 3 (UI/UX)    FASE 4 (PDF)
                              │               │
                              └───────┬───────┘
                                      v
                              FASE 5 (Codigo)
```

Fases 3 y 4 pueden ejecutarse en paralelo despues de Fase 2.

## Archivos Criticos a Modificar
- `style.css` (Fases 1, 3)
- `header.php` (Fases 1, 3)
- `db.php` (Fases 2, 5)
- `main.js` (Fases 1, 3)
- `ver_cierre.php` (Fases 1, 2, 4)
- `cargar.php` (Fases 1, 2, 3)
- `historial.php` (Fases 1, 2, 3, 4)
- `index.php` (Fases 1, 2)
- `dashboard.php` (Fases 1, 2, 3)

## Archivos Nuevos a Crear
- `config.php` — credenciales DB (no se commitea)
- `app_config.php` — reglas de negocio
- `functions.php` — funciones compartidas
- `generar_pdf.php` — endpoint de generacion PDF
- `templates/pdf_cierre.php` — template PDF liquidacion
- `templates/pdf_horas.php` — template PDF horas
- `templates/pdf_reporte.php` — template PDF reporte mensual
- `.gitignore`
