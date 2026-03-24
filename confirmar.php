<?php
// ============================================================
// confirmar.php — Resumen y confirmacion final de la tira
// ============================================================
session_start();
if (!isset($_SESSION['alumno_id'])) { header("Location: login.php"); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: panel.php"); exit(); }
 
require 'db.php';
$alumno_id = $_SESSION['alumno_id'];
 
$stmt = $conn->prepare("SELECT * FROM alumnos WHERE id = ?");
$stmt->bind_param("i", $alumno_id);
$stmt->execute();
$alumno = $stmt->get_result()->fetch_assoc();
 
$periodo = $conn->query("SELECT * FROM periodo_preinscripcion WHERE activo = 1 LIMIT 1")->fetch_assoc();
$semestre = $_POST['semestre'] ?? $periodo['semestre'];
$materias_ids = $_POST['materias'] ?? [];
 
$horarios = [
    11 => ['grupo' => '301', 'horario' => 'Lun/Mie 8:00-10:00'],
    12 => ['grupo' => '302', 'horario' => 'Mar/Jue 10:00-12:00'],
    13 => ['grupo' => '303', 'horario' => 'Lun/Mie 12:00-14:00'],
    14 => ['grupo' => '304', 'horario' => 'Mar/Jue 8:00-10:00'],
    15 => ['grupo' => '305', 'horario' => 'Vie 9:00-12:00'],
    16 => ['grupo' => '306', 'horario' => 'Mie 14:00-17:00'],
    17 => ['grupo' => '307', 'horario' => 'Jue 14:00-17:00'],
];
 
// Obtener detalles de las materias seleccionadas
$materias_seleccionadas = [];
$total_creditos = 0;
foreach ($materias_ids as $mid) {
    $mid = (int)$mid;
    $stmt = $conn->prepare("SELECT * FROM materias WHERE id = ?");
    $stmt->bind_param("i", $mid);
    $stmt->execute();
    $mat = $stmt->get_result()->fetch_assoc();
    if ($mat) {
        $mat['grupo']   = $horarios[$mid]['grupo']   ?? 'N/A';
        $mat['horario'] = $horarios[$mid]['horario'] ?? 'Por definir';
        $materias_seleccionadas[] = $mat;
        $total_creditos += $mat['creditos'];
    }
}
 
// Si el formulario de activacion fue enviado
$activada = false;
if (isset($_POST['activar'])) {
    // Crear preinscripcion
    $stmt = $conn->prepare("INSERT INTO preinscripciones (alumno_id, semestre, estado, fecha_activacion) VALUES (?, ?, 'activada', NOW())");
    $stmt->bind_param("is", $alumno_id, $semestre);
    $stmt->execute();
    $preinscripcion_id = $conn->insert_id;
 
    // Insertar materias
    foreach ($materias_seleccionadas as $mat) {
        $stmt = $conn->prepare("INSERT INTO preinscripcion_materias (preinscripcion_id, materia_id, grupo, horario) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $preinscripcion_id, $mat['id'], $mat['grupo'], $mat['horario']);
        $stmt->execute();
    }
    $activada = true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preinscripcion UABC — Confirmacion</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 14px; background: #f0f2f5; color: #1a1a2e; }
        nav { background: #1A3A5C; color: white; padding: 0 2rem; height: 56px; display: flex; align-items: center; justify-content: space-between; }
        nav .brand { font-size: 15px; font-weight: 600; }
        nav a { color: white; text-decoration: none; font-size: 13px; opacity: 0.8; }
        .container { max-width: 680px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: white; border-radius: 12px; border: 1px solid #e5e7eb; padding: 1.8rem; }
        .icono-exito { width: 50px; height: 50px; border-radius: 50%; background: #f0fdf4; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 22px; }
        .icono-revision { width: 50px; height: 50px; border-radius: 50%; background: #eff6ff; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 22px; }
        h1 { font-size: 16px; font-weight: 600; text-align: center; margin-bottom: 4px; }
        .subtitulo { font-size: 13px; color: #6b7280; text-align: center; margin-bottom: 1.5rem; }
        .separador { border: none; border-top: 1px solid #e5e7eb; margin: 1.2rem 0; }
        .fila { display: flex; justify-content: space-between; align-items: center; padding: 5px 0; font-size: 13px; }
        .fila .etiqueta { color: #6b7280; }
        .fila .valor { font-weight: 500; }
        .materia-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: #f9fafb; border-radius: 8px; margin-bottom: 6px; }
        .mat-nombre { font-size: 13px; font-weight: 500; }
        .mat-detalle { font-size: 12px; color: #6b7280; margin-top: 2px; }
        .mat-creditos { font-size: 12px; color: #6b7280; }
        .total-creditos { display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: #eff6ff; border-radius: 8px; margin-top: 4px; }
        .total-creditos span:first-child { font-size: 13px; font-weight: 600; color: #1e40af; }
        .total-creditos span:last-child { font-size: 16px; font-weight: 700; color: #1A3A5C; }
        .btn-primary { background: #1A3A5C; color: white; border: none; border-radius: 8px; padding: 11px 24px; font-size: 14px; font-weight: 600; cursor: pointer; font-family: Arial, sans-serif; width: 100%; margin-top: 1rem; }
        .btn-primary:hover { background: #2E6DA4; }
        .btn-secondary { background: white; color: #1a1a2e; border: 1px solid #dde1e7; border-radius: 8px; padding: 11px 24px; font-size: 14px; cursor: pointer; font-family: Arial, sans-serif; width: 100%; margin-top: 8px; }
        .exito-msg { text-align: center; padding: 1rem; background: #f0fdf4; border-radius: 10px; color: #166534; font-size: 13px; margin-top: 1rem; }
    </style>
</head>
<body>
<nav>
    <span class="brand">Sistema de Preinscripcion — UABC</span>
    <a href="logout.php">Cerrar sesion</a>
</nav>
<div class="container">
<div class="card">
 
<?php if ($activada): ?>
    <div class="icono-exito">✓</div>
    <h1>Tira de materias activada</h1>
    <p class="subtitulo">Tu preinscripcion ha sido registrada exitosamente</p>
    <hr class="separador">
    <div class="fila"><span class="etiqueta">Alumno</span><span class="valor"><?= htmlspecialchars($alumno['nombre']) ?></span></div>
    <div class="fila"><span class="etiqueta">Matricula</span><span class="valor"><?= htmlspecialchars($alumno['matricula']) ?></span></div>
    <div class="fila"><span class="etiqueta">Semestre</span><span class="valor"><?= htmlspecialchars($semestre) ?></span></div>
    <hr class="separador">
    <p style="font-size:13px;font-weight:600;color:#6b7280;margin-bottom:10px;">Materias inscritas</p>
    <?php foreach ($materias_seleccionadas as $mat): ?>
    <div class="materia-row">
        <div>
            <div class="mat-nombre"><?= htmlspecialchars($mat['nombre']) ?></div>
            <div class="mat-detalle"><?= htmlspecialchars($mat['horario']) ?> · Grupo <?= htmlspecialchars($mat['grupo']) ?></div>
        </div>
        <span class="mat-creditos"><?= $mat['creditos'] ?> cr.</span>
    </div>
    <?php endforeach; ?>
    <div class="total-creditos">
        <span>Total de creditos</span>
        <span><?= $total_creditos ?> creditos</span>
    </div>
    <div class="exito-msg">Se ha enviado un correo de confirmacion a <strong><?= htmlspecialchars($alumno['correo']) ?></strong></div>
    <a href="panel.php"><button class="btn-secondary" style="margin-top:1rem;">Volver al panel</button></a>
 
<?php else: ?>
    <div class="icono-revision">?</div>
    <h1>Resumen de preinscripcion</h1>
    <p class="subtitulo">Revisa tu seleccion antes de activar la tira</p>
    <hr class="separador">
    <div class="fila"><span class="etiqueta">Alumno</span><span class="valor"><?= htmlspecialchars($alumno['nombre']) ?></span></div>
    <div class="fila"><span class="etiqueta">Carrera</span><span class="valor"><?= htmlspecialchars($alumno['carrera']) ?></span></div>
    <div class="fila"><span class="etiqueta">Semestre</span><span class="valor"><?= htmlspecialchars($semestre) ?></span></div>
    <hr class="separador">
    <p style="font-size:13px;font-weight:600;color:#6b7280;margin-bottom:10px;">Materias seleccionadas</p>
    <?php foreach ($materias_seleccionadas as $mat): ?>
    <div class="materia-row">
        <div>
            <div class="mat-nombre"><?= htmlspecialchars($mat['nombre']) ?></div>
            <div class="mat-detalle"><?= htmlspecialchars($mat['horario']) ?> · Grupo <?= htmlspecialchars($mat['grupo']) ?></div>
        </div>
        <span class="mat-creditos"><?= $mat['creditos'] ?> cr.</span>
    </div>
    <?php endforeach; ?>
    <div class="total-creditos">
        <span>Total de creditos</span>
        <span><?= $total_creditos ?> creditos</span>
    </div>
    <form method="POST">
        <?php foreach ($materias_ids as $mid): ?>
        <input type="hidden" name="materias[]" value="<?= (int)$mid ?>">
        <?php endforeach; ?>
        <input type="hidden" name="semestre" value="<?= htmlspecialchars($semestre) ?>">
        <input type="hidden" name="activar" value="1">
        <button type="submit" class="btn-primary">Activar tira de materias</button>
    </form>
    <a href="panel.php"><button class="btn-secondary">Regresar y modificar</button></a>
<?php endif; ?>
 
</div>
</div>
</body>
</html>
 