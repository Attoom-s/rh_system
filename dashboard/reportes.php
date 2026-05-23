<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
if ($_SESSION['role'] !== 'rh')   { header("Location: ../aspirante/index.php"); exit(); }

// Estadísticas generales
$total          = $pdo->query("SELECT COUNT(*) FROM applicants")->fetchColumn();
$considerados   = $pdo->query("SELECT COUNT(*) FROM applicants WHERE estado='Considerado'")->fetchColumn();
$noConsiderados = $pdo->query("SELECT COUNT(*) FROM applicants WHERE estado='No Considerado'")->fetchColumn();
$noRevisados    = $pdo->query("SELECT COUNT(*) FROM applicants WHERE estado='No Revisado'")->fetchColumn();

// Por género
$generos = $pdo->query("SELECT genero, COUNT(*) as total FROM applicants GROUP BY genero")->fetchAll();

// Por nacionalidad (top 5)
$nacionalidades = $pdo->query("SELECT nacionalidad, COUNT(*) as total FROM applicants GROUP BY nacionalidad ORDER BY total DESC LIMIT 5")->fetchAll();

// Por mes (últimos 6 meses)
$porMes = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as mes, COUNT(*) as total 
    FROM applicants 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY mes ORDER BY mes ASC
")->fetchAll();

// Por estado civil
$estadosCiviles = $pdo->query("SELECT COALESCE(estado_civil,'No especificado') as ec, COUNT(*) as total FROM applicants GROUP BY ec")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reportes — RH System</title>
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
    <a href="reportes.php" class="menu-item active">
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
        <h1 style="font-size:24px;font-weight:800;letter-spacing:-0.5px;margin-bottom:4px">Reportes</h1>
        <p style="color:var(--text-secondary);margin-bottom:28px">Análisis general del proceso de reclutamiento</p>

        <!-- Resumen rápido -->
        <div class="stats-grid" style="margin-bottom:28px">
            <div class="stat-card">
                <div class="stat-card-top">
                    <div><div class="stat-label">Total Solicitudes</div><div class="stat-value"><?= intval($total) ?></div></div>
                    <div class="stat-icon icon-blue"><i class="fa-solid fa-users"></i></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-top">
                    <div><div class="stat-label">Tasa de Considerados</div>
                    <div class="stat-value"><?= $total > 0 ? round($considerados/$total*100) : 0 ?>%</div></div>
                    <div class="stat-icon icon-green"><i class="fa-solid fa-percent"></i></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-top">
                    <div><div class="stat-label">Pendientes de Revisión</div><div class="stat-value"><?= intval($noRevisados) ?></div></div>
                    <div class="stat-icon icon-yellow"><i class="fa-solid fa-clock"></i></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-top">
                    <div><div class="stat-label">No Considerados</div><div class="stat-value"><?= intval($noConsiderados) ?></div></div>
                    <div class="stat-icon icon-purple"><i class="fa-solid fa-circle-xmark"></i></div>
                </div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">

            <!-- Solicitudes por mes -->
            <div class="card-dark">
                <div class="section-title" style="margin-bottom:20px">Solicitudes por Mes</div>
                <canvas id="barChart" height="200"></canvas>
                <?php if (empty($porMes)): ?>
                <p style="color:var(--text-muted);text-align:center;padding:30px 0;font-size:13px">Sin datos aún</p>
                <?php endif; ?>
            </div>

            <!-- Estado de solicitudes -->
            <div class="card-dark">
                <div class="section-title" style="margin-bottom:20px">Distribución por Estado</div>
                <canvas id="donutEstado" width="200" height="200" style="display:block;margin:0 auto 16px"></canvas>
                <div style="display:flex;flex-direction:column;gap:8px">
                    <div style="display:flex;justify-content:space-between;font-size:13px">
                        <span style="display:flex;align-items:center;gap:6px"><span style="width:10px;height:10px;border-radius:50%;background:#818cf8;display:inline-block"></span>No Revisado</span>
                        <strong><?= intval($noRevisados) ?> (<?= $total>0?round($noRevisados/$total*100,1):0 ?>%)</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:13px">
                        <span style="display:flex;align-items:center;gap:6px"><span style="width:10px;height:10px;border-radius:50%;background:#4ade80;display:inline-block"></span>Considerado</span>
                        <strong><?= intval($considerados) ?> (<?= $total>0?round($considerados/$total*100,1):0 ?>%)</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:13px">
                        <span style="display:flex;align-items:center;gap:6px"><span style="width:10px;height:10px;border-radius:50%;background:#f87171;display:inline-block"></span>No Considerado</span>
                        <strong><?= intval($noConsiderados) ?> (<?= $total>0?round($noConsiderados/$total*100,1):0 ?>%)</strong>
                    </div>
                </div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

            <!-- Por género -->
            <div class="card-dark">
                <div class="section-title" style="margin-bottom:16px">Por Género</div>
                <?php if (empty($generos)): ?>
                    <p style="color:var(--text-muted);text-align:center;padding:30px 0;font-size:13px">Sin datos aún</p>
                <?php else: ?>
                    <?php foreach ($generos as $g): ?>
                    <?php $pct = $total > 0 ? round($g['total']/$total*100) : 0; ?>
                    <div style="margin-bottom:14px">
                        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px">
                            <span><?= e($g['genero'] ?: 'No especificado') ?></span>
                            <span style="color:var(--text-secondary)"><?= intval($g['total']) ?> (<?= $pct ?>%)</span>
                        </div>
                        <div style="height:8px;background:var(--border);border-radius:8px;overflow:hidden">
                            <div style="height:100%;width:<?= $pct ?>%;background:var(--accent-blue);border-radius:8px;transition:width 0.6s"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Top nacionalidades -->
            <div class="card-dark">
                <div class="section-title" style="margin-bottom:16px">Top Nacionalidades</div>
                <?php if (empty($nacionalidades)): ?>
                    <p style="color:var(--text-muted);text-align:center;padding:30px 0;font-size:13px">Sin datos aún</p>
                <?php else: ?>
                    <?php $maxNac = $nacionalidades[0]['total'] ?? 1; ?>
                    <?php $colores = ['#4f7ef8','#22c55e','#f59e0b','#8b5cf6','#ef4444']; ?>
                    <?php foreach ($nacionalidades as $i => $n): ?>
                    <?php $pct = round($n['total']/$maxNac*100); ?>
                    <div style="margin-bottom:14px">
                        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px">
                            <span><?= e($n['nacionalidad']) ?></span>
                            <span style="color:var(--text-secondary)"><?= intval($n['total']) ?></span>
                        </div>
                        <div style="height:8px;background:var(--border);border-radius:8px;overflow:hidden">
                            <div style="height:100%;width:<?= $pct ?>%;background:<?= $colores[$i % 5] ?>;border-radius:8px;transition:width 0.6s"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
// ── Donut estado ──
(function() {
    const c = document.getElementById('donutEstado');
    if (!c) return;
    const ctx = c.getContext('2d');
    const data   = [<?= intval($noRevisados) ?>, <?= intval($considerados) ?>, <?= intval($noConsiderados) ?>];
    const colors = ['#818cf8','#4ade80','#f87171'];
    const total  = data.reduce((a,b)=>a+b,0);
    if (!total) { return; }
    let start = -Math.PI/2;
    data.forEach((v,i) => {
        if (!v) return;
        const slice = v/total*Math.PI*2;
        ctx.beginPath(); ctx.moveTo(100,100);
        ctx.arc(100,100,80,start,start+slice);
        ctx.closePath(); ctx.fillStyle=colors[i]; ctx.fill();
        start += slice;
    });
    ctx.beginPath(); ctx.arc(100,100,50,0,Math.PI*2);
    ctx.fillStyle='#1a1d27'; ctx.fill();
    ctx.fillStyle='#e8eaf0'; ctx.font='bold 20px Plus Jakarta Sans,sans-serif';
    ctx.textAlign='center'; ctx.textBaseline='middle';
    ctx.fillText(total,100,95);
    ctx.font='11px Plus Jakarta Sans,sans-serif'; ctx.fillStyle='#6b7280';
    ctx.fillText('total',100,112);
})();

// ── Bar chart por mes ──
(function() {
    const c = document.getElementById('barChart');
    if (!c) return;
    const labels = <?= json_encode(array_column($porMes, 'mes')) ?>;
    const values = <?= json_encode(array_map(fn($r)=>intval($r['total']), $porMes)) ?>;
    if (!values.length) return;

    const ctx = c.getContext('2d');
    const W = c.width = c.parentElement.offsetWidth - 48;
    const H = c.height = 200;
    const pad = {top:20, right:20, bottom:40, left:40};
    const chartW = W - pad.left - pad.right;
    const chartH = H - pad.top  - pad.bottom;
    const maxV = Math.max(...values, 1);
    const barW = Math.min(chartW / values.length * 0.6, 40);
    const step = chartW / values.length;

    ctx.strokeStyle = 'rgba(255,255,255,0.06)';
    ctx.lineWidth = 1;
    for (let i=0; i<=4; i++) {
        const y = pad.top + chartH - (i/4)*chartH;
        ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(pad.left+chartW, y); ctx.stroke();
        ctx.fillStyle='#4b5563'; ctx.font='11px Plus Jakarta Sans,sans-serif';
        ctx.textAlign='right';
        ctx.fillText(Math.round(maxV*i/4), pad.left-6, y+4);
    }

    values.forEach((v, i) => {
        const x = pad.left + i*step + step/2 - barW/2;
        const barH = (v/maxV)*chartH;
        const y = pad.top + chartH - barH;
        const grad = ctx.createLinearGradient(0, y, 0, y+barH);
        grad.addColorStop(0, '#4f7ef8');
        grad.addColorStop(1, 'rgba(79,126,248,0.3)');
        ctx.fillStyle = grad;
        ctx.beginPath();
        ctx.roundRect(x, y, barW, barH, [4,4,0,0]);
        ctx.fill();

        ctx.fillStyle='#6b7280'; ctx.font='11px Plus Jakarta Sans,sans-serif';
        ctx.textAlign='center';
        const label = labels[i] ? labels[i].substring(5) + '/' + labels[i].substring(2,4) : '';
        ctx.fillText(label, pad.left + i*step + step/2, H - 10);
    });
})();
</script>
</body>
</html>
