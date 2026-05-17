<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
require 'db.php';

$msg = $err = "";
$admin_id = $_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $nuevo_usuario = trim($_POST['usuario'] ?? '');
    $pass_actual   = trim($_POST['pass_actual'] ?? '');
    $nueva_pass    = trim($_POST['nueva_pass'] ?? '');
    $confirmar     = trim($_POST['confirmar'] ?? '');

    $stmt = $conn->prepare("SELECT * FROM admin_usuarios WHERE id=?");
    $stmt->bind_param("i",$admin_id);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    if ($pass_actual !== $admin['contrasena']) {
        $err = "La contraseña actual es incorrecta.";
    } elseif (empty($nuevo_usuario)) {
        $err = "El nombre de usuario no puede estar vacío.";
    } elseif (!empty($nueva_pass) && $nueva_pass !== $confirmar) {
        $err = "Las contraseñas nuevas no coinciden.";
    } elseif (!empty($nueva_pass) && (strlen($nueva_pass) < 8 || !preg_match('/[A-Z]/', $nueva_pass) || !preg_match('/[0-9]/', $nueva_pass))) {
        $err = "La nueva contraseña debe tener al menos 8 caracteres, incluir una mayúscula y un número.";
    } else {
        $nueva_c = !empty($nueva_pass) ? $nueva_pass : $admin['contrasena'];
        $stmt2 = $conn->prepare("UPDATE admin_usuarios SET usuario=?,contrasena=? WHERE id=?");
        $stmt2->bind_param("ssi",$nuevo_usuario,$nueva_c,$admin_id);
        $stmt2->execute();
        $_SESSION['admin_usuario'] = $nuevo_usuario;
        $msg = "Cuenta actualizada correctamente.";
    }
}

$stmt = $conn->prepare("SELECT * FROM admin_usuarios WHERE id=?");
$stmt->bind_param("i",$admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Mi cuenta — Admin UABC</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
    --white:#ffffff; --off-white:#f8f7f4; --surface:#f1efe9;
    --border:#ddd9d0; --navy:#1A3A5C; --navy-light:#2E6DA4; --navy-bg:#e8f0f8;
    --accent:#c47d0e; --text:#1c1917; --text-mid:#44403c; --muted:#78716c;
    --success:#15803d; --success-bg:#f0fdf4; --success-border:#bbf7d0;
    --danger:#b91c1c; --danger-bg:#fef2f2; --danger-border:#fecaca;
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
.page-sub{font-size:13px;color:var(--muted);margin-bottom:2rem;}

.msg{background:var(--success-bg);border:1.5px solid var(--success-border);color:var(--success);border-radius:9px;padding:10px 14px;font-size:13px;margin-bottom:1.2rem;}
.err{background:var(--danger-bg);border:1.5px solid var(--danger-border);color:var(--danger);border-radius:9px;padding:10px 14px;font-size:13px;margin-bottom:1.2rem;}

.card{background:var(--white);border:1.5px solid var(--border);border-radius:12px;padding:1.8rem;max-width:440px;}
.card h3{font-size:14px;font-weight:600;color:var(--navy);margin-bottom:1.2rem;padding-bottom:.8rem;border-bottom:1.5px solid var(--border);}

label{font-size:11px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);display:block;margin-bottom:5px;}
input{width:100%;background:var(--white);border:1.5px solid var(--border);border-radius:9px;padding:10px 13px;font-size:13px;color:var(--text);font-family:'DM Sans',sans-serif;margin-bottom:1rem;outline:none;transition:border-color .2s,box-shadow .2s;}
input:focus{border-color:var(--navy-light);box-shadow:0 0 0 3px rgba(46,109,164,.1);}
input:disabled{background:var(--surface);cursor:not-allowed;opacity:.7;}

.divider{height:1.5px;background:var(--border);margin:1.2rem 0;}
.btn-p{width:100%;background:var(--navy);color:white;border:none;border-radius:9px;padding:11px;font-size:14px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:background .2s;margin-top:.4rem;}
.btn-p:hover{background:var(--navy-light);}
.nota{font-size:12px;color:var(--muted);margin-top:.7rem;}
</style>
</head>
<body>
<?php include 'admin_sidebar.php'; ?>
<div class="main">
    <div class="page-title">Mi cuenta</div>
    <div class="page-sub">Actualiza tu usuario y contraseña de acceso al panel</div>

    <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="card">
        <h3>Datos de acceso</h3>
        <form method="POST">
            <label>Usuario actual</label>
            <input type="text" value="<?= htmlspecialchars($admin['usuario']) ?>" disabled>

            <div class="divider"></div>

            <label>Nuevo nombre de usuario</label>
            <input type="text" name="usuario" value="<?= htmlspecialchars($admin['usuario']) ?>" required>

            <label>Contraseña actual <span style="color:var(--danger)">*</span></label>
            <input type="password" name="pass_actual" placeholder="Requerida para guardar cambios" required>

            <div class="divider"></div>

            <label>Nueva contraseña <span style="color:var(--muted);font-weight:400;font-size:10px;">(Mín. 8 caracteres, una mayúscula y un número)</span></label>
            <input type="password" name="nueva_pass" placeholder="Dejar vacío para no cambiar">

            <label>Confirmar nueva contraseña</label>
            <input type="password" name="confirmar" placeholder="Repite la nueva contraseña">

            <button type="submit" class="btn-p">Guardar cambios</button>
            <p class="nota">* Si no deseas cambiar la contraseña, deja los campos de contraseña vacíos.</p>
        </form>
    </div>
</div>
</body>
</html>