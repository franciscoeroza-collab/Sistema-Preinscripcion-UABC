<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: admin_login.php"); exit(); }
require 'db.php';

$msg = $err = "";

// Agregar adeudo
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_adeudo'])) {
    $aid  = (int)$_POST['alumno_id'];
    $desc = trim($_POST['descripcion']);
    $tipo = $_POST['tipo'];
    $vent = trim($_POST['ventanilla']);
    if ($desc && $tipo && $vent) {
        $stmt = $conn->prepare("INSERT INTO adeudos (alumno_id,descripcion,tipo,ventanilla,activo) VALUES (?,?,?,?,1)");
        $stmt->bind_param("isss",$aid,$desc,$tipo,$vent);
        $stmt->execute();
        $msg = "Adeudo agregado correctamente.";
    } else { $err = "Completa todos los campos del adeudo."; }
}

// Quitar adeudo
if (isset($_GET['quitar_adeudo'])) {
    $id = (int)$_GET['quitar_adeudo'];
    $conn->query("UPDATE adeudos SET activo=0 WHERE id=$id");
    $msg = "Adeudo desactivado.";
}

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
        // Historial
        $hist = $conn->prepare("SELECT m.nombre,m.clave,m.semestre,c.calificacion,c.veces_cursada,c.semestre AS periodo FROM calificaciones c JOIN materias m ON c.materia_id=m.id WHERE c.alumno_id=? ORDER BY m.semestre,m.nombre");
        $hist->bind_param("i",$aid);
        $hist->execute();
        $historial = $hist->get_result()->fetch_all(MYSQLI_ASSOC);

        // Adeudos
        $stmt2 = $conn->prepare("SELECT * FROM adeudos WHERE alumno_id=? AND activo=1");
        $stmt2->bind_param("i",$aid);
        $stmt2->execute();
        $adeudos = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

        // Preinscripcion actual
        $periodo = $conn->query("SELECT * FROM periodo_preinscripcion WHERE activo=1 LIMIT 1")->fetch_assoc();
        $preinsc = null;
        $mat_preinsc = [];
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

        // EP check
        $ep = $conn->prepare("SELECT m.nombre FROM calificaciones c JOIN materias m ON c.materia_id=m.id WHERE c.alumno_id=? AND c.veces_cursada>=2 AND c.calificacion<60");
        $ep->bind_param("i",$aid);
        $ep->execute();
        $ep_mats = $ep->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Lista de alumnos
$busq  = trim($_GET['q'] ?? '');
$carrera_f = (int)($_GET['carrera'] ?? 0);
$where = "WHERE 1=1";
$params = [];
$types  = "";
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
.toolbar{display:flex;gap:10px;margin-bottom:1.2rem;flex-wrap:wrap;}
input[type="text"],select{background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;padding:9px 13px;font-size:13px;color:var(--text);font-family:'IBM Plex Sans',sans-serif;outline:none;}
input[type="text"]:focus,select:focus{border-color:var(--accent2);}
select option{background:#0f1e2e;}
.btn-p{background:var(--blue);color:white;border:1px solid var(--accent2);border-radius:8px;padding:9px 18px;font-size:13px;font-weight:600;cursor:pointer;font-family:'IBM Plex Sans',sans-serif;text-decoration:none;display:inline-block;}
.btn-p:hover{background:var(--accent2);}
.btn-s{background:transparent;color:var(--text);border:1px solid var(--border);border-radius:8px;padding:7px 14px;font-size:12px;cursor:pointer;font-family:'IBM Plex Sans',sans-serif;text-decoration:none;display:inline-block;}
.btn-s:hover{border-color:var(--accent2);color:#fff;}
.btn-danger{background:transparent;color:#f87171;border:1px solid rgba(248,113,113,.3);border-radius:6px;padding:5px 10px;font-size:11px;cursor:pointer;font-family:'IBM Plex Sans',sans-serif;text-decoration:none;display:inline-block;}
.btn-danger:hover{background:rgba(248,113,113,.1);}
.table{width:100%;border-collapse:collapse;}
.table th{font-family:'IBM Plex Mono',monospace;font-size:10px;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;padding:10px 12px;border-bottom:1px solid var(--border);text-align:left;}
.table td{padding:10px 12px;font-size:13px;border-bottom:1px solid rgba(255,255,255,.04);}
.table tr:hover td{background:rgba(255,255,255,.02);}
.bdg{font-size:10px;padding:2px 7px;border-radius:4px;font-family:'IBM Plex Mono',monospace;}
.bdg-act{background:rgba(74,222,128,.1);color:#4ade80;border:1px solid rgba(74,222,128,.2);}
.bdg-inact{background:rgba(248,113,113,.1);color:#f87171;border:1px solid rgba(248,113,113,.2);}
.bdg-ep{background:rgba(251,191,36,.1);color:#fbbf24;border:1px solid rgba(251,191,36,.2);}
.bdg-adeu{background:rgba(248,113,113,.1);color:#f87171;border:1px solid rgba(248,113,113,.2);}
.msg{background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.25);color:#4ade80;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:1rem;}
.err{background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.25);color:#f87171;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:1rem;}
/* DETALLE */
.detalle-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;}
.det-name{font-size:20px;font-weight:600;color:#fff;}
.det-sub{font-size:13px;color:var(--muted);margin-top:2px;}
.grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:1.2rem;}
.scard{background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:8px;padding:.9rem;text-align:center;}
.scard .v{font-size:22px;font-weight:600;color:#fff;}
.scard .l{font-size:11px;color:var(--muted);margin-top:3px;}
.box{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:1.2rem;margin-bottom:12px;}
.box h4{font-size:13px;font-weight:600;color:#fff;margin-bottom:.8rem;}
.hist-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.04);}
.hist-row:last-child{border:none;}
.hist-nm{font-size:12px;color:var(--text);}
.hist-sem{font-size:11px;color:var(--muted);}
.cal-ok{color:#4ade80;font-family:'IBM Plex Mono',monospace;font-size:12px;}
.cal-fail{color:#f87171;font-family:'IBM Plex Mono',monospace;font-size:12px;}
.cal-ep{color:#fbbf24;font-family:'IBM Plex Mono',monospace;font-size:12px;}
.mat-preinsc{display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.04);font-size:12px;}
.mat-preinsc:last-child{border:none;}
.adeudo-row{display:flex;justify-content:space-between;align-items:center;padding:8px 10px;background:rgba(248,113,113,.06);border:1px solid rgba(248,113,113,.15);border-radius:6px;margin-bottom:6px;}
.adeudo-desc{font-size:12px;color:var(--text);}
.adeudo-vent{font-size:11px;color:var(--muted);}
.add-adeudo-form{background:rgba(255,255,255,.03);border-radius:8px;padding:1rem;margin-top:.8rem;}
.add-adeudo-form label{font-size:10px;font-family:'IBM Plex Mono',monospace;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;display:block;margin-bottom:4px;}
.add-adeudo-form input,.add-adeudo-form select{width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:6px;padding:8px 10px;font-size:12px;color:var(--text);font-family:'IBM Plex Sans',sans-serif;margin-bottom:.7rem;outline:none;}
.add-adeudo-form input:focus,.add-adeudo-form select:focus{border-color:var(--accent2);}
.tab-btn{font-size:12px;padding:6px 14px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;font-family:'IBM Plex Sans',sans-serif;}
.tab-btn.on{background:rgba(46,109,164,.2);border-color:var(--accent2);color:#fff;}
.tabs{display:flex;gap:8px;margin-bottom:1rem;}
</style>
</head>
<body>
<?php include 'admin_sidebar.php'; ?>
<div class="main">

<?php if ($detalle): ?>
<!-- DETALLE ALUMNO -->
<div>
    <a href="admin_alumnos.php" class="btn-s" style="margin-bottom:1.2rem;display:inline-block;">← Volver a la lista</a>
    <?php if ($msg): ?><div class="msg"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="err"><?= $err ?></div><?php endif; ?>

    <div class="detalle-header">
        <div>
            <div class="det-name"><?= htmlspecialchars($detalle['nombre']) ?></div>
            <div class="det-sub"><?= htmlspecialchars($detalle['matricula']) ?> · <?= htmlspecialchars($detalle['correo']) ?></div>
            <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">
                <span class="bdg <?= $detalle['inscripcion_activa']?'bdg-act':'bdg-inact' ?>"><?= $detalle['inscripcion_activa']?'ACTIVO':'INACTIVO' ?></span>
                <?php if (!empty($ep_mats)): ?><span class="bdg bdg-ep">EVAL. PERMANENTE</span><?php endif; ?>
                <?php if (!empty($adeudos)): ?><span class="bdg bdg-adeu"><?= count($adeudos) ?> ADEUDO<?= count($adeudos)!=1?'S':'' ?></span><?php endif; ?>
            </div>
        </div>
        <a href="?id=<?= $detalle['id'] ?>&toggle_inscripcion=<?= $detalle['id'] ?>" onclick="return confirm('¿Cambiar estado de inscripción?')" class="btn-s"><?= $detalle['inscripcion_activa']?'Desactivar inscripción':'Activar inscripción' ?></a>
    </div>

    <div class="grid3">
        <div class="scard"><div class="v"><?= $detalle['semestre_actual'] ?>°</div><div class="l">Semestre actual</div></div>
        <div class="scard"><div class="v"><?= $detalle['creditos_acumulados'] ?></div><div class="l">Créditos acumulados</div></div>
        <div class="scard"><div class="v"><?= round(($detalle['creditos_acumulados']/$detalle['total_creditos'])*100) ?>%</div><div class="l">Avance carrera</div></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <!-- HISTORIAL -->
        <div class="box">
            <h4>Historial académico</h4>
            <?php if (empty($historial)): ?>
            <p style="font-size:12px;color:var(--muted)">Sin calificaciones registradas.</p>
            <?php else: ?>
            <?php foreach ($historial as $h): ?>
            <div class="hist-row">
                <div>
                    <div class="hist-nm"><?= htmlspecialchars($h['nombre']) ?></div>
                    <div class="hist-sem">Sem <?= $h['semestre'] ?> · Periodo <?= $h['periodo'] ?> <?= $h['veces_cursada']>1?" · <span style='color:#fbbf24'>Cursada {$h['veces_cursada']}x</span>":'' ?></div>
                </div>
                <?php
                $cal = $h['calificacion'];
                $cls = $cal>=60?'cal-ok':($h['veces_cursada']>=2?'cal-ep':'cal-fail');
                ?>
                <span class="<?= $cls ?>"><?= $cal ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div>
            <!-- ADEUDOS -->
            <div class="box">
                <h4>Adeudos activos</h4>
                <?php if (empty($adeudos)): ?>
                <p style="font-size:12px;color:var(--muted)">Sin adeudos.</p>
                <?php else: ?>
                <?php foreach ($adeudos as $a): ?>
                <div class="adeudo-row">
                    <div><div class="adeudo-desc"><?= htmlspecialchars($a['descripcion']) ?></div><div class="adeudo-vent"><?= htmlspecialchars($a['ventanilla']) ?> · <?= $a['tipo'] ?></div></div>
                    <a href="?id=<?= $detalle['id'] ?>&quitar_adeudo=<?= $a['id'] ?>" onclick="return confirm('¿Desactivar este adeudo?')" class="btn-danger">Quitar</a>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
                <div class="add-adeudo-form">
                    <div style="font-size:11px;color:var(--muted);margin-bottom:.6rem;">Agregar adeudo</div>
                    <form method="POST">
                        <input type="hidden" name="alumno_id" value="<?= $detalle['id'] ?>">
                        <label>Descripción</label>
                        <input type="text" name="descripcion" placeholder="Ej. Pago de credencial pendiente" required>
                        <label>Tipo</label>
                        <select name="tipo">
                            <option value="administrativo">Administrativo</option>
                            <option value="academico">Académico</option>
                        </select>
                        <label>Ventanilla</label>
                        <input type="text" name="ventanilla" placeholder="Ej. Ventanilla 3 - Servicios Escolares" required>
                        <button type="submit" name="add_adeudo" class="btn-p" style="font-size:12px;padding:8px 16px;">Agregar</button>
                    </form>
                </div>
            </div>

            <!-- PREINSCRIPCION ACTUAL -->
            <?php if ($periodo): ?>
            <div class="box">
                <h4>Preinscripción <?= htmlspecialchars($periodo['semestre']) ?></h4>
                <?php if (!$preinsc): ?>
                <p style="font-size:12px;color:var(--muted)">Sin preinscripción registrada.</p>
                <?php else: ?>
                <div style="margin-bottom:.8rem;">
                    <span class="bdg <?= $preinsc['estado']=='activada'||$preinsc['estado']=='auto_activada'?'bdg-act':'' ?>" style="font-size:11px;"><?= strtoupper(str_replace('_',' ',$preinsc['estado'])) ?></span>
                    <?php if ($preinsc['fecha_activacion']): ?>
                    <span style="font-size:11px;color:var(--muted);margin-left:8px;">Activada: <?= date('d/m/Y H:i',strtotime($preinsc['fecha_activacion'])) ?></span>
                    <?php endif; ?>
                </div>
                <?php foreach ($mat_preinsc as $m): ?>
                <div class="mat-preinsc"><span><?= htmlspecialchars($m['nombre']) ?></span><span style="color:var(--muted)"><?= $m['creditos'] ?> cr.</span></div>
                <?php endforeach; ?>
                <?php if (!empty($mat_preinsc)): ?>
                <div style="display:flex;justify-content:flex-end;margin-top:8px;font-size:12px;color:var(--accent)">Total: <?= array_sum(array_column($mat_preinsc,'creditos')) ?> créditos</div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
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
<div style="font-size:12px;color:var(--muted);margin-bottom:.8rem;"><?= count($alumnos) ?> alumno<?= count($alumnos)!=1?'s':'' ?> encontrado<?= count($alumnos)!=1?'s':'' ?></div>

<div style="background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;">
<table class="table">
    <thead><tr>
        <th>Alumno</th><th>Matrícula</th><th>Carrera</th><th>Sem.</th><th>Créditos</th><th>Estado</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($alumnos as $a):
        // Verificar EP y adeudos
        $stmt_ep = $conn->prepare("SELECT COUNT(*) as t FROM calificaciones WHERE alumno_id=? AND veces_cursada>=2 AND calificacion<60");
        $stmt_ep->bind_param("i",$a['id']); $stmt_ep->execute();
        $tiene_ep = $stmt_ep->get_result()->fetch_assoc()['t'] > 0;
        $stmt_ad = $conn->prepare("SELECT COUNT(*) as t FROM adeudos WHERE alumno_id=? AND activo=1");
        $stmt_ad->bind_param("i",$a['id']); $stmt_ad->execute();
        $tiene_ad = $stmt_ad->get_result()->fetch_assoc()['t'] > 0;
    ?>
    <tr>
        <td>
            <div style="font-weight:500;color:#fff"><?= htmlspecialchars($a['nombre']) ?></div>
            <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($a['correo']) ?></div>
        </td>
        <td><span style="font-family:'IBM Plex Mono',monospace;font-size:12px"><?= htmlspecialchars($a['matricula']) ?></span></td>
        <td><span style="font-size:12px"><?= htmlspecialchars($a['carrera_clave']) ?></span></td>
        <td style="text-align:center"><?= $a['semestre_actual'] ?>°</td>
        <td style="text-align:center"><?= $a['creditos_acumulados'] ?></td>
        <td>
            <div style="display:flex;gap:4px;flex-wrap:wrap;">
                <span class="bdg <?= $a['inscripcion_activa']?'bdg-act':'bdg-inact' ?>"><?= $a['inscripcion_activa']?'ACTIVO':'INACTIVO' ?></span>
                <?php if ($tiene_ep): ?><span class="bdg bdg-ep">EP</span><?php endif; ?>
                <?php if ($tiene_ad): ?><span class="bdg bdg-adeu">ADEUDO</span><?php endif; ?>
            </div>
        </td>
        <td><a href="?id=<?= $a['id'] ?>" class="btn-s" style="font-size:11px;padding:5px 10px;">Ver detalle</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($alumnos)): ?>
    <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem;">No se encontraron alumnos.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

</div>
</body>
</html>