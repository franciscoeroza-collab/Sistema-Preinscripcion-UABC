<?php
// ============================================================
// panel.php — Panel principal del alumno con validaciones
// ============================================================
session_start();
 
// Si no hay sesion, redirige al login
if (!isset($_SESSION['alumno_id'])) {
    header("Location: login.php");
    exit();
}
 
require 'db.php';
 
$alumno_id = $_SESSION['alumno_id'];
 
// --- 1. OBTENER DATOS DEL ALUMNO ---
$stmt = $conn->prepare("SELECT * FROM alumnos WHERE id = ?");
$stmt->bind_param("i", $alumno_id);
$stmt->execute();
$alumno = $stmt->get_result()->fetch_assoc();
 
// --- 2. VERIFICAR PERIODO ACTIVO ---
$periodo = $conn->query("SELECT * FROM periodo_preinscripcion WHERE activo = 1 LIMIT 1")->fetch_assoc();
$periodo_activo = $periodo && (date('Y-m-d') >= $periodo['fecha_inicio']) && (date('Y-m-d') <= $periodo['fecha_fin']);
 
// --- 3. VERIFICAR ADEUDOS ---
$stmt = $conn->prepare("SELECT * FROM adeudos WHERE alumno_id = ? AND activo = 1");
$stmt->bind_param("i", $alumno_id);
$stmt->execute();
$adeudos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$tiene_adeudos = count($adeudos) > 0;
 
// --- 4. VERIFICAR REGULARIDAD ---
// Obtener materias del semestre anterior
$stmt = $conn->prepare("
    SELECT c.materia_id, c.calificacion, c.semestre
    FROM calificaciones c
    WHERE c.alumno_id = ?
    ORDER BY c.semestre DESC
");
$stmt->bind_param("i", $alumno_id);
$stmt->execute();
$historial = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
 
// Agrupar por materia, tomar la ultima calificacion
$calificaciones_por_materia = [];
foreach ($historial as $cal) {
    $mid = $cal['materia_id'];
    if (!isset($calificaciones_por_materia[$mid])) {
        $calificaciones_por_materia[$mid] = $cal;
    }
}
 
$materias_reprobadas = [];
foreach ($calificaciones_por_materia as $mid => $cal) {
    if ($cal['calificacion'] < 60) {
        $materias_reprobadas[] = $mid;
    }
}
$es_regular = count($materias_reprobadas) === 0;
 
// --- 5. VERIFICAR MATERIAS CON EVALUACION PERMANENTE (cursadas 2+ veces y reprobadas) ---
$stmt = $conn->prepare("
    SELECT materia_id, MAX(veces_cursada) as veces, MIN(calificacion) as min_cal
    FROM calificaciones
    WHERE alumno_id = ?
    GROUP BY materia_id
    HAVING veces >= 2 AND min_cal < 60
");
$stmt->bind_param("i", $alumno_id);
$stmt->execute();
$ep_materias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$ep_ids = array_column($ep_materias, 'materia_id');
 
// --- 6. OBTENER MATERIAS APROBADAS (para validar seriacion) ---
$aprobadas_ids = [];
foreach ($calificaciones_por_materia as $mid => $cal) {
    if ($cal['calificacion'] >= 60) {
        $aprobadas_ids[] = $mid;
    }
}
 
// --- 7. OBTENER CATALOGO DE MATERIAS DISPONIBLES ---
$etapa = $alumno['etapa'];
 
// Materias de la etapa actual no cursadas aun
$stmt = $conn->prepare("
    SELECT m.*
    FROM materias m
    WHERE m.etapa = ?
    AND m.id NOT IN (
        SELECT materia_id FROM calificaciones
        WHERE alumno_id = ? AND calificacion >= 60
    )
");
$stmt->bind_param("ii", $etapa, $alumno_id);
$stmt->execute();
$materias_disponibles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
 
// Si es irregular, agregar materias reprobadas de etapas anteriores
if (!$es_regular && count($materias_reprobadas) > 0) {
    $ids_str = implode(',', $materias_reprobadas);
    $stmt_rep = $conn->query("SELECT * FROM materias WHERE id IN ($ids_str)");
    $rep_materias = $stmt_rep->fetch_all(MYSQLI_ASSOC);
    $materias_disponibles = array_merge($materias_disponibles, $rep_materias);
}
 
// Verificar seriacion para cada materia
foreach ($materias_disponibles as &$mat) {
    $stmt = $conn->prepare("SELECT prerequisito_id FROM seriacion WHERE materia_id = ?");
    $stmt->bind_param("i", $mat['id']);
    $stmt->execute();
    $prereqs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
 
    $mat['bloqueada'] = false;
    $mat['motivo_bloqueo'] = '';
    $mat['es_ep'] = in_array($mat['id'], $ep_ids);
 
    foreach ($prereqs as $pre) {
        if (!in_array($pre['prerequisito_id'], $aprobadas_ids)) {
            // Obtener nombre del prerequisito
            $stmt2 = $conn->prepare("SELECT nombre FROM materias WHERE id = ?");
            $stmt2->bind_param("i", $pre['prerequisito_id']);
            $stmt2->execute();
            $pre_nombre = $stmt2->get_result()->fetch_assoc()['nombre'];
            $mat['bloqueada'] = true;
            $mat['motivo_bloqueo'] = "Prerequisito faltante: " . $pre_nombre;
            break;
        }
    }
}
unset($mat);
 
// Horarios ficticios para las materias
$horarios = [
    11 => ['grupo' => '301', 'horario' => 'Lun/Mie 8:00-10:00'],
    12 => ['grupo' => '302', 'horario' => 'Mar/Jue 10:00-12:00'],
    13 => ['grupo' => '303', 'horario' => 'Lun/Mie 12:00-14:00'],
    14 => ['grupo' => '304', 'horario' => 'Mar/Jue 8:00-10:00'],
    15 => ['grupo' => '305', 'horario' => 'Vie 9:00-12:00'],
    16 => ['grupo' => '306', 'horario' => 'Mie 14:00-17:00'],
    17 => ['grupo' => '307', 'horario' => 'Jue 14:00-17:00'],
];
 
// Limites de creditos
$min_creditos = 12;
$max_creditos = 24;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preinscripcion UABC — Panel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 14px; background: #f0f2f5; color: #1a1a2e; }
 
        /* NAV */
        nav {
            background: #1A3A5C;
            color: white;
            padding: 0 2rem;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        nav .brand { font-size: 15px; font-weight: 600; }
        nav .user { font-size: 13px; opacity: 0.85; }
        nav a { color: white; text-decoration: none; font-size: 13px; opacity: 0.8; }
        nav a:hover { opacity: 1; }
 
        /* LAYOUT */
        .container { max-width: 960px; margin: 2rem auto; padding: 0 1rem; }
 
        /* ALERTAS */
        .alerta {
            border-radius: 10px;
            padding: 1rem 1.2rem;
            margin-bottom: 1rem;
            font-size: 13px;
        }
        .alerta-danger { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
        .alerta-warning { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
        .alerta-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .alerta-info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
        .alerta strong { display: block; margin-bottom: 4px; }
 
        /* TARJETAS RESUMEN */
        .cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 1.5rem; }
        .card {
            background: white;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            padding: 1rem;
            text-align: center;
        }
        .card .label { font-size: 12px; color: #6b7280; margin-bottom: 4px; }
        .card .valor { font-size: 22px; font-weight: 600; color: #1A3A5C; }
        .card .valor.verde { color: #16a34a; }
        .card .valor.rojo { color: #dc2626; }
 
        /* PROGRESO */
        .progreso-wrap {
            background: white;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            padding: 1.2rem;
            margin-bottom: 1.5rem;
        }
        .progreso-label { font-size: 12px; color: #6b7280; margin-bottom: 1rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        .pasos { display: flex; align-items: center; }
        .paso { display: flex; flex-direction: column; align-items: center; flex: 1; }
        .paso-circulo {
            width: 30px; height: 30px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 600;
        }
        .paso-circulo.hecho { background: #1A3A5C; color: white; }
        .paso-circulo.actual { background: #2E6DA4; color: white; }
        .paso-circulo.pendiente { background: #e5e7eb; color: #9ca3af; }
        .paso-texto { font-size: 11px; margin-top: 5px; text-align: center; color: #6b7280; }
        .paso-texto.activo { color: #1A3A5C; font-weight: 600; }
        .linea { flex: 1; height: 2px; margin-bottom: 16px; }
        .linea.hecha { background: #1A3A5C; }
        .linea.pendiente { background: #e5e7eb; }
 
        /* SECCION */
        .seccion {
            background: white;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            padding: 1.2rem;
            margin-bottom: 1.5rem;
        }
        .seccion h2 { font-size: 15px; font-weight: 600; color: #1a1a2e; margin-bottom: 1rem; }
 
        /* TABLA MATERIAS */
        .materia-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 8px;
            border: 1px solid #e5e7eb;
        }
        .materia-item.disponible { background: white; }
        .materia-item.bloqueada { background: #f9fafb; opacity: 0.75; }
        .materia-item.ep { background: #fffbeb; border-color: #fde68a; }
        .materia-info { flex: 1; }
        .materia-nombre { font-size: 14px; font-weight: 500; color: #1a1a2e; }
        .materia-detalle { font-size: 12px; color: #6b7280; margin-top: 2px; }
        .badge {
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 6px;
            font-weight: 500;
            white-space: nowrap;
        }
        .badge-blue { background: #eff6ff; color: #1e40af; }
        .badge-red { background: #fef2f2; color: #b91c1c; }
        .badge-amber { background: #fffbeb; color: #92400e; }
        .badge-green { background: #f0fdf4; color: #166534; }
 
        /* CREDITOS CONTADOR */
        .creditos-bar {
            background: #f0f2f5;
            border-radius: 10px;
            padding: 1rem 1.2rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .creditos-bar .info { font-size: 13px; color: #6b7280; }
        .creditos-bar .numero { font-size: 22px; font-weight: 700; color: #1A3A5C; }
        .creditos-bar .rango { font-size: 12px; color: #9ca3af; }
 
        /* BOTONES */
        .btn-primary {
            background: #1A3A5C;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: Arial, sans-serif;
        }
        .btn-primary:hover { background: #2E6DA4; }
        .btn-secondary {
            background: white;
            color: #1a1a2e;
            border: 1px solid #dde1e7;
            border-radius: 8px;
            padding: 10px 24px;
            font-size: 14px;
            cursor: pointer;
            font-family: Arial, sans-serif;
        }
        .acciones { display: flex; gap: 8px; justify-content: flex-end; }
 
        input[type="checkbox"] { width: 16px; height: 16px; accent-color: #1A3A5C; cursor: pointer; }
        input[type="checkbox"]:disabled { cursor: not-allowed; }
    </style>
</head>
<body>
 
<nav>
    <span class="brand">Sistema de Preinscripcion — UABC</span>
    <div style="display:flex;align-items:center;gap:1.5rem;">
        <span class="user"><?= htmlspecialchars($alumno['nombre']) ?></span>
        <a href="logout.php">Cerrar sesion</a>
    </div>
</nav>
 
<div class="container">
 
    <?php if (!$periodo_activo): ?>
    <div class="alerta alerta-warning">
        <strong>Periodo de preinscripcion no activo</strong>
        El periodo de preinscripcion aun no ha iniciado o ya finalizo. Consulta las fechas oficiales en Control Escolar.
    </div>
    <?php endif; ?>
 
    <?php if ($tiene_adeudos): ?>
    <div class="alerta alerta-danger">
        <strong>Preinscripcion bloqueada por adeudos</strong>
        <?php foreach ($adeudos as $adeudo): ?>
            <?= htmlspecialchars($adeudo['descripcion']) ?> — <strong><?= htmlspecialchars($adeudo['ventanilla']) ?></strong><br>
        <?php endforeach; ?>
        Debes regularizar tu situacion antes de continuar.
    </div>
    <?php endif; ?>
 
    <?php if (!empty($ep_materias)): ?>
    <div class="alerta alerta-warning">
        <strong>Atencion: Evaluacion Permanente</strong>
        Tienes materias que has reprobado dos veces. Si las vuelves a inscribir, estaran bajo la modalidad de Evaluacion Permanente. Tu tutor ha sido notificado.
    </div>
    <?php endif; ?>
 
    <!-- RESUMEN ACADEMICO -->
    <div class="cards">
        <div class="card">
            <div class="label">Estatus</div>
            <div class="valor <?= $es_regular ? 'verde' : 'rojo' ?>"><?= $es_regular ? 'Regular' : 'Irregular' ?></div>
        </div>
        <div class="card">
            <div class="label">Creditos acumulados</div>
            <div class="valor"><?= $alumno['creditos_acumulados'] ?></div>
        </div>
        <div class="card">
            <div class="label">Etapa actual</div>
            <div class="valor"><?= $alumno['etapa'] ?>a</div>
        </div>
        <div class="card">
            <div class="label">Adeudos</div>
            <div class="valor <?= $tiene_adeudos ? 'rojo' : 'verde' ?>"><?= $tiene_adeudos ? count($adeudos) : 'Ninguno' ?></div>
        </div>
    </div>
 
    <!-- PROGRESO -->
    <div class="progreso-wrap">
        <div class="progreso-label">Progreso del proceso</div>
        <div class="pasos">
            <div class="paso">
                <div class="paso-circulo hecho">✓</div>
                <div class="paso-texto activo">Adeudos</div>
            </div>
            <div class="linea hecha"></div>
            <div class="paso">
                <div class="paso-circulo hecho">✓</div>
                <div class="paso-texto activo">Clasificacion</div>
            </div>
            <div class="linea hecha"></div>
            <div class="paso">
                <div class="paso-circulo actual">3</div>
                <div class="paso-texto activo">Seriacion</div>
            </div>
            <div class="linea pendiente"></div>
            <div class="paso">
                <div class="paso-circulo pendiente">4</div>
                <div class="paso-texto">Seleccion</div>
            </div>
            <div class="linea pendiente"></div>
            <div class="paso">
                <div class="paso-circulo pendiente">5</div>
                <div class="paso-texto">Confirmacion</div>
            </div>
        </div>
    </div>
 
    <!-- SELECCION DE MATERIAS -->
    <?php if (!$tiene_adeudos && $periodo_activo): ?>
    <form method="POST" action="confirmar.php" id="formMaterias">
    <div class="seccion">
        <h2>Seleccion de materias — Semestre <?= htmlspecialchars($periodo['semestre']) ?></h2>
 
        <?php foreach ($materias_disponibles as $mat): ?>
        <?php
            $info_horario = $horarios[$mat['id']] ?? ['grupo' => 'N/A', 'horario' => 'Por definir'];
        ?>
        <div class="materia-item <?= $mat['bloqueada'] ? 'bloqueada' : ($mat['es_ep'] ? 'ep' : 'disponible') ?>">
            <input
                type="checkbox"
                name="materias[]"
                value="<?= $mat['id'] ?>"
                data-creditos="<?= $mat['creditos'] ?>"
                <?= $mat['bloqueada'] ? 'disabled' : '' ?>
                onchange="actualizarCreditos()"
            >
            <div class="materia-info">
                <div class="materia-nombre"><?= htmlspecialchars($mat['nombre']) ?></div>
                <div class="materia-detalle">
                    <?= $mat['bloqueada']
                        ? htmlspecialchars($mat['motivo_bloqueo'])
                        : htmlspecialchars($info_horario['horario']) . ' · Grupo ' . $info_horario['grupo']
                    ?>
                </div>
            </div>
            <?php if ($mat['bloqueada']): ?>
                <span class="badge badge-red">Bloqueada</span>
            <?php elseif ($mat['es_ep']): ?>
                <span class="badge badge-amber">Eval. Permanente</span>
            <?php else: ?>
                <span class="badge badge-blue"><?= $mat['creditos'] ?> creditos</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
 
        <?php if (empty($materias_disponibles)): ?>
        <div class="alerta alerta-success">No tienes materias pendientes para este semestre.</div>
        <?php endif; ?>
    </div>
 
    <!-- CONTADOR DE CREDITOS -->
    <div class="creditos-bar">
        <div>
            <div class="info">Creditos seleccionados</div>
            <div class="rango">Minimo <?= $min_creditos ?> · Maximo <?= $max_creditos ?></div>
        </div>
        <div class="numero" id="contadorCreditos">0</div>
    </div>
 
    <!-- BOTONES -->
    <div class="acciones">
        <button type="button" class="btn-secondary" onclick="window.location='logout.php'">Cancelar</button>
        <button type="submit" class="btn-primary" id="btnConfirmar" disabled>Confirmar seleccion</button>
    </div>
 
    <!-- Pasar datos del alumno al formulario de confirmacion -->
    <input type="hidden" name="semestre" value="<?= htmlspecialchars($periodo['semestre']) ?>">
    </form>
    <?php endif; ?>
 
</div>
 
<script>
const MIN = <?= $min_creditos ?>;
const MAX = <?= $max_creditos ?>;
 
function actualizarCreditos() {
    let total = 0;
    document.querySelectorAll('input[name="materias[]"]:checked').forEach(cb => {
        total += parseInt(cb.dataset.creditos);
    });
 
    document.getElementById('contadorCreditos').textContent = total;
    const btn = document.getElementById('btnConfirmar');
 
    if (total >= MIN && total <= MAX) {
        btn.disabled = false;
        document.getElementById('contadorCreditos').style.color = '#16a34a';
    } else {
        btn.disabled = true;
        document.getElementById('contadorCreditos').style.color = total > MAX ? '#dc2626' : '#1A3A5C';
    }
}
</script>
 
</body>
</html>