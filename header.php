<?php
// Detectar el nombre del archivo actual para marcar la pestaña activa
$pagina_actual = basename($_SERVER['PHP_SELF']);
?>
<header class="main-header">
    <div class="header-glass">
        <!-- Logo / Marca -->
        <div class="header-brand">
            <div class="brand-logo">
                <img src="img/logo.png" alt="Logo Imperio Cierres" style="width:32px; height:32px; object-fit:contain; border-radius:6px;">
            </div>
            <span class="brand-text">Imperio<span class="brand-dot">.</span></span>
        </div>

        <!-- Menú Central (escritorio) -->
        <nav class="header-nav" id="headerNav">
            <a href="dashboard.php" class="nav-link <?= $pagina_actual === 'dashboard.php' ? 'active' : '' ?>">
                Dashboard
            </a>
            
            <!-- Grupo Cobranzas -->
            <a href="cargar.php" class="nav-link <?= $pagina_actual === 'cargar.php' ? 'active' : '' ?>">
                Cargar
            </a>
            <a href="cargar_horas.php" class="nav-link <?= $pagina_actual === 'cargar_horas.php' ? 'active' : '' ?>">
                Jornales
            </a>

            <!-- Historial -->
            <a href="historial.php" class="nav-link <?= $pagina_actual === 'historial.php' ? 'active' : '' ?>">
                Historial
            </a>
            <a href="historial_horas.php" class="nav-link <?= in_array($pagina_actual, ['historial_horas.php', 'liquidar_horas.php']) ? 'active' : '' ?>">
                Hist. Horas
            </a>

            <a href="reportes_mensuales.php" class="nav-link <?= $pagina_actual === 'reportes_mensuales.php' ? 'active' : '' ?>">
                Reportes
            </a>
            
            <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="crear_usuario.php" class="nav-link <?= $pagina_actual === 'crear_usuario.php' ? 'active' : '' ?>">
                    Usuarios
                </a>
            <?php endif; ?>
        </nav>

        <!-- Usuario / Salir (escritorio) -->
        <div class="header-user">
            <div class="user-info">
                <span class="user-name"><?= $_SESSION['username'] ?? 'User' ?></span>
                <span class="user-role"><?= $_SESSION['role'] ?? 'Guest' ?></span>
            </div>
            <a href="logout.php" class="btn-logout" title="Cerrar Sesión">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            </a>
        </div>

        <!-- Botón Hamburguesa (solo mobile) -->
        <button class="hamburger-btn" id="hamburgerBtn" aria-label="Abrir menú" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>
</header>

<!-- Overlay Mobile Nav -->
<div class="mobile-nav-overlay" id="mobileNavOverlay" aria-hidden="true">
    <div class="mobile-nav-panel">
        <!-- Cabecera del drawer -->
        <div class="mobile-nav-header">
            <div class="header-brand">
                <div class="brand-logo">
                    <img src="img/logo.png" alt="Logo Imperio Cierres" style="width:32px; height:32px; object-fit:contain; border-radius:6px;">
                </div>
                <span class="brand-text">Imperio<span class="brand-dot">.</span></span>
            </div>
            <button class="mobile-nav-close" id="mobileNavClose" aria-label="Cerrar menú">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <!-- Usuario en mobile -->
        <div class="mobile-nav-user">
            <div class="mobile-user-avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?></div>
            <div>
                <div class="mobile-user-name"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></div>
                <div class="mobile-user-role"><?= htmlspecialchars($_SESSION['role'] ?? 'Guest') ?></div>
            </div>
        </div>

        <!-- Links de navegación mobile -->
        <nav class="mobile-nav-links">
            <a href="dashboard.php" class="mobile-nav-link <?= $pagina_actual === 'dashboard.php' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                Dashboard
            </a>
            <a href="cargar.php" class="mobile-nav-link <?= $pagina_actual === 'cargar.php' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Cargar
            </a>
            <a href="cargar_horas.php" class="mobile-nav-link <?= $pagina_actual === 'cargar_horas.php' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                Jornales
            </a>
            <a href="historial.php" class="mobile-nav-link <?= $pagina_actual === 'historial.php' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                Historial
            </a>
            <a href="historial_horas.php" class="mobile-nav-link <?= in_array($pagina_actual, ['historial_horas.php', 'liquidar_horas.php']) ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><circle cx="10" cy="13" r="2"></circle><line x1="10" y1="11" x2="10" y2="10"></line></svg>
                Hist. Horas
            </a>
            <a href="reportes_mensuales.php" class="mobile-nav-link <?= $pagina_actual === 'reportes_mensuales.php' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                Reportes
            </a>
            <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="crear_usuario.php" class="mobile-nav-link <?= $pagina_actual === 'crear_usuario.php' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                Usuarios
            </a>
            <?php endif; ?>
        </nav>

        <div class="mobile-nav-footer">
            <a href="logout.php" class="mobile-logout-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                Cerrar Sesión
            </a>
        </div>
    </div>
</div>

<script>
(function() {
    // Favicon dinámico
    var favicon = document.createElement('link');
    favicon.rel = 'icon';
    favicon.type = 'image/png';
    favicon.href = 'img/logo.png';
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