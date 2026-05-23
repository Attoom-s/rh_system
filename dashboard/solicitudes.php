<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
if ($_SESSION['role'] !== 'rh')    { header("Location: ../aspirante/index.php"); exit(); }

$csrf = getCsrfToken();
$flash = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Filtros
$filtroEstado = $_GET['estado'] ?? '';
$busqueda     = trim($_GET['q'] ?? '');

$where  = [];
$params = [];
if ($filtroEstado && in_array($filtroEstado, ['No Revisado','Considerado','No Considerado'])) {
    $where[]  = "a.estado = ?";
    $params[] = $filtroEstado;
}
if ($busqueda) {
    $where[]  = "(a.nombre LIKE ? OR a.apellido LIKE ? OR a.correo LIKE ? OR a.cedula_pasaporte LIKE ?)";
    $like     = "%$busqueda%";
    $params   = array_merge($params, [$like, $like, $like, $like]);
}
$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM applicants a $whereClause");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

// Paginación
$perPage    = 10;
$page       = max(1, intval($_GET['page'] ?? 1));
$offset     = ($page - 1) * $perPage;
$totalPages = max(1, ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT a.*, u.username FROM applicants a JOIN users u ON a.user_id = u.id $whereClause ORDER BY a.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$apps = $stmt->fetchAll();

$avatarColors = ['blue','green','red','yellow','purple','pink','teal'];
function avatarColor($n) { global $avatarColors; return $avatarColors[ord($n[0] ?? 'A') % count($avatarColors)]; }
function initials($a,$b) { return strtoupper(mb_substr($a,0,1).mb_substr($b,0,1)); }

// Stats sidebar
$total_all  = $pdo->query("SELECT COUNT(*) FROM applicants")->fetchColumn();
$consid     = $pdo->query("SELECT COUNT(*) FROM applicants WHERE estado='Considerado'")->fetchColumn();
$noCons     = $pdo->query("SELECT COUNT(*) FROM applicants WHERE estado='No Considerado'")->fetchColumn();
$noRev      = $pdo->query("SELECT COUNT(*) FROM applicants WHERE estado='No Revisado'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Solicitudes — RH System</title>
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
        <div class="topbar-left"></div>
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

        <h1 style="font-size:24px;font-weight:800;letter-spacing:-0.5px;margin-bottom:4px">Solicitudes de Empleo</h1>
        <p style="color:var(--text-secondary);margin-bottom:24px">Gestiona y revisa todas las solicitudes</p>

        <!-- Filtros -->
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px">
            <input type="text" name="q" class="form-control" placeholder="Buscar por nombre, correo, cédula..." value="<?= e($busqueda) ?>" style="max-width:300px">
            <select name="estado" class="form-control" style="max-width:200px">
                <option value="">Todos los estados</option>
                <option value="No Revisado"    <?= $filtroEstado === 'No Revisado'    ? 'selected' : '' ?>>No Revisado</option>
                <option value="Considerado"    <?= $filtroEstado === 'Considerado'    ? 'selected' : '' ?>>Considerado</option>
                <option value="No Considerado" <?= $filtroEstado === 'No Considerado' ? 'selected' : '' ?>>No Considerado</option>
            </select>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Filtrar</button>
            <?php if ($filtroEstado || $busqueda): ?>
                <a href="solicitudes.php" class="btn-outline"><i class="fa-solid fa-xmark"></i> Limpiar</a>
            <?php endif; ?>
        </form>

        <div class="card-dark">
            <table class="rh-table">
                <thead>
                    <tr>
                        <th>Aspirante</th>
                        <th>Cédula/Pasaporte</th>
                        <th>Correo</th>
                        <th>Teléfono</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($apps)): ?>
                    <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:40px">No hay solicitudes</td></tr>
                <?php else: ?>
                    <?php foreach ($apps as $row): ?>
                    <tr>
                        <td>
                            <div class="applicant-cell">
                                <div class="avatar avatar-sm avatar-<?= avatarColor($row['nombre']) ?>">
                                    <?= initials($row['nombre'], $row['apellido']) ?>
                                </div>
                                <div>
                                    <div class="applicant-name"><?= e($row['nombre'] . ' ' . $row['apellido']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td style="color:var(--text-secondary)"><?= e($row['cedula_pasaporte']) ?></td>
                        <td style="color:var(--text-secondary)"><?= e($row['correo']) ?></td>
                        <td style="color:var(--text-secondary)"><?= e($row['telefono']) ?></td>
                        <td style="color:var(--text-secondary)"><?= date('d/m/Y', strtotime($row['created_at'])) ?></td>
                        <td>
                            <?php $bc = match($row['estado']) { 'Considerado' => 'badge-ok', 'No Considerado' => 'badge-no', default => 'badge-review' }; ?>
                            <span class="badge <?= $bc ?>"><?= e($row['estado']) ?></span>
                        </td>
                        <td>
                            <div class="action-btns">
                                <a href="ver_aspirante.php?id=<?= intval($row['id']) ?>" class="btn-icon" title="Ver detalle"><i class="fa-regular fa-eye"></i></a>
                                <button class="btn-icon" title="Cambiar estado"
                                    onclick="openModal(<?= intval($row['id']) ?>,'<?= e($row['nombre'].' '.$row['apellido']) ?>','<?= e($row['estado']) ?>')">
                                    <i class="fa-regular fa-pen-to-square"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page-1 ?>&q=<?= urlencode($busqueda) ?>&estado=<?= urlencode($filtroEstado) ?>" class="page-btn"><i class="fa-solid fa-chevron-left" style="font-size:11px"></i></a>
                <?php endif; ?>
                <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
                    <a href="?page=<?= $p ?>&q=<?= urlencode($busqueda) ?>&estado=<?= urlencode($filtroEstado) ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page+1 ?>&q=<?= urlencode($busqueda) ?>&estado=<?= urlencode($filtroEstado) ?>" class="page-btn"><i class="fa-solid fa-chevron-right" style="font-size:11px"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal estado -->
<div class="modal-overlay" id="statusModal">
    <div class="modal-box" style="max-width:360px">
        <div class="modal-header">
            <div class="modal-title">Cambiar Estado</div>
            <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <p style="color:var(--text-secondary);font-size:13px;margin-bottom:18px" id="modalName"></p>
        <form method="POST" action="update-status.php">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" id="modalId">
            <input type="hidden" name="redirect" value="solicitudes.php">
            <div class="form-group">
                <label class="form-label">Nuevo estado</label>
                <select name="estado" id="modalEstado" class="form-control">
                    <option value="No Revisado">No Revisado</option>
                    <option value="Considerado">Considerado</option>
                    <option value="No Considerado">No Considerado</option>
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
function openModal(id, name, estado) {
    document.getElementById('modalId').value = id;
    document.getElementById('modalName').textContent = 'Aspirante: ' + name;
    document.getElementById('modalEstado').value = estado;
    document.getElementById('statusModal').classList.add('open');
}
function closeModal() { document.getElementById('statusModal').classList.remove('open'); }
document.getElementById('statusModal').addEventListener('click', e => { if (e.target === document.getElementById('statusModal')) closeModal(); });
const flash = document.getElementById('flashMsg');
if (flash) setTimeout(() => flash.style.opacity = '0', 3500);
</script>
</body>
</html>
