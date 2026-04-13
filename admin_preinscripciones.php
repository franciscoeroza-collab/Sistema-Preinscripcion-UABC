<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: admin_login.php"); exit(); }
require 'db.php';

$msg = "";
$periodo = $conn->query("SELECT * FROM periodo_preinscripcion WHERE activo=1 LIMIT 1")->fetch_assoc();

// Cancelar preinscripcióo
if (isset($_GET['cancelar'])) {
    $pid = (int)$_GET['cancelar'];
    $conn->query("UPDATE preinscripciones SET estado='cancelada' WHERE id=$pid");
    $msg = "Preinscripción cancelada.";
}

// Ejecutar auto-activar manual
if (isset($_GET['autoactivar'])) {
    // Activar las que están en proceso con materias
    if ($periodo) {
        $sem = $periodo['semestre'];
        $pendientes = $conn->query("
            SELECT p.* FROM preinscripciones p
            WHERE p.semestre='$sem' AND p.estado='en_proceso'
        ")->fetch_all(MYSQLI_ASSOC);
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

// Exportar CSV
if (isset($_GET['exportar']) && $periodo) {
    $sem = $periodo['semestre'];
    $rows = $conn->query("
        SELECT a.matricula, a.nombre, a.correo, c.clave AS carrera, a.semestre_actual,
               p.estado, p.fecha_activacion,
               GROUP_CONCAT(m.clave ORDER BY m.nombre SEPARATOR ', ') AS claves_materias,
               GROUP_CONCAT(m.nombre ORDER BY m.nombre SEPARATOR ' | ') AS materias,
               SUM(m.creditos) AS total_creditos
        FROM preinscripciones p
        JOIN alumnos a ON p.alumno_id=a.id
        JOIN carreras c ON a.carrera_id=c.id
        LEFT JOIN preinscripcion_materias pm ON pm.preinscripcion_id=p.id
        LEFT JOIN materias m ON pm.materia_id=m.id
        WHERE p.semestre='$sem'
        GROUP BY p.id
        ORDER BY a.nombre
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

// Filtros
$estado_f = $_GET['estado'] ?? '';
$where = $periodo ? "WHERE p.semestre='{$periodo['semestre']}'" : "WHERE 1=0";
if ($estado_f) $where .= " AND p.estado='$estado_f'";

$preinscripciones = $conn->query("
    SELECT p.*, a.nombre, a.matricula, a.correo, c.clave AS carrera_clave, a.semestre_actual,
           (SELECT COUNT(*) FROM preinscripcion_materias pm WHERE pm.preinscripcion_id=p.id) AS num_materias,
           (SELECT SUM(m.creditos) FROM preinscripcion_materias pm JOIN materias m ON pm.materia_id=m.id WHERE pm.preinscripcion_id=p.id) AS total_cred
    FROM preinscripciones p
    JOIN alumnos a ON p.alumno_id=a.id
    JOIN carreras c ON a.carrera_id=c.id
    $where ORDER BY p.id DESC
")->fetch_all(MYSQLI_ASSOC);

// Conteos por estado
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
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--navy:#0f1e2e;--blue:#1A3A5C;--accent:#e8a020;--accent2:#2E6DA4;--border:#2a3f55;--text:#c8d8e8;--muted:#6a8aaa;--card:rgba(26,58,92,.2);}
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
.page-title{font-size:22px;font-weight:600;color:#fff;margin-bottom:4px;}
.page-sub{font-size:13px;color:var(--muted);margin-bottom:1.5rem;}
.toolbar{display:flex;gap:10px;margin-bottom:1.2rem;flex-wrap:wrap;align-items:center;}
.btn-p{background:var(--blue);color:white;border:1px solid var(--accent2);border-radius:8px;padding:9px 16px;font-size:13px;font-weight:600;cursor:pointer;font-family:'IBM Plex Sans',sans-serif;text-decoration:none;display:inline-block;}
.btn-p:hover{background:var(--accent2);}
.btn-s{background:transparent;color:var(--text);border:1px solid var(--border);border-radius:8px;padding:8px 14px;font-size:12px;cursor:pointer;font-family:'IBM Plex Sans',sans-serif;text-decoration:none;display:inline-block;}
.btn-s:hover{border-color:var(--accent2);color:#fff;}
.btn-warn{background:rgba(251,191,36,.08);color:#fbbf24;border:1px solid rgba(251,191,36,.25);border-radius:8px;padding:8px 14px;font-size:12px;cursor:pointer;font-family:'IBM Plex Sans',sans-serif;text-decoration:none;display:inline-block;}
.btn-warn:hover{background:rgba(251,191,36,.15);}
.btn-danger{background:transparent;color:#f87171;border:1px solid rgba(248,113,113,.3);border-radius:6px;padding:5px 10px;font-size:11px;cursor:pointer;text-decoration:none;display:inline-block;}
.btn-danger:hover{background:rgba(248,113,113,.1);}
.msg{background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.25);color:#4ade80;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:1rem;}
.filter-tabs{display:flex;gap:8px;margin-bottom:1.2rem;flex-wrap:wrap;}
.ftab{font-size:12px;padding:6px 14px;border-radius:20px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;text-decoration:none;transition:all .15s;}
.ftab:hover{border-color:var(--accent2);color:#fff;}
.ftab.on{background:rgba(46,109,164,.2);border-color:var(--accent2);color:#fff;}
.cnt{font-family:'IBM Plex Mono',monospace;font-size:10px;margin-left:5px;opacity:.7;}
.table{width:100%;border-collapse:collapse;}
.table th{font-family:'IBM Plex Mono',monospace;font-size:10px;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;padding:10px 12px;border-bottom:1px solid var(--border);text-align:left;}
.table td{padding:10px 12px;font-size:12px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:top;}
.table tr:hover td{background:rgba(255,255,255,.02);}
.bdg{font-size:10px;padding:2px 7px;border-radius:4px;font-family:'IBM Plex Mono',monospace;white-space:nowrap;}
.b-en{background:rgba(96,165,250,.1);color:#60a5fa;border:1px solid rgba(96,165,250,.2);}
.b-act{background:rgba(74,222,128,.1);color:#4ade80;border:1px solid rgba(74,222,128,.2);}
.b-auto{background:rgba(251,191,36,.1);color:#fbbf24;border:1px solid rgba(251,191,36,.2);}
.b-can{background:rgba(248,113,113,.1);color:#f87171;border:1px solid rgba(248,113,113,.2);}
.mat-list{font-size:11px;color:var(--muted);margin-top:3px;line-height:1.5;}
select{background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;padding:8px 13px;font-size:13px;color:var(--text);font-family:'IBM Plex Sans',sans-serif;outline:none;}
select option{background:#0f1e2e;}
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
    <div style="background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:10px;padding:2rem;text-align:center;color:var(--muted);">No hay periodo activo. <a href="admin_periodos.php" style="color:var(--accent2)">Crear uno</a></div>
    <?php else: ?>

    <!-- CONTEOS RÁPIDOS -->
    <div style="display:flex;gap:10px;margin-bottom:1.2rem;flex-wrap:wrap;">
        <?php
        $labels=['en_proceso'=>['En proceso','b-en'],'activada'=>['Activadas','b-act'],'auto_activada'=>['Auto-activadas','b-auto'],'cancelada'=>['Canceladas','b-can']];
        foreach ($labels as $k=>[$l,$cls]):
        ?><div style="background:var(--card);border:1px solid var(--border);border-radius:8px;padding:.7rem 1.2rem;text-align:center;">
            <div style="font-size:20px;font-weight:600;color:#fff"><?= $conteos[$k] ?? 0 ?></div>
            <div style="font-size:11px;color:var(--muted);margin-top:2px"><?= $l ?></div>
        </div><?php endforeach; ?>
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
    <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;">
    <table class="table">
        <thead><tr>
            <th>Alumno</th><th>Matrícula</th><th>Carrera</th><th>Sem.</th><th>Estado</th><th>Materias</th><th>Créditos</th><th>Activación</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($preinscripciones as $p): ?>
        <tr>
            <td>
                <div style="font-weight:500;color:#fff"><?= htmlspecialchars($p['nombre']) ?></div>
                <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($p['correo']) ?></div>
            </td>
            <td><span style="font-family:'IBM Plex Mono',monospace"><?= htmlspecialchars($p['matricula']) ?></span></td>
            <td><?= htmlspecialchars($p['carrera_clave']) ?></td>
            <td style="text-align:center"><?= $p['semestre_actual'] ?>°</td>
            <td>
                <?php
                $bmap=['en_proceso'=>'b-en','activada'=>'b-act','auto_activada'=>'b-auto','cancelada'=>'b-can'];
                $lmap=['en_proceso'=>'EN PROCESO','activada'=>'ACTIVADA','auto_activada'=>'AUTO-ACTIV.','cancelada'=>'CANCELADA'];
                $est=$p['estado'];
                ?><span class="bdg <?= $bmap[$est]??'' ?>"><?= $lmap[$est]??strtoupper($est) ?></span>
            </td>
            <td style="text-align:center"><?= $p['num_materias'] ?></td>
            <td style="text-align:center"><?= $p['total_cred'] ?? 0 ?></td>
            <td style="font-size:11px;color:var(--muted)"><?= $p['fecha_activacion']?date('d/m/Y H:i',strtotime($p['fecha_activacion'])):'-' ?></td>
            <td>
                <?php if ($p['estado']==='en_proceso'): ?>
                <a href="?cancelar=<?= $p['id'] ?>" onclick="return confirm('¿Cancelar esta preinscripción?')" class="btn-danger">Cancelar</a>
                <?php endif; ?>
                <a href="admin_alumnos.php?id=<?= $p['alumno_id'] ?>" class="btn-s" style="font-size:11px;padding:4px 8px;">Ver alumno</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($preinscripciones)): ?>
        <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:2rem;">Sin resultados.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
</body>
</html>