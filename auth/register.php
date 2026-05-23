<?php
session_start();
require_once '../config/database.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../dashboard/index.php");
    exit();
}

$error   = '';
$success = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // ── Validaciones ──
    if (empty($username) || empty($password) || empty($confirm)) {
        $error = "Por favor complete todos los campos.";

    } elseif (strlen($username) < 4 || strlen($username) > 50) {
        $error = "El usuario debe tener entre 4 y 50 caracteres.";

    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = "El usuario solo puede contener letras, números y guion bajo.";

    } elseif ($password !== $confirm) {
        $error = "Las contraseñas no coinciden.";

    } elseif (strlen($password) < 15) {
        $error = "La contraseña debe tener mínimo 15 caracteres.";

    } elseif (!preg_match('/(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&#+\-])/u', $password)) {
        $error = "La contraseña debe incluir: mayúscula, minúscula, número y carácter especial (@\$!%*?&#+-)";

    } else {
        // Verificar usuario duplicado
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->rowCount() > 0) {
            $error = "El nombre de usuario ya está en uso. Elige otro.";
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $ins  = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'user')");

            if ($ins->execute([$username, $hash])) {
                $success  = "¡Registro exitoso! Ya puedes iniciar sesión.";
                $username = '';
            } else {
                $error = "Error al registrar. Intente nuevamente.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registro — RH System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card" style="max-width:480px">

        <div class="auth-logo">
            <div class="logo-icon"><i class="fa-solid fa-users"></i></div>
            <span>RH System</span>
        </div>

        <h1 class="auth-title">Crear cuenta</h1>
        <p class="auth-subtitle">Regístrate para enviar tu solicitud de empleo</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= e($success) ?>
                <a href="login.php" style="color:inherit;font-weight:700;margin-left:6px">Ir al login →</a>
            </div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="off" id="regForm">
            <div class="form-group">
                <label class="form-label">Usuario <span class="req">*</span></label>
                <input type="text" name="username" class="form-control"
                       placeholder="mínimo 4 caracteres"
                       value="<?= e($username) ?>"
                       minlength="4" maxlength="50" required>
                <small style="color:var(--text-muted);font-size:11px;margin-top:4px;display:block">Solo letras, números y guion bajo</small>
            </div>

            <div class="form-group">
                <label class="form-label">Contraseña <span class="req">*</span></label>
                <div style="position:relative">
                    <input type="password" name="password" id="pwd" class="form-control"
                           placeholder="mínimo 15 caracteres"
                           maxlength="200" required
                           oninput="checkStrength(this.value)"
                           style="padding-right:42px">
                    <button type="button" onclick="togglePwd('pwd','eye1')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:15px">
                        <i class="fa-regular fa-eye" id="eye1"></i>
                    </button>
                </div>
                <div class="pw-strength">
                    <div class="pw-bar"><div class="pw-fill" id="pwFill" style="width:0%;background:#ef4444"></div></div>
                    <div class="pw-text" id="pwText" style="color:var(--text-muted)">Escribe una contraseña</div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:24px">
                <label class="form-label">Confirmar Contraseña <span class="req">*</span></label>
                <div style="position:relative">
                    <input type="password" name="confirm_password" id="cpwd" class="form-control"
                           placeholder="repite la contraseña"
                           maxlength="200" required
                           style="padding-right:42px">
                    <button type="button" onclick="togglePwd('cpwd','eye2')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:15px">
                        <i class="fa-regular fa-eye" id="eye2"></i>
                    </button>
                </div>
            </div>

            <div style="background:rgba(79,126,248,0.08);border:1px solid rgba(79,126,248,0.2);border-radius:8px;padding:12px 14px;font-size:12px;color:var(--text-secondary);margin-bottom:20px;line-height:1.7">
                <strong style="color:var(--accent-blue)">Requisitos de contraseña:</strong><br>
                ✓ Mínimo 15 caracteres &nbsp; ✓ Una mayúscula &nbsp; ✓ Una minúscula<br>
                ✓ Un número &nbsp; ✓ Un carácter especial (@$!%*?&#+-)
            </div>

            <button type="submit" class="btn-primary" style="width:100%;justify-content:center;padding:12px">
                <i class="fa-solid fa-user-plus"></i> Crear cuenta
            </button>
        </form>

        <div class="auth-footer">
            ¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a>
        </div>
    </div>
</div>

<script>
function togglePwd(id, iconId) {
    const i = document.getElementById(id), e = document.getElementById(iconId);
    if (i.type === 'password') { i.type = 'text'; e.className = 'fa-regular fa-eye-slash'; }
    else { i.type = 'password'; e.className = 'fa-regular fa-eye'; }
}

function checkStrength(val) {
    const fill = document.getElementById('pwFill');
    const text = document.getElementById('pwText');
    let score = 0;
    if (val.length >= 15) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[a-z]/.test(val)) score++;
    if (/\d/.test(val)) score++;
    if (/[@$!%*?&#+\-]/.test(val)) score++;

    const levels = [
        {pct:'10%', color:'#ef4444', label:'Muy débil'},
        {pct:'25%', color:'#f97316', label:'Débil'},
        {pct:'50%', color:'#f59e0b', label:'Regular'},
        {pct:'75%', color:'#84cc16', label:'Buena'},
        {pct:'100%',color:'#22c55e', label:'Muy segura'},
    ];
    const l = levels[score > 0 ? score-1 : 0];
    fill.style.width   = score === 0 ? '0%' : l.pct;
    fill.style.background = l.color;
    text.textContent   = score === 0 ? 'Escribe una contraseña' : l.label;
    text.style.color   = score === 0 ? 'var(--text-muted)' : l.color;
}
</script>
</body>
</html>
