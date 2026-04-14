<?php
// Detectar el nombre del archivo actual para marcar la pestaña activa
$pagina_actual = basename($_SERVER['PHP_SELF']);
?>
<a href="#main-content" class="skip-link">Saltar al contenido</a>
<header class="main-header">
    <div class="header-glass">
        <!-- Logo / Marca -->
        <div class="header-brand">
            <div class="brand-logo">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            </div>
            <span class="brand-text">Cobranzas<span class="brand-dot">.</span></span>
        </div>

        <!-- Menú Central (desktop) -->
        <nav class="header-nav" id="mainNav">
            <a href="dashboard.php" class="nav-link <?= $pagina_actual === 'dashboard.php' ? 'active' : '' ?>">
                Dashboard
            </a>
            <a href="cargar.php" class="nav-link <?= $pagina_actual === 'cargar.php' ? 'active' : '' ?>">
                Cargar
            </a>

            <a href="historial.php" class="nav-link <?= $pagina_actual === 'historial.php' ? 'active' : '' ?>">
                Historial
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

        <!-- Usuario / Salir -->
        <div class="header-user">
            <div class="user-info">
                <span class="user-name"><?= h($_SESSION['username'] ?? 'User') ?></span>
                <span class="user-role"><?= h($_SESSION['role'] ?? 'Guest') ?></span>
            </div>
            <a href="logout.php" class="btn-logout" aria-label="Cerrar Sesión" title="Cerrar Sesión">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            </a>
        </div>

        <!-- Botón hamburguesa (solo visible en mobile) -->
        <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Abrir menú" aria-expanded="false">
            <svg class="icon-menu" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            <svg class="icon-close" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
    </div>

    <!-- Menú mobile (drawer) -->
    <div class="mobile-nav" id="mobileNav" aria-hidden="true">
        <nav>
            <a href="dashboard.php" class="mobile-nav-link <?= $pagina_actual === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
            <a href="cargar.php" class="mobile-nav-link <?= $pagina_actual === 'cargar.php' ? 'active' : '' ?>">Cargar Cobranza</a>
            <a href="historial.php" class="mobile-nav-link <?= $pagina_actual === 'historial.php' ? 'active' : '' ?>">Historial Cobranzas</a>
            <a href="reportes_mensuales.php" class="mobile-nav-link <?= $pagina_actual === 'reportes_mensuales.php' ? 'active' : '' ?>">Reportes</a>
            <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="crear_usuario.php" class="mobile-nav-link <?= $pagina_actual === 'crear_usuario.php' ? 'active' : '' ?>">Usuarios</a>
            <?php endif; ?>
            <a href="logout.php" class="mobile-nav-link" style="color: var(--accent-red); margin-top: 10px; border-top: 1px solid var(--border-color); padding-top: 10px;">Cerrar Sesión</a>
        </nav>
    </div>
</header>

<script>
(function() {
    const toggle = document.getElementById('mobileMenuToggle');
    const nav    = document.getElementById('mobileNav');
    if (!toggle || !nav) return;
    toggle.addEventListener('click', function() {
        const isOpen = nav.classList.toggle('open');
        toggle.setAttribute('aria-expanded', isOpen);
        nav.setAttribute('aria-hidden', !isOpen);
        document.body.classList.toggle('mobile-nav-open', isOpen);
    });
    // Cierra el menú al hacer clic en un link
    nav.querySelectorAll('.mobile-nav-link').forEach(function(link) {
        link.addEventListener('click', function() {
            nav.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
            nav.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('mobile-nav-open');
        });
    });
})();
</script>
