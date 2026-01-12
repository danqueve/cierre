<?php
require 'db.php';

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
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
    <title>Login - Cierres Semanales</title>
    <!-- Usamos la misma fuente del sistema -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- ESTILOS EXCLUSIVOS PARA LOGIN MODERNO --- */
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background-color: #0f0f13;
            color: #ffffff;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* Orbes de luz de fondo (Efecto Aurora) */
        .background-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 1;
            opacity: 0.6;
        }
        .orb-1 {
            top: -10%;
            left: -10%;
            width: 50vw;
            height: 50vw;
            background: radial-gradient(circle, #7c3aed 0%, transparent 70%);
            animation: float 10s infinite alternate;
        }
        .orb-2 {
            bottom: -10%;
            right: -10%;
            width: 40vw;
            height: 40vw;
            background: radial-gradient(circle, #3b82f6 0%, transparent 70%);
            animation: float 15s infinite alternate-reverse;
        }

        @keyframes float {
            0% { transform: translate(0, 0); }
            100% { transform: translate(30px, 50px); }
        }

        /* Tarjeta Glassmorphism */
        .login-card {
            position: relative;
            z-index: 10;
            background: rgba(30, 30, 35, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 3rem;
            border-radius: 24px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: center;
            animation: fadeUp 0.8s ease-out;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Logo y Títulos */
        .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #9d7cd8, #7aa2f7);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem auto;
            box-shadow: 0 0 20px rgba(124, 58, 237, 0.3);
            font-size: 28px;
        }
        
        h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            letter-spacing: -0.5px;
            background: linear-gradient(to right, #fff, #cbd5e1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        p {
            color: #94a3b8;
            font-size: 0.95rem;
            margin-bottom: 2rem;
        }

        /* Inputs */
        .input-group {
            position: relative;
            margin-bottom: 1.25rem;
            text-align: left;
        }

        .input-group label {
            display: block;
            color: #cbd5e1;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper svg {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            width: 20px;
            height: 20px;
            transition: color 0.3s;
        }

        input {
            width: 100%;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            padding: 12px 12px 12px 44px; /* espacio para el icono */
            border-radius: 12px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
        }

        input:focus {
            outline: none;
            border-color: #7c3aed;
            background: rgba(0, 0, 0, 0.5);
            box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.1);
        }

        input:focus + svg {
            color: #a78bfa;
        }

        /* Botón */
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #7c3aed, #4f46e5);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 1rem;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -10px rgba(124, 58, 237, 0.5);
        }

        button:active {
            transform: scale(0.98);
        }

        /* Mensajes de Error */
        .alert {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            padding: 10px;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }

        .footer {
            margin-top: 2rem;
            font-size: 0.8rem;
            color: #64748b;
        }
    </style>
</head>
<body>

    <!-- Fondos animados -->
    <div class="background-orb orb-1"></div>
    <div class="background-orb orb-2"></div>

    <div class="login-card">
        <div class="logo-icon">
            <!-- Icono SVG simple -->
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
        </div>
        
        <h1>Bienvenido</h1>
        <p>Sistema de Gestión de Cierres</p>

        <?php if($error): ?>
            <div class="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label>Usuario</label>
                <div class="input-wrapper">
                    <input type="text" name="username" placeholder="Ingresa tu usuario" required autocomplete="off">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </div>
            </div>

            <div class="input-group">
                <label>Contraseña</label>
                <div class="input-wrapper">
                    <input type="password" name="password" placeholder="••••••••" required>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                </div>
            </div>

            <button type="submit">Iniciar Sesión</button>
        </form>

        <div class="footer">
            &copy; <?= date('Y') ?> Gestión Imperio
        </div>
    </div>

</body>
</html>