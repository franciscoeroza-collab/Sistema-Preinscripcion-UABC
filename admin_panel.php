<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: admin_login.php"); exit(); }
require 'db.php';

// Estadisticas generales
$total_alumnos     = $conn->query("SELECT COUNT(*) as t FROM alumnos")->fetch_assoc()['t'];
$total_activos     = $conn->query("SELECT COUNT(*) as t FROM alumnos WHERE inscripcion_activa=1")->fetch_assoc()['t'];
$total_adeudos     = $conn->query("SELECT COUNT(DISTINCT alumno_id) as t FROM adeudos WHERE activo=1")->fetch_assoc()['t'];
$periodo           = $conn->query("SELECT * FROM periodo_preinscripcion WHERE activo=1 LIMIT 1")->fetch_assoc();

$total_preinsc     = 0;
$activadas         = 0;
$en_proceso        = 0;
$auto_act          = 0;
if ($periodo) {
    $sem = $periodo['semestre'];
    $total_preinsc = $conn->query("SELECT COUNT(*) as t FROM preinscripciones WHERE semestre='$sem'")->fetch_assoc()['t'];
    $activadas     = $conn->query("SELECT COUNT(*) as t FROM preinscripciones WHERE semestre='$sem' AND estado='activada'")->fetch_assoc()['t'];
    $en_proceso    = $conn->query("SELECT COUNT(*) as t FROM preinscripciones WHERE semestre='$sem' AND estado='en_proceso'")->fetch_assoc()['t'];
    $auto_act      = $conn->query("SELECT COUNT(*) as t FROM preinscripciones WHERE semestre='$sem' AND estado='auto_activada'")->fetch_assoc()['t'];
}

// EP
$ep_count = $conn->query("
    SELECT COUNT(DISTINCT alumno_id) as t FROM calificaciones
    WHERE veces_cursada >= 2 AND calificacion < 60
")->fetch_assoc()['t'];

// Sin servicio comunitario y >= 40%
$sc_count = $conn->query("
    SELECT COUNT(*) as t FROM alumnos a
    JOIN carreras c ON a.carrera_id = c.id
    WHERE a.servicio_comunitario = 0
    AND (a.creditos_acumulados / c.total_creditos) >= 0.4
")->fetch_assoc()['t'];

// Alumnos en proceso sin ninguna materia (riesgo)
$riesgo = 0;
if ($periodo) {
    $riesgo = $conn->query("
        SELECT COUNT(*) as t FROM preinscripciones p
        WHERE p.semestre='{$periodo['semestre']}' AND p.estado='en_proceso'
        AND (SELECT COUNT(*) FROM preinscripcion_materias pm WHERE pm.preinscripcion_id = p.id) = 0
    ")->fetch_assoc()['t'];
}

// Materias mas solicitadas
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

// Dias restantes
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
<title>Admin Panel — UABC FCQI</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
    --navy:#0f1e2e; --blue:#1A3A5C; --accent:#e8a020; --accent2:#2E6DA4;
    --border:#2a3f55; --text:#c8d8e8; --muted:#6a8aaa; --card:rgba(26,58,92,.2);
    --success:#16a34a; --danger:#dc2626; --warning:#d97706;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'IBM Plex Sans',sans-serif;background:var(--navy);color:var(--text);min-height:100vh;display:flex;}

.sidebar{width:220px;min-height:100vh;background:rgba(15,30,46,.9);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;top:0;left:0;}
.sb-logo{padding:1.5rem 1.2rem;border-bottom:1px solid var(--border);}
.sb-logo .mark{font-family:'IBM Plex Mono',monospace;font-size:11px;color:var(--accent);letter-spacing:.1em;}
.sb-logo h2{font-size:14px;font-weight:600;color:#fff;margin-top:4px;line-height:1.3;}
.sb-logo p{font-size:11px;color:var(--muted);margin-top:2px;}
.sb-nav{flex:1;padding:1rem 0;}
.sb-section{font-family:'IBM Plex Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:.12em;text-transform:uppercase;padding:8px 1.2rem 4px;}
.sb-link{display:flex;align-items:center;gap:10px;padding:9px 1.2rem;font-size:13px;color:var(--text);text-decoration:none;transition:all .15s;border-left:3px solid transparent;}
.sb-link:hover{background:rgba(46,109,164,.15);color:#fff;}
.sb-link.active{background:rgba(46,109,164,.2);border-left-color:var(--accent);color:#fff;font-weight:500;}
.sb-link .ico{width:16px;text-align:center;opacity:.7;}
.sb-footer{padding:1rem 1.2rem;border-top:1px solid var(--border);}
.sb-user{font-size:12px;color:var(--muted);margin-bottom:6px;}
.sb-user strong{color:var(--text);display:block;}
.sb-out{display:block;font-size:12px;color:var(--muted);text-decoration:none;padding:6px 0;}
.sb-out:hover{color:#fff;}

.main{margin-left:220px;flex:1;padding:2rem;}
.top-bar{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:2rem;}
.page-title{font-size:22px;font-weight:600;color:#fff;}
.page-sub{font-size:13px;color:var(--muted);margin-top:2px;}
.periodo-badge{background:rgba(232,160,32,.1);border:1px solid rgba(232,160,32,.3);border-radius:8px;padding:8px 14px;text-align:right;}
.periodo-badge .sem{font-size:13px;font-weight:600;color:var(--accent);}
.periodo-badge .dias{font-size:12px;color:var(--muted);}

.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:1.5rem;}
.stat{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:1.2rem;}
.stat .lbl{font-size:11px;font-family:'IBM Plex Mono',monospace;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;margin-bottom:8px;}
.stat .val{font-size:28px;font-weight:600;color:#fff;}
.stat .sub{font-size:12px;color:var(--muted);margin-top:4px;}
.stat.danger .val{color:#f87171;}
.stat.warning .val{color:#fbbf24;}
.stat.success .val{color:#4ade80;}

.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:1.5rem;}
.box{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:1.4rem;}
.box h3{font-size:13px;font-weight:600;color:#fff;margin-bottom:1rem;display:flex;align-items:center;gap:8px;}
.box h3 span{font-family:'IBM Plex Mono',monospace;font-size:10px;color:var(--muted);font-weight:400;}
/* ALERTAS */
.alert-item{display:flex;align-items:flex-start;gap:10px;padding:10px;background:rgba(255,255,255,.03);border-radius:8px;margin-bottom:6px;}
.alert-dot{width:8px;height:8px;border-radius:50%;margin-top:4px;flex-shrink:0;}
.dot-danger{background:#f87171;} .dot-warn{background:#fbbf24;} .dot-info{background:#60a5fa;}
.alert-item p{font-size:13px;color:var(--text);}
.alert-item .av{font-size:11px;color:var(--muted);margin-top:2px;}

.pbar-row{display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;}
.pbar{height:8px;border-radius:4px;background:rgba(255,255,255,.07);overflow:hidden;margin-bottom:10px;}
.pbar-fill{height:100%;border-radius:4px;transition:width .5s;}
/* MATERIAS TOP */
.mat-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);}
.mat-row:last-child{border:none;}
.mat-nm{font-size:12px;color:var(--text);}
.mat-cnt{font-family:'IBM Plex Mono',monospace;font-size:12px;color:var(--accent);}

.pstat{display:flex;gap:8px;margin-bottom:1rem;}
.pst{flex:1;text-align:center;background:rgba(255,255,255,.04);border-radius:8px;padding:10px 4px;}
.pst .n{font-size:20px;font-weight:600;color:#fff;}
.pst .l{font-size:11px;color:var(--muted);margin-top:2px;}
</style>
</head>
<body>
<?php include 'admin_sidebar.php'; ?>
<div class="main">
    <div class="top-bar">
        <div>
            <div class="page-title">Dashboard</div>
            <div class="page-sub">Resumen general del sistema de preinscripción</div>
        </div>
        <?php if ($periodo): ?>
        <div class="periodo-badge">
            <div class="sem">Semestre <?= htmlspecialchars($periodo['semestre']) ?></div>
            <div class="dias">
                <?php if ($dias_restantes === null): ?>Sin periodo activo
                <?php elseif ($dias_restantes < 0): ?>Periodo cerrado
                <?php elseif ($dias_restantes == 0): ?>Cierra hoy
                <?php else: ?>Cierra en <?= $dias_restantes ?> día<?= $dias_restantes!=1?'s':'' ?> (<?= date('d/m/Y',strtotime($periodo['fecha_fin'])) ?>)
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="stats">
        <div class="stat">
            <div class="lbl">Total alumnos</div>
            <div class="val"><?= $total_alumnos ?></div>
            <div class="sub"><?= $total_activos ?> con inscripción activa</div>
        </div>
        <div class="stat <?= $total_adeudos>0?'warning':'' ?>">
            <div class="lbl">Con adeudos</div>
            <div class="val"><?= $total_adeudos ?></div>
            <div class="sub">Preinscripción bloqueada</div>
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

    <div class="grid2">
        /* MATERIAS TOP */
        <!-- PREINSCRIPCIONES -->
        <div class="box">
            <h3>Preinscripciones <span><?= $periodo ? $periodo['semestre'] : 'Sin periodo' ?></span></h3>
            <?php if ($periodo): ?>
            <div class="pstat">
                <div class="pst"><div class="n"><?= $total_preinsc ?></div><div class="l">Total</div></div>
                <div class="pst"><div class="n" style="color:#4ade80"><?= $activadas ?></div><div class="l">Activadas</div></div>
                <div class="pst"><div class="n" style="color:#60a5fa"><?= $en_proceso ?></div><div class="l">En proceso</div></div>
                <div class="pst"><div class="n" style="color:#fbbf24"><?= $auto_act ?></div><div class="l">Auto-activ.</div></div>
            </div>
            <?php if ($total_preinsc > 0): ?>
            <div class="pbar-row"><span style="color:#4ade80">Activadas</span><span><?= $activadas + $auto_act ?> / <?= $total_preinsc ?></span></div>
            <div class="pbar"><div class="pbar-fill" style="width:<?= $total_preinsc>0?round(($activadas+$auto_act)/$total_preinsc*100):0 ?>%;background:linear-gradient(90deg,#16a34a,#4ade80)"></div></div>
            <div class="pbar-row"><span style="color:#60a5fa">Sin activar aún</span><span><?= $en_proceso ?> alumnos</span></div>
            <div class="pbar"><div class="pbar-fill" style="width:<?= $total_preinsc>0?round($en_proceso/$total_preinsc*100):0 ?>%;background:linear-gradient(90deg,#1e40af,#60a5fa)"></div></div>
            <?php endif; ?>
            <?php else: ?><p style="font-size:13px;color:var(--muted)">No hay periodo activo.</p><?php endif; ?>
        </div>

        <!-- ALERTAS -->
        <div class="box">
            <h3>Alertas del sistema</h3>
            <?php if ($riesgo > 0): ?>
            <div class="alert-item">
                <div class="alert-dot dot-danger"></div>
                <div><p><strong style="color:#f87171"><?= $riesgo ?> alumno<?= $riesgo!=1?'s':'' ?></strong> en proceso sin ninguna materia seleccionada</p><div class="av">Riesgo de auto-activación vacía al cierre</div></div>
            </div>
            <?php endif; ?>
            <?php if ($ep_count > 0): ?>
            <div class="alert-item">
                <div class="alert-dot dot-warn"></div>
                <div><p><strong style="color:#fbbf24"><?= $ep_count ?> alumno<?= $ep_count!=1?'s':'' ?></strong> en Evaluación Permanente</p><div class="av">Carga limitada a 3 actividades. Tutor notificado.</div></div>
            </div>
            <?php endif; ?>
            <?php if ($sc_count > 0): ?>
            <div class="alert-item">
                <div class="alert-dot dot-warn"></div>
                <div><p><strong style="color:#fbbf24"><?= $sc_count ?> alumno<?= $sc_count!=1?'s':'' ?></strong> sin servicio comunitario con ≥40% créditos</p><div class="av">Carga limitada a 3 materias</div></div>
            </div>
            <?php endif; ?>
            <?php if ($total_adeudos > 0): ?>
            <div class="alert-item">
                <div class="alert-dot dot-info"></div>
                <div><p><strong style="color:#60a5fa"><?= $total_adeudos ?> alumno<?= $total_adeudos!=1?'s':'' ?></strong> con adeudos activos</p><div class="av">Preinscripción bloqueada hasta saldar</div></div>
            </div>
            <?php endif; ?>
            <?php if ($riesgo==0 && $ep_count==0 && $sc_count==0 && $total_adeudos==0): ?>
            <div class="alert-item">
                <div class="alert-dot" style="background:#4ade80"></div>
                <div><p style="color:#4ade80">Sin alertas activas</p></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MATERIAS TOP -->
    <?php if (!empty($mat_top)): ?>
    <div class="box" style="max-width:500px;">
        <h3>Materias más solicitadas <span><?= $periodo['semestre'] ?></span></h3>
        <?php foreach ($mat_top as $m): ?>
        <div class="mat-row">
            <div><div class="mat-nm"><?= htmlspecialchars($m['nombre']) ?></div><div style="font-size:11px;color:var(--muted)"><?= $m['clave'] ?></div></div>
            <div class="mat-cnt"><?= $m['total'] ?> alumnos</div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>