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

$max_cred = $alumno['max_creditos_semestre'];
$min_cred = 0;
$sem      = $alumno['semestre_actual'];  // semestre AL QUE VA A PASAR (actual)
$carrera_id = $alumno['carrera_id'];

// Periodo activo
$periodo = $conn->query("SELECT * FROM periodo_preinscripcion WHERE activo = 1 LIMIT 1")->fetch_assoc();
$hoy     = date('Y-m-d');
$periodo_activo = $periodo && $hoy >= $periodo['fecha_inicio'] && $hoy <= $periodo['fecha_fin'];

// Adeudos
$stmt = $conn->prepare("SELECT * FROM adeudos WHERE alumno_id = ? AND activo = 1");
$stmt->bind_param("i", $alumno_id);
$stmt->execute();
$adeudos       = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$tiene_adeudos = count($adeudos) > 0;

// ============================================================
// HISTORIAL ACADÉMICO
// Para cada materia tomar la MEJOR calificación y veces cursada
// ============================================================
$stmt = $conn->prepare("
    SELECT materia_id,
           MAX(calificacion) as mejor_cal,
           MAX(veces_cursada) as veces
    FROM calificaciones WHERE alumno_id = ? GROUP BY materia_id
");
$stmt->bind_param("i", $alumno_id);
$stmt->execute();
$hist_raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$aprobadas_ids = [];   // materia_id aprobadas
$reprobadas_ids = [];  // materia_id reprobadas (pendientes)
$ep_ids = [];          // materia_id en EP (reprobada 2+ veces)

foreach ($hist_raw as $h) {
    if ($h['mejor_cal'] >= 60) {
        $aprobadas_ids[] = $h['materia_id'];
    } else {
        $reprobadas_ids[] = $h['materia_id'];
        if ($h['veces'] >= 2) $ep_ids[] = $h['materia_id'];
    }
}

// ============================================================
// LÓGICA DE MATERIAS DISPONIBLES
//
// 1. Materias del semestre actual (sem) no aprobadas
// 2. Materias de semestres ANTERIORES reprobadas (sin importar cuál semestre)
// 3. Materias del semestre siguiente (sem+1) cuyo prerequisito esté aprobado
//    → Estas van con borde rojo (adelantadas)
// 4. Optativas: sin restricción de semestre
// ============================================================

// --- GRUPO 1 & 2: Semestre actual + atrasadas ---
// Materias obligatorias/tronco_comun del semestre actual que NO hayan sido aprobadas
$stmt = $conn->prepare("
    SELECT * FROM materias
    WHERE semestre = ?
      AND (carrera_id = ? OR carrera_id IS NULL)
      AND tipo IN ('obligatoria','tronco_comun')
      AND id NOT IN (SELECT materia_id FROM calificaciones WHERE alumno_id = ? AND calificacion >= 60)
");
$stmt->bind_param("iii", $sem, $carrera_id, $alumno_id);
$stmt->execute();
$mat_actuales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Materias reprobadas de semestres ANTERIORES (sem < semestre actual)
$mat_atrasadas = [];
if (!empty($reprobadas_ids)) {
    $ids_str = implode(',', array_map('intval', $reprobadas_ids));
    $stmt = $conn->prepare("
        SELECT * FROM materias
        WHERE id IN ($ids_str)
          AND semestre < ?
          AND tipo IN ('obligatoria','tronco_comun')
    ");
    $stmt->bind_param("i", $sem);
    $stmt->execute();
    $mat_atrasadas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// --- GRUPO 3: Semestre siguiente (adelantadas, borde rojo) ---
$sem_siguiente = $sem + 1;
$stmt = $conn->prepare("
    SELECT * FROM materias
    WHERE semestre = ?
      AND (carrera_id = ? OR carrera_id IS NULL)
      AND tipo IN ('obligatoria','tronco_comun')
      AND id NOT IN (SELECT materia_id FROM calificaciones WHERE alumno_id = ? AND calificacion >= 60)
");
$stmt->bind_param("iii", $sem_siguiente, $carrera_id, $alumno_id);
$stmt->execute();
$candidatas_adelantadas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Filtrar: solo las que NO tienen seriación pendiente
// (su prerequisito debe estar aprobado)
$mat_adelantadas = [];
foreach ($candidatas_adelantadas as $m) {
    $stmt_ser = $conn->prepare("
        SELECT s.prerequisito_id FROM seriacion s WHERE s.materia_id = ?
    ");
    $stmt_ser->bind_param("i", $m['id']);
    $stmt_ser->execute();
    $prereqs = $stmt_ser->get_result()->fetch_all(MYSQLI_ASSOC);

    $puede = true;
    foreach ($prereqs as $p) {
        if (!in_array($p['prerequisito_id'], $aprobadas_ids)) {
            $puede = false;
            break;
        }
    }
    if ($puede) {
        $m['adelantada'] = true;
        $mat_adelantadas[] = $m;
    }
}

// --- GRUPO 4: Optativas ---
$stmt = $conn->prepare("
    SELECT * FROM materias
    WHERE (carrera_id = ? OR carrera_id IS NULL)
      AND tipo = 'optativa'
      AND id NOT IN (SELECT materia_id FROM calificaciones WHERE alumno_id = ? AND calificacion >= 60)
    ORDER BY nombre
");
$stmt->bind_param("ii", $carrera_id, $alumno_id);
$stmt->execute();
$mat_optativas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// --- Combinar sin duplicados ---
$todas_raw = array_merge($mat_actuales, $mat_atrasadas, $mat_adelantadas, $mat_optativas);
$vistas = []; $materias = [];
foreach ($todas_raw as $m) {
    if (!in_array($m['id'], $vistas)) {
        $vistas[] = $m['id'];
        // Clasificar
        $m['es_ep']         = in_array($m['id'], $ep_ids);
        $m['es_atrasada']   = in_array($m['id'], $reprobadas_ids) && ($m['semestre'] < $sem);
        $m['es_adelantada'] = isset($m['adelantada']) && $m['adelantada'];
        $m['es_optativa']   = $m['tipo'] === 'optativa';
        $materias[] = $m;
    }
}

// Ordenar: atrasadas > actuales > adelantadas > optativas
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

// Flags especiales
$tiene_ep  = !empty($ep_ids);
$pct       = $alumno['total_creditos'] > 0 ? ($alumno['creditos_acumulados'] / $alumno['total_creditos']) * 100 : 0;
$alerta_sc = !$alumno['servicio_comunitario'] && $pct >= 40;
$alerta_sp = $alumno['servicio_comunitario'] && $pct >= 60;
$limite_mat = ($tiene_ep || $alerta_sc) ? 3 : 99;

// Preinscripcion existente
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
<title>Preinscripcion UABC</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:Arial,sans-serif;font-size:14px;background:#f0f2f5;color:#1a1a2e;}
nav{background:#1A3A5C;color:white;padding:0 2rem;height:56px;display:flex;align-items:center;justify-content:space-between;}
nav .brand{font-size:15px;font-weight:600;}
nav a{color:white;text-decoration:none;font-size:13px;opacity:.8;}
nav a:hover{opacity:1;}
.container{max-width:960px;margin:1.5rem auto;padding:0 1rem;}
.alerta{border-radius:10px;padding:12px 16px;margin-bottom:12px;font-size:13px;}
.alerta strong{display:block;margin-bottom:3px;}
.a-danger{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;}
.a-warning{background:#fffbeb;border:1px solid #fde68a;color:#92400e;}
.a-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;}
.a-info{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;}
.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:1.25rem;}
.card{background:white;border-radius:10px;border:1px solid #e5e7eb;padding:1rem;text-align:center;}
.card .lbl{font-size:12px;color:#6b7280;margin-bottom:4px;}
.card .val{font-size:20px;font-weight:600;color:#1A3A5C;}
.box{background:white;border-radius:10px;border:1px solid #e5e7eb;padding:1.2rem;margin-bottom:1.25rem;}
.box h2{font-size:15px;font-weight:600;margin-bottom:1rem;}
.pasos{display:flex;align-items:center;}
.paso{display:flex;flex-direction:column;align-items:center;flex:1;}
.pc{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;}
.hecho{background:#1A3A5C;color:white;}
.actual{background:#2E6DA4;color:white;}
.pend{background:#e5e7eb;color:#9ca3af;}
.pt{font-size:10px;margin-top:4px;text-align:center;color:#9ca3af;}
.pt.on{color:#1A3A5C;font-weight:600;}
.linea{flex:1;height:2px;margin-bottom:14px;}
.lh{background:#1A3A5C;}.lp{background:#e5e7eb;}

/* GRUPO SEPARATOR */
.grupo-label{font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;padding:12px 0 6px;display:flex;align-items:center;gap:8px;}
.grupo-label::after{content:'';flex:1;height:1px;background:#e5e7eb;}

/* MATERIA ITEM */
.mi{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:8px;margin-bottom:6px;border:1px solid #e5e7eb;background:white;}
.mi.ep{background:#fffbeb;border-color:#fde68a;}
.mi.atrasada{background:#f5f3ff;border-color:#c4b5fd;}
.mi.adelantada{
    background:#fff8f8;
    border:2px solid #ef4444;
    box-shadow:0 0 0 1px rgba(239,68,68,.15);
}
.mi.optativa{background:#f0fdf4;border-color:#bbf7d0;}
.mn{font-size:13px;font-weight:500;}
.mc{font-size:12px;color:#6b7280;margin-top:1px;}
.bdg{font-size:11px;padding:2px 8px;border-radius:6px;font-weight:500;white-space:nowrap;}
.bb{background:#eff6ff;color:#1e40af;}       /* actual */
.ba{background:#fffbeb;color:#92400e;}       /* EP */
.bp{background:#f5f3ff;color:#5b21b6;}       /* atrasada */
.br{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;} /* adelantada */
.bg{background:#f0fdf4;color:#166534;}       /* optativa */

/* AVISO ADELANTADA */
.aviso-adelantada{background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px 14px;font-size:12px;color:#b91c1c;margin-bottom:12px;display:flex;align-items:flex-start;gap:8px;}
.aviso-adelantada strong{display:block;}

.cbar{background:#f0f2f5;border-radius:10px;padding:1rem 1.2rem;margin-bottom:1.25rem;display:flex;align-items:center;justify-content:space-between;}
.cnum{font-size:22px;font-weight:700;color:#1A3A5C;}
.clbl{font-size:13px;color:#6b7280;}
.crng{font-size:12px;color:#9ca3af;}
.btn-p{background:#1A3A5C;color:white;border:none;border-radius:8px;padding:10px 24px;font-size:14px;font-weight:600;cursor:pointer;font-family:Arial,sans-serif;}
.btn-p:hover{background:#2E6DA4;}
.btn-s{background:white;color:#1a1a2e;border:1px solid #dde1e7;border-radius:8px;padding:10px 24px;font-size:14px;cursor:pointer;font-family:Arial,sans-serif;}
.acc{display:flex;gap:8px;justify-content:flex-end;margin-top:1rem;}
input[type="checkbox"]{width:16px;height:16px;accent-color:#1A3A5C;cursor:pointer;}
input[type="checkbox"]:disabled{cursor:not-allowed;opacity:.5;}
.plan-img{width:100%;border-radius:8px;border:1px solid #e5e7eb;}
.plan-link{display:inline-block;margin-top:8px;font-size:13px;color:#2E6DA4;text-decoration:none;}
.plan-link:hover{text-decoration:underline;}
.act-msg{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:1.5rem;text-align:center;}
.act-msg p{color:#166534;font-weight:600;font-size:15px;margin-bottom:4px;}
.act-msg span{color:#16a34a;font-size:13px;}
.aviso-auto{background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;font-size:13px;color:#92400e;margin-bottom:1rem;}
</style>
</head>
<body>
<nav>
  <span class="brand">Sistema de Preinscripcion — UABC</span>
  <div style="display:flex;align-items:center;gap:1.5rem;">
    <span style="font-size:13px;opacity:.85;"><?= htmlspecialchars($alumno['nombre']) ?></span>
    <a href="logout.php">Cerrar sesion</a>
  </div>
</nav>
<div class="container">

<?php if (!$periodo_activo): ?>
<div class="alerta a-warning"><strong>Periodo de preinscripcion no activo</strong>Consulta las fechas oficiales en Control Escolar.</div>
<?php endif; ?>

<?php if ($tiene_adeudos): ?>
<div class="alerta a-danger">
  <strong>Preinscripcion bloqueada — Adeudos pendientes</strong>
  <?php foreach ($adeudos as $a): ?><?= htmlspecialchars($a['descripcion']) ?> — <strong><?= htmlspecialchars($a['ventanilla']) ?></strong><br><?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($alerta_sc): ?>
<div class="alerta a-warning"><strong>Servicio comunitario pendiente</strong>Has alcanzado el 40% de creditos sin completar el servicio comunitario. Tu carga quedara limitada a 3 materias.</div>
<?php endif; ?>

<?php if ($alerta_sp): ?>
<div class="alerta a-success"><strong>Ya puedes iniciar tu servicio profesional</strong>Has alcanzado el 60% de los creditos de tu carrera. Acude a coordinacion.</div>
<?php endif; ?>

<?php if ($tiene_ep): ?>
<div class="alerta a-warning"><strong>Evaluacion Permanente</strong>Tienes materias reprobadas dos veces. Tu carga queda limitada a 3 actividades. Tu tutor sera notificado.</div>
<?php endif; ?>

<!-- RESUMEN -->
<div class="cards">
  <div class="card"><div class="lbl">Semestre actual</div><div class="val"><?= $sem ?>o</div></div>
  <div class="card"><div class="lbl">Creditos acumulados</div><div class="val"><?= $alumno['creditos_acumulados'] ?></div></div>
  <div class="card"><div class="lbl">Carrera</div><div class="val" style="font-size:13px;line-height:1.3;"><?= htmlspecialchars($alumno['carrera_clave']) ?></div></div>
  <div class="card"><div class="lbl">Adeudos</div><div class="val" style="color:<?= $tiene_adeudos?'#dc2626':'#16a34a' ?>"><?= $tiene_adeudos ? count($adeudos) : 'Ninguno' ?></div></div>
</div>

<!-- PROGRESO -->
<div class="box">
  <div style="font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:1rem;">Progreso del proceso</div>
  <div class="pasos">
    <div class="paso"><div class="pc <?= $tiene_adeudos?'actual':'hecho' ?>">✓</div><div class="pt on">Adeudos</div></div>
    <div class="linea <?= $tiene_adeudos?'lp':'lh' ?>"></div>
    <div class="paso"><div class="pc <?= $tiene_adeudos?'pend':'hecho' ?>">✓</div><div class="pt <?= !$tiene_adeudos?'on':'' ?>">Validacion</div></div>
    <div class="linea <?= $tiene_adeudos?'lp':'lh' ?>"></div>
    <div class="paso">
      <div class="pc <?= (!$tiene_adeudos && !$pi) ? 'actual' : ($pi ? 'hecho' : 'pend') ?>">3</div>
      <div class="pt <?= !$tiene_adeudos?'on':'' ?>">Seleccion</div>
    </div>
    <div class="linea <?= ($pi && in_array($pi['estado'],['activada','auto_activada']))?'lh':'lp' ?>"></div>
    <div class="paso">
      <div class="pc <?= ($pi && in_array($pi['estado'],['activada','auto_activada']))?'hecho':'pend' ?>">4</div>
      <div class="pt">Confirmacion</div>
    </div>
  </div>
</div>

<!-- PLAN DE ESTUDIOS -->
<div class="box">
  <h2>Plan de estudios — <?= htmlspecialchars($alumno['carrera_nombre']) ?></h2>
  <?php if ($alumno['imagen_plan']): ?>
    <img src="<?= htmlspecialchars($alumno['imagen_plan']) ?>" alt="Plan de estudios" class="plan-img">
  <?php else: ?>
    <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:1.5rem;text-align:center;">
      <p style="color:#6b7280;font-size:13px;margin-bottom:8px;">Imagen del plan de estudios no cargada aun.</p>
      <a href="http://fcqi.tij.uabc.mx/wp-content/uploads/2024/01/Mapa_ISTE.pdf" target="_blank" class="plan-link">Ver mapa curricular oficial PDF (UABC FCQI)</a>
    </div>
  <?php endif; ?>
</div>

<?php if ($pi && in_array($pi['estado'],['activada','auto_activada'])): ?>
<!-- TIRA YA ACTIVADA -->
<div class="act-msg">
  <p>Tu tira de materias ya esta activada</p>
  <span><?= $pi['estado']=='auto_activada' ? 'Activada automaticamente al cierre del periodo.' : 'Activada el '.date('d/m/Y H:i',strtotime($pi['fecha_activacion'])).'.' ?> Confirmacion enviada a <?= htmlspecialchars($alumno['correo']) ?></span>
  <br><br>
  <a href="confirmar.php?ver=1"><button class="btn-p" style="font-size:13px;padding:8px 18px;">Ver mi tira activada</button></a>
</div>

<?php elseif (!$tiene_adeudos && $periodo_activo): ?>

<?php if ($periodo): ?>
<div class="aviso-auto">Si no activas tu tira antes del <strong><?= date('d/m/Y',strtotime($periodo['fecha_fin'])) ?></strong>, el sistema la activara automaticamente con las materias que hayas seleccionado y te enviara un correo de notificacion.</div>
<?php endif; ?>

<!-- LEYENDA DE COLORES -->
<div class="box" style="padding:.9rem 1.2rem;">
  <div style="font-size:12px;font-weight:600;color:#6b7280;margin-bottom:.6rem;">Leyenda</div>
  <div style="display:flex;gap:12px;flex-wrap:wrap;font-size:12px;">
    <span style="display:flex;align-items:center;gap:5px;"><span style="width:12px;height:12px;border-radius:2px;background:#eff6ff;border:1px solid #bfdbfe;display:inline-block;"></span>Semestre <?= $sem ?> (actual)</span>
    <span style="display:flex;align-items:center;gap:5px;"><span style="width:12px;height:12px;border-radius:2px;background:#f5f3ff;border:1px solid #c4b5fd;display:inline-block;"></span>Atrasadas</span>
    <span style="display:flex;align-items:center;gap:5px;"><span style="width:12px;height:12px;border-radius:2px;background:#fff8f8;border:2px solid #ef4444;display:inline-block;"></span>Semestre <?= $sem_siguiente ?> (adelantadas — difíciles)</span>
    <span style="display:flex;align-items:center;gap:5px;"><span style="width:12px;height:12px;border-radius:2px;background:#f0fdf4;border:1px solid #bbf7d0;display:inline-block;"></span>Optativas</span>
    <span style="display:flex;align-items:center;gap:5px;"><span style="width:12px;height:12px;border-radius:2px;background:#fffbeb;border:1px solid #fde68a;display:inline-block;"></span>Eval. Permanente</span>
  </div>
</div>

<form method="POST" action="confirmar.php" id="fmMat">
<div class="box">
  <h2>Materias disponibles — Semestre <?= htmlspecialchars($periodo['semestre']) ?></h2>

  <?php if (empty($materias)): ?>
    <div class="alerta a-success">No tienes materias pendientes para este semestre.</div>
  <?php endif; ?>

  <?php
  // Renderizar por grupos
  $grupos = [
    ['label' => 'Materias atrasadas (semestres anteriores)', 'key' => 'es_atrasada', 'filter' => fn($m) => $m['es_atrasada'] && !$m['es_optativa']],
    ['label' => "Semestre $sem — Materias del periodo actual", 'key' => 'actual', 'filter' => fn($m) => !$m['es_atrasada'] && !$m['es_adelantada'] && !$m['es_optativa']],
    ['label' => "Semestre $sem_siguiente — Adelantadas (borde rojo = dificultad alta)", 'key' => 'es_adelantada', 'filter' => fn($m) => $m['es_adelantada']],
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
    <div><strong>Materias del siguiente semestre</strong>Puedes tomarlas porque no tienes seriación pendiente, pero son más difíciles. No se recomienda a menos que tengas buena base. Se marcan con borde rojo.</div>
  </div>
  <?php endif; ?>

  <?php foreach ($items as $m): ?>
  <?php
    $cls = 'mi';
    if ($m['es_ep'])          $cls .= ' ep';
    elseif ($m['es_adelantada']) $cls .= ' adelantada';
    elseif ($m['es_atrasada'])   $cls .= ' atrasada';
    elseif ($m['es_optativa'])   $cls .= ' optativa';

    if ($m['es_ep']) {
      $badge = '<span class="bdg ba">Eval. Permanente</span>';
    } elseif ($m['es_adelantada']) {
      $badge = '<span class="bdg br">Sem '.$m['semestre'].' — Adelantada</span>';
    } elseif ($m['es_atrasada']) {
      $badge = '<span class="bdg bp">Sem '.$m['semestre'].' — Atrasada</span>';
    } elseif ($m['es_optativa']) {
      $badge = '<span class="bdg bg">Optativa</span>';
    } else {
      $badge = '<span class="bdg bb">Sem '.$m['semestre'].'</span>';
    }
  ?>
  <div class="<?= $cls ?>">
    <input type="checkbox" name="materias[]" value="<?= $m['id'] ?>" data-cred="<?= $m['creditos'] ?>" onchange="upd()">
    <div style="flex:1">
      <div class="mn"><?= htmlspecialchars($m['nombre']) ?></div>
      <div class="mc">Clave: <?= htmlspecialchars($m['clave']) ?> · <?= $m['creditos'] ?> creditos</div>
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
  <div class="cnum" id="cnt">0</div>
</div>
<div class="acc">
  <a href="logout.php"><button type="button" class="btn-s">Cancelar</button></a>
  <button type="submit" class="btn-p" id="btnOk" disabled>Continuar</button>
</div>
<input type="hidden" name="semestre" value="<?= htmlspecialchars($periodo['semestre']) ?>">
</form>

<?php elseif (!$periodo_activo): ?>
<div class="alerta a-info"><strong>Fuera del periodo de preinscripcion</strong>Podras preinscribirte del <?= $periodo ? date('d/m/Y',strtotime($periodo['fecha_inicio'])) : 'fecha por definir' ?> al <?= $periodo ? date('d/m/Y',strtotime($periodo['fecha_fin'])) : '' ?>.</div>
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
  const ok=tc<=MAX;
  btn.disabled=!ok;
  document.getElementById('cnt').style.color=tc>MAX?'#dc2626':(ok?'#16a34a':'#1A3A5C');
}
</script>
</body>
</html>