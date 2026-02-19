<?php
// Detectar el nombre del archivo actual para marcar la pestaña activa
$pagina_actual = basename($_SERVER['PHP_SELF']);
?>
<header class="main-header">
    <div class="header-glass">
        <!-- Logo / Marca -->
        <div class="header-brand">
            <div class="brand-logo">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            </div>
            <span class="brand-text">Cobranzas<span class="brand-dot">.</span></span>
        </div>

        <!-- Menú Central -->
        <nav class="header-nav">
            <a href="dashboard.php" class="nav-link <?= $pagina_actual === 'dashboard.php' ? 'active' : '' ?>">
                Dashboard
            </a>
            
            <!-- Grupo Cobranzas -->
            <a href="cargar.php" class="nav-link <?= $pagina_actual === 'cargar.php' ? 'active' : '' ?>">
                Cargar
            </a>
            
            <!-- Grupo Horarios -->
            <a href="cargar_horas.php" class="nav-link <?= $pagina_actual === 'cargar_horas.php' ? 'active' : '' ?>">
                Horas
            </a>
            
            <!-- Grupo Historiales (Dropdown visual simplificado) -->
            <div style="display: flex; gap: 5px; border-left: 1px solid rgba(255,255,255,0.1); padding-left: 10px; margin-left: 5px;">
                <a href="historial.php" class="nav-link <?= $pagina_actual === 'historial.php' ? 'active' : '' ?>" title="Historial Cobranzas">
                    H. Cobros
                </a>
                <a href="historial_horas.php" class="nav-link <?= $pagina_actual === 'historial_horas.php' ? 'active' : '' ?>" title="Historial Horas">
                    H. Horas
                </a>
            </div>

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
                <span class="user-name"><?= $_SESSION['username'] ?? 'User' ?></span>
                <span class="user-role"><?= $_SESSION['role'] ?? 'Guest' ?></span>
            </div>
            <a href="logout.php" class="btn-logout" title="Cerrar Sesión">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            </a>
        </div>
    </div>
</header>