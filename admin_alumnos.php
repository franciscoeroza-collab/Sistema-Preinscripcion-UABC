<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
require 'db.php';

$msg = $err = "";


// Toggle inscripcion activa
if (isset($_GET['toggle_inscripcion'])) {
    $aid = (int)$_GET['toggle_inscripcion'];
    $conn->query("UPDATE alumnos SET inscripcion_activa = NOT inscripcion_activa WHERE id=$aid");
    $msg = "Estado de inscripción actualizado.";
}

// Detalle de alumno
$detalle = null;
if (isset($_GET['id'])) {
    $aid = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT a.*,c.nombre AS carrera_nombre,c.clave AS carrera_clave,c.total_creditos FROM alumnos a JOIN carreras c ON a.carrera_id=c.id WHERE a.id=?");
    $stmt->bind_param("i",$aid);
    $stmt->execute();
    $detalle = $stmt->get_result()->fetch_assoc();

    if ($detalle) {
        $hist = $conn->prepare("SELECT m.nombre,m.clave,m.semestre,c.calificacion,c.veces_cursada,c.semestre AS periodo FROM calificaciones c JOIN materias m ON c.materia_id=m.id WHERE c.alumno_id=? ORDER BY m.semestre,m.nombre");
        $hist->bind_param("i",$aid);
        $hist->execute();
        $historial = $hist->get_result()->fetch_all(MYSQLI_ASSOC);



        $periodo = $conn->query("SELECT * FROM periodo_preinscripcion WHERE activo=1 LIMIT 1")->fetch_assoc();
        $preinsc = null; $mat_preinsc = [];
        if ($periodo) {
            $stmt3 = $conn->prepare("SELECT * FROM preinscripciones WHERE alumno_id=? AND semestre=? ORDER BY id DESC LIMIT 1");
            $stmt3->bind_param("is",$aid,$periodo['semestre']);
            $stmt3->execute();
            $preinsc = $stmt3->get_result()->fetch_assoc();
            if ($preinsc) {
                $stmt4 = $conn->prepare("SELECT pm.*,m.nombre,m.clave,m.creditos FROM preinscripcion_materias pm JOIN materias m ON pm.materia_id=m.id WHERE pm.preinscripcion_id=?");
                $stmt4->bind_param("i",$preinsc['id']);
                $stmt4->execute();
                $mat_preinsc = $stmt4->get_result()->fetch_all(MYSQLI_ASSOC);
            }
        }

        $ep = $conn->prepare("SELECT m.nombre FROM calificaciones c JOIN materias m ON c.materia_id=m.id WHERE c.alumno_id=? AND c.veces_cursada>=2 AND c.calificacion<60");
        $ep->bind_param("i",$aid);
        $ep->execute();
        $ep_mats = $ep->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Lista
$busq = trim($_GET['q'] ?? '');
$carrera_f = (int)($_GET['carrera'] ?? 0);
$where = "WHERE 1=1"; $params = []; $types = "";
if ($busq) { $where .= " AND (a.nombre LIKE ? OR a.matricula LIKE ? OR a.correo LIKE ?)"; $b="%$busq%"; $params[]=$b; $params[]=$b; $params[]=$b; $types.="sss"; }
if ($carrera_f) { $where .= " AND a.carrera_id=?"; $params[]=$carrera_f; $types.="i"; }
$sql = "SELECT a.*,c.nombre AS carrera_nombre,c.clave AS carrera_clave FROM alumnos a JOIN carreras c ON a.carrera_id=c.id $where ORDER BY a.nombre";
$stmt = $conn->prepare($sql);
if ($params) { $stmt->bind_param($types,...$params); }
$stmt->execute();
$alumnos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$carreras = $conn->query("SELECT * FROM carreras ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Alumnos — Admin UABC</title>
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

/* SIDEBAR */
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

/* MAIN */
.main{margin-left:240px;flex:1;padding:2rem 2.4rem;}
.page-title{font-family:'Fraunces',serif;font-size:26px;font-weight:600;color:var(--navy);margin-bottom:4px;}
.page-sub{font-size:13px;color:var(--muted);margin-bottom:1.5rem;}

.msg{background:var(--success-bg);border:1.5px solid var(--success-border);color:var(--success);border-radius:9px;padding:10px 14px;font-size:13px;margin-bottom:1rem;}
.err{background:var(--danger-bg);border:1.5px solid var(--danger-border);color:var(--danger);border-radius:9px;padding:10px 14px;font-size:13px;margin-bottom:1rem;}

/* BOTONES */
.btn-p{background:var(--navy);color:white;border:none;border-radius:9px;padding:9px 18px;font-size:13px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;text-decoration:none;display:inline-block;transition:background .2s;}
.btn-p:hover{background:var(--navy-light);}
.btn-s{background:var(--white);color:var(--text-mid);border:1.5px solid var(--border);border-radius:8px;padding:7px 14px;font-size:12px;cursor:pointer;font-family:'DM Sans',sans-serif;text-decoration:none;display:inline-block;transition:all .15s;}
.btn-s:hover{border-color:var(--navy-light);color:var(--navy);}
.btn-danger{background:var(--white);color:var(--danger);border:1px solid var(--danger-border);border-radius:6px;padding:5px 10px;font-size:11px;cursor:pointer;font-family:'DM Sans',sans-serif;text-decoration:none;display:inline-block;transition:all .15s;}
.btn-danger:hover{background:var(--danger-bg);}
.btn-toggle-on{background:var(--danger-bg);color:var(--danger);border:1.5px solid var(--danger-border);border-radius:8px;padding:8px 16px;font-size:12px;cursor:pointer;font-family:'DM Sans',sans-serif;text-decoration:none;display:inline-block;transition:all .15s;}
.btn-toggle-on:hover{background:#fecaca;}
.btn-toggle-off{background:var(--success-bg);color:var(--success);border:1.5px solid var(--success-border);border-radius:8px;padding:8px 16px;font-size:12px;cursor:pointer;font-family:'DM Sans',sans-serif;text-decoration:none;display:inline-block;transition:all .15s;}
.btn-toggle-off:hover{background:#bbf7d0;}

/* TOOLBAR */
.toolbar{display:flex;gap:10px;margin-bottom:1.2rem;flex-wrap:wrap;align-items:center;}
input[type="text"]{background:var(--white);border:1.5px solid var(--border);border-radius:9px;padding:9px 13px;font-size:13px;color:var(--text);font-family:'DM Sans',sans-serif;outline:none;transition:border-color .2s;}
input[type="text"]:focus{border-color:var(--navy-light);}
select{background:var(--white);border:1.5px solid var(--border);border-radius:9px;padding:9px 13px;font-size:13px;color:var(--text);font-family:'DM Sans',sans-serif;outline:none;transition:border-color .2s;}
select:focus{border-color:var(--navy-light);}

/* TABLE */
.table-wrap{background:var(--white);border:1.5px solid var(--border);border-radius:12px;overflow:hidden;}
table{width:100%;border-collapse:collapse;}
th{font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);padding:11px 13px;border-bottom:1.5px solid var(--border);text-align:left;background:var(--surface);}
td{padding:11px 13px;font-size:13px;border-bottom:1px solid var(--surface);vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:var(--off-white);}

/* BADGES */
.bdg{font-size:10px;padding:3px 8px;border-radius:5px;font-weight:600;white-space:nowrap;}
.bdg-act  {background:var(--success-bg);color:var(--success);border:1px solid var(--success-border);}
.bdg-inact{background:var(--danger-bg);color:var(--danger);border:1px solid var(--danger-border);}
.bdg-ep   {background:var(--warning-bg);color:var(--warning);border:1px solid var(--warning-border);}
.bdg-adeu {background:var(--danger-bg);color:var(--danger);border:1px solid var(--danger-border);}
.bdg-preinsc{background:var(--info-bg);color:var(--info);border:1px solid var(--info-border);}

/* DETALLE */
.back-link{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--muted);text-decoration:none;margin-bottom:1.2rem;padding:7px 12px;border:1.5px solid var(--border);border-radius:8px;background:var(--white);transition:all .15s;}
.back-link:hover{color:var(--navy);border-color:var(--navy-light);}

.det-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.4rem;background:var(--white);border:1.5px solid var(--border);border-radius:12px;padding:1.3rem 1.5rem;}
.det-name{font-family:'Fraunces',serif;font-size:20px;font-weight:600;color:var(--navy);}
.det-sub{font-size:13px;color:var(--muted);margin-top:3px;}
.det-badges{display:flex;gap:7px;flex-wrap:wrap;margin-top:10px;}

.grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:1.2rem;}
.scard{background:var(--white);border:1.5px solid var(--border);border-radius:10px;padding:1rem;text-align:center;}
.scard .v{font-family:'Fraunces',serif;font-size:24px;font-weight:700;color:var(--navy);}
.scard .l{font-size:11px;color:var(--muted);margin-top:4px;}

.grid2d{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.box{background:var(--white);border:1.5px solid var(--border);border-radius:12px;padding:1.2rem;margin-bottom:12px;}
.box h4{font-size:14px;font-weight:600;color:var(--navy);margin-bottom:.9rem;padding-bottom:.6rem;border-bottom:1.5px solid var(--border);}

/* HISTORIAL */
.hist-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--surface);}
.hist-row:last-child{border:none;}
.hist-nm{font-size:12px;font-weight:500;color:var(--text-mid);}
.hist-sem{font-size:11px;color:var(--muted);margin-top:2px;}
.cal-ok  {color:var(--success);font-size:13px;font-weight:700;}
.cal-fail{color:var(--danger);font-size:13px;font-weight:700;}
.cal-ep  {color:var(--warning);font-size:13px;font-weight:700;}



/* PREINSCRIPCIÓN EN DETALLE */
.mat-preinsc{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--surface);font-size:12px;color:var(--text-mid);}
.mat-preinsc:last-child{border:none;}
.mat-preinsc span:last-child{color:var(--muted);}
</style>
</head>
<body>
<?php include 'admin_sidebar.php'; ?>
<div class="main">

<?php if ($detalle): ?>
<!-- DETALLE ALUMNO -->
<a href="admin_alumnos.php" class="back-link">← Volver a la lista</a>

<?php if ($msg): ?><div class="msg"><?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="err"><?= $err ?></div><?php endif; ?>

<div class="det-header">
    <div>
        <div class="det-name"><?= htmlspecialchars($detalle['nombre']) ?></div>
        <div class="det-sub"><?= htmlspecialchars($detalle['matricula']) ?> · <?= htmlspecialchars($detalle['correo']) ?> · <?= htmlspecialchars($detalle['carrera_nombre']) ?></div>
        <div class="det-badges">
            <span class="bdg <?= $detalle['inscripcion_activa']?'bdg-act':'bdg-inact' ?>"><?= $detalle['inscripcion_activa']?'INSCRIPCIÓN ACTIVA':'INSCRIPCIÓN INACTIVA' ?></span>
            <?php if (!empty($ep_mats)): ?><span class="bdg bdg-ep">EVAL. PERMANENTE</span><?php endif; ?>

        </div>
    </div>
    <a href="?id=<?= $detalle['id'] ?>&toggle_inscripcion=<?= $detalle['id'] ?>"
       onclick="return confirm('¿Cambiar estado de inscripción?')"
       class="<?= $detalle['inscripcion_activa'] ? 'btn-toggle-on' : 'btn-toggle-off' ?>">
        <?= $detalle['inscripcion_activa'] ? 'Desactivar inscripción' : 'Activar inscripción' ?>
    </a>
</div>

<div class="grid3">
    <div class="scard"><div class="v"><?= $detalle['semestre_actual'] ?>°</div><div class="l">Semestre actual</div></div>
    <div class="scard"><div class="v"><?= $detalle['creditos_acumulados'] ?></div><div class="l">Créditos acumulados</div></div>
    <div class="scard"><div class="v"><?= $detalle['total_creditos'] > 0 ? round(($detalle['creditos_acumulados']/$detalle['total_creditos'])*100) : 0 ?>%</div><div class="l">Avance de carrera</div></div>
</div>

<div class="grid2d">
    <!-- HISTORIAL -->
    <div class="box">
        <h4>Historial académico</h4>
        <?php if (empty($historial)): ?>
        <p style="font-size:12px;color:var(--muted)">Sin calificaciones registradas.</p>
        <?php else: ?>
        <?php foreach ($historial as $h):
            $cal = $h['calificacion'];
            $cls = $cal>=60 ? 'cal-ok' : ($h['veces_cursada']>=2 ? 'cal-ep' : 'cal-fail');
        ?>
        <div class="hist-row">
            <div>
                <div class="hist-nm"><?= htmlspecialchars($h['nombre']) ?></div>
                <div class="hist-sem">Sem <?= $h['semestre'] ?> · Periodo <?= $h['periodo'] ?>
                    <?php if ($h['veces_cursada'] > 1): ?> · <span style="color:var(--warning);font-weight:600">Cursada <?= $h['veces_cursada'] ?>x</span><?php endif; ?>
                </div>
            </div>
            <span class="<?= $cls ?>"><?= $cal ?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div>


        <!-- PREINSCRIPCIÓN ACTUAL -->
        <?php if ($periodo): ?>
        <div class="box">
            <h4>Preinscripción <?= htmlspecialchars($periodo['semestre']) ?></h4>
            <?php if (!$preinsc): ?>
            <p style="font-size:12px;color:var(--muted)">Sin preinscripción registrada en este periodo.</p>
            <?php else:
                $bmap=['en_proceso'=>'bdg-preinsc','activada'=>'bdg-act','auto_activada'=>'bdg-ep','cancelada'=>'bdg-inact'];
                $lmap=['en_proceso'=>'EN PROCESO','activada'=>'ACTIVADA','auto_activada'=>'AUTO-ACTIVADA','cancelada'=>'CANCELADA'];
                $est=$preinsc['estado'];
            ?>
            <div style="margin-bottom:.8rem;display:flex;align-items:center;gap:10px;">
                <span class="bdg <?= $bmap[$est]??'' ?>"><?= $lmap[$est]??strtoupper($est) ?></span>
                <?php if ($preinsc['fecha_activacion']): ?>
                <span style="font-size:11px;color:var(--muted)">Activada: <?= date('d/m/Y H:i',strtotime($preinsc['fecha_activacion'])) ?></span>
                <?php endif; ?>
            </div>
            <?php foreach ($mat_preinsc as $m): ?>
            <div class="mat-preinsc">
                <span><?= htmlspecialchars($m['nombre']) ?> <span style="color:var(--muted);font-size:11px">(<?= $m['clave'] ?>)</span></span>
                <span><?= $m['creditos'] ?> cr.</span>
            </div>
            <?php endforeach; ?>
            <?php if (!empty($mat_preinsc)): ?>
            <div style="display:flex;justify-content:flex-end;margin-top:8px;font-size:13px;font-weight:700;color:var(--navy)">
                Total: <?= array_sum(array_column($mat_preinsc,'creditos')) ?> créditos
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<!-- LISTA ALUMNOS -->
<div class="page-title">Alumnos</div>
<div class="page-sub">Consulta el detalle de cada alumno, historial y adeudos</div>

<?php if ($msg): ?><div class="msg"><?= $msg ?></div><?php endif; ?>

<form method="GET" class="toolbar">
    <input type="text" name="q" placeholder="Buscar por nombre, matrícula o correo..." value="<?= htmlspecialchars($busq) ?>" style="flex:1;min-width:200px;">
    <select name="carrera">
        <option value="">Todas las carreras</option>
        <?php foreach ($carreras as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $carrera_f==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['clave']) ?> — <?= htmlspecialchars($c['nombre']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn-p">Buscar</button>
    <?php if ($busq || $carrera_f): ?><a href="admin_alumnos.php" class="btn-s">Limpiar</a><?php endif; ?>
</form>

<div style="font-size:12px;color:var(--muted);margin-bottom:.8rem;">
    <?= count($alumnos) ?> alumno<?= count($alumnos)!=1?'s':'' ?> encontrado<?= count($alumnos)!=1?'s':'' ?>
</div>

<div class="table-wrap">
<table>
    <thead><tr>
        <th>Alumno</th><th>Matrícula</th><th>Carrera</th><th>Sem.</th><th>Créditos</th><th>Estado</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($alumnos as $a):
        $stmt_ep = $conn->prepare("SELECT COUNT(*) as t FROM calificaciones WHERE alumno_id=? AND veces_cursada>=2 AND calificacion<60");
        $stmt_ep->bind_param("i",$a['id']); $stmt_ep->execute();
        $tiene_ep = $stmt_ep->get_result()->fetch_assoc()['t'] > 0;

    ?>
    <tr>
        <td>
            <div style="font-weight:600;color:var(--navy)"><?= htmlspecialchars($a['nombre']) ?></div>
            <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($a['correo']) ?></div>
        </td>
        <td><span style="font-family:monospace;font-size:12px"><?= htmlspecialchars($a['matricula']) ?></span></td>
        <td><span style="font-size:12px"><?= htmlspecialchars($a['carrera_clave']) ?></span></td>
        <td style="text-align:center"><?= $a['semestre_actual'] ?>°</td>
        <td style="text-align:center"><?= $a['creditos_acumulados'] ?></td>
        <td>
            <div style="display:flex;gap:5px;flex-wrap:wrap;">
                <span class="bdg <?= $a['inscripcion_activa']?'bdg-act':'bdg-inact' ?>"><?= $a['inscripcion_activa']?'ACTIVO':'INACTIVO' ?></span>
                <?php if ($tiene_ep): ?><span class="bdg bdg-ep">EP</span><?php endif; ?>

            </div>
        </td>
        <td><a href="?id=<?= $a['id'] ?>" class="btn-s" style="font-size:11px;padding:5px 10px;">Ver detalle</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($alumnos)): ?>
    <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem;font-size:13px;">No se encontraron alumnos.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

</div>
</body>
</html>