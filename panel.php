<?php
session_start();
if (!isset($_SESSION['alumno_id'])) { header("Location: login.php"); exit(); }
require 'db.php';
$alumno_id = $_SESSION['alumno_id'];

// Datos del alumno y carrera
$stmt = $conn->prepare("
    SELECT a.*, c.nombre AS carrera_nombre, c.max_creditos_semestre,
           c.clave AS carrera_clave, c.imagen_plan, c.total_creditos
    FROM alumnos a JOIN carreras c ON a.carrera_id = c.id WHERE a.id = ?");
$stmt->bind_param("i", $alumno_id);
$stmt->execute();
$alumno = $stmt->get_result()->fetch_assoc();

$max_cred   = $alumno['max_creditos_semestre'];
$min_cred   = 0;
$sem        = $alumno['semestre_actual'];
$carrera_id = $alumno['carrera_id'];

// Periodo activo
$periodo       = $conn->query("SELECT * FROM periodo_preinscripcion WHERE activo = 1 LIMIT 1")->fetch_assoc();
$hoy           = date('Y-m-d');
$periodo_activo = $periodo && $hoy >= $periodo['fecha_inicio'] && $hoy <= $periodo['fecha_fin'];


// Historial académico
$stmt = $conn->prepare("
    SELECT materia_id, MAX(calificacion) as mejor_cal, MAX(veces_cursada) as veces
    FROM calificaciones WHERE alumno_id = ? GROUP BY materia_id
");
$stmt->bind_param("i", $alumno_id);
$stmt->execute();
$hist_raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$aprobadas_ids = []; $reprobadas_ids = []; $ep_ids = [];
foreach ($hist_raw as $h) {
    if ($h['mejor_cal'] >= 60) {
        $aprobadas_ids[] = $h['materia_id'];
    } else {
        $reprobadas_ids[] = $h['materia_id'];
        if ($h['veces'] >= 2) $ep_ids[] = $h['materia_id'];
    }
}
$tiene_ep = !empty($ep_ids);

// Materias del semestre actual
$stmt = $conn->prepare("
    SELECT * FROM materias
    WHERE semestre = ? AND (carrera_id = ? OR carrera_id IS NULL)
      AND tipo IN ('obligatoria','tronco_comun')
      AND id NOT IN (SELECT materia_id FROM calificaciones WHERE alumno_id = ? AND calificacion >= 60)
");
$stmt->bind_param("iii", $sem, $carrera_id, $alumno_id);
$stmt->execute();
$mat_actuales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Materias atrasadas
$mat_atrasadas = [];
if (!empty($reprobadas_ids)) {
    $ids_str = implode(',', array_map('intval', $reprobadas_ids));
    $stmt = $conn->prepare("SELECT * FROM materias WHERE id IN ($ids_str) AND semestre < ? AND tipo IN ('obligatoria','tronco_comun')");
    $stmt->bind_param("i", $sem);
    $stmt->execute();
    $mat_atrasadas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Materias adelantadas
$sem_siguiente = $sem + 1;
$stmt = $conn->prepare("
    SELECT * FROM materias
    WHERE semestre = ? AND (carrera_id = ? OR carrera_id IS NULL)
      AND tipo IN ('obligatoria','tronco_comun')
      AND id NOT IN (SELECT materia_id FROM calificaciones WHERE alumno_id = ? AND calificacion >= 60)
");
$stmt->bind_param("iii", $sem_siguiente, $carrera_id, $alumno_id);
$stmt->execute();
$candidatas_adelantadas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$mat_adelantadas = [];
foreach ($candidatas_adelantadas as $m) {
    $stmt_ser = $conn->prepare("SELECT s.prerequisito_id FROM seriacion s WHERE s.materia_id = ?");
    $stmt_ser->bind_param("i", $m['id']);
    $stmt_ser->execute();
    $prereqs = $stmt_ser->get_result()->fetch_all(MYSQLI_ASSOC);
    $puede = true;
    foreach ($prereqs as $p) {
        if (!in_array($p['prerequisito_id'], $aprobadas_ids)) { $puede = false; break; }
    }
    if ($puede) { $m['adelantada'] = true; $mat_adelantadas[] = $m; }
}

// Optativas
$stmt = $conn->prepare("
    SELECT * FROM materias
    WHERE (carrera_id = ? OR carrera_id IS NULL) AND tipo = 'optativa'
      AND id NOT IN (SELECT materia_id FROM calificaciones WHERE alumno_id = ? AND calificacion >= 60)
    ORDER BY nombre
");
$stmt->bind_param("ii", $carrera_id, $alumno_id);
$stmt->execute();
$mat_optativas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Combinar sin duplicados
$todas_raw = array_merge($mat_actuales, $mat_atrasadas, $mat_adelantadas, $mat_optativas);
$vistas = []; $materias = [];
foreach ($todas_raw as $m) {
    if (!in_array($m['id'], $vistas)) {
        $vistas[] = $m['id'];
        $m['es_ep']         = in_array($m['id'], $ep_ids);
        $m['es_atrasada']   = in_array($m['id'], $reprobadas_ids) && ($m['semestre'] < $sem);
        $m['es_adelantada'] = isset($m['adelantada']) && $m['adelantada'];
        $m['es_optativa']   = $m['tipo'] === 'optativa';
        $materias[] = $m;
    }
}

usort($materias, function($a, $b) {
    $order = function($m) {
        if ($m['es_atrasada']) return 0;
        if (!$m['es_adelantada'] && !$m['es_optativa']) return 1;
        if ($m['es_adelantada']) return 2;
        return 3;
    };
    $oa = $order($a); $ob = $order($b);
    if ($oa !== $ob) return $oa - $ob;
    return $a['semestre'] - $b['semestre'];
});

$pct       = $alumno['total_creditos'] > 0 ? ($alumno['creditos_acumulados'] / $alumno['total_creditos']) * 100 : 0;
$alerta_sc = !$alumno['servicio_comunitario'] && $pct >= 40;
$alerta_sp = $alumno['servicio_comunitario'] && $pct >= 60;
$limite_mat = ($tiene_ep || $alerta_sc) ? 3 : 99;

$pi = null;
if ($periodo) {
    $stmt = $conn->prepare("SELECT * FROM preinscripciones WHERE alumno_id = ? AND semestre = ? AND estado IN ('en_proceso','activada','auto_activada') ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("is", $alumno_id, $periodo['semestre']);
    $stmt->execute();
    $pi = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Preinscripción UABC</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
    --white: #ffffff;
    --off-white: #f8f7f4;
    --surface: #f1efe9;
    --border: #ddd9d0;
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
    --purple-bg: #f5f3ff;
    --purple-border: #c4b5fd;
    --green-bg: #f0fdf4;
    --green-border: #bbf7d0;
}
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'DM Sans', sans-serif; background: var(--off-white); color: var(--text); min-height: 100vh; }

/* NAV */
nav {
    background: var(--white);
    border-bottom: 1.5px solid var(--border);
    padding: 0 2rem;
    height: 58px;
    display: flex; align-items: center; justify-content: space-between;
    position: sticky; top: 0; z-index: 10;
}
.nav-brand {
    display: flex; align-items: center; gap: 12px;
}
.nav-logo {
    width: 36px; height: 36px;
    background: var(--navy);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Fraunces', serif;
    font-size: 16px; font-weight: 700; color: white;
}
.nav-brand-text { font-size: 14px; font-weight: 600; color: var(--navy); }
.nav-brand-sub  { font-size: 11px; color: var(--muted); }
.nav-right { display: flex; align-items: center; gap: 1.4rem; }
.nav-user  { font-size: 13px; color: var(--text-mid); font-weight: 500; }
.nav-out {
    font-size: 12px; color: var(--muted); text-decoration: none;
    padding: 6px 12px; border-radius: 7px;
    border: 1px solid var(--border);
    transition: all .15s;
}
.nav-out:hover { background: var(--danger-bg); border-color: var(--danger-border); color: var(--danger); }

/* CONTAINER */
.container { max-width: 960px; margin: 1.8rem auto; padding: 0 1.5rem 3rem; }

/* ALERTAS */
.alerta {
    border-radius: 10px; padding: 12px 16px;
    margin-bottom: 12px; font-size: 13px;
    border: 1.5px solid;
}
.alerta strong { display: block; margin-bottom: 3px; font-weight: 600; }
.a-danger  { background: var(--danger-bg);  border-color: var(--danger-border);  color: var(--danger); }
.a-warning { background: var(--warning-bg); border-color: var(--warning-border); color: var(--warning); }
.a-success { background: var(--success-bg); border-color: var(--success-border); color: var(--success); }
.a-info    { background: var(--info-bg);    border-color: var(--info-border);    color: var(--info); }

/* CARDS DE RESUMEN */
.cards { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; margin-bottom: 1.4rem; }
.card {
    background: var(--white);
    border-radius: 10px; border: 1.5px solid var(--border);
    padding: 1rem 1.1rem; text-align: center;
}
.card .lbl { font-size: 11px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; }
.card .val { font-family: 'Fraunces', serif; font-size: 24px; font-weight: 700; color: var(--navy); }

/* BOX GENERAL */
.box {
    background: var(--white);
    border-radius: 10px; border: 1.5px solid var(--border);
    padding: 1.3rem; margin-bottom: 1.4rem;
}
.box h2 {
    font-family: 'Fraunces', serif;
    font-size: 16px; font-weight: 600;
    color: var(--navy); margin-bottom: 1rem;
}

/* PASOS */
.pasos { display: flex; align-items: center; }
.paso  { display: flex; flex-direction: column; align-items: center; flex: 1; }
.pc {
    width: 30px; height: 30px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700;
}
.hecho  { background: var(--navy); color: white; }
.actual { background: var(--navy-light); color: white; }
.pend   { background: var(--surface); color: var(--muted); border: 1.5px solid var(--border); }
.pt     { font-size: 11px; margin-top: 5px; text-align: center; color: var(--muted); }
.pt.on  { color: var(--navy); font-weight: 600; }
.linea  { flex: 1; height: 2px; margin-bottom: 14px; }
.lh { background: var(--navy); } .lp { background: var(--border); }

/* GRUPO SEPARATOR */
.grupo-label {
    font-size: 11px; font-weight: 700; color: var(--muted);
    text-transform: uppercase; letter-spacing: .07em;
    padding: 14px 0 6px; display: flex; align-items: center; gap: 10px;
}
.grupo-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }

/* MATERIA ITEM */
.mi {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 13px; border-radius: 9px; margin-bottom: 6px;
    border: 1.5px solid var(--border); background: var(--white);
    transition: border-color .15s;
}
.mi:hover { border-color: var(--navy-light); }
.mi.ep         { background: var(--warning-bg); border-color: var(--warning-border); }
.mi.atrasada   { background: var(--purple-bg); border-color: var(--purple-border); }
.mi.adelantada { background: var(--danger-bg); border: 2px solid var(--danger); }
.mi.optativa   { background: var(--green-bg); border-color: var(--green-border); }
.mn { font-size: 13px; font-weight: 600; color: var(--text); }
.mc { font-size: 12px; color: var(--muted); margin-top: 2px; }
.bdg {
    font-size: 11px; padding: 3px 9px; border-radius: 6px;
    font-weight: 600; white-space: nowrap;
}
.bb { background: var(--navy-bg); color: var(--navy); }
.ba { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning-border); }
.bp { background: var(--purple-bg); color: #5b21b6; border: 1px solid var(--purple-border); }
.br { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger-border); }
.bg { background: var(--green-bg); color: var(--success); border: 1px solid var(--green-border); }

/* AVISO ADELANTADAS */
.aviso-adelantada {
    background: var(--danger-bg); border: 1.5px solid var(--danger-border);
    border-radius: 9px; padding: 11px 14px; font-size: 13px;
    color: var(--danger); margin-bottom: 12px;
    display: flex; align-items: flex-start; gap: 10px;
}
.aviso-adelantada strong { display: block; margin-bottom: 2px; }

/* BARRA CRÉDITOS */
.cbar {
    background: var(--white); border: 1.5px solid var(--border);
    border-radius: 10px; padding: 1rem 1.3rem;
    margin-bottom: 1.4rem; display: flex; align-items: center; justify-content: space-between;
    position: sticky; bottom: 1.2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,.08);
}
.cnum { font-family: 'Fraunces', serif; font-size: 28px; font-weight: 700; color: var(--navy); }
.clbl { font-size: 13px; color: var(--text-mid); font-weight: 500; }
.crng { font-size: 12px; color: var(--muted); margin-top: 2px; }

.btn-p {
    background: var(--navy); color: white; border: none;
    border-radius: 9px; padding: 11px 28px;
    font-size: 14px; font-weight: 600; cursor: pointer;
    font-family: 'DM Sans', sans-serif; transition: background .2s;
}
.btn-p:hover { background: var(--navy-light); }
.btn-p:disabled { background: var(--border); color: var(--muted); cursor: not-allowed; }
.btn-s {
    background: var(--white); color: var(--text-mid);
    border: 1.5px solid var(--border); border-radius: 9px;
    padding: 11px 28px; font-size: 14px; cursor: pointer;
    font-family: 'DM Sans', sans-serif; transition: all .15s;
    text-decoration: none; display: inline-block;
}
.btn-s:hover { border-color: var(--navy-light); color: var(--navy); }
.acc { display: flex; gap: 8px; justify-content: flex-end; margin-top: .8rem; }

input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--navy); cursor: pointer; flex-shrink: 0; }
input[type="checkbox"]:disabled { cursor: not-allowed; opacity: .4; }

.plan-img  { width: 100%; border-radius: 8px; border: 1.5px solid var(--border); }
.plan-link { display: inline-block; margin-top: 8px; font-size: 13px; color: var(--navy-light); text-decoration: none; font-weight: 500; }
.plan-link:hover { text-decoration: underline; }

.act-msg {
    background: var(--success-bg); border: 1.5px solid var(--success-border);
    border-radius: 10px; padding: 1.5rem; text-align: center; margin-bottom: 1.4rem;
}
.act-msg p    { color: var(--success); font-weight: 700; font-size: 15px; margin-bottom: 4px; }
.act-msg span { color: var(--success); font-size: 13px; }

.aviso-auto {
    background: var(--accent-bg); border: 1.5px solid #f0c060;
    border-radius: 9px; padding: 10px 14px; font-size: 13px;
    color: var(--accent); margin-bottom: 1.2rem;
}

/* LEYENDA */
.leyenda { display: flex; gap: 12px; flex-wrap: wrap; font-size: 12px; color: var(--text-mid); }
.ley-item { display: flex; align-items: center; gap: 5px; }
.ley-dot  { width: 12px; height: 12px; border-radius: 3px; border: 1.5px solid; flex-shrink: 0; }
</style>
</head>
<body>

<nav>
    <div class="nav-brand">
        <div class="nav-logo">U</div>
        <div>
            <div class="nav-brand-text">Sistema de Preinscripción — UABC</div>
            <div class="nav-brand-sub">Facultad de Ciencias Químicas e Ingeniería</div>
        </div>
    </div>
    <div class="nav-right">
        <span class="nav-user"><?= htmlspecialchars($alumno['nombre']) ?></span>
        <a href="logout.php" class="nav-out">Cerrar sesión</a>
    </div>
</nav>

<div class="container">

<?php if (!$periodo_activo): ?>
<div class="alerta a-warning"><strong>Periodo de preinscripción no activo</strong>Consulta las fechas oficiales en Control Escolar.</div>
<?php endif; ?>

<?php if ($alerta_sc): ?>
<div class="alerta a-warning"><strong>Servicio comunitario pendiente</strong>Has alcanzado el 40% de créditos sin completar el servicio comunitario. Tu carga quedará limitada a 3 materias.</div>
<?php endif; ?>

<?php if ($alerta_sp): ?>
<div class="alerta a-success"><strong>Ya puedes iniciar tu servicio profesional</strong>Has alcanzado el 60% de los créditos de tu carrera. Acude a coordinación.</div>
<?php endif; ?>

<?php if ($tiene_ep): ?>
<div class="alerta a-warning"><strong>Evaluación Permanente</strong>Tienes materias reprobadas dos veces. Tu carga queda limitada a 3 actividades. Tu tutor será notificado.</div>
<?php endif; ?>

<!-- RESUMEN -->
<div class="cards">
    <div class="card"><div class="lbl">Semestre</div><div class="val"><?= $sem ?>°</div></div>
    <div class="card"><div class="lbl">Créditos acumulados</div><div class="val"><?= $alumno['creditos_acumulados'] ?></div></div>
    <div class="card"><div class="lbl">Carrera</div><div class="val" style="font-size:14px;line-height:1.3;padding-top:4px"><?= htmlspecialchars($alumno['carrera_clave']) ?></div></div>

</div>

<!-- PROGRESO -->
<div class="box">
    <div style="font-size:12px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:1rem;">Progreso del proceso</div>
    <div class="pasos">
        <div class="paso"><div class="pc hecho">✓</div><div class="pt on">Validación</div></div>
        <div class="linea lh"></div>
        <div class="paso">
            <div class="pc <?= (!$pi) ? 'actual' : ($pi ? 'hecho' : 'pend') ?>">2</div>
            <div class="pt on">Selección</div>
        </div>
        <div class="linea <?= ($pi && in_array($pi['estado'],['activada','auto_activada']))?'lh':'lp' ?>"></div>
        <div class="paso">
            <div class="pc <?= ($pi && in_array($pi['estado'],['activada','auto_activada']))?'hecho':'pend' ?>">3</div>
            <div class="pt">Confirmación</div>
        </div>
    </div>
</div>

<!-- PLAN DE ESTUDIOS -->
<div class="box">
    <h2>Plan de estudios — <?= htmlspecialchars($alumno['carrera_nombre']) ?></h2>
    <?php if ($alumno['imagen_plan']): ?>
        <img src="<?= htmlspecialchars($alumno['imagen_plan']) ?>" alt="Plan de estudios" class="plan-img">
    <?php else: ?>
        <div style="background:var(--surface);border:1.5px solid var(--border);border-radius:8px;padding:1.5rem;text-align:center;">
            <p style="color:var(--muted);font-size:13px;margin-bottom:8px;">Imagen del plan de estudios no cargada aún.</p>
            <a href="http://fcqi.tij.uabc.mx/wp-content/uploads/2024/01/Mapa_ISTE.pdf" target="_blank" class="plan-link">Ver mapa curricular oficial PDF (UABC FCQI)</a>
        </div>
    <?php endif; ?>
</div>

<?php if ($pi && in_array($pi['estado'],['activada','auto_activada'])): ?>
<!-- TIRA YA ACTIVADA -->
<div class="act-msg">
    <p>Tu tira de materias ya está activada</p>
    <span><?= $pi['estado']=='auto_activada' ? 'Activada automáticamente al cierre del periodo.' : 'Activada el '.date('d/m/Y H:i',strtotime($pi['fecha_activacion'])).'.' ?> Confirmación enviada a <?= htmlspecialchars($alumno['correo']) ?></span>
    <br><br>
    <a href="confirmar.php?ver=1"><button class="btn-p" style="font-size:13px;padding:9px 20px;">Ver mi tira activada</button></a>
</div>

<?php elseif ($periodo_activo): ?>

<?php if ($periodo): ?>
<div class="aviso-auto">Si no activas tu tira antes del <strong><?= date('d/m/Y',strtotime($periodo['fecha_fin'])) ?></strong>, el sistema la activará automáticamente con las materias que hayas seleccionado y te enviará un correo de notificación.</div>
<?php endif; ?>

<!-- LEYENDA -->
<div class="box" style="padding:.9rem 1.2rem;margin-bottom:1rem;">
    <div style="font-size:12px;font-weight:600;color:var(--muted);margin-bottom:.7rem;text-transform:uppercase;letter-spacing:.05em;">Leyenda</div>
    <div class="leyenda">
        <div class="ley-item"><div class="ley-dot" style="background:var(--navy-bg);border-color:#bfdbfe;"></div>Semestre <?= $sem ?> (actual)</div>
        <div class="ley-item"><div class="ley-dot" style="background:var(--purple-bg);border-color:var(--purple-border);"></div>Atrasadas</div>
        <div class="ley-item"><div class="ley-dot" style="background:var(--danger-bg);border-color:var(--danger);border-width:2px;"></div>Sem. <?= $sem_siguiente ?> (adelantadas)</div>
        <div class="ley-item"><div class="ley-dot" style="background:var(--green-bg);border-color:var(--green-border);"></div>Optativas</div>
        <div class="ley-item"><div class="ley-dot" style="background:var(--warning-bg);border-color:var(--warning-border);"></div>Eval. Permanente</div>
    </div>
</div>

<form method="POST" action="confirmar.php" id="fmMat">
<div class="box">
    <h2>Materias disponibles — Semestre <?= htmlspecialchars($periodo['semestre']) ?></h2>

    <?php if (empty($materias)): ?>
    <div class="alerta a-success">No tienes materias pendientes para este semestre.</div>
    <?php endif; ?>

    <?php
    $grupos = [
        ['label' => 'Materias atrasadas (semestres anteriores)', 'key' => 'es_atrasada', 'filter' => fn($m) => $m['es_atrasada'] && !$m['es_optativa']],
        ['label' => "Semestre $sem — Materias del periodo actual", 'key' => 'actual', 'filter' => fn($m) => !$m['es_atrasada'] && !$m['es_adelantada'] && !$m['es_optativa']],
        ['label' => "Semestre $sem_siguiente — Adelantadas", 'key' => 'es_adelantada', 'filter' => fn($m) => $m['es_adelantada']],
        ['label' => 'Materias optativas', 'key' => 'optativa', 'filter' => fn($m) => $m['es_optativa']],
    ];

    foreach ($grupos as $grupo):
        $items = array_filter($materias, $grupo['filter']);
        if (empty($items)) continue;
    ?>
    <div class="grupo-label"><?= $grupo['label'] ?></div>

    <?php if ($grupo['key'] === 'es_adelantada'): ?>
    <div class="aviso-adelantada">
        <span>⚠️</span>
        <div><strong>Materias del siguiente semestre</strong>Puedes tomarlas porque no tienes seriación pendiente, pero son más difíciles. No se recomienda a menos que tengas buena base.</div>
    </div>
    <?php endif; ?>

    <?php foreach ($items as $m):
        $cls = 'mi';
        if ($m['es_ep'])               $cls .= ' ep';
        elseif ($m['es_adelantada'])   $cls .= ' adelantada';
        elseif ($m['es_atrasada'])     $cls .= ' atrasada';
        elseif ($m['es_optativa'])     $cls .= ' optativa';

        if ($m['es_ep'])               $badge = '<span class="bdg ba">Eval. Permanente</span>';
        elseif ($m['es_adelantada'])   $badge = '<span class="bdg br">Sem '.$m['semestre'].' — Adelantada</span>';
        elseif ($m['es_atrasada'])     $badge = '<span class="bdg bp">Sem '.$m['semestre'].' — Atrasada</span>';
        elseif ($m['es_optativa'])     $badge = '<span class="bdg bg">Optativa</span>';
        else                           $badge = '<span class="bdg bb">Sem '.$m['semestre'].'</span>';
    ?>
    <div class="<?= $cls ?>">
        <input type="checkbox" name="materias[]" value="<?= $m['id'] ?>" data-cred="<?= $m['creditos'] ?>" onchange="upd()">
        <div style="flex:1">
            <div class="mn"><?= htmlspecialchars($m['nombre']) ?></div>
            <div class="mc">Clave: <?= htmlspecialchars($m['clave']) ?> · <?= $m['creditos'] ?> créditos</div>
        </div>
        <?= $badge ?>
    </div>
    <?php endforeach; ?>
    <?php endforeach; ?>
</div>

<div class="cbar">
    <div>
        <div class="clbl">Créditos seleccionados</div>
        <div class="crng">Máximo permitido: <?= $max_cred ?> créditos</div>
    </div>
    <div style="display:flex;align-items:center;gap:1.5rem;">
        <div class="cnum" id="cnt">0</div>
        <div class="acc" style="margin:0;">
            <a href="logout.php"><button type="button" class="btn-s">Cancelar</button></a>
            <button type="submit" class="btn-p" id="btnOk" disabled>Continuar →</button>
        </div>
    </div>
</div>
<input type="hidden" name="semestre" value="<?= htmlspecialchars($periodo['semestre']) ?>">
</form>

<?php elseif (!$periodo_activo): ?>
<div class="alerta a-info"><strong>Fuera del periodo de preinscripción</strong>Podrás preinscribirte del <?= $periodo ? date('d/m/Y',strtotime($periodo['fecha_inicio'])) : 'fecha por definir' ?> al <?= $periodo ? date('d/m/Y',strtotime($periodo['fecha_fin'])) : '' ?>.</div>
<?php endif; ?>

</div>

<script>
const MIN=<?= $min_cred ?>,MAX=<?= $max_cred ?>,LIM=<?= $limite_mat ?>;
function upd(){
    let tc=0,tm=0;
    document.querySelectorAll('input[name="materias[]"]').forEach(c=>{if(c.checked){tc+=parseInt(c.dataset.cred);tm++;}});
    document.getElementById('cnt').textContent=tc;
    const btn=document.getElementById('btnOk');
    document.querySelectorAll('input[name="materias[]"]').forEach(c=>{if(!c.checked)c.disabled=tm>=LIM;});
    const ok=tc<=MAX && tc>0;
    btn.disabled=!ok;
    document.getElementById('cnt').style.color=tc>MAX?'var(--danger)':(tc>0?'var(--success)':'var(--navy)');
}
</script>
</body>
</html>