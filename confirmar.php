<?php
session_start();
if (!isset($_SESSION['alumno_id'])) { header("Location: login.php"); exit(); }
require 'db.php';
$alumno_id = $_SESSION['alumno_id'];
 
$stmt = $conn->prepare("SELECT a.*, c.nombre AS carrera_nombre, c.clave AS carrera_clave FROM alumnos a JOIN carreras c ON a.carrera_id = c.id WHERE a.id = ?");
$stmt->bind_param("i", $alumno_id);
$stmt->execute();
$alumno = $stmt->get_result()->fetch_assoc();
 
$periodo = $conn->query("SELECT * FROM periodo_preinscripcion WHERE activo = 1 LIMIT 1")->fetch_assoc();
 
// Ver tira ya activada
$ver_activada = isset($_GET['ver']) && $_GET['ver'] == 1;
$preinscripcion_id = null;
 
if ($ver_activada) {
    $stmt = $conn->prepare("SELECT * FROM preinscripciones WHERE alumno_id = ? AND semestre = ? AND estado IN ('activada','auto_activada') ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("is", $alumno_id, $periodo['semestre']);
    $stmt->execute();
    $pi = $stmt->get_result()->fetch_assoc();
    if ($pi) {
        $preinscripcion_id = $pi['id'];
        $stmt2 = $conn->prepare("SELECT pm.*, m.nombre, m.clave, m.creditos FROM preinscripcion_materias pm JOIN materias m ON pm.materia_id = m.id WHERE pm.preinscripcion_id = ?");
        $stmt2->bind_param("i", $preinscripcion_id);
        $stmt2->execute();
        $materias_activadas = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $total_cred = array_sum(array_column($materias_activadas, 'creditos'));
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: panel.php"); exit();
}
 
$materias_ids = $_POST['materias'] ?? [];
$semestre     = $_POST['semestre'] ?? $periodo['semestre'];
$activar      = isset($_POST['activar']);
$activada     = false;
$materias_sel = [];
$total_cred   = 0;
 
if (!$ver_activada) {
    foreach ($materias_ids as $mid) {
        $mid = (int)$mid;
        $stmt = $conn->prepare("SELECT * FROM materias WHERE id = ?");
        $stmt->bind_param("i", $mid);
        $stmt->execute();
        $m = $stmt->get_result()->fetch_assoc();
        if ($m) { $materias_sel[] = $m; $total_cred += $m['creditos']; }
    }
}
 
if ($activar) {
    // Crear preinscripcion
    $stmt = $conn->prepare("INSERT INTO preinscripciones (alumno_id, semestre, estado, fecha_activacion) VALUES (?, ?, 'activada', NOW())");
    $stmt->bind_param("is", $alumno_id, $semestre);
    $stmt->execute();
    $pid = $conn->insert_id;
    foreach ($materias_sel as $m) {
        $stmt = $conn->prepare("INSERT INTO preinscripcion_materias (preinscripcion_id, materia_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $pid, $m['id']);
        $stmt->execute();
    }
    // Simular envio de correo (en produccion usarias PHPMailer)
    $asunto  = "Confirmacion de preinscripcion — Semestre $semestre";
    $cuerpo  = "Hola {$alumno['nombre']},\n\nTu tira de materias para el semestre $semestre ha sido activada exitosamente.\n\n";
    $cuerpo .= "Materias inscritas:\n";
    foreach ($materias_sel as $m) { $cuerpo .= "- {$m['nombre']} ({$m['clave']}) — {$m['creditos']} creditos\n"; }
    $cuerpo .= "\nTotal: $total_cred creditos\n\nAtentamente,\nSistema de Preinscripcion UABC";
    // mail($alumno['correo'], $asunto, $cuerpo); // Descomentar en produccion con servidor de correo
    $activada = true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Confirmacion — Preinscripcion UABC</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:Arial,sans-serif;font-size:14px;background:#f0f2f5;color:#1a1a2e;}
nav{background:#1A3A5C;color:white;padding:0 2rem;height:56px;display:flex;align-items:center;justify-content:space-between;}
nav .brand{font-size:15px;font-weight:600;}
nav a{color:white;text-decoration:none;font-size:13px;opacity:.8;}
.container{max-width:680px;margin:2rem auto;padding:0 1rem;}
.card{background:white;border-radius:12px;border:1px solid #e5e7eb;padding:1.8rem;}
.icono{width:50px;height:50px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-size:22px;}
h1{font-size:16px;font-weight:600;text-align:center;margin-bottom:4px;}
.sub{font-size:13px;color:#6b7280;text-align:center;margin-bottom:1.5rem;}
hr{border:none;border-top:1px solid #e5e7eb;margin:1.2rem 0;}
.fila{display:flex;justify-content:space-between;padding:5px 0;font-size:13px;}
.fila .et{color:#6b7280;}
.fila .vl{font-weight:500;}
.mrow{display:flex;justify-content:space-between;align-items:center;padding:9px 12px;background:#f9fafb;border-radius:8px;margin-bottom:6px;}
.mn{font-size:13px;font-weight:500;}
.mc{font-size:12px;color:#6b7280;margin-top:2px;}
.mc2{font-size:12px;color:#6b7280;}
.total{display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:#eff6ff;border-radius:8px;margin-top:4px;}
.total span:first-child{font-size:13px;font-weight:600;color:#1e40af;}
.total span:last-child{font-size:16px;font-weight:700;color:#1A3A5C;}
.btn-p{background:#1A3A5C;color:white;border:none;border-radius:8px;padding:11px;font-size:14px;font-weight:600;cursor:pointer;font-family:Arial,sans-serif;width:100%;margin-top:1rem;}
.btn-p:hover{background:#2E6DA4;}
.btn-s{background:white;color:#1a1a2e;border:1px solid #dde1e7;border-radius:8px;padding:11px;font-size:14px;cursor:pointer;font-family:Arial,sans-serif;width:100%;margin-top:8px;}
.exito-msg{text-align:center;padding:1rem;background:#f0fdf4;border-radius:10px;color:#166534;font-size:13px;margin-top:1rem;}
</style>
</head>
<body>
<nav><span class="brand">Sistema de Preinscripcion — UABC</span><a href="logout.php">Cerrar sesion</a></nav>
<div class="container">
<div class="card">
 
<?php if ($activada || $ver_activada):
    $lista = $ver_activada ? ($materias_activadas ?? []) : $materias_sel;
    $tc    = $ver_activada ? ($total_cred ?? 0) : $total_cred;
?>
  <div class="icono" style="background:#f0fdf4;">✓</div>
  <h1>Tira de materias activada</h1>
  <p class="sub">Tu preinscripcion ha sido registrada exitosamente</p>
  <hr>
  <div class="fila"><span class="et">Alumno</span><span class="vl"><?= htmlspecialchars($alumno['nombre']) ?></span></div>
  <div class="fila"><span class="et">Matricula</span><span class="vl"><?= htmlspecialchars($alumno['matricula']) ?></span></div>
  <div class="fila"><span class="et">Carrera</span><span class="vl"><?= htmlspecialchars($alumno['carrera_nombre']) ?></span></div>
  <div class="fila"><span class="et">Semestre</span><span class="vl"><?= htmlspecialchars($semestre) ?></span></div>
  <hr>
  <p style="font-size:13px;font-weight:600;color:#6b7280;margin-bottom:10px;">Materias inscritas</p>
  <?php foreach ($lista as $m): ?>
  <div class="mrow">
    <div><div class="mn"><?= htmlspecialchars($m['nombre']) ?></div><div class="mc">Clave: <?= htmlspecialchars($m['clave']) ?></div></div>
    <span class="mc2"><?= $m['creditos'] ?> cr.</span>
  </div>
  <?php endforeach; ?>
  <div class="total"><span>Total de creditos</span><span><?= $tc ?> creditos</span></div>
  <div class="exito-msg">Confirmacion enviada a <strong><?= htmlspecialchars($alumno['correo']) ?></strong></div>
  <a href="panel.php"><button class="btn-s" style="margin-top:1rem;">Volver al panel</button></a>
 
<?php else: ?>
  <div class="icono" style="background:#eff6ff;">?</div>
  <h1>Resumen de preinscripcion</h1>
  <p class="sub">Revisa tu seleccion antes de activar la tira</p>
  <hr>
  <div class="fila"><span class="et">Alumno</span><span class="vl"><?= htmlspecialchars($alumno['nombre']) ?></span></div>
  <div class="fila"><span class="et">Carrera</span><span class="vl"><?= htmlspecialchars($alumno['carrera_nombre']) ?></span></div>
  <div class="fila"><span class="et">Semestre</span><span class="vl"><?= htmlspecialchars($semestre) ?></span></div>
  <hr>
  <p style="font-size:13px;font-weight:600;color:#6b7280;margin-bottom:10px;">Materias seleccionadas</p>
  <?php foreach ($materias_sel as $m): ?>
  <div class="mrow">
    <div><div class="mn"><?= htmlspecialchars($m['nombre']) ?></div><div class="mc">Clave: <?= htmlspecialchars($m['clave']) ?></div></div>
    <span class="mc2"><?= $m['creditos'] ?> cr.</span>
  </div>
  <?php endforeach; ?>
  <div class="total"><span>Total de creditos</span><span><?= $total_cred ?> creditos</span></div>
  <form method="POST">
    <?php foreach ($materias_ids as $mid): ?><input type="hidden" name="materias[]" value="<?= (int)$mid ?>"><?php endforeach; ?>
    <input type="hidden" name="semestre" value="<?= htmlspecialchars($semestre) ?>">
    <input type="hidden" name="activar" value="1">
    <button type="submit" class="btn-p">Activar tira de materias</button>
  </form>
  <a href="panel.php"><button class="btn-s">Regresar y modificar</button></a>
<?php endif; ?>
 
</div>
</div>
</body>
</html>