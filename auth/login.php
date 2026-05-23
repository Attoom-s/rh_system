<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Usar la configuración centralizada
require_once '../config/database.php';

$error = '';
$locked_time = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Complete todos los campos";
    } else {
        // Verificar si el usuario existe y su estado de bloqueo
        $stmt = $pdo->prepare("SELECT id, username, password, role, login_attempts, locked_until FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Verificar si la cuenta está bloqueada
            $locked_until = $user['locked_until'];
            $now = new DateTime();
            
            if ($locked_until && new DateTime($locked_until) > $now) {
                // Cuenta bloqueada
                $remaining = (new DateTime($locked_until))->diff($now);
                $minutes = $remaining->i;
                $seconds = $remaining->s;
                $error = "Cuenta temporalmente bloqueada. Intente nuevamente en " . ($minutes > 0 ? "$minutes minutos" : "$seconds segundos");
            } elseif (password_verify($password, $user['password'])) {
                // ✅ Login exitoso - Resetear intentos
                $reset = $pdo->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?");
                $reset->execute([$user['id']]);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                if ($user['role'] === 'rh') {
                    header("Location: ../dashboard/index.php");
                } else {
                    header("Location: ../aspirante/index.php");
                }
                exit();
            } else {
                // ❌ Contraseña incorrecta - Incrementar intentos
                $newAttempts = $user['login_attempts'] + 1;
                $locked_until_sql = NULL;
                $block_minutes = 0;
                
                // Configurar bloqueo según número de intentos
                if ($newAttempts >= 5) {
                    // 5 intentos fallidos = bloqueo de 5 minutos
                    $locked_until_sql = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                    $block_minutes = 5;
                    $error = "Demasiados intentos fallidos. Cuenta bloqueada por 5 minutos.";
                } elseif ($newAttempts >= 3) {
                    // 3 intentos fallidos = bloqueo de 1 minuto
                    $locked_until_sql = date('Y-m-d H:i:s', strtotime('+1 minute'));
                    $block_minutes = 1;
                    $error = "Demasiados intentos fallidos. Cuenta bloqueada por 1 minuto.";
                } else {
                    $error = "Credenciales inválidas. Intento " . $newAttempts . " de 5.";
                }
                
                $update = $pdo->prepare("UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?");
                $update->execute([$newAttempts, $locked_until_sql, $user['id']]);
            }
        } else {
            // Usuario no existe - Mostrar mensaje genérico por seguridad
            $error = "Credenciales inválidas";
            
            // Opcional: Registrar intento de login a usuario inexistente (para auditoría)
            error_log("Intento de login con usuario inexistente: $username desde " . $_SERVER['REMOTE_ADDR']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — RH System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">

        <div class="auth-logo">
            <div class="logo-icon"><i class="fa-solid fa-users"></i></div>
            <span>RH System</span>
        </div>

        <h1 class="auth-title">Iniciar Sesión</h1>
        <p class="auth-subtitle">Ingresa a tu cuenta de RH System</p>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label class="form-label">Usuario</label>
                <input type="text" name="username" id="username" class="form-control" 
                       placeholder="Ingresa tu usuario" 
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label">Contraseña</label>
                <div style="position:relative">
                    <input type="password" name="password" id="password" class="form-control" 
                           placeholder="••••••••" required
                           style="padding-right:42px">
                    <button type="button" onclick="togglePassword()" 
                            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                                   background:none;border:none;color:var(--text-muted);
                                   cursor:pointer;font-size:15px">
                        <i class="fa-regular fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-primary" style="width:100%;justify-content:center;padding:12px" id="loginBtn">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> Ingresar
            </button>
        </form>

        <div class="auth-footer">
            ¿No tienes cuenta? <a href="register.php">Regístrate aquí</a>
        </div>
        
        <!-- Información de seguridad -->
        <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border); font-size: 11px; color: var(--text-muted); text-align: center;">
            <i class="fa-solid fa-shield-haltered"></i> La cuenta se bloquea temporalmente después de 3 intentos fallidos
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const pwd = document.getElementById('password');
    const icon = document.getElementById('toggleIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.className = 'fa-regular fa-eye-slash';
    } else {
        pwd.type = 'password';
        icon.className = 'fa-regular fa-eye';
    }
}

// Prevenir múltiples envíos del formulario
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('loginBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verificando...';
});
</script>
</body>
</html>