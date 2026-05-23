<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
if ($_SESSION['role'] !== 'rh')   { header("Location: ../aspirante/index.php"); exit(); }

$csrf    = getCsrfToken();
$success = '';
$errors  = [];

// Estadísticas del sistema
$totalUsers   = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalRH      = $pdo->query("SELECT COUNT(*) FROM users WHERE role='rh'")->fetchColumn();
$totalAsp     = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$totalApp     = $pdo->query("SELECT COUNT(*) FROM applicants")->fetchColumn();
$totalLogs    = $pdo->query("SELECT COUNT(*) FROM activity_log")->fetchColumn();

// Últimos usuarios registrados
$lastUsers = $pdo->query("SELECT username, role, created_at FROM users ORDER BY created_at DESC LIMIT 8")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // Crear nuevo usuario RH
    if ($action === 'create_rh') {
        $newUser = trim($_POST['new_username'] ?? '');
        $newPass = $_POST['new_password'] ?? '';

        if (empty($newUser) || empty($newPass)) {
            $errors[] = "Usuario y contraseña son obligatorios.";
        } elseif (strlen($newUser) < 4 || strlen($newUser) > 50) {
            $errors[] = "El usuario debe tener entre 4 y 50 caracteres.";
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $newUser)) {
            $errors[] = "El usuario solo puede contener letras, números y guion bajo.";
        } elseif (strlen($newPass) < 15 || !preg_match('/(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&#+\-])/', $newPass)) {
            $errors[] = "La contraseña no cumple los requisitos de seguridad.";
        } else {
            $chk = $pdo->prepare("SELECT id FROM users WHERE username=?");
            $chk->execute([$newUser]);
            if ($chk->rowCount() > 0) {
                $errors[] = "El nombre de usuario ya existe.";
            } else {
                $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost'=>12]);
                $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?,?,'rh')")->execute([$newUser, $hash]);
                $success = "Usuario RH '$newUser' creado correctamente.";
                // Reload stats
                $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                $totalRH    = $pdo->query("SELECT COUNT(*) FROM users WHERE role='rh'")->fetchColumn();
                $lastUsers  = $pdo->query("SELECT username, role, created_at FROM users ORDER BY created_at DESC LIMIT 8")->fetchAll();
            }
        }
    }

    // Limpiar logs
    if ($action === 'clear_logs') {
        $pdo->query("DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $success = "Logs anteriores a 30 días eliminados.";
        $totalLogs = $pdo->query("SELECT COUNT(*) FROM activity_log")->fetchColumn();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuración — RH System</title>
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
    <a href="configuracion.php" class="menu-item active">
        <i class="fa-solid fa-gear"></i> Configuración
    </a>

    <div class="sidebar-section">Cuenta</div>
    <a href="perfil.php" class="menu-item">
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

    <div class="page-body" style="max-width:900px">
        <h1 style="font-size:24px;font-weight:800;letter-spacing:-0.5px;margin-bottom:4px">Configuración</h1>
        <p style="color:var(--text-secondary);margin-bottom:28px">Administración del sistema RH</p>

        <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom:20px"><i class="fa-solid fa-circle-check"></i> <?= e($success) ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="margin-bottom:20px">
            <?php foreach ($errors as $e_): ?><div><i class="fa-solid fa-circle-exclamation"></i> <?= e($e_) ?></div><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Resumen sistema -->
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px">
            <?php
            $cards = [
                ['Total Usuarios',  $totalUsers, 'fa-users',       'icon-blue'],
                ['Personal RH',     $totalRH,    'fa-user-shield', 'icon-purple'],
                ['Aspirantes',      $totalAsp,   'fa-user-group',  'icon-green'],
                ['Registros Log',   $totalLogs,  'fa-list-check',  'icon-yellow'],
            ];
            foreach ($cards as [$label, $val, $icon, $cls]):
            ?>
            <div class="stat-card">
                <div class="stat-card-top">
                    <div><div class="stat-label"><?= $label ?></div><div class="stat-value" style="font-size:28px"><?= intval($val) ?></div></div>
                    <div class="stat-icon <?= $cls ?>"><i class="fa-solid <?= $icon ?>"></i></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

            <!-- Crear usuario RH -->
            <div class="card-dark">
                <h3 style="font-size:15px;font-weight:700;margin-bottom:6px">
                    <i class="fa-solid fa-user-plus" style="color:var(--accent-blue);margin-right:8px"></i>
                    Crear Usuario RH
                </h3>
                <p style="color:var(--text-secondary);font-size:12px;margin-bottom:18px">
                    Crea nuevas cuentas con acceso al panel de RH.
                </p>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="create_rh">
                    <div class="form-group">
                        <label class="form-label">Usuario <span class="req">*</span></label>
                        <input type="text" name="new_username" class="form-control" placeholder="nombre_rh" minlength="4" maxlength="50" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contraseña <span class="req">*</span></label>
                        <input type="password" name="new_password" class="form-control" placeholder="mínimo 15 caracteres" maxlength="200" required>
                        <small style="color:var(--text-muted);font-size:11px;margin-top:4px;display:block">
                            Debe incluir: mayúscula, minúscula, número y especial (@$!%*?&#+-)
                        </small>
                    </div>
                    <button type="submit" class="btn-primary" style="width:100%;justify-content:center">
                        <i class="fa-solid fa-plus"></i> Crear usuario RH
                    </button>
                </form>
            </div>

            <!-- Usuarios del sistema -->
            <div class="card-dark">
                <h3 style="font-size:15px;font-weight:700;margin-bottom:16px">
                    <i class="fa-solid fa-users" style="color:var(--accent-blue);margin-right:8px"></i>
                    Usuarios Registrados
                </h3>
                <div style="display:flex;flex-direction:column;gap:10px;max-height:280px;overflow-y:auto">
                    <?php foreach ($lastUsers as $u): ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)">
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="avatar <?= $u['role']==='rh' ? 'avatar-rh' : 'avatar-blue' ?>" style="width:32px;height:32px;font-size:11px">
                                <?= strtoupper(mb_substr($u['username'],0,2)) ?>
                            </div>
                            <div>
                                <div style="font-size:13px;font-weight:600"><?= e($u['username']) ?></div>
                                <div style="font-size:11px;color:var(--text-muted)"><?= date('d/m/Y', strtotime($u['created_at'])) ?></div>
                            </div>
                        </div>
                        <span class="badge <?= $u['role']==='rh' ? 'badge-ok' : 'badge-review' ?>" style="font-size:11px">
                            <?= $u['role'] === 'rh' ? 'RH' : 'Aspirante' ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- Mantenimiento -->
        <div class="card-dark" style="margin-top:24px">
            <h3 style="font-size:15px;font-weight:700;margin-bottom:6px">
                <i class="fa-solid fa-wrench" style="color:var(--accent-yellow);margin-right:8px"></i>
                Mantenimiento
            </h3>
            <p style="color:var(--text-secondary);font-size:12px;margin-bottom:16px">
                Acciones de mantenimiento del sistema. Proceda con cuidado.
            </p>
            <div style="display:flex;gap:12px;flex-wrap:wrap">
                <form method="POST" onsubmit="return confirm('¿Eliminar logs con más de 30 días?')">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="clear_logs">
                    <button type="submit" class="btn-outline" style="border-color:rgba(245,158,11,0.4);color:var(--accent-yellow)">
                        <i class="fa-solid fa-trash-can"></i> Limpiar logs antiguos (+30 días)
                    </button>
                </form>
            </div>
            <!-- En la sección de Mantenimiento, agregar: -->
<div style="margin-top: 16px">
    <a href="unlock_user.php" class="btn-outline" style="border-color:rgba(79,126,248,0.4)">
        <i class="fa-solid fa-user-lock"></i> Usuarios bloqueados
    </a>
</div>
        </div>

    </div>
</div>
</body>
</html>
