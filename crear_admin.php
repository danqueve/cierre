<?php
require 'db.php';

// ESTE ARCHIVO ES DE USO UNICO PARA CREAR EL PRIMER ADMIN
// NO REQUIERE ESTAR LOGUEADO. BORRAR DESPUES DE USAR.

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validaciones básicas
    if (empty($username) || empty($password)) {
        $mensaje = "<span class='text-red'>Por favor complete todos los campos.</span>";
    } elseif ($password !== $confirm_password) {
        $mensaje = "<span class='text-red'>Las contraseñas no coinciden.</span>";
    } else {
        // Verificar si el usuario ya existe
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetch()) {
            $mensaje = "<span class='text-red'>El usuario '$username' ya existe. Prueba con otro.</span>";
        } else {
            // Crear el Administrador
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $rol = 'admin'; // Forzamos que sea admin
            
            try {
                $stmtInsert = $pdo->prepare("INSERT INTO usuarios (username, password, rol) VALUES (?, ?, ?)");
                if ($stmtInsert->execute([$username, $hashed_password, $rol])) {
                    $mensaje = "<div style='text-align:center;'>
                                    <h3 class='text-green'>¡Usuario Administrador Creado!</h3>
                                    <p>Usuario: <strong>$username</strong></p>
                                    <a href='index.php' class='btn btn-primary'>Ir al Login ahora</a>
                                </div>";
                } else {
                    $mensaje = "<span class='text-red'>Error al intentar guardar en la base de datos.</span>";
                }
            } catch (Exception $e) {
                $mensaje = "<span class='text-red'>Error SQL: " . $e->getMessage() . "</span>";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Setup Inicial - Crear Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <div class="card" style="width: 400px; border-color: var(--accent-yellow);">
            <div style="text-align: center; margin-bottom: 20px;">
                <h2 style="color: var(--accent-yellow);">Configuración Inicial</h2>
                <p style="color: var(--text-muted); font-size: 0.9em;">Crea tu usuario Administrador Maestro</p>
            </div>
            
            <?php if($mensaje): ?>
                <div style="margin-bottom: 20px; text-align: center;">
                    <?= $mensaje ?>
                </div>
            <?php endif; ?>

            <!-- Si el mensaje contiene el botón de éxito, ocultamos el formulario -->
            <?php if (strpos($mensaje, 'Ir al Login') === false): ?>
            <form method="POST">
                <div style="margin-bottom: 15px;">
                    <label style="color: var(--accent-blue);">Nuevo Usuario Admin</label>
                    <input type="text" name="username" required placeholder="Ej. miusuario" autocomplete="off">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="color: var(--text-muted);">Contraseña</label>
                    <input type="password" name="password" required placeholder="Crea una contraseña segura">
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="color: var(--text-muted);">Repetir Contraseña</label>
                    <input type="password" name="confirm_password" required placeholder="Repite la contraseña">
                </div>

                <button type="submit" class="btn" style="width: 100%; background-color: var(--accent-yellow); color: #1e1e1e;">Registrar y Entrar</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>