<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
if ($_SESSION['role'] !== 'rh') { header("Location: ../aspirante/index.php"); exit(); }

$unlock_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    if ($user_id) {
        $stmt = $pdo->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?");
        $stmt->execute([$user_id]);
        $unlock_message = "Usuario desbloqueado correctamente.";
    }
}

// Obtener usuarios bloqueados
$blockedUsers = $pdo->query("
    SELECT id, username, role, login_attempts, locked_until 
    FROM users 
    WHERE locked_until IS NOT NULL AND locked_until > NOW()
    ORDER BY locked_until DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Desbloquear Usuarios — RH System</title>
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
    <div class="page-body">
        <h1>Gestión de Usuarios Bloqueados</h1>
        
        <?php if ($unlock_message): ?>
            <div class="alert alert-success"><?= $unlock_message ?></div>
        <?php endif; ?>
        
        <?php if (empty($blockedUsers)): ?>
            <div class="alert alert-info">No hay usuarios bloqueados actualmente.</div>
        <?php else: ?>
            <table class="rh-table">
                <thead>
                    <tr><th>Usuario</th><th>Rol</th><th>Intentos</th><th>Bloqueado hasta</th><th>Acción</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($blockedUsers as $u): ?>
                    <tr>
                        <td><?= e($u['username']) ?></td>
                        <td><?= $u['role'] === 'rh' ? 'RH' : 'Aspirante' ?></td>
                        <td><?= $u['login_attempts'] ?>/5</td>
                        <td><?= date('d/m/Y H:i:s', strtotime($u['locked_until'])) ?></td>
                        <td>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn-primary" style="padding:5px 12px">
                                    <i class="fa-solid fa-unlock"></i> Desbloquear
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>