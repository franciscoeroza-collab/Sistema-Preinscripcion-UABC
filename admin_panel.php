<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
require 'db.php';

// Estadísticas generales
$total_alumnos     = $conn->query("SELECT COUNT(*) as t FROM alumnos")->fetch_assoc()['t'];
$total_activos     = $conn->query("SELECT COUNT(*) as t FROM alumnos WHERE inscripcion_activa=1")->fetch_assoc()['t'];
$periodo           = $conn->query("SELECT * FROM periodo_preinscripcion WHERE activo=1 LIMIT 1")->fetch_assoc();

$total_preinsc = $activadas = $en_proceso = $auto_act = 0;
if ($periodo) {
    $sem = $periodo['semestre'];
    $total_preinsc = $conn->query("SELECT COUNT(*) as t FROM preinscripciones WHERE semestre='$sem'")->fetch_assoc()['t'];
    $activadas     = $conn->query("SELECT COUNT(*) as t FROM preinscripciones WHERE semestre='$sem' AND estado='activada'")->fetch_assoc()['t'];
    $en_proceso    = $conn->query("SELECT COUNT(*) as t FROM preinscripciones WHERE semestre='$sem' AND estado='en_proceso'")->fetch_assoc()['t'];
    $auto_act      = $conn->query("SELECT COUNT(*) as t FROM preinscripciones WHERE semestre='$sem' AND estado='auto_activada'")->fetch_assoc()['t'];
}

$ep_count = $conn->query("
    SELECT COUNT(DISTINCT alumno_id) as t FROM calificaciones
    WHERE veces_cursada >= 2 AND calificacion < 60
")->fetch_assoc()['t'];

$sc_count = $conn->query("
    SELECT COUNT(*) as t FROM alumnos a
    JOIN carreras c ON a.carrera_id = c.id
    WHERE a.servicio_comunitario = 0
    AND (a.creditos_acumulados / c.total_creditos) >= 0.4
")->fetch_assoc()['t'];

$riesgo = 0;
if ($periodo) {
    $riesgo = $conn->query("
        SELECT COUNT(*) as t FROM preinscripciones p
        WHERE p.semestre='{$periodo['semestre']}' AND p.estado='en_proceso'
        AND (SELECT COUNT(*) FROM preinscripcion_materias pm WHERE pm.preinscripcion_id = p.id) = 0
    ")->fetch_assoc()['t'];
}

$mat_top = [];
if ($periodo) {
    $mat_top = $conn->query("
        SELECT m.nombre, m.clave, COUNT(*) as total
        FROM preinscripcion_materias pm
        JOIN preinscripciones p ON pm.preinscripcion_id = p.id
        JOIN materias m ON pm.materia_id = m.id
        WHERE p.semestre='{$periodo['semestre']}'
        GROUP BY m.id ORDER BY total DESC LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);
}

$dias_restantes = null;
if ($periodo) {
    $hoy = new DateTime();
    $fin = new DateTime($periodo['fecha_fin']);
    $dias_restantes = $hoy <= $fin ? $hoy->diff($fin)->days : -1;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard — Admin UABC</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
    --white: #ffffff;
    --off-white: #f8f7f4;
    --surface: #f1efe9;
    --border: #ddd9d0;
    --border-dark: #c5bfb3;
    --navy: #1A3A5C;
    --navy-light: #2E6DA4;
    --navy-bg: #e8f0f8;
    --accent: #c47d0e;
    --accent-light: #e8a020;
    --accent-bg: #fef3e2;
    --text: #1c1917;
    --text-mid: #44403c;
    --muted: #78716c;
    --success: #15803d;
    --success-bg: #f0fdf4;
    --success-border: #bbf7d0;
    --warning: #92400e;
    --warning-bg: #fffbeb;
    --warning-border: #fde68a;
    --danger: #b91c1c;
    --danger-bg: #fef2f2;
    --danger-border: #fecaca;
    --info: #1e40af;
    --info-bg: #eff6ff;
    --info-border: #bfdbfe;
}
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'DM Sans', sans-serif; background: var(--off-white); color: var(--text); min-height: 100vh; display: flex; }

/* SIDEBAR */
.sidebar {
    width: 240px; min-height: 100vh;
    background: var(--white);
    border-right: 1.5px solid var(--border);
    display: flex; flex-direction: column;
    position: fixed; top: 0; left: 0;
    z-index: 10;
}
.sb-logo {
    padding: 1.6rem 1.4rem 1.4rem;
    border-bottom: 1.5px solid var(--border);
}
.sb-logo .mark {
    font-size: 10px; font-weight: 600;
    letter-spacing: .12em; text-transform: uppercase;
    color: var(--accent); margin-bottom: 6px;
}
.sb-logo h2 {
    font-family: 'Fraunces', serif;
    font-size: 16px; font-weight: 600; color: var(--navy); line-height: 1.3;
}
.sb-logo p { font-size: 12px; color: var(--muted); margin-top: 3px; }
.sb-nav { flex: 1; padding: 1rem 0; }
.sb-section {
    font-size: 10px; font-weight: 600;
    letter-spacing: .1em; text-transform: uppercase;
    color: var(--muted); padding: 10px 1.4rem 5px;
}
.sb-link {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 1.4rem; font-size: 13px; font-weight: 500;
    color: var(--text-mid); text-decoration: none;
    transition: all .15s; border-left: 3px solid transparent;
}
.sb-link:hover { background: var(--surface); color: var(--navy); }
.sb-link.active {
    background: var(--navy-bg);
    border-left-color: var(--navy);
    color: var(--navy); font-weight: 600;
}
.sb-link .ico { width: 18px; text-align: center; font-size: 14px; opacity: .7; }
.sb-footer { padding: 1rem 1.4rem; border-top: 1.5px solid var(--border); }
.sb-user { font-size: 12px; color: var(--muted); margin-bottom: 8px; }
.sb-user strong { display: block; color: var(--text-mid); font-weight: 600; }
.sb-out {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 12px; color: var(--muted); text-decoration: none;
    padding: 6px 10px; border-radius: 7px;
    border: 1px solid var(--border); transition: all .15s;
}
.sb-out:hover { background: var(--danger-bg); border-color: var(--danger-border); color: var(--danger); }

/* MAIN */
.main { margin-left: 240px; flex: 1; padding: 2rem 2.4rem; }
.top-bar { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; }
.page-title { font-family: 'Fraunces', serif; font-size: 26px; font-weight: 600; color: var(--navy); }
.page-sub { font-size: 13px; color: var(--muted); margin-top: 3px; }

.periodo-pill {
    background: var(--accent-bg);
    border: 1.5px solid #f0c060;
    border-radius: 10px;
    padding: 10px 16px;
    text-align: right;
}
.periodo-pill .sem { font-size: 13px; font-weight: 700; color: var(--accent); }
.periodo-pill .dias { font-size: 12px; color: var(--text-mid); margin-top: 2px; }

/* STATS GRID */
.stats { display: grid; grid-template-columns: repeat(4,1fr); gap: 14px; margin-bottom: 1.5rem; }
.stat {
    background: var(--white);
    border: 1.5px solid var(--border);
    border-radius: 12px; padding: 1.2rem 1.3rem;
}
.stat .lbl {
    font-size: 11px; font-weight: 600;
    letter-spacing: .06em; text-transform: uppercase;
    color: var(--muted); margin-bottom: 8px;
}
.stat .val { font-family: 'Fraunces', serif; font-size: 32px; font-weight: 700; color: var(--navy); }
.stat .sub { font-size: 12px; color: var(--muted); margin-top: 5px; }
.stat.warning .val { color: var(--accent); }
.stat.danger .val { color: var(--danger); }

/* 2-COL GRID */
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 1.5rem; }
.box {
    background: var(--white);
    border: 1.5px solid var(--border);
    border-radius: 12px; padding: 1.4rem;
}
.box h3 {
    font-size: 14px; font-weight: 600; color: var(--navy);
    margin-bottom: 1.1rem;
    display: flex; align-items: center; justify-content: space-between;
}
.box h3 span { font-size: 11px; color: var(--muted); font-weight: 400; }

/* PREINSCRIPCIONES */
.pstat { display: flex; gap: 8px; margin-bottom: 1rem; }
.pst {
    flex: 1; text-align: center;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px; padding: 10px 4px;
}
.pst .n { font-family: 'Fraunces', serif; font-size: 22px; font-weight: 700; color: var(--navy); }
.pst .l { font-size: 11px; color: var(--muted); margin-top: 2px; }
.pst.c-ok .n  { color: var(--success); }
.pst.c-pro .n { color: var(--navy-light); }
.pst.c-aut .n { color: var(--accent); }

.pbar-row { display: flex; justify-content: space-between; font-size: 12px; color: var(--muted); margin-bottom: 4px; }
.pbar { height: 7px; border-radius: 4px; background: var(--surface); overflow: hidden; margin-bottom: 10px; }
.pbar-fill { height: 100%; border-radius: 4px; transition: width .5s; }

/* ALERTAS */
.alert-item {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 10px 12px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px; margin-bottom: 8px;
}
.alert-dot { width: 8px; height: 8px; border-radius: 50%; margin-top: 5px; flex-shrink: 0; }
.dot-danger  { background: var(--danger); }
.dot-warn    { background: var(--accent-light); }
.dot-info    { background: var(--navy-light); }
.dot-ok      { background: var(--success); }
.alert-item p  { font-size: 13px; color: var(--text-mid); }
.alert-item .av { font-size: 12px; color: var(--muted); margin-top: 3px; }

/* MATERIAS TOP */
.mat-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 9px 0; border-bottom: 1px solid var(--surface);
}
.mat-row:last-child { border: none; }
.mat-nm { font-size: 13px; color: var(--text-mid); font-weight: 500; }
.mat-key { font-size: 11px; color: var(--muted); margin-top: 1px; }
.mat-cnt {
    font-size: 12px; font-weight: 700;
    color: var(--navy); background: var(--navy-bg);
    padding: 3px 10px; border-radius: 20px;
}
</style>
</head>
<body>
<?php
$current = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sb-logo">
        <div class="mark">UABC · FCQI</div>
        <h2>Panel de Administración</h2>
        <p>Control Escolar</p>
    </div>
    <nav class="sb-nav">
        <div class="sb-section">Principal</div>
        <a href="admin_panel.php" class="sb-link <?= $current=='admin_panel.php'?'active':'' ?>">
            <span class="ico">◈</span> Dashboard
        </a>
        <div class="sb-section">Gestión</div>
        <a href="admin_periodos.php" class="sb-link <?= $current=='admin_periodos.php'?'active':'' ?>">
            <span class="ico">◷</span> Periodos
        </a>
        <a href="admin_alumnos.php" class="sb-link <?= $current=='admin_alumnos.php'?'active':'' ?>">
            <span class="ico">◉</span> Alumnos
        </a>
        <a href="admin_preinscripciones.php" class="sb-link <?= $current=='admin_preinscripciones.php'?'active':'' ?>">
            <span class="ico">◎</span> Preinscripciones
        </a>
        <div class="sb-section">Configuración</div>
        <a href="admin_cuenta.php" class="sb-link <?= $current=='admin_cuenta.php'?'active':'' ?>">
            <span class="ico">◐</span> Mi cuenta
        </a>
    </nav>
    <div class="sb-footer">
        <div class="sb-user">
            Sesión activa
            <strong><?= htmlspecialchars($_SESSION['admin_usuario'] ?? '') ?></strong>
        </div>
        <a href="logout.php" class="sb-out">← Cerrar sesión</a>
    </div>
</div>

<div class="main">
    <div class="top-bar">
        <div>
            <div class="page-title">Dashboard</div>
            <div class="page-sub">Resumen general del sistema de preinscripción</div>
        </div>
        <?php if ($periodo): ?>
        <div class="periodo-pill">
            <div class="sem">Semestre <?= htmlspecialchars($periodo['semestre']) ?></div>
            <div class="dias">
                <?php if ($dias_restantes === null): ?>Sin periodo activo
                <?php elseif ($dias_restantes < 0): ?>Periodo cerrado
                <?php elseif ($dias_restantes == 0): ?>Cierra hoy
                <?php else: ?>Cierra en <?= $dias_restantes ?> día<?= $dias_restantes!=1?'s':'' ?> · <?= date('d/m/Y',strtotime($periodo['fecha_fin'])) ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- STATS -->
    <div class="stats">
        <div class="stat">
            <div class="lbl">Total alumnos</div>
            <div class="val"><?= $total_alumnos ?></div>
            <div class="sub"><?= $total_activos ?> con inscripción activa</div>
        </div>

        <div class="stat <?= $ep_count>0?'danger':'' ?>">
            <div class="lbl">Eval. Permanente</div>
            <div class="val"><?= $ep_count ?></div>
            <div class="sub">Materia reprobada 2+ veces</div>
        </div>
        <div class="stat <?= $sc_count>0?'warning':'' ?>">
            <div class="lbl">Sin serv. comunitario</div>
            <div class="val"><?= $sc_count ?></div>
            <div class="sub">≥40% créditos sin completar</div>
        </div>
    </div>

    <!-- GRID 2 COL -->
    <div class="grid2">
        <!-- PREINSCRIPCIONES -->
        <div class="box">
            <h3>Preinscripciones <span><?= $periodo ? $periodo['semestre'] : 'Sin periodo' ?></span></h3>
            <?php if ($periodo): ?>
            <div class="pstat">
                <div class="pst"><div class="n"><?= $total_preinsc ?></div><div class="l">Total</div></div>
                <div class="pst c-ok"><div class="n"><?= $activadas ?></div><div class="l">Activadas</div></div>
                <div class="pst c-pro"><div class="n"><?= $en_proceso ?></div><div class="l">En proceso</div></div>
                <div class="pst c-aut"><div class="n"><?= $auto_act ?></div><div class="l">Auto-activ.</div></div>
            </div>
            <?php if ($total_preinsc > 0): ?>
            <div class="pbar-row"><span>Activadas</span><span><?= $activadas + $auto_act ?> / <?= $total_preinsc ?></span></div>
            <div class="pbar"><div class="pbar-fill" style="width:<?= round(($activadas+$auto_act)/$total_preinsc*100) ?>%;background:linear-gradient(90deg,#15803d,#22c55e)"></div></div>
            <div class="pbar-row"><span>Sin activar aún</span><span><?= $en_proceso ?> alumnos</span></div>
            <div class="pbar"><div class="pbar-fill" style="width:<?= round($en_proceso/$total_preinsc*100) ?>%;background:linear-gradient(90deg,#1e40af,#60a5fa)"></div></div>
            <?php endif; ?>
            <?php else: ?><p style="font-size:13px;color:var(--muted)">No hay periodo activo.</p><?php endif; ?>
        </div>

        <!-- ALERTAS -->
        <div class="box">
            <h3>Alertas del sistema</h3>
            <?php if ($riesgo > 0): ?>
            <div class="alert-item">
                <div class="alert-dot dot-danger"></div>
                <div><p><strong style="color:var(--danger)"><?= $riesgo ?> alumno<?= $riesgo!=1?'s':'' ?></strong> en proceso sin ninguna materia seleccionada</p><div class="av">Riesgo de auto-activación vacía al cierre</div></div>
            </div>
            <?php endif; ?>
            <?php if ($ep_count > 0): ?>
            <div class="alert-item">
                <div class="alert-dot dot-warn"></div>
                <div><p><strong style="color:var(--accent)"><?= $ep_count ?> alumno<?= $ep_count!=1?'s':'' ?></strong> en Evaluación Permanente</p><div class="av">Carga limitada a 3 actividades. Tutor notificado.</div></div>
            </div>
            <?php endif; ?>
            <?php if ($sc_count > 0): ?>
            <div class="alert-item">
                <div class="alert-dot dot-warn"></div>
                <div><p><strong style="color:var(--accent)"><?= $sc_count ?> alumno<?= $sc_count!=1?'s':'' ?></strong> sin servicio comunitario con ≥40% créditos</p><div class="av">Carga limitada a 3 materias</div></div>
            </div>
            <?php endif; ?>

            <?php if ($riesgo==0 && $ep_count==0 && $sc_count==0): ?>
            <div class="alert-item">
                <div class="alert-dot dot-ok"></div>
                <div><p style="color:var(--success)">Sin alertas activas</p></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MATERIAS TOP -->
    <?php if (!empty($mat_top)): ?>
    <div class="box" style="max-width:520px;">
        <h3>Materias más solicitadas <span><?= $periodo['semestre'] ?></span></h3>
        <?php foreach ($mat_top as $m): ?>
        <div class="mat-row">
            <div>
                <div class="mat-nm"><?= htmlspecialchars($m['nombre']) ?></div>
                <div class="mat-key"><?= htmlspecialchars($m['clave']) ?></div>
            </div>
            <div class="mat-cnt"><?= $m['total'] ?> alumnos</div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>