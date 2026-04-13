<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: admin_login.php"); exit(); }
require 'db.php';

$msg = $err = "";

// Crear periodo
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['crear'])) {
    $sem   = trim($_POST['semestre']);
    $ini   = $_POST['fecha_inicio'];
    $fin   = $_POST['fecha_fin'];
    if ($sem && $ini && $fin) {
        // Desactivar todos primero
        $conn->query("UPDATE periodo_preinscripcion SET activo=0");
        $stmt = $conn->prepare("INSERT INTO periodo_preinscripcion (semestre,fecha_inicio,fecha_fin,activo) VALUES (?,?,?,1)");
        $stmt->bind_param("sss",$sem,$ini,$fin);
        $stmt->execute();
        $msg = "Periodo <strong>$sem</strong> creado y activado correctamente.";
    } else { $err = "Todos los campos son requeridos."; }
}

// Activar un periodo existente
if (isset($_GET['activar'])) {
    $id = (int)$_GET['activar'];
    $conn->query("UPDATE periodo_preinscripcion SET activo=0");
    $conn->query("UPDATE periodo_preinscripcion SET activo=1 WHERE id=$id");
    $msg = "Periodo activado.";
}

// Cerrar periodo activo
if (isset($_GET['cerrar'])) {
    $id = (int)$_GET['cerrar'];
    $conn->query("UPDATE periodo_preinscripcion SET activo=0 WHERE id=$id");
    $msg = "Periodo cerrado manualmente.";
}

// Editar fechas
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['editar'])) {
    $id  = (int)$_POST['edit_id'];
    $ini = $_POST['edit_ini'];
    $fin = $_POST['edit_fin'];
    $sem = trim($_POST['edit_sem']);
    $stmt = $conn->prepare("UPDATE periodo_preinscripcion SET semestre=?,fecha_inicio=?,fecha_fin=? WHERE id=?");
    $stmt->bind_param("sssi",$sem,$ini,$fin,$id);
    $stmt->execute();
    $msg = "Periodo actualizado correctamente.";
}

$periodos = $conn->query("SELECT * FROM periodo_preinscripcion ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$activo   = null;
foreach ($periodos as $p) { if ($p['activo']) { $activo = $p; break; } }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Periodos — Admin UABC</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--navy:#0f1e2e;--blue:#1A3A5C;--accent:#e8a020;--accent2:#2E6DA4;--border:#2a3f55;--text:#c8d8e8;--muted:#6a8aaa;--card:rgba(26,58,92,.2);}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'IBM Plex Sans',sans-serif;background:var(--navy);color:var(--text);min-height:100vh;display:flex;}
<?php include_styles(); ?>
.main{margin-left:220px;flex:1;padding:2rem;}
.page-title{font-size:22px;font-weight:600;color:#fff;margin-bottom:4px;}
.page-sub{font-size:13px;color:var(--muted);margin-bottom:2rem;}
.grid2{display:grid;grid-template-columns:1fr 1.6fr;gap:16px;}
.box{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:1.4rem;margin-bottom:16px;}
.box h3{font-size:13px;font-weight:600;color:#fff;margin-bottom:1rem;}
label{font-size:11px;font-family:'IBM Plex Mono',monospace;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;display:block;margin-bottom:5px;}
input[type="text"],input[type="date"]{width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;padding:10px 12px;font-size:13px;color:var(--text);font-family:'IBM Plex Sans',sans-serif;margin-bottom:1rem;outline:none;transition:border-color .2s;}
input:focus{border-color:var(--accent2);}
.btn-p{background:var(--blue);color:white;border:1px solid var(--accent2);border-radius:8px;padding:10px 20px;font-size:13px;font-weight:600;cursor:pointer;font-family:'IBM Plex Sans',sans-serif;transition:all .2s;}
.btn-p:hover{background:var(--accent2);}
.btn-s{background:transparent;color:var(--text);border:1px solid var(--border);border-radius:8px;padding:8px 16px;font-size:12px;cursor:pointer;font-family:'IBM Plex Sans',sans-serif;transition:all .2s;text-decoration:none;display:inline-block;}
.btn-s:hover{border-color:var(--accent2);color:#fff;}
.btn-danger{background:transparent;color:#f87171;border:1px solid rgba(248,113,113,.3);border-radius:8px;padding:8px 16px;font-size:12px;cursor:pointer;font-family:'IBM Plex Sans',sans-serif;text-decoration:none;display:inline-block;}
.btn-danger:hover{background:rgba(248,113,113,.1);}
.msg{background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.25);color:#4ade80;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:1rem;}
.err{background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.25);color:#f87171;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:1rem;}
.periodo-card{border:1px solid var(--border);border-radius:10px;padding:1rem 1.2rem;margin-bottom:10px;background:rgba(255,255,255,.03);}
.periodo-card.activo{border-color:rgba(232,160,32,.4);background:rgba(232,160,32,.05);}
.pc-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;}
.pc-sem{font-size:14px;font-weight:600;color:#fff;}
.pc-badge{font-family:'IBM Plex Mono',monospace;font-size:10px;padding:3px 8px;border-radius:4px;}
.badge-act{background:rgba(74,222,128,.1);color:#4ade80;border:1px solid rgba(74,222,128,.2);}
.badge-inact{background:rgba(255,255,255,.05);color:var(--muted);border:1px solid var(--border);}
.pc-fechas{font-size:12px;color:var(--muted);margin-bottom:10px;}
.pc-acc{display:flex;gap:8px;flex-wrap:wrap;}
.activo-box{background:rgba(232,160,32,.08);border:1px solid rgba(232,160,32,.25);border-radius:10px;padding:1.2rem;margin-bottom:1rem;}
.activo-box .sem{font-size:15px;font-weight:600;color:var(--accent);}
.activo-box .fechas{font-size:13px;color:var(--text);margin-top:4px;}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:100;align-items:center;justify-content:center;}
.modal.open{display:flex;}
.modal-box{background:#0f1e2e;border:1px solid var(--border);border-radius:12px;padding:1.6rem;width:380px;}
.modal-box h4{font-size:15px;font-weight:600;color:#fff;margin-bottom:1.2rem;}
</style>
<?php
function include_styles() {
    echo "
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
    ";
}
?>
</head>
<body>
<?php include 'admin_sidebar.php'; ?>
<div class="main">
    <div class="page-title">Periodos de preinscripción</div>
    <div class="page-sub">Gestiona las fechas de apertura y cierre del proceso</div>

    <?php if ($msg): ?><div class="msg"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="err"><?= $err ?></div><?php endif; ?>

    <div class="grid2">
        <!-- CREAR NUEVO -->
        <div>
            <div class="box">
                <h3>Crear nuevo periodo</h3>
                <form method="POST">
                    <label>Semestre (ej. 2026-2)</label>
                    <input type="text" name="semestre" placeholder="2026-2" required>
                    <label>Fecha de inicio</label>
                    <input type="date" name="fecha_inicio" required>
                    <label>Fecha de cierre</label>
                    <input type="date" name="fecha_fin" required>
                    <button type="submit" name="crear" class="btn-p">Crear y activar</button>
                </form>
            </div>

            <?php if ($activo): ?>
            <div class="activo-box">
                <div style="font-size:11px;font-family:'IBM Plex Mono',monospace;color:var(--accent);letter-spacing:.08em;margin-bottom:6px;">PERIODO ACTIVO</div>
                <div class="sem"><?= htmlspecialchars($activo['semestre']) ?></div>
                <div class="fechas"><?= date('d/m/Y',strtotime($activo['fecha_inicio'])) ?> — <?= date('d/m/Y',strtotime($activo['fecha_fin'])) ?></div>
                <a href="?cerrar=<?= $activo['id'] ?>" onclick="return confirm('¿Cerrar este periodo manualmente?')" class="btn-danger" style="margin-top:10px;font-size:12px;">Cerrar periodo</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- HISTORIAL -->
        <div class="box">
            <h3>Historial de periodos</h3>
            <?php foreach ($periodos as $p): ?>
            <div class="periodo-card <?= $p['activo']?'activo':'' ?>">
                <div class="pc-top">
                    <span class="pc-sem"><?= htmlspecialchars($p['semestre']) ?></span>
                    <span class="pc-badge <?= $p['activo']?'badge-act':'badge-inact' ?>"><?= $p['activo']?'ACTIVO':'CERRADO' ?></span>
                </div>
                <div class="pc-fechas"><?= date('d/m/Y',strtotime($p['fecha_inicio'])) ?> al <?= date('d/m/Y',strtotime($p['fecha_fin'])) ?></div>
                <div class="pc-acc">
                    <button onclick="openEdit(<?= $p['id'] ?>,'<?= htmlspecialchars($p['semestre']) ?>','<?= $p['fecha_inicio'] ?>','<?= $p['fecha_fin'] ?>')" class="btn-s">Editar fechas</button>
                    <?php if (!$p['activo']): ?>
                    <a href="?activar=<?= $p['id'] ?>" onclick="return confirm('¿Activar este periodo?')" class="btn-s">Activar</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($periodos)): ?><p style="font-size:13px;color:var(--muted)">No hay periodos registrados.</p><?php endif; ?>
        </div>
    </div>
</div>

<!-- MODAL EDITAR -->
<div class="modal" id="modalEdit">
<div class="modal-box">
    <h4>Editar periodo</h4>
    <form method="POST">
        <input type="hidden" name="edit_id" id="eId">
        <label>Semestre</label>
        <input type="text" name="edit_sem" id="eSem" required>
        <label>Fecha inicio</label>
        <input type="date" name="edit_ini" id="eIni" required>
        <label>Fecha fin</label>
        <input type="date" name="edit_fin" id="eFin" required>
        <div style="display:flex;gap:8px;margin-top:4px;">
            <button type="submit" name="editar" class="btn-p">Guardar</button>
            <button type="button" onclick="document.getElementById('modalEdit').classList.remove('open')" class="btn-s">Cancelar</button>
        </div>
    </form>
</div>
</div>
<script>
function openEdit(id,sem,ini,fin){
    document.getElementById('eId').value=id;
    document.getElementById('eSem').value=sem;
    document.getElementById('eIni').value=ini;
    document.getElementById('eFin').value=fin;
    document.getElementById('modalEdit').classList.add('open');
}
</script>
</body>
</html>