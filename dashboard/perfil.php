<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
if ($_SESSION['role'] !== 'rh')   { header("Location: ../aspirante/index.php"); exit(); }

$csrf = getCsrfToken();
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id, username, role, created_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $pwActual  = $_POST['pw_actual']  ?? '';
    $pwNueva   = $_POST['pw_nueva']   ?? '';
    $pwConfirm = $_POST['pw_confirm'] ?? '';

    if (empty($pwActual) || empty($pwNueva) || empty($pwConfirm)) {
        $errors[] = "Todos los campos de contraseña son obligatorios.";
    } else {
        // Verificar contraseña actual
        $s = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $s->execute([$userId]);
        $hash = $s->fetchColumn();

        if (!password_verify($pwActual, $hash)) {
            $errors[] = "La contraseña actual es incorrecta.";
        } elseif ($pwNueva !== $pwConfirm) {
            $errors[] = "La nueva contraseña y su confirmación no coinciden.";
        } elseif (strlen($pwNueva) < 15) {
            $errors[] = "La nueva contraseña debe tener mínimo 15 caracteres.";
        } elseif (!preg_match('/(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&#+\-])/u', $pwNueva)) {
            $errors[] = "La nueva contraseña debe incluir mayúscula, minúscula, número y carácter especial.";
        } elseif ($pwActual === $pwNueva) {
            $errors[] = "La nueva contraseña debe ser diferente a la actual.";
        } else {
            $newHash = password_hash($pwNueva, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $userId]);
            $success = "Contraseña actualizada correctamente.";
        }
    }
}

// Total solicitudes gestionadas (cambios hechos por este usuario)
$gestionadas = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE user_id = ? AND action='update_status'");
$gestionadas->execute([$userId]);
$totalGestionadas = $gestionadas->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Perfil — RH System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<!-- ══ SIDEBAR ══ -->
<div class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon"><i class="fa-solid fa-users"></i></div>
        <span>RH System</span>
    </div>

    <div class="sidebar-section">Principal</div>

    <a href="index.php" class="menu-item">
        <i class="fa-solid fa-house"></i> Inicio
    </a>
    <a href="solicitudes.php" class="menu-item">
        <i class="fa-solid fa-user-group"></i> Solicitudes
    </a>
    <a href="reportes.php" class="menu-item">
        <i class="fa-solid fa-chart-line"></i> Reportes
    </a>
    <a href="configuracion.php" class="menu-item">
        <i class="fa-solid fa-gear"></i> Configuración
    </a>

    <div class="sidebar-section">Cuenta</div>
    <a href="perfil.php" class="menu-item active">
        <i class="fa-solid fa-circle-user"></i> Perfil
    </a>

    <div class="sidebar-bottom">
        <a href="../auth/logout.php" class="menu-item">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Cerrar Sesión
        </a>
    </div>
</div>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-left"></div>
        <div class="topbar-right">
            <a href="../auth/logout.php" class="user-chip">
                <div class="avatar avatar-rh"><?= strtoupper(mb_substr($_SESSION['username'],0,2)) ?></div>
                Departamento RH
            </a>
        </div>
    </div>

    <div class="page-body" style="max-width:800px">
        <h1 style="font-size:24px;font-weight:800;letter-spacing:-0.5px;margin-bottom:4px">Mi Perfil</h1>
        <p style="color:var(--text-secondary);margin-bottom:28px">Información de tu cuenta del sistema</p>

        <!-- Tarjeta de perfil -->
        <div class="card-dark" style="margin-bottom:24px">
            <div style="display:flex;align-items:center;gap:20px;padding-bottom:20px;border-bottom:1px solid var(--border);margin-bottom:20px">
                <div class="avatar avatar-rh" style="width:68px;height:68px;font-size:24px;font-weight:800;border-radius:50%">
                    <?= strtoupper(mb_substr($user['username'],0,2)) ?>
                </div>
                <div>
                    <div style="font-size:20px;font-weight:800"><?= e($user['username']) ?></div>
                    <div style="margin-top:6px;display:flex;align-items:center;gap:8px">
                        <span class="badge badge-ok">Departamento RH</span>
                    </div>
                </div>
            </div>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Usuario</div>
                    <div class="detail-value"><?= e($user['username']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Rol</div>
                    <div class="detail-value">Recursos Humanos</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Miembro desde</div>
                    <div class="detail-value"><?= date('d/m/Y', strtotime($user['created_at'])) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Solicitudes gestionadas</div>
                    <div class="detail-value"><?= intval($totalGestionadas) ?> cambios de estado</div>
                </div>
            </div>
        </div>

        <!-- Estado de seguridad de la cuenta -->
<div class="card-dark" style="margin-top: 24px">
    <h3 style="font-size:16px;font-weight:700;margin-bottom:16px">
        <i class="fa-solid fa-shield-haltered" style="color:var(--accent-green);margin-right:8px"></i>
        Seguridad de la Cuenta
    </h3>
    <?php
    // Obtener información de seguridad del usuario
    $secStmt = $pdo->prepare("SELECT login_attempts, locked_until FROM users WHERE id = ?");
    $secStmt->execute([$userId]);
    $security = $secStmt->fetch();
    
    $isLocked = $security['locked_until'] && new DateTime($security['locked_until']) > new DateTime();
    ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div class="detail-item">
            <div class="detail-label">Intentos fallidos registrados</div>
            <div class="detail-value" style="color: <?= $security['login_attempts'] >= 3 ? 'var(--accent-red)' : 'var(--text-primary)' ?>">
                <?= intval($security['login_attempts']) ?> / 5
            </div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Estado de la cuenta</div>
            <div class="detail-value">
                <?php if ($isLocked): ?>
                    <span style="color:var(--accent-red)"><i class="fa-solid fa-lock"></i> Temporalmente bloqueada</span>
                <?php else: ?>
                    <span style="color:var(--accent-green)"><i class="fa-solid fa-check-circle"></i> Activa</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if ($security['login_attempts'] > 0): ?>
    <div style="margin-top: 16px; padding: 12px; background: rgba(79,126,248,0.08); border-radius: 8px; font-size: 12px;">
        <i class="fa-solid fa-info-circle"></i> 
        Después de 3 intentos fallidos, tu cuenta se bloqueará por 1 minuto. 
        Después de 5 intentos, el bloqueo será de 5 minutos.
    </div>
    <?php endif; ?>
</div>

        <!-- Cambio de contraseña -->
        <div class="card-dark">
            <h3 style="font-size:16px;font-weight:700;margin-bottom:6px">
                <i class="fa-solid fa-lock" style="color:var(--accent-blue);margin-right:8px"></i>
                Cambiar Contraseña
            </h3>
            <p style="color:var(--text-secondary);font-size:13px;margin-bottom:20px">
                Mínimo 15 caracteres, incluir mayúscula, minúscula, número y carácter especial.
            </p>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $err): ?><div><i class="fa-solid fa-circle-exclamation"></i> <?= e($err) ?></div><?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if ($success): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= e($success) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <div class="form-group">
                    <label class="form-label">Contraseña actual <span class="req">*</span></label>
                    <input type="password" name="pw_actual" class="form-control" placeholder="Tu contraseña actual" maxlength="200" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nueva contraseña <span class="req">*</span></label>
                        <input type="password" name="pw_nueva" id="pwNueva" class="form-control"
                               placeholder="mínimo 15 caracteres" maxlength="200" required
                               oninput="checkStr(this.value)">
                        <div style="margin-top:6px">
                            <div style="height:4px;background:var(--border);border-radius:4px;overflow:hidden">
                                <div id="strBar" style="height:100%;width:0;border-radius:4px;transition:all 0.3s"></div>
                            </div>
                            <div id="strText" style="font-size:11px;color:var(--text-muted);margin-top:4px">Escribe la nueva contraseña</div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirmar nueva contraseña <span class="req">*</span></label>
                        <input type="password" name="pw_confirm" class="form-control" placeholder="repite la contraseña" maxlength="200" required>
                    </div>
                </div>
                <button type="submit" class="btn-primary" style="margin-top:6px">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar contraseña
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function checkStr(v) {
    let s = 0;
    if (v.length>=15) s++;
    if (/[A-Z]/.test(v)) s++;
    if (/[a-z]/.test(v)) s++;
    if (/\d/.test(v)) s++;
    if (/[@$!%*?&#+\-]/.test(v)) s++;
    const lvls=[{w:'10%',c:'#ef4444',l:'Muy débil'},{w:'25%',c:'#f97316',l:'Débil'},{w:'50%',c:'#f59e0b',l:'Regular'},{w:'75%',c:'#84cc16',l:'Buena'},{w:'100%',c:'#22c55e',l:'Muy segura'}];
    const bar=document.getElementById('strBar'), txt=document.getElementById('strText');
    if (!s) { bar.style.width='0'; txt.textContent='Escribe la nueva contraseña'; txt.style.color='var(--text-muted)'; return; }
    const l=lvls[s-1];
    bar.style.width=l.w; bar.style.background=l.c;
    txt.textContent=l.l; txt.style.color=l.c;
}
</script>
</body>
</html>
