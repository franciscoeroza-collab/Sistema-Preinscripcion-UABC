<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: admin_login.php"); exit(); }
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
    } elseif (!empty($nueva_pass) && strlen($nueva_pass) < 6) {
        $err = "La nueva contraseña debe tener al menos 6 caracteres.";
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
.page-sub{font-size:13px;color:var(--muted);margin-bottom:2rem;}
.card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:1.8rem;max-width:440px;}
.card h3{font-size:14px;font-weight:600;color:#fff;margin-bottom:1.2rem;padding-bottom:.8rem;border-bottom:1px solid var(--border);}
label{font-size:11px;font-family:'IBM Plex Mono',monospace;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;display:block;margin-bottom:5px;}
input{width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;padding:10px 12px;font-size:13px;color:var(--text);font-family:'IBM Plex Sans',sans-serif;margin-bottom:1rem;outline:none;transition:border-color .2s;}
input:focus{border-color:var(--accent2);}
.btn-p{width:100%;background:var(--blue);color:white;border:1px solid var(--accent2);border-radius:8px;padding:11px;font-size:14px;font-weight:600;cursor:pointer;font-family:'IBM Plex Sans',sans-serif;transition:all .2s;margin-top:.4rem;}
.btn-p:hover{background:var(--accent2);}
.msg{background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.25);color:#4ade80;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:1rem;}
.err{background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.25);color:#f87171;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:1rem;}
.divider{height:1px;background:var(--border);margin:1.2rem 0;}
.nota{font-size:12px;color:var(--muted);margin-top:.6rem;}
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
            <input type="text" value="<?= htmlspecialchars($admin['usuario']) ?>" disabled style="opacity:.5;cursor:not-allowed;">

            <div class="divider"></div>

            <label>Nuevo nombre de usuario</label>
            <input type="text" name="usuario" value="<?= htmlspecialchars($admin['usuario']) ?>" required>

            <label>Contraseña actual <span style="color:#f87171">*</span></label>
            <input type="password" name="pass_actual" placeholder="Requerida para guardar cambios" required>

            <div class="divider"></div>

            <label>Nueva contraseña <span style="color:var(--muted)">(opcional)</span></label>
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