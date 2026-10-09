<?php
require __DIR__ . '/includes/db.php';

// Seguridad: Solo admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}

$mensaje = '';
$msg_type = 'success';

// ═══════════════════════════════════════════════════════
//  PROCESAR ACCIONES POST
// ═══════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'create';

    // --- CREAR USUARIO ---
    if ($action === 'create') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $rol = $_POST['rol'];

        if (strlen($password) < 4) {
            $mensaje = "La contraseña debe tener al menos 4 caracteres.";
            $msg_type = 'error';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $mensaje = "El usuario '" . e($username) . "' ya existe.";
                $msg_type = 'error';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO usuarios (username, password, rol) VALUES (?, ?, ?)")
                    ->execute([$username, $hashed, $rol]);
                $mensaje = "Usuario '" . e($username) . "' creado exitosamente.";
            }
        }
    }

    // --- CAMBIAR ROL ---
    if ($action === 'change_role') {
        $uid = (int)$_POST['user_id'];
        $newRole = $_POST['new_role'];
        // No permitir cambiar el rol del usuario logueado
        if ($uid === (int)$_SESSION['user_id']) {
            $mensaje = "No podés cambiar tu propio rol.";
            $msg_type = 'error';
        } else {
            $pdo->prepare("UPDATE usuarios SET rol = ? WHERE id = ?")->execute([$newRole, $uid]);
            $mensaje = "Rol actualizado correctamente.";
        }
    }

    // --- CAMBIAR CONTRASEÑA ---
    if ($action === 'change_password') {
        $uid = (int)$_POST['user_id'];
        $newPass = $_POST['new_password'];
        if (strlen($newPass) < 4) {
            $mensaje = "La contraseña debe tener al menos 4 caracteres.";
            $msg_type = 'error';
        } else {
            $hashed = password_hash($newPass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?")->execute([$hashed, $uid]);
            $mensaje = "Contraseña actualizada correctamente.";
        }
    }

    // --- ELIMINAR USUARIO ---
    if ($action === 'delete') {
        $uid = (int)$_POST['user_id'];
        if ($uid === (int)$_SESSION['user_id']) {
            $mensaje = "No podés eliminar tu propia cuenta.";
            $msg_type = 'error';
        } else {
            $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$uid]);
            $mensaje = "Usuario eliminado.";
        }
    }
}

// Obtener usuarios
$users = $pdo->query("SELECT id, username, rol FROM usuarios ORDER BY username ASC")->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - SGO</title>
    <link rel="stylesheet" href="assets/css/organic.css">
    <link rel="stylesheet" href="assets/css/imperio.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="assets/js/main.js" defer></script>
</head>
<body>

    <?php include __DIR__ . '/includes/header.php'; ?>

    <main class="imp-main">
        <div id="toast-container" class="toast-container"></div>

        <?php if($mensaje): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showToast("<?= addslashes($mensaje) ?>", '<?= $msg_type ?>');
                });
            </script>
        <?php endif; ?>

        <div class="imp-page">
            <!-- Encabezado -->
            <div class="imp-page-head">
                <span class="imp-kicker">Administración</span>
                <h1>Gestión de usuarios</h1>
                <p>Crear, editar y administrar cuentas del sistema.</p>
            </div>

            <!-- Formulario de creación -->
            <form method="POST" class="imp-section">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <span class="imp-label">Nuevo usuario</span>
                <div class="imp-row">
                    <div class="field" style="flex:1 1 220px;">
                        <label for="username">Nombre de usuario</label>
                        <input type="text" id="username" name="username" required placeholder="ej. supervisor_zona1" class="input on-light">
                    </div>
                    <div class="field" style="flex:1 1 220px;">
                        <label for="password">Contraseña</label>
                        <input type="password" id="password" name="password" required placeholder="Mínimo 4 caracteres" class="input on-light" minlength="4">
                    </div>
                    <div class="field" style="flex:1 1 180px;">
                        <label for="rol">Rol</label>
                        <select name="rol" id="rol" required class="input on-light">
                            <option value="supervisor">Supervisor</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="min-height:44px; color:var(--color-text);">
                        <i data-lucide="user-plus" style="width:16px; height:16px;"></i> Crear
                    </button>
                </div>
            </form>

            <!-- Usuarios existentes -->
            <div class="imp-h3" style="margin-top:0;">
                <h3>Usuarios</h3><small><?= count($users) ?> registrados</small>
            </div>

            <div class="imp-users">
                <?php foreach($users as $u):
                    $isSelf = ($u['id'] === (int)$_SESSION['user_id']);
                    $isAdmin = ($u['rol'] === 'admin');
                ?>
                <div class="imp-user-card">
                    <div class="imp-user-top">
                        <div class="imp-avatar imp-avatar-lg <?= $isAdmin ? '' : 'accent' ?>"><?= e(strtoupper(substr($u['username'], 0, 2))) ?></div>
                        <div class="imp-user-id">
                            <strong><?= e($u['username']) ?></strong>
                            <span class="tag <?= $isAdmin ? 'tag-accent' : 'tag-accent-2' ?>"><?= $isAdmin ? 'Admin' : 'Supervisor' ?></span>
                        </div>
                        <?php if($isSelf): ?><span class="tag tag-neutral">Tú</span><?php endif; ?>
                    </div>

                    <div class="imp-user-actions">
                        <!-- Cambiar rol -->
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="change_role">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="new_role" value="<?= $isAdmin ? 'supervisor' : 'admin' ?>">
                            <button type="submit" class="btn btn-secondary imp-mini" <?= $isSelf ? 'disabled title="No podés cambiar tu propio rol"' : '' ?>>
                                <i data-lucide="repeat" style="width:14px; height:14px;"></i>
                                <?= $isAdmin ? 'Hacer supervisor' : 'Hacer admin' ?>
                            </button>
                        </form>

                        <!-- Cambiar contraseña -->
                        <button type="button" class="btn btn-secondary imp-mini" onclick="togglePassModal(<?= $u['id'] ?>)">
                            <i data-lucide="key" style="width:14px; height:14px;"></i> Contraseña
                        </button>

                        <!-- Eliminar -->
                        <?php if(!$isSelf): ?>
                        <form method="POST" onsubmit="return confirm('¿Eliminar usuario <?= e($u['username']) ?>?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn imp-mini imp-mini-danger" title="Eliminar usuario">
                                <i data-lucide="trash-2" style="width:14px; height:14px;"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>

                    <!-- Cambiar contraseña (inline) -->
                    <form method="POST" class="imp-passform" id="passModal_<?= $u['id'] ?>" hidden>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="change_password">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <input type="password" name="new_password" class="input on-light" placeholder="Nueva contraseña" minlength="4" required>
                        <button type="submit" class="btn btn-primary imp-mini" style="color:var(--color-text);">
                            <i data-lucide="check" style="width:14px; height:14px;"></i> Guardar
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <script>
        window.addEventListener('load', function() { lucide.createIcons(); });

        function togglePassModal(uid) {
            const modal = document.getElementById('passModal_' + uid);
            modal.hidden = !modal.hidden;
            if (!modal.hidden) {
                modal.querySelector('input[type=password]').focus();
            }
        }
    </script>
</body>
</html>
