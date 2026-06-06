<?php
require 'db.php';

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
    <link rel="stylesheet" href="style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="main.js" defer></script>
    <style>
        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
            margin-top: 1.5rem;
        }
        .user-card {
            background: rgba(255,255,255,0.025);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 16px;
            padding: 1.2rem 1.4rem;
            transition: all 0.3s ease;
        }
        .user-card:hover {
            border-color: rgba(187,154,247,0.3);
            background: rgba(255,255,255,0.04);
        }
        .user-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        .user-avatar {
            width: 44px; height: 44px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800;
            font-size: 1rem;
            text-transform: uppercase;
        }
        .user-avatar-admin {
            background: linear-gradient(135deg, rgba(187,154,247,0.2), rgba(122,162,247,0.2));
            color: var(--accent-purple);
            border: 1px solid rgba(187,154,247,0.3);
        }
        .user-avatar-supervisor {
            background: linear-gradient(135deg, rgba(158,206,106,0.2), rgba(122,162,247,0.1));
            color: var(--accent-green);
            border: 1px solid rgba(158,206,106,0.3);
        }
        .user-info h4 { margin: 0; font-size: 1rem; font-weight: 700; }
        .user-info .role-tag {
            display: inline-block;
            font-size: 0.72rem;
            padding: 3px 10px;
            border-radius: 99px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-top: 4px;
        }
        .role-admin { background: rgba(187,154,247,0.12); color: var(--accent-purple); }
        .role-supervisor { background: rgba(158,206,106,0.12); color: var(--accent-green); }

        .user-actions {
            display: flex;
            gap: 6px;
            margin-top: 0.8rem;
            padding-top: 0.8rem;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        .user-actions form { display: inline; }
        .action-mini {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.04);
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
        }
        .action-mini:hover { background: rgba(255,255,255,0.1); color: var(--text-main); }
        .action-mini svg { width: 12px; height: 12px; }
        .action-del { border-color: rgba(247,118,142,0.2); color: var(--accent-red); }
        .action-del:hover { background: rgba(247,118,142,0.15); }
        .self-badge {
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 99px;
            background: rgba(122,162,247,0.12);
            color: var(--accent-blue);
            font-weight: 700;
        }

        /* Page header */
        .page-header {
            display: flex; align-items: center; gap: 16px; margin-bottom: 1.8rem;
        }
        .page-header-icon {
            width: 52px; height: 52px; border-radius: 16px;
            background: linear-gradient(135deg, rgba(187,154,247,0.2), rgba(122,162,247,0.2));
            border: 1px solid rgba(187,154,247,0.3);
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .page-header-icon svg { width: 24px; height: 24px; color: var(--accent-purple); }
        .page-header h2 { margin: 0; font-size: 1.4rem; font-weight: 800; }
        .page-header p { margin: 3px 0 0; font-size: 0.85rem; color: var(--text-muted); }

        /* Create form */
        .create-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            align-items: end;
        }

        /* Modal inline */
        .inline-modal {
            display: none;
            background: rgba(0,0,0,0.4);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px;
            padding: 1rem 1.2rem;
            margin-top: 0.6rem;
        }
        .inline-modal.show { display: block; }
        .inline-modal input {
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.05);
            color: white;
            font-size: 0.88rem;
            width: 100%;
            margin-bottom: 8px;
        }
    </style>
</head>
<body class="fade-in">

    <?php include 'header.php'; ?>

    <div class="container">
        <div id="toast-container" class="toast-container"></div>

        <?php if($mensaje): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showToast("<?= addslashes($mensaje) ?>", '<?= $msg_type ?>');
                });
            </script>
        <?php endif; ?>

        <div class="card">
            <!-- Encabezado -->
            <div class="page-header">
                <div class="page-header-icon">
                    <i data-lucide="users"></i>
                </div>
                <div>
                    <h2>Gestión de Usuarios</h2>
                    <p>Crear, editar y administrar cuentas del sistema</p>
                </div>
            </div>

            <!-- Formulario de Creación -->
            <form method="POST" style="margin-bottom: 1.5rem;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="create-form-grid">
                    <div class="input-group">
                        <label>Nombre de Usuario</label>
                        <i data-lucide="user" class="input-icon"></i>
                        <input type="text" name="username" required placeholder="ej. supervisor_zona1" class="input-with-icon">
                    </div>
                    <div class="input-group">
                        <label>Contraseña</label>
                        <i data-lucide="lock" class="input-icon"></i>
                        <input type="password" name="password" required placeholder="Mínimo 4 caracteres" class="input-with-icon" minlength="4">
                    </div>
                    <div class="input-group">
                        <label>Rol</label>
                        <i data-lucide="shield" class="input-icon"></i>
                        <select name="rol" required class="input-with-icon">
                            <option value="supervisor">Supervisor</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary" style="width:100%; display:flex; align-items:center; justify-content:center; gap:8px; padding:12px;">
                            <i data-lucide="user-plus" style="width:16px; height:16px;"></i> Crear
                        </button>
                    </div>
                </div>
            </form>

            <!-- Usuarios Existentes -->
            <div style="margin-top:0.5rem;">
                <div style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; font-weight:700; margin-bottom:1rem;">
                    <?= count($users) ?> usuarios registrados
                </div>

                <div class="user-grid">
                    <?php foreach($users as $u): 
                        $isSelf = ($u['id'] === (int)$_SESSION['user_id']);
                        $isAdmin = ($u['rol'] === 'admin');
                    ?>
                    <div class="user-card">
                        <div class="user-top">
                            <div style="display:flex; align-items:center; gap:12px;">
                                <div class="user-avatar <?= $isAdmin ? 'user-avatar-admin' : 'user-avatar-supervisor' ?>">
                                    <?= strtoupper(substr($u['username'], 0, 2)) ?>
                                </div>
                                <div class="user-info">
                                    <h4><?= e($u['username']) ?></h4>
                                    <span class="role-tag <?= $isAdmin ? 'role-admin' : 'role-supervisor' ?>">
                                        <?= $isAdmin ? 'Admin' : 'Supervisor' ?>
                                    </span>
                                </div>
                            </div>
                            <?php if($isSelf): ?>
                                <span class="self-badge">Tú</span>
                            <?php endif; ?>
                        </div>

                        <div class="user-actions">
                            <!-- Cambiar Rol -->
                            <form method="POST" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="change_role">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="new_role" value="<?= $isAdmin ? 'supervisor' : 'admin' ?>">
                                <button type="submit" class="action-mini" <?= $isSelf ? 'disabled title="No podés cambiar tu propio rol"' : '' ?>>
                                    <i data-lucide="repeat"></i>
                                    <?= $isAdmin ? 'Hacer Supervisor' : 'Hacer Admin' ?>
                                </button>
                            </form>

                            <!-- Cambiar Contraseña -->
                            <button class="action-mini" onclick="togglePassModal(<?= $u['id'] ?>)">
                                <i data-lucide="key"></i> Contraseña
                            </button>

                            <!-- Eliminar -->
                            <?php if(!$isSelf): ?>
                            <form method="POST" onsubmit="return confirm('¿Eliminar usuario <?= e($u['username']) ?>?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="action-mini action-del">
                                    <i data-lucide="trash-2"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>

                        <!-- Modal Cambiar Contraseña (inline) -->
                        <div class="inline-modal" id="passModal_<?= $u['id'] ?>">
                            <form method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="change_password">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="password" name="new_password" placeholder="Nueva contraseña" minlength="4" required>
                                <button type="submit" class="action-mini" style="width:100%; justify-content:center;">
                                    <i data-lucide="check"></i> Guardar
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>

    <script>
        window.onload = function() { lucide.createIcons(); }

        function togglePassModal(uid) {
            const modal = document.getElementById('passModal_' + uid);
            modal.classList.toggle('show');
            if (modal.classList.contains('show')) {
                modal.querySelector('input[type=password]').focus();
            }
        }
    </script>
</body>
</html>