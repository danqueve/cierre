<?php
require 'db.php';

// Seguridad: Verificar si está logueado y si es ADMIN
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $rol = $_POST['rol'];

    // Validar que no exista el usuario
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->fetch()) {
        $mensaje = "<span class='text-red'>El usuario '$username' ya existe.</span>";
    } else {
        // Encriptar contraseña y guardar
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmtInsert = $pdo->prepare("INSERT INTO usuarios (username, password, rol) VALUES (?, ?, ?)");
        if ($stmtInsert->execute([$username, $hashed_password, $rol])) {
            $mensaje = "<span class='text-green'>Usuario creado exitosamente.</span>";
        } else {
            $mensaje = "<span class='text-red'>Error al crear usuario.</span>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Usuario</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="navbar">
        <div class="brand">Gestión de Usuarios</div>
        <div class="nav-links">
            <a href="dashboard.php">Volver al Inicio</a>
            <a href="logout.php" style="color: var(--accent-red);">Salir</a>
        </div>
    </nav>

    <div class="container" style="max-width: 500px;">
        <div class="card">
            <div class="card-header">Registrar Nuevo Usuario</div>
            
            <?php if($mensaje): ?>
                <div style="margin-bottom: 15px; text-align: center; font-weight: bold;">
                    <?= $mensaje ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div style="margin-bottom: 15px;">
                    <label style="color: var(--text-muted);">Nombre de Usuario</label>
                    <input type="text" name="username" required placeholder="ej. supervisor_zona1">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="color: var(--text-muted);">Contraseña</label>
                    <input type="password" name="password" required placeholder="Mínimo 4 caracteres">
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="color: var(--text-muted);">Rol / Permisos</label>
                    <select name="rol" required>
                        <option value="supervisor">Supervisor (Solo carga datos)</option>
                        <option value="admin">Administrador (Acceso total)</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Crear Usuario</button>
            </form>
        </div>

        <div class="card" style="margin-top: 20px;">
            <div class="card-header">Usuarios Existentes</div>
            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Rol</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $users = $pdo->query("SELECT username, rol FROM usuarios ORDER BY username ASC")->fetchAll();
                    foreach($users as $u):
                    ?>
                    <tr>
                        <td style="text-align: left;"><?= htmlspecialchars($u['username']) ?></td>
                        <td style="text-align: left;">
                            <?php if($u['rol'] === 'admin'): ?>
                                <span style="color: var(--accent-purple);">Admin</span>
                            <?php else: ?>
                                <span style="color: var(--accent-green);">Supervisor</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>