<?php
session_start();
require_once '../config/database.php';

// ── Verificar sesión ──
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// ── Redirigir aspirantes a su área ──
if ($_SESSION['role'] === 'user') {
    header("Location: ../aspirante/index.php");
    exit();
}

// ── CSRF ──
$csrf = getCsrfToken();

// ── Flash message ──
$flash = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// ── Estadísticas ──
$total         = $pdo->query("SELECT COUNT(*) FROM applicants")->fetchColumn();
$considerados  = $pdo->query("SELECT COUNT(*) FROM applicants WHERE estado='Considerado'")->fetchColumn();
$noConsiderados= $pdo->query("SELECT COUNT(*) FROM applicants WHERE estado='No Considerado'")->fetchColumn();
$noRevisados   = $pdo->query("SELECT COUNT(*) FROM applicants WHERE estado='No Revisado'")->fetchColumn();

// ── Paginación ──
$perPage = 5;
$page    = max(1, intval($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;
$totalPages = max(1, ceil($total / $perPage));

$stmt = $pdo->prepare("
    SELECT a.*, u.username 
    FROM applicants a 
    JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $offset]);
$apps = $stmt->fetchAll();

// ── Actividad reciente ──
$actStmt = $pdo->query("
    SELECT al.details, al.created_at, al.action, u.username
    FROM activity_log al
    JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 5
");
$activities = $actStmt ? $actStmt->fetchAll() : [];

// ── Helper: colores de avatar ──
$avatarColors = ['blue','green','red','yellow','purple','pink','teal'];
function avatarColor($name) {
    global $avatarColors;
    return $avatarColors[ord($name[0] ?? 'A') % count($avatarColors)];
}
function initials($nombre, $apellido) {
    return strtoupper(mb_substr($nombre,0,1) . mb_substr($apellido,0,1));
}

// ── Calcular tiempo relativo ──
function timeAgo($datetime) {
    $now  = time();
    $then = strtotime($datetime);
    $diff = $now - $then;
    if ($diff < 60)     return "Hace unos segundos";
    if ($diff < 3600)   return "Hace " . floor($diff/60) . " minuto(s)";
    if ($diff < 86400)  return "Hace " . floor($diff/3600) . " hora(s)";
    return "Hace " . floor($diff/86400) . " día(s)";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — RH System</title>
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

    <a href="index.php" class="menu-item active">
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
    <a href="perfil.php" class="menu-item">
        <i class="fa-solid fa-circle-user"></i> Perfil
    </a>

    <div class="sidebar-bottom">
        <a href="../auth/logout.php" class="menu-item">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Cerrar Sesión
        </a>
    </div>
</div>

<!-- ══ MAIN ══ -->
<div class="main-content">

    <!-- TOPBAR (sin botón de hamburguesa ni notificaciones) -->
    <div class="topbar">
        <div class="topbar-left"></div>
        <div class="topbar-right">
            <div class="user-chip">
                <div class="avatar avatar-rh"><?= strtoupper(mb_substr($_SESSION['username'],0,2)) ?></div>
                Departamento RH
            </div>
        </div>
    </div>

    <!-- PAGE BODY -->
    <div class="page-body">

        <?php if ($flash): ?>
        <div class="flash flash-<?= $flashType === 'success' ? 'success' : 'error' ?>" id="flashMsg">
            <?= e($flash) ?>
        </div>
        <?php endif; ?>

        <h1 style="font-size:26px;font-weight:800;letter-spacing:-0.5px;margin-bottom:4px">
            Bienvenido, Departamento de RH 👋
        </h1>
        <p style="color:var(--text-secondary);margin-bottom:28px">Aquí tienes un resumen general del sistema</p>

        <!-- ══ STATS ══ -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-top">
                    <div>
                        <div class="stat-label">Total Solicitudes</div>
                        <div class="stat-value"><?= intval($total) ?></div>
                        <div class="stat-sub">+12% este mes</div>
                    </div>
                    <div class="stat-icon icon-blue"><i class="fa-solid fa-users"></i></div>
                </div>
                <div class="sparkline-wrap"><canvas class="sparkline" id="spark1"></canvas></div>
            </div>
            <div class="stat-card">
                <div class="stat-card-top">
                    <div>
                        <div class="stat-label">Considerados</div>
                        <div class="stat-value"><?= intval($considerados) ?></div>
                        <div class="stat-sub">+8% este mes</div>
                    </div>
                    <div class="stat-icon icon-green"><i class="fa-solid fa-circle-check"></i></div>
                </div>
                <div class="sparkline-wrap"><canvas class="sparkline" id="spark2"></canvas></div>
            </div>
            <div class="stat-card">
                <div class="stat-card-top">
                    <div>
                        <div class="stat-label">No Considerados</div>
                        <div class="stat-value"><?= intval($noConsiderados) ?></div>
                        <div class="stat-sub">+3% este mes</div>
                    </div>
                    <div class="stat-icon icon-yellow"><i class="fa-solid fa-circle-minus"></i></div>
                </div>
                <div class="sparkline-wrap"><canvas class="sparkline" id="spark3"></canvas></div>
            </div>
            <div class="stat-card">
                <div class="stat-card-top">
                    <div>
                        <div class="stat-label">No Revisados</div>
                        <div class="stat-value"><?= intval($noRevisados) ?></div>
                        <div class="stat-sub">+4% este mes</div>
                    </div>
                    <div class="stat-icon icon-purple"><i class="fa-solid fa-clock"></i></div>
                </div>
                <div class="sparkline-wrap"><canvas class="sparkline" id="spark4"></canvas></div>
            </div>
        </div>

        <!-- ══ MAIN GRID ══ -->
        <div class="dash-grid">

            <!-- Tabla solicitudes -->
            <div class="card-dark">
                <div class="section-header">
                    <div class="section-title">Solicitudes Recientes</div>
                    <a href="solicitudes.php" class="btn-outline" style="font-size:12px;padding:7px 14px">Ver todas</a>
                </div>

                <table class="rh-table">
                    <thead>
                        <tr>
                            <th>Aspirante</th>
                            <th>Correo</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($apps)): ?>
                        <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:32px">No hay solicitudes registradas</td></tr>
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
                            <td style="color:var(--text-secondary)"><?= e($row['correo']) ?></td>
                            <td style="color:var(--text-secondary)"><?= date('d/m/Y', strtotime($row['created_at'])) ?></td>
                            <td>
                                <?php
                                $badgeClass = match($row['estado']) {
                                    'Considerado'    => 'badge-ok',
                                    'No Considerado' => 'badge-no',
                                    default          => 'badge-review',
                                };
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= e($row['estado']) ?></span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <!-- Ver detalle -->
                                    <a href="ver_aspirante.php?id=<?= intval($row['id']) ?>" class="btn-icon" title="Ver detalle">
                                        <i class="fa-regular fa-eye"></i>
                                    </a>
                                    <!-- Cambiar estado -->
                                    <button class="btn-icon" title="Cambiar estado"
                                        onclick="openModal(<?= intval($row['id']) ?>, '<?= e($row['nombre'] . ' ' . $row['apellido']) ?>', '<?= e($row['estado']) ?>')">
                                        <i class="fa-regular fa-pen-to-square"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>

                <!-- Paginación -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page-1 ?>" class="page-btn"><i class="fa-solid fa-chevron-left" style="font-size:11px"></i></a>
                    <?php endif; ?>
                    <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
                        <a href="?page=<?= $p ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page+1 ?>" class="page-btn"><i class="fa-solid fa-chevron-right" style="font-size:11px"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Panel derecho -->
            <div style="display:flex;flex-direction:column;gap:20px">

                <!-- Donut chart -->
                <div class="card-dark">
                    <div class="section-title" style="margin-bottom:16px">Solicitudes por Estado</div>
                    <div class="donut-wrap">
                        <canvas id="donutChart" width="180" height="180" style="max-width:180px"></canvas>
                        <div class="donut-legend" style="width:100%">
                            <div class="legend-item">
                                <div class="legend-left">
                                    <div class="legend-dot" style="background:#818cf8"></div>
                                    No Revisado
                                </div>
                                <div class="legend-right">
                                    <?= intval($noRevisados) ?>
                                    <?php if ($total > 0): ?>
                                        <span style="color:var(--text-muted);font-weight:400;font-size:12px">
                                            (<?= round($noRevisados / $total * 100, 1) ?>%)
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="legend-item">
                                <div class="legend-left">
                                    <div class="legend-dot" style="background:#4ade80"></div>
                                    Considerado
                                </div>
                                <div class="legend-right">
                                    <?= intval($considerados) ?>
                                    <?php if ($total > 0): ?>
                                        <span style="color:var(--text-muted);font-weight:400;font-size:12px">
                                            (<?= round($considerados / $total * 100, 1) ?>%)
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="legend-item">
                                <div class="legend-left">
                                    <div class="legend-dot" style="background:#f87171"></div>
                                    No Considerado
                                </div>
                                <div class="legend-right">
                                    <?= intval($noConsiderados) ?>
                                    <?php if ($total > 0): ?>
                                        <span style="color:var(--text-muted);font-weight:400;font-size:12px">
                                            (<?= round($noConsiderados / $total * 100, 1) ?>%)
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actividad reciente -->
                <div class="card-dark">
                    <div class="section-title" style="margin-bottom:16px">Actividad Reciente</div>
                    <div class="activity-list">
                    <?php if (empty($activities)): ?>
                        <?php
                        // Mostrar actividad ficticia basada en datos reales si no hay log
                        foreach (array_slice($apps, 0, 4) as $i => $a):
                            $colors = ['#818cf8','#4ade80','#f87171','#818cf8'];
                        ?>
                        <div class="activity-item">
                            <div class="activity-dot" style="background:<?= $colors[$i % 4] ?>"></div>
                            <div>
                                <div class="activity-text">
                                    Nueva solicitud de <?= e($a['nombre'] . ' ' . $a['apellido']) ?>
                                </div>
                                <div class="activity-time"><?= timeAgo($a['created_at']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($apps)): ?>
                            <p style="color:var(--text-muted);font-size:13px;text-align:center">Sin actividad reciente</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php
                        $actColors = ['#818cf8','#4ade80','#f87171','#818cf8','#f59e0b'];
                        foreach ($activities as $i => $act):
                        ?>
                        <div class="activity-item">
                            <div class="activity-dot" style="background:<?= $actColors[$i % 5] ?>"></div>
                            <div>
                                <div class="activity-text"><?= e($act['details']) ?></div>
                                <div class="activity-time"><?= timeAgo($act['created_at']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div><!-- /page-body -->
</div><!-- /main-content -->

<!-- ══ MODAL CAMBIAR ESTADO ══ -->
<div class="modal-overlay" id="statusModal">
    <div class="modal-box" style="max-width:380px">
        <div class="modal-header">
            <div class="modal-title">Cambiar Estado</div>
            <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <p style="color:var(--text-secondary);font-size:13px;margin-bottom:18px" id="modalName"></p>

        <form method="POST" action="update-status.php">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" id="modalId">

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
                <button type="submit" class="btn-primary" style="flex:1;justify-content:center">
                    <i class="fa-solid fa-check"></i> Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Modal ──
function openModal(id, name, estado) {
    document.getElementById('modalId').value = id;
    document.getElementById('modalName').textContent = 'Aspirante: ' + name;
    document.getElementById('modalEstado').value = estado;
    document.getElementById('statusModal').classList.add('open');
}
function closeModal() {
    document.getElementById('statusModal').classList.remove('open');
}
document.getElementById('statusModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// ── Flash auto-hide ──
const flash = document.getElementById('flashMsg');
if (flash) setTimeout(() => flash.style.opacity = '0', 3500);

// ── Donut chart (canvas puro) ──
(function() {
    const canvas = document.getElementById('donutChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const data  = [<?= intval($noRevisados) ?>, <?= intval($considerados) ?>, <?= intval($noConsiderados) ?>];
    const colors= ['#818cf8','#4ade80','#f87171'];
    const total = data.reduce((a,b) => a+b, 0);

    if (total === 0) {
        ctx.fillStyle = '#2d3148';
        ctx.beginPath();
        ctx.arc(90, 90, 70, 0, Math.PI*2);
        ctx.fill();
        ctx.clearRect(0, 0, 180, 180);
        ctx.fillStyle = '#2d3148';
        ctx.beginPath();
        ctx.arc(90, 90, 70, 0, Math.PI*2);
        ctx.fill();
        return;
    }

    let start = -Math.PI / 2;
    data.forEach((val, i) => {
        if (val === 0) return;
        const slice = (val / total) * Math.PI * 2;
        ctx.beginPath();
        ctx.moveTo(90, 90);
        ctx.arc(90, 90, 70, start, start + slice);
        ctx.closePath();
        ctx.fillStyle = colors[i];
        ctx.fill();
        start += slice;
    });

    // Hole
    ctx.beginPath();
    ctx.arc(90, 90, 44, 0, Math.PI*2);
    ctx.fillStyle = '#1a1d27';
    ctx.fill();

    // Total text
    ctx.fillStyle = '#e8eaf0';
    ctx.font = 'bold 22px Plus Jakarta Sans, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(total, 90, 86);
    ctx.font = '11px Plus Jakarta Sans, sans-serif';
    ctx.fillStyle = '#6b7280';
    ctx.fillText('total', 90, 104);
})();

// ── Sparklines ──
function drawSparkline(id, color) {
    const canvas = document.getElementById(id);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const w = canvas.width = canvas.offsetWidth;
    const h = canvas.height = 40;
    const pts = Array.from({length:12}, () => Math.random() * 0.7 + 0.3);
    const max = Math.max(...pts), min = Math.min(...pts);
    const scaleY = v => h - ((v - min) / (max - min + 0.01)) * (h * 0.8) - h * 0.1;
    const stepX  = w / (pts.length - 1);

    // Gradient fill
    const grad = ctx.createLinearGradient(0,0,0,h);
    grad.addColorStop(0, color + '40');
    grad.addColorStop(1, color + '00');

    ctx.beginPath();
    ctx.moveTo(0, scaleY(pts[0]));
    pts.forEach((v, i) => ctx.lineTo(i * stepX, scaleY(v)));
    ctx.lineTo(w, h);
    ctx.lineTo(0, h);
    ctx.closePath();
    ctx.fillStyle = grad;
    ctx.fill();

    ctx.beginPath();
    ctx.moveTo(0, scaleY(pts[0]));
    pts.forEach((v, i) => ctx.lineTo(i * stepX, scaleY(v)));
    ctx.strokeStyle = color;
    ctx.lineWidth = 2;
    ctx.stroke();
}

drawSparkline('spark1','#4f7ef8');
drawSparkline('spark2','#22c55e');
drawSparkline('spark3','#f59e0b');
drawSparkline('spark4','#8b5cf6');
</script>

</body>
</html>