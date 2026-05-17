<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
require 'db.php';

$msg = $err = "";

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['crear'])) {
    $sem = trim($_POST['semestre']);
    $ini = $_POST['fecha_inicio'];
    $fin = $_POST['fecha_fin'];
    if ($sem && $ini && $fin) {
        $conn->query("UPDATE periodo_preinscripcion SET activo=0");
        $stmt = $conn->prepare("INSERT INTO periodo_preinscripcion (semestre,fecha_inicio,fecha_fin,activo) VALUES (?,?,?,1)");
        $stmt->bind_param("sss",$sem,$ini,$fin);
        $stmt->execute();
        $msg = "Periodo <strong>$sem</strong> creado y activado correctamente.";
    } else { $err = "Todos los campos son requeridos."; }
}

if (isset($_GET['activar'])) {
    $id = (int)$_GET['activar'];
    $conn->query("UPDATE periodo_preinscripcion SET activo=0");
    $conn->query("UPDATE periodo_preinscripcion SET activo=1 WHERE id=$id");
    $msg = "Periodo activado.";
}

if (isset($_GET['cerrar'])) {
    $id = (int)$_GET['cerrar'];
    $conn->query("UPDATE periodo_preinscripcion SET activo=0 WHERE id=$id");
    $msg = "Periodo cerrado manualmente.";
}

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
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
    --white:#ffffff; --off-white:#f8f7f4; --surface:#f1efe9;
    --border:#ddd9d0; --border-dark:#c5bfb3;
    --navy:#1A3A5C; --navy-light:#2E6DA4; --navy-bg:#e8f0f8;
    --accent:#c47d0e; --accent-light:#e8a020; --accent-bg:#fef3e2;
    --text:#1c1917; --text-mid:#44403c; --muted:#78716c;
    --success:#15803d; --success-bg:#f0fdf4; --success-border:#bbf7d0;
    --warning:#92400e; --warning-bg:#fffbeb; --warning-border:#fde68a;
    --danger:#b91c1c; --danger-bg:#fef2f2; --danger-border:#fecaca;
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
.page-sub{font-size:13px;color:var(--muted);margin-bottom:2rem;}

.msg{background:var(--success-bg);border:1.5px solid var(--success-border);color:var(--success);border-radius:9px;padding:10px 14px;font-size:13px;margin-bottom:1.2rem;}
.err{background:var(--danger-bg);border:1.5px solid var(--danger-border);color:var(--danger);border-radius:9px;padding:10px 14px;font-size:13px;margin-bottom:1.2rem;}

.grid2{display:grid;grid-template-columns:1fr 1.6fr;gap:16px;}
.box{background:var(--white);border:1.5px solid var(--border);border-radius:12px;padding:1.4rem;margin-bottom:16px;}
.box h3{font-size:14px;font-weight:600;color:var(--navy);margin-bottom:1.1rem;}

label{font-size:11px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);display:block;margin-bottom:5px;}
input[type="text"],input[type="date"]{
    width:100%;background:var(--white);border:1.5px solid var(--border);
    border-radius:9px;padding:10px 13px;font-size:13px;color:var(--text);
    font-family:'DM Sans',sans-serif;margin-bottom:1rem;outline:none;
    transition:border-color .2s,box-shadow .2s;
}
input:focus{border-color:var(--navy-light);box-shadow:0 0 0 3px rgba(46,109,164,.1);}

.btn-p{background:var(--navy);color:white;border:none;border-radius:9px;padding:10px 20px;font-size:13px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:background .2s;}
.btn-p:hover{background:var(--navy-light);}
.btn-s{background:var(--white);color:var(--text-mid);border:1.5px solid var(--border);border-radius:8px;padding:8px 16px;font-size:12px;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .15s;text-decoration:none;display:inline-block;}
.btn-s:hover{border-color:var(--navy-light);color:var(--navy);}
.btn-danger{background:var(--white);color:var(--danger);border:1.5px solid var(--danger-border);border-radius:8px;padding:8px 16px;font-size:12px;cursor:pointer;font-family:'DM Sans',sans-serif;text-decoration:none;display:inline-block;transition:all .15s;}
.btn-danger:hover{background:var(--danger-bg);}

.activo-box{background:var(--accent-bg);border:1.5px solid #f0c060;border-radius:11px;padding:1.2rem;margin-bottom:16px;}
.activo-box .label-act{font-size:10px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--accent);margin-bottom:6px;}
.activo-box .sem{font-size:15px;font-weight:700;color:var(--accent);}
.activo-box .fechas{font-size:13px;color:var(--text-mid);margin-top:4px;}

.periodo-card{border:1.5px solid var(--border);border-radius:10px;padding:1rem 1.2rem;margin-bottom:10px;background:var(--white);}
.periodo-card.activo{border-color:#f0c060;background:var(--accent-bg);}
.pc-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;}
.pc-sem{font-size:14px;font-weight:700;color:var(--navy);}
.pc-badge{font-size:10px;font-weight:600;padding:3px 9px;border-radius:4px;letter-spacing:.04em;}
.badge-act{background:var(--success-bg);color:var(--success);border:1px solid var(--success-border);}
.badge-inact{background:var(--surface);color:var(--muted);border:1px solid var(--border);}
.pc-fechas{font-size:12px;color:var(--muted);margin-bottom:10px;}
.pc-acc{display:flex;gap:8px;flex-wrap:wrap;}

/* MODAL */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:100;align-items:center;justify-content:center;}
.modal.open{display:flex;}
.modal-box{background:var(--white);border:1.5px solid var(--border);border-radius:12px;padding:1.6rem;width:380px;box-shadow:0 20px 50px rgba(0,0,0,.12);}
.modal-box h4{font-family:'Fraunces',serif;font-size:17px;font-weight:600;color:var(--navy);margin-bottom:1.2rem;}
</style>
</head>
<body>
<?php include 'admin_sidebar.php'; ?>
<div class="main">
    <div class="page-title">Periodos de preinscripción</div>
    <div class="page-sub">Gestiona las fechas de apertura y cierre del proceso</div>

    <?php if ($msg): ?><div class="msg"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="err"><?= $err ?></div><?php endif; ?>

    <div class="grid2">
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
                <div class="label-act">Periodo activo</div>
                <div class="sem"><?= htmlspecialchars($activo['semestre']) ?></div>
                <div class="fechas"><?= date('d/m/Y',strtotime($activo['fecha_inicio'])) ?> — <?= date('d/m/Y',strtotime($activo['fecha_fin'])) ?></div>
                <a href="?cerrar=<?= $activo['id'] ?>" onclick="return confirm('¿Cerrar este periodo manualmente?')" class="btn-danger" style="margin-top:12px;font-size:12px;">Cerrar periodo</a>
            </div>
            <?php endif; ?>
        </div>

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