<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
if ($_SESSION['role'] !== 'rh')   { header("Location: ../aspirante/index.php"); exit(); }

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { header("Location: index.php"); exit(); }

$stmt = $pdo->prepare("SELECT a.*, u.username FROM applicants a JOIN users u ON a.user_id = u.id WHERE a.id = ?");
$stmt->execute([$id]);
$a = $stmt->fetch();
if (!$a) { header("Location: index.php"); exit(); }

$csrf = getCsrfToken();
$flash = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$badgeClass = match($a['estado']) { 'Considerado' => 'badge-ok', 'No Considerado' => 'badge-no', default => 'badge-review' };

$avatarColors = ['blue','green','red','yellow','purple','pink','teal'];
$color = $avatarColors[ord($a['nombre'][0] ?? 'A') % count($avatarColors)];
$initials = strtoupper(mb_substr($a['nombre'],0,1) . mb_substr($a['apellido'],0,1));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($a['nombre'].' '.$a['apellido']) ?> — RH System</title>
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
    <a href="solicitudes.php" class="menu-item active">
        <i class="fa-solid fa-user-group"></i> Solicitudes
    </a>
    <a href="reportes.php" class="menu-item">
        <i class="fa-solid fa-chart-line"></i> Reportes
    </a>
    <a href="configuracion.php" class="menu-item">
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
        <div class="topbar-left">
            <a href="solicitudes.php" style="color:var(--text-secondary);text-decoration:none;font-size:13px;display:flex;align-items:center;gap:6px">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </a>
        </div>
        <div class="topbar-right">
            <a href="../auth/logout.php" class="user-chip">
                <div class="avatar avatar-rh"><?= strtoupper(mb_substr($_SESSION['username'],0,2)) ?></div>
                Departamento RH
            </a>
        </div>
    </div>

    <div class="page-body">
        <?php if ($flash): ?>
        <div class="flash flash-<?= $flashType === 'success' ? 'success' : 'error' ?>" id="flashMsg"><?= e($flash) ?></div>
        <?php endif; ?>

        <!-- Cabecera -->
        <div class="profile-hero">
            <div class="profile-avatar avatar-<?= $color ?>"><?= $initials ?></div>
            <div style="flex:1">
                <div class="profile-name"><?= e($a['nombre'] . ' ' . $a['apellido']) ?></div>
                <div class="profile-sub"><?= e($a['correo']) ?> · <?= e($a['telefono']) ?></div>
            </div>
            <div style="display:flex;align-items:center;gap:10px">
                <span class="badge <?= $badgeClass ?>" style="font-size:13px;padding:6px 14px"><?= e($a['estado']) ?></span>
                <button class="btn-primary" onclick="openModal()" style="font-size:13px">
                    <i class="fa-solid fa-pen"></i> Cambiar estado
                </button>
            </div>
        </div>

        <!-- Datos -->
        <div class="card-dark">
            <h3 style="font-size:16px;font-weight:700;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid var(--border)">
                <i class="fa-solid fa-id-card" style="color:var(--accent-blue);margin-right:8px"></i>
                Información Personal
            </h3>
            <div class="detail-grid">
                <div class="detail-item"><div class="detail-label">Cédula / Pasaporte</div><div class="detail-value"><?= e($a['cedula_pasaporte']) ?></div></div>
                <div class="detail-item"><div class="detail-label">Nombre completo</div><div class="detail-value"><?= e($a['nombre'] . ' ' . $a['apellido']) ?></div></div>
                <div class="detail-item"><div class="detail-label">Género</div><div class="detail-value"><?= e($a['genero']) ?></div></div>
                <div class="detail-item"><div class="detail-label">Estado civil</div><div class="detail-value"><?= e($a['estado_civil'] ?: '—') ?></div></div>
                <div class="detail-item"><div class="detail-label">Fecha de nacimiento</div><div class="detail-value"><?= e($a['fecha_nacimiento']) ?></div></div>
                <div class="detail-item"><div class="detail-label">Tipo de sangre</div><div class="detail-value"><?= e($a['tipo_sangre'] ?: '—') ?></div></div>
                <div class="detail-item"><div class="detail-label">Nacionalidad</div><div class="detail-value"><?= e($a['nacionalidad']) ?></div></div>
                <div class="detail-item"><div class="detail-label">Teléfono</div><div class="detail-value"><?= e($a['telefono']) ?></div></div>
                <div class="detail-item"><div class="detail-label">Correo electrónico</div><div class="detail-value"><?= e($a['correo']) ?></div></div>
                <div class="detail-item"><div class="detail-label">Residencia</div><div class="detail-value"><?= e($a['residencia']) ?></div></div>
                <div class="detail-item"><div class="detail-label">Usuario del sistema</div><div class="detail-value"><?= e($a['username']) ?></div></div>
                <div class="detail-item"><div class="detail-label">Fecha de registro</div><div class="detail-value"><?= date('d/m/Y H:i', strtotime($a['created_at'])) ?></div></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="statusModal">
    <div class="modal-box" style="max-width:360px">
        <div class="modal-header">
            <div class="modal-title">Cambiar Estado</div>
            <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="update-status.php">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= intval($a['id']) ?>">
            <input type="hidden" name="redirect" value="ver_aspirante.php?id=<?= intval($a['id']) ?>">
            <div class="form-group">
                <label class="form-label">Estado actual: <span class="badge <?= $badgeClass ?>"><?= e($a['estado']) ?></span></label>
                <select name="estado" class="form-control" style="margin-top:10px">
                    <option value="No Revisado"    <?= $a['estado']==='No Revisado'?'selected':'' ?>>No Revisado</option>
                    <option value="Considerado"    <?= $a['estado']==='Considerado'?'selected':'' ?>>Considerado</option>
                    <option value="No Considerado" <?= $a['estado']==='No Considerado'?'selected':'' ?>>No Considerado</option>
                </select>
            </div>
            <div style="display:flex;gap:10px;margin-top:20px">
                <button type="button" class="btn-outline" onclick="closeModal()" style="flex:1;justify-content:center">Cancelar</button>
                <button type="submit" class="btn-primary" style="flex:1;justify-content:center"><i class="fa-solid fa-check"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() { document.getElementById('statusModal').classList.add('open'); }
function closeModal() { document.getElementById('statusModal').classList.remove('open'); }
const flash = document.getElementById('flashMsg');
if (flash) setTimeout(() => flash.style.opacity = '0', 3500);
</script>
</body>
</html>
