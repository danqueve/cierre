# Sistema de Cierres y Cobranzas 💼🧾

Un portal web moderno, rápido y seguro diseñado para gestionar las rendiciones y liquidaciones financieras semanales. Optimizado meticulosamente con un enfoque en la velocidad de la contabilidad diaria y la facilidad de auditoría.

## 🚀 Características Principales

*   **⚡ Carga Ágil de Cierres**: Interfaz fluida y diseñada intuitivamente para registrar los ingresos diarios (de lunes a viernes) en fracciones de segundo.
*   **📊 Comisiones Dinámicas e Inteligentes**: Despídete de los "números fijos". Ahora es posible fijar una tasa de comisión personalizada (ej: 6.0%, 5.5% u 8%) a nivel de *cada* cierre particular. Los cruces de cálculos resuelven promedios y reportes basándose inteligentemente en el histórico del periodo.
*   **📝 Motor de Edición Histórica (Time-travel)**: Un poderoso sistema que permite tomar un reporte ya guardado del sistema, interactuar con él cargando su "estado guardado" en los formularios activos y aplicar recálculos o parches de datos por algún fallo de tipado antiguo.
*   **🖨️ Exportación Contable a PDF**: Transformación en un clic a "Vista de Hoja A4" puramente orientada a la limpieza visual y lista para impresión nativa sin elementos distractores (optimizada al máximo en B/N por defecto).
*   **📈 Dashboard de Analítica Activo**: Un centro de comandos gerencial que grafica KPIs de rendimientos como "Total Generado", "Acreditado a Cuentas vs Dinero Físico" y el histórico de actividad segmentado mes a mes o por zona geográfica.
*   **🛡️ Seguridad Estructural Sólida**: Autenticación basada en JWT / Sesiones seguras, con arquitectura Full-Guardia ante vulnerabilidades CSRF (Cross-Site Request Forgery) empleando validación por token dinámico transaccional.

---

## 🛠️ Stack Tecnológico Integrado

Este proyecto prescinde al máximo de dependencias ruidosas, acercándolo a los estándares *Zero-Overhead*:

-   **Frontend / UI**: HTML5 Semántico + **CSS Vainilla (Diseño Glassmorphism Premium)** apoyado por la iconografía fluida en Javascript de [Lucide Icons](https://lucide.dev/).
-   **Backend**: **PHP 8.2+** estructurado, puro y enfocado puramente al rendimiento lógico de los controladores.
-   **Base de Datos**: **MySQL 8** emparejado eficientemente con Data Objects (PDO), manejando inyecciones paramétricas por seguridad y transacciones de base de datos inter-relacionadas en dos bloques: Cabecera (Cierres) y Registros (Lineas Diarias).
-   **Exportación de Docs**: Motor CSS / Paginación del Navegador (`@media print`) – evita librerías lentas de rendering como TCPDF/Dompdf en favor del rasterizador acelerado por hardware de los *Chromium-based browsers*.

---

## ⚙️ Guía Rápida de Instalación (Entornos Locales WAMP/XAMPP)

1.  **Clonar el repositorio** dentro de tu directorio público de servidor (`www` si usas WampServer, o `htdocs` si usas Xampp).
    ```bash
    git clone git@github.com:danqueve/cierre.git
    cd cierre
    ```

2.  **Base de Datos**:
    *   Crea una base de datos MySQL local o remota.
    *   Importa el archivo principal para poblar la arquitectura estandarizada en tu BD.
    *   Renombra el archivo de configuración si es necesario y enlázalo en tu proyecto.
    *   Asegúrate de tener un usuario de pruebas en la tabla `usuarios`. (Las contraseñas siempre se almacenan hasheadas con `password_hash()`).

3.  **Ejecutar e Iniciar**:
    *   Ve a `http://localhost/cierre/login.php`.
    *   Inicia sesión con tus credenciales seguras.

---

## 🏗️ Estructura de Proyecto Rápida al Vistazo

```text
/
├── assets/                  # Recursos, logos de cabecera e imágenes base.
├── includes/                # Fragmentos globales (db.php para base de datos y config, etc).
├── style.css                # Único origen de la verdad (Maneja variables maestras de colores para branding rápido).
├── dashboard.php            # Visualizador Macro Principal de KPIs.
├── cargar.php               # Frontal central de inserciones semanales (Crear).
├── editar_cierre.php        # Inyector contextual para edición del pasado (Actualizar).
├── historial.php            # Datatable paginado visual con historial (Leer).
├── reportes_mensuales.php   # Generador masivo de agrupaciones inter-cláusulas cronológicas.
├── generar_pdf.php          # Vista estricta dedicada exclusivamente al engine de impresión (@media print).
└── login.php                # Gateway de ingreso y verificación con Tokens CSRF de seguridad.
```

## 🔐 Licencia y Privacidad
El manejo de los reportes es completamente de uso administrativo, contable e interno y el código opera procesando números delicados; mantenga los privilegios de servidor SSH o permisos de repositorios cuidadosamente guardados para proteger la salud de la data y evitar sobrescrituras con los `.git`.
