<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
require 'db.php';

$msg = "";
$periodo = $conn->query("SELECT * FROM periodo_preinscripcion WHERE activo=1 LIMIT 1")->fetch_assoc();

if (isset($_GET['cancelar'])) {
    $pid = (int)$_GET['cancelar'];
    $conn->query("UPDATE preinscripciones SET estado='cancelada' WHERE id=$pid");
    $msg = "Preinscripción cancelada.";
}

if (isset($_GET['autoactivar'])) {
    if ($periodo) {
        $sem = $periodo['semestre'];
        $pendientes = $conn->query("SELECT p.* FROM preinscripciones p WHERE p.semestre='$sem' AND p.estado='en_proceso'")->fetch_all(MYSQLI_ASSOC);
        $activados = 0;
        foreach ($pendientes as $p) {
            $cnt = $conn->query("SELECT COUNT(*) as t FROM preinscripcion_materias WHERE preinscripcion_id={$p['id']}")->fetch_assoc()['t'];
            if ($cnt > 0) {
                $conn->query("UPDATE preinscripciones SET estado='auto_activada',fecha_activacion=NOW() WHERE id={$p['id']}");
                $activados++;
            }
        }
        $msg = "Auto-activación ejecutada: $activados preinscripción(es) activada(s).";
    }
}

if (isset($_GET['exportar']) && $periodo) {
    $sem = $periodo['semestre'];
    $rows = $conn->query("
        SELECT a.matricula, a.nombre, a.correo, c.clave AS carrera, a.semestre_actual,
               p.estado, p.fecha_activacion,
               GROUP_CONCAT(m.nombre ORDER BY m.nombre SEPARATOR ' | ') AS materias,
               SUM(m.creditos) AS total_creditos
        FROM preinscripciones p
        JOIN alumnos a ON p.alumno_id=a.id JOIN carreras c ON a.carrera_id=c.id
        LEFT JOIN preinscripcion_materias pm ON pm.preinscripcion_id=p.id
        LEFT JOIN materias m ON pm.materia_id=m.id
        WHERE p.semestre='$sem' GROUP BY p.id ORDER BY a.nombre
    ")->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="preinscripciones_'.$sem.'.csv"');
    $out = fopen('php://output','w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out,['Matrícula','Nombre','Correo','Carrera','Semestre','Estado','Fecha Activación','Materias','Total Créditos']);
    foreach ($rows as $r) {
        fputcsv($out,[$r['matricula'],$r['nombre'],$r['correo'],$r['carrera'],$r['semestre_actual'],$r['estado'],$r['fecha_activacion'],$r['materias'],$r['total_creditos']]);
    }
    fclose($out); exit();
}

$estado_f = $_GET['estado'] ?? '';
$where = $periodo ? "WHERE p.semestre='{$periodo['semestre']}'" : "WHERE 1=0";
if ($estado_f) $where .= " AND p.estado='$estado_f'";

$preinscripciones = $conn->query("
    SELECT p.*, a.nombre, a.matricula, a.correo, c.clave AS carrera_clave, a.semestre_actual,
           (SELECT COUNT(*) FROM preinscripcion_materias pm WHERE pm.preinscripcion_id=p.id) AS num_materias,
           (SELECT SUM(m.creditos) FROM preinscripcion_materias pm JOIN materias m ON pm.materia_id=m.id WHERE pm.preinscripcion_id=p.id) AS total_cred
    FROM preinscripciones p JOIN alumnos a ON p.alumno_id=a.id JOIN carreras c ON a.carrera_id=c.id
    $where ORDER BY p.id DESC
")->fetch_all(MYSQLI_ASSOC);

$conteos = [];
if ($periodo) {
    $sem = $periodo['semestre'];
    foreach (['en_proceso','activada','auto_activada','cancelada'] as $est) {
        $conteos[$est] = $conn->query("SELECT COUNT(*) as t FROM preinscripciones WHERE semestre='$sem' AND estado='$est'")->fetch_assoc()['t'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Preinscripciones — Admin UABC</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
    --white:#ffffff; --off-white:#f8f7f4; --surface:#f1efe9;
    --border:#ddd9d0; --navy:#1A3A5C; --navy-light:#2E6DA4; --navy-bg:#e8f0f8;
    --accent:#c47d0e; --accent-light:#e8a020; --accent-bg:#fef3e2;
    --text:#1c1917; --text-mid:#44403c; --muted:#78716c;
    --success:#15803d; --success-bg:#f0fdf4; --success-border:#bbf7d0;
    --warning:#92400e; --warning-bg:#fffbeb; --warning-border:#fde68a;
    --danger:#b91c1c; --danger-bg:#fef2f2; --danger-border:#fecaca;
    --info:#1e40af; --info-bg:#eff6ff; --info-border:#bfdbfe;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:var(--off-white);color:var(--text);min-height:100vh;display:flex;}

.sidebar{width:240px;min-height:100vh;background:var(--white);border-right:1.5px solid var(--border);display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:10;}
.sb-logo{padding:1.6rem 1.4rem 1.4rem;border-bottom:1.5px solid var(--border);}
.sb-logo .mark{font-size:10px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--accent);margin-bottom:6px;}
.sb-logo h2{font-family:'Fraunces',serif;font-size:16px;font-weight:600;color:var(--navy);line-height:1.3;}
.sb-logo p{font-size:12px;color:var(--muted);margin-top:3px;}
.sb-nav{flex:1;padding:1rem 0;}
.sb-section{font-size:10px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);padding:10px 1.4rem 5px;}
.sb-link{display:flex;align-items:center;gap:10px;padding:9px 1.4rem;font-size:13px;font-weight:500;color:var(--text-mid);text-decoration:none;transition:all .15s;border-left:3px solid transparent;}
.sb-link:hover{background:var(--surface);color:var(--navy);}
.sb-link.active{background:var(--navy-bg);border-left-color:var(--navy);color:var(--navy);font-weight:600;}
.sb-link .ico{width:18px;text-align:center;font-size:14px;opacity:.7;}
.sb-footer{padding:1rem 1.4rem;border-top:1.5px solid var(--border);}
.sb-user{font-size:12px;color:var(--muted);margin-bottom:8px;}
.sb-user strong{display:block;color:var(--text-mid);font-weight:600;}
.sb-out{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);text-decoration:none;padding:6px 10px;border-radius:7px;border:1px solid var(--border);transition:all .15s;}
.sb-out:hover{background:var(--danger-bg);border-color:var(--danger-border);color:var(--danger);}

.main{margin-left:240px;flex:1;padding:2rem 2.4rem;}
.page-title{font-family:'Fraunces',serif;font-size:26px;font-weight:600;color:var(--navy);margin-bottom:4px;}
.page-sub{font-size:13px;color:var(--muted);margin-bottom:1.5rem;}

.msg{background:var(--success-bg);border:1.5px solid var(--success-border);color:var(--success);border-radius:9px;padding:10px 14px;font-size:13px;margin-bottom:1rem;}

.toolbar{display:flex;gap:10px;margin-bottom:1.2rem;flex-wrap:wrap;align-items:center;}
.btn-p{background:var(--navy);color:white;border:none;border-radius:9px;padding:9px 16px;font-size:13px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;text-decoration:none;display:inline-block;transition:background .2s;}
.btn-p:hover{background:var(--navy-light);}
.btn-s{background:var(--white);color:var(--text-mid);border:1.5px solid var(--border);border-radius:8px;padding:8px 14px;font-size:12px;cursor:pointer;font-family:'DM Sans',sans-serif;text-decoration:none;display:inline-block;transition:all .15s;}
.btn-s:hover{border-color:var(--navy-light);color:var(--navy);}
.btn-warn{background:var(--warning-bg);color:var(--warning);border:1.5px solid var(--warning-border);border-radius:8px;padding:8px 14px;font-size:12px;cursor:pointer;font-family:'DM Sans',sans-serif;text-decoration:none;display:inline-block;transition:all .15s;}
.btn-warn:hover{background:#fef0b0;}
.btn-danger-sm{background:var(--white);color:var(--danger);border:1px solid var(--danger-border);border-radius:6px;padding:5px 10px;font-size:11px;cursor:pointer;text-decoration:none;display:inline-block;transition:all .15s;}
.btn-danger-sm:hover{background:var(--danger-bg);}

/* COUNTERS */
.counters{display:flex;gap:10px;margin-bottom:1.2rem;flex-wrap:wrap;}
.counter{background:var(--white);border:1.5px solid var(--border);border-radius:9px;padding:.8rem 1.2rem;text-align:center;min-width:100px;}
.counter .n{font-family:'Fraunces',serif;font-size:22px;font-weight:700;color:var(--navy);}
.counter .l{font-size:11px;color:var(--muted);margin-top:2px;}

/* FILTER TABS */
.filter-tabs{display:flex;gap:8px;margin-bottom:1.2rem;flex-wrap:wrap;}
.ftab{font-size:12px;font-weight:500;padding:6px 14px;border-radius:20px;border:1.5px solid var(--border);background:var(--white);color:var(--muted);cursor:pointer;text-decoration:none;transition:all .15s;}
.ftab:hover{border-color:var(--navy-light);color:var(--navy);}
.ftab.on{background:var(--navy-bg);border-color:var(--navy);color:var(--navy);font-weight:600;}
.cnt{font-size:10px;margin-left:4px;opacity:.7;}

/* TABLE */
.table-wrap{background:var(--white);border:1.5px solid var(--border);border-radius:12px;overflow:hidden;}
table{width:100%;border-collapse:collapse;}
th{font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);padding:11px 13px;border-bottom:1.5px solid var(--border);text-align:left;background:var(--surface);}
td{padding:11px 13px;font-size:12px;border-bottom:1px solid var(--surface);vertical-align:top;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:var(--off-white);}

/* BADGES */
.bdg{font-size:10px;padding:3px 8px;border-radius:5px;font-weight:600;white-space:nowrap;letter-spacing:.03em;}
.b-en  {background:var(--info-bg);color:var(--info);border:1px solid var(--info-border);}
.b-act {background:var(--success-bg);color:var(--success);border:1px solid var(--success-border);}
.b-auto{background:var(--accent-bg);color:var(--accent);border:1px solid #f0c060;}
.b-can {background:var(--danger-bg);color:var(--danger);border:1px solid var(--danger-border);}

.empty-state{text-align:center;color:var(--muted);padding:2.5rem;font-size:13px;}
</style>
</head>
<body>
<?php include 'admin_sidebar.php'; ?>
<div class="main">
    <div class="page-title">Preinscripciones</div>
    <div class="page-sub">
        <?php if ($periodo): ?>Semestre activo: <strong style="color:var(--accent)"><?= htmlspecialchars($periodo['semestre']) ?></strong> · <?= date('d/m/Y',strtotime($periodo['fecha_inicio'])) ?> — <?= date('d/m/Y',strtotime($periodo['fecha_fin'])) ?>
        <?php else: ?>Sin periodo activo<?php endif; ?>
    </div>

    <?php if ($msg): ?><div class="msg"><?= $msg ?></div><?php endif; ?>

    <?php if (!$periodo): ?>
    <div style="background:var(--white);border:1.5px solid var(--border);border-radius:10px;padding:2rem;text-align:center;color:var(--muted);">No hay periodo activo. <a href="admin_periodos.php" style="color:var(--navy-light);font-weight:600;">Crear uno</a></div>
    <?php else: ?>

    <!-- CONTEOS -->
    <div class="counters">
        <?php $labels=['en_proceso'=>['En proceso','b-en'],'activada'=>['Activadas','b-act'],'auto_activada'=>['Auto-activadas','b-auto'],'cancelada'=>['Canceladas','b-can']];
        foreach ($labels as $k=>[$l,$cls]): ?>
        <div class="counter">
            <div class="n"><?= $conteos[$k] ?? 0 ?></div>
            <div class="l"><?= $l ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ACCIONES -->
    <div class="toolbar">
        <a href="?exportar=1" class="btn-p">↓ Exportar CSV</a>
        <a href="?autoactivar=1" onclick="return confirm('¿Ejecutar auto-activación manual ahora?')" class="btn-warn">⚡ Auto-activar pendientes</a>
    </div>

    <!-- FILTROS -->
    <div class="filter-tabs">
        <a href="admin_preinscripciones.php" class="ftab <?= !$estado_f?'on':'' ?>">Todos <span class="cnt"><?= array_sum($conteos) ?></span></a>
        <?php foreach ($labels as $k=>[$l,$cls]): ?>
        <a href="?estado=<?= $k ?>" class="ftab <?= $estado_f==$k?'on':'' ?>"><?= $l ?> <span class="cnt"><?= $conteos[$k]??0 ?></span></a>
        <?php endforeach; ?>
    </div>

    <!-- TABLA -->
    <div class="table-wrap">
    <table>
        <thead><tr>
            <th>Alumno</th><th>Matrícula</th><th>Carrera</th><th>Sem.</th>
            <th>Estado</th><th>Materias</th><th>Créd.</th><th>Activación</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($preinscripciones as $p):
            $bmap=['en_proceso'=>'b-en','activada'=>'b-act','auto_activada'=>'b-auto','cancelada'=>'b-can'];
            $lmap=['en_proceso'=>'EN PROCESO','activada'=>'ACTIVADA','auto_activada'=>'AUTO-ACTIV.','cancelada'=>'CANCELADA'];
            $est=$p['estado'];
        ?>
        <tr>
            <td>
                <div style="font-weight:600;color:var(--navy)"><?= htmlspecialchars($p['nombre']) ?></div>
                <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($p['correo']) ?></div>
            </td>
            <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($p['matricula']) ?></td>
            <td><?= htmlspecialchars($p['carrera_clave']) ?></td>
            <td style="text-align:center"><?= $p['semestre_actual'] ?>°</td>
            <td><span class="bdg <?= $bmap[$est]??'' ?>"><?= $lmap[$est]??strtoupper($est) ?></span></td>
            <td style="text-align:center"><?= $p['num_materias'] ?></td>
            <td style="text-align:center"><?= $p['total_cred'] ?? 0 ?></td>
            <td style="font-size:11px;color:var(--muted)"><?= $p['fecha_activacion']?date('d/m/Y H:i',strtotime($p['fecha_activacion'])):'-' ?></td>
            <td style="display:flex;gap:6px;flex-wrap:wrap;">
                <?php if ($p['estado']==='en_proceso'): ?>
                <a href="?cancelar=<?= $p['id'] ?>" onclick="return confirm('¿Cancelar esta preinscripción?')" class="btn-danger-sm">Cancelar</a>
                <?php endif; ?>
                <a href="admin_alumnos.php?id=<?= $p['alumno_id'] ?>" class="btn-s" style="font-size:11px;padding:4px 9px;">Ver alumno</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($preinscripciones)): ?>
        <tr><td colspan="9" class="empty-state">Sin resultados para este filtro.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
</body>
</html>