<?php
require __DIR__ . '/includes/db.php';

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    $authenticated = false;
    if ($user) {
        // Soporte bcrypt (password_verify)
        if (password_verify($password, $user['password'])) {
            $authenticated = true;
        }
        // Auto-migración de hash MD5 legacy a bcrypt
        elseif (strlen($user['password']) === 32 && md5($password) === $user['password']) {
            $authenticated = true;
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?")->execute([$newHash, $user['id']]);
        }
    }

    if ($authenticated) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['rol'];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Credenciales incorrectas";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Imperio Cierres</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link rel="stylesheet" href="assets/css/organic.css">
    <link rel="stylesheet" href="assets/css/imperio.css">
    <script src="assets/js/main.js" defer></script>
</head>
<body>
    <main class="imp-login">
        <div class="imp-login-card">
            <div class="imp-brand-logo imp-login-logo"><img src="assets/img/logo.png" alt="Imperio"></div>
            <div class="imp-login-head">
                <span class="imp-kicker">Sistema de gestión</span>
                <h1>Bienvenido<span class="dot">.</span></h1>
                <p>Ingresá con tu usuario para cargar y revisar los cierres.</p>
            </div>

            <div id="toast-container" class="toast-container"></div>

            <?php if($error): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        showToast("<?= addslashes($error) ?>", 'error');
                    });
                </script>
            <?php endif; ?>

            <form method="POST" class="imp-login-form">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="username">Usuario</label>
                    <input type="text" id="username" name="username" class="input on-light" placeholder="Ingresá tu usuario" required autocomplete="off">
                </div>
                <div class="field">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" class="input on-light" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary imp-login-btn">Iniciar sesión</button>
            </form>

            <div class="imp-login-foot">&copy; <?= date('Y') ?> Gestión Imperio</div>
        </div>
    </main>
</body>
</html>
