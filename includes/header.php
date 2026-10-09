<?php
// Detectar el nombre del archivo actual para marcar la pestaña activa
$pagina_actual = basename($_SERVER['PHP_SELF']);
$es_admin  = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$nombre_u  = $_SESSION['username'] ?? 'User';
$rol_u     = $_SESSION['role'] ?? 'Guest';
$inicial_u = strtoupper(mb_substr($nombre_u, 0, 1));

$menu = [
    ['dashboard.php',         'Dashboard',  ['dashboard.php'],                              '<rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect>'],
    ['cargar.php',            'Cargar',     ['cargar.php'],                                 '<line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line>'],
    ['cargar_horas.php',      'Jornales',   ['cargar_horas.php'],                           '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>'],
    ['historial.php',         'Historial',  ['historial.php'],                              '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line>'],
    ['historial_horas.php',   'Hist. Horas', ['historial_horas.php', 'liquidar_horas.php'],  '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><circle cx="10" cy="13" r="2"></circle>'],
    ['reportes_mensuales.php','Reportes',   ['reportes_mensuales.php'],                     '<line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line>'],
];
if ($es_admin) {
    $menu[] = ['crear_usuario.php', 'Usuarios', ['crear_usuario.php'], '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>'];
}
$svg_logout = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>';
?>
<header class="imp-header no-print">
    <!-- Logo / Marca -->
    <a href="dashboard.php" class="imp-brand">
        <span class="imp-brand-logo"><img src="assets/img/logo.png" alt="Logo Imperio Cierres"></span>
        <span class="imp-brand-text">Imperio<span>.</span></span>
    </a>

    <!-- Menú (escritorio) -->
    <nav class="imp-nav" id="headerNav">
        <?php foreach ($menu as [$href, $label, $activos]): ?>
            <a href="<?= $href ?>" class="<?= in_array($pagina_actual, $activos) ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </nav>

    <!-- Usuario / Salir (escritorio) -->
    <div class="imp-user">
        <div class="imp-user-info">
            <span class="imp-user-name"><?= htmlspecialchars($nombre_u) ?></span>
            <span class="imp-user-role"><?= htmlspecialchars($rol_u) ?></span>
        </div>
        <div class="imp-avatar"><?= htmlspecialchars($inicial_u) ?></div>
        <a href="logout.php" class="imp-logout" title="Cerrar Sesión"><?= $svg_logout ?></a>
    </div>

    <!-- Botón Hamburguesa (solo mobile) -->
    <button class="imp-hamburger" id="hamburgerBtn" aria-label="Abrir menú" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>
</header>

<!-- Overlay Mobile Nav -->
<div class="imp-drawer no-print" id="mobileNavOverlay" aria-hidden="true">
    <div class="imp-drawer-panel">
        <div class="imp-drawer-head">
            <div class="imp-brand">
                <span class="imp-brand-logo"><img src="assets/img/logo.png" alt="Logo Imperio Cierres"></span>
                <span class="imp-brand-text">Imperio<span>.</span></span>
            </div>
            <button class="imp-drawer-close" id="mobileNavClose" aria-label="Cerrar menú">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <div class="imp-drawer-user">
            <div class="imp-avatar"><?= htmlspecialchars($inicial_u) ?></div>
            <div class="imp-user-info" style="align-items:flex-start;">
                <span class="imp-user-name"><?= htmlspecialchars($nombre_u) ?></span>
                <span class="imp-user-role"><?= htmlspecialchars($rol_u) ?></span>
            </div>
        </div>

        <nav class="imp-drawer-links">
            <?php foreach ($menu as [$href, $label, $activos, $icono]): ?>
                <a href="<?= $href ?>" class="<?= in_array($pagina_actual, $activos) ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round"><?= $icono ?></svg>
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="imp-drawer-foot">
            <a href="logout.php" class="btn btn-secondary" style="width:100%; min-height:44px;"><?= $svg_logout ?> Cerrar Sesión</a>
        </div>
    </div>
</div>

<script>
(function() {
    // Favicon dinámico
    var favicon = document.createElement('link');
    favicon.rel = 'icon';
    favicon.type = 'image/png';
    favicon.href = 'assets/img/logo.png';
    document.head.appendChild(favicon);
})();

(function() {
    var btn     = document.getElementById('hamburgerBtn');
    var overlay = document.getElementById('mobileNavOverlay');
    var close   = document.getElementById('mobileNavClose');

    function openMenu() {
        overlay.classList.add('active');
        btn.classList.add('open');
        btn.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }
    function closeMenu() {
        overlay.classList.remove('active');
        btn.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    btn.addEventListener('click', openMenu);
    close.addEventListener('click', closeMenu);
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeMenu();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeMenu();
    });
})();
</script>
