<?php
// ============================================================
// login.php — Inicio de sesión unificado
// Detecta automáticamente si es alumno o administrador
// ============================================================
session_start();

if (isset($_SESSION['alumno_id']))  { header("Location: panel.php");       exit(); }
if (isset($_SESSION['admin_id']))   { header("Location: admin_panel.php"); exit(); }

require 'db.php';

// Crear tabla admin si no existe
$conn->query("CREATE TABLE IF NOT EXISTS admin_usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(100) NOT NULL UNIQUE,
    contrasena VARCHAR(255) NOT NULL
)");
$check = $conn->query("SELECT COUNT(*) as t FROM admin_usuarios")->fetch_assoc();
if ($check['t'] == 0) {
    $conn->query("INSERT INTO admin_usuarios (usuario, contrasena) VALUES ('admin', 'Admin1234')");
}

$error = "";

function validarContrasena($pass) {
    if (strlen($pass) < 8)             return "La contraseña debe tener al menos 8 caracteres.";
    if (!preg_match('/[A-Z]/', $pass)) return "La contraseña debe contener al menos una letra mayúscula.";
    if (!preg_match('/[0-9]/', $pass)) return "La contraseña debe contener al menos un número.";
    return "";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo     = trim($_POST['correo'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');

    $errorPass = validarContrasena($contrasena);
    if ($errorPass) {
        $error = $errorPass;
    } else {
        // Buscar en administradores primero
        $stmt = $conn->prepare("SELECT * FROM admin_usuarios WHERE usuario = ?");
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();

        if ($admin && $contrasena === $admin['contrasena']) {
            $_SESSION['admin_id']      = $admin['id'];
            $_SESSION['admin_usuario'] = $admin['usuario'];
            header("Location: admin_panel.php"); exit();
        }

        // Buscar en alumnos
        $stmt = $conn->prepare("SELECT id, nombre, contrasena, inscripcion_activa FROM alumnos WHERE correo = ?");
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $alumno = $stmt->get_result()->fetch_assoc();

        if ($alumno && $contrasena === $alumno['contrasena']) {
            if (!$alumno['inscripcion_activa']) {
                $error = "Tu inscripción no está activa. Acude a Control Escolar.";
            } else {
                $_SESSION['alumno_id']     = $alumno['id'];
                $_SESSION['alumno_nombre'] = $alumno['nombre'];
                header("Location: panel.php"); exit();
            }
        } else {
            $error = "Correo o contraseña incorrectos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar sesión — UABC FCQI</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
    --white: #ffffff;
    --off-white: #f8f7f4;
    --surface: #f1efe9;
    --border: #ddd9d0;
    --border-dark: #c5bfb3;
    --navy: #1A3A5C;
    --navy-light: #2E6DA4;
    --accent: #c47d0e;
    --accent-light: #e8a020;
    --text: #1c1917;
    --text-mid: #44403c;
    --muted: #78716c;
    --danger: #b91c1c;
    --danger-bg: #fef2f2;
    --danger-border: #fecaca;
}
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'DM Sans', sans-serif;
    background: var(--off-white);
    min-height: 100vh;
    display: flex;
    align-items: stretch;
}

/* LEFT PANEL */
.left {
    width: 420px;
    flex-shrink: 0;
    background: var(--navy);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 3rem 3rem 2.5rem;
    position: relative;
    overflow: hidden;
}
.left::before {
    content: '';
    position: absolute;
    top: -80px; right: -80px;
    width: 320px; height: 320px;
    border-radius: 50%;
    border: 1px solid rgba(255,255,255,.07);
}
.left::after {
    content: '';
    position: absolute;
    bottom: 60px; left: -60px;
    width: 220px; height: 220px;
    border-radius: 50%;
    border: 1px solid rgba(255,255,255,.05);
}
.left-top { position: relative; z-index: 1; }
.escudo {
    width: 52px; height: 52px;
    background: rgba(255,255,255,.1);
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Fraunces', serif;
    font-size: 22px; font-weight: 700;
    color: white;
    margin-bottom: 2rem;
}
.left h1 {
    font-family: 'Fraunces', serif;
    font-size: 32px;
    font-weight: 600;
    color: white;
    line-height: 1.2;
    margin-bottom: 1rem;
}
.left h1 em {
    font-style: italic;
    color: var(--accent-light);
}
.left p {
    font-size: 14px;
    color: rgba(255,255,255,.6);
    line-height: 1.7;
    max-width: 280px;
}
.left-bottom { position: relative; z-index: 1; }
.left-bottom .inst {
    font-size: 12px;
    color: rgba(255,255,255,.35);
    letter-spacing: .04em;
}
.left-bottom .inst strong {
    display: block;
    color: rgba(255,255,255,.6);
    margin-bottom: 2px;
    font-weight: 500;
}
.divider-line {
    width: 40px; height: 2px;
    background: var(--accent-light);
    border-radius: 2px;
    margin: 1.5rem 0;
}

/* RIGHT PANEL */
.right {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    background: var(--off-white);
}
.form-card {
    width: 100%;
    max-width: 400px;
}
.form-card .eyebrow {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}
.form-card .eyebrow::before {
    content: '';
    display: inline-block;
    width: 20px; height: 2px;
    background: var(--accent);
    border-radius: 2px;
}
.form-card h2 {
    font-family: 'Fraunces', serif;
    font-size: 28px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 6px;
}
.form-card .sub {
    font-size: 14px;
    color: var(--muted);
    margin-bottom: 2.2rem;
}

.field { margin-bottom: 1.2rem; }
.field label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-mid);
    margin-bottom: 7px;
    letter-spacing: .02em;
}
.field input {
    width: 100%;
    background: var(--white);
    border: 1.5px solid var(--border);
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 14px;
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    outline: none;
    transition: border-color .2s, box-shadow .2s;
}
.field input:focus {
    border-color: var(--navy-light);
    box-shadow: 0 0 0 3px rgba(46,109,164,.12);
}
.field input::placeholder { color: #b8b2ab; }

.pass-wrap { position: relative; }
.pass-wrap input { padding-right: 48px; }
.toggle-pass {
    position: absolute; right: 14px; top: 50%;
    transform: translateY(-50%);
    background: none; border: none;
    cursor: pointer; color: var(--muted);
    font-size: 16px; line-height: 1;
    padding: 4px; transition: color .2s;
}
.toggle-pass:hover { color: var(--text); }

.req-box {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 10px 14px;
    margin-top: 8px;
    display: none;
}
.req {
    font-size: 12px;
    color: var(--muted);
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 2px 0;
    transition: color .2s;
}
.req::before {
    content: '○';
    font-size: 10px;
    flex-shrink: 0;
}
.req.ok { color: #15803d; }
.req.ok::before { content: '●'; color: #15803d; }

.error-box {
    background: var(--danger-bg);
    border: 1.5px solid var(--danger-border);
    border-radius: 10px;
    padding: 11px 15px;
    font-size: 13px;
    color: var(--danger);
    margin-bottom: 1.2rem;
    display: flex;
    align-items: flex-start;
    gap: 8px;
}
.error-box::before { content: '!'; font-weight: 700; flex-shrink: 0; }

.btn-submit {
    width: 100%;
    background: var(--navy);
    color: white;
    border: none;
    border-radius: 10px;
    padding: 13px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    font-family: 'DM Sans', sans-serif;
    transition: background .2s, transform .1s;
    margin-top: .4rem;
    letter-spacing: .01em;
}
.btn-submit:hover { background: var(--navy-light); }
.btn-submit:active { transform: scale(.99); }

.footer-note {
    font-size: 12px;
    color: var(--muted);
    text-align: center;
    margin-top: 1.5rem;
}

/* Responsive */
@media (max-width: 700px) {
    .left { display: none; }
    body { justify-content: center; }
}
</style>
</head>
<body>

<!-- PANEL IZQUIERDO -->
<div class="left">
    <div class="left-top">
        <div class="escudo">U</div>
        <h1>Sistema de<br><em>Preinscripción</em><br>UABC</h1>
        <div class="divider-line"></div>
        <p>Gestión académica en línea para alumnos y personal de control escolar de la FCQI.</p>
    </div>
    <div class="left-bottom">
        <div class="inst">
            <strong>Facultad de Ciencias Químicas e Ingeniería</strong>
            Universidad Autónoma de Baja California
        </div>
    </div>
</div>

<!-- PANEL DERECHO -->
<div class="right">
    <div class="form-card">
        <div class="eyebrow">Acceso al sistema</div>
        <h2>Iniciar sesión</h2>
        <p class="sub">Ingresa con tu correo institucional o usuario de administrador.</p>

        <?php if ($error): ?>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <div class="field">
                <label>Correo institucional o usuario</label>
                <input type="text" name="correo" id="correo"
                       placeholder="tu.nombre@uabc.edu.mx"
                       value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>"
                       required autocomplete="off">
            </div>

            <div class="field">
                <label>Contraseña</label>
                <div class="pass-wrap">
                    <input type="password" name="contrasena" id="passInput"
                           placeholder="••••••••" required
                           oninput="checkPass(this.value)">
                    <button type="button" class="toggle-pass" onclick="togglePass()" title="Mostrar contraseña">👁</button>
                </div>
                <div class="req-box" id="reqBox">
                    <div class="req" id="req-len">Mínimo 8 caracteres</div>
                    <div class="req" id="req-may">Al menos una mayúscula</div>
                    <div class="req" id="req-num">Al menos un número</div>
                </div>
            </div>

            <button type="submit" class="btn-submit">Ingresar →</button>
        </form>

        <p class="footer-note">Acceso restringido · Solo personal autorizado y alumnos activos</p>
    </div>
</div>

<script>
function checkPass(val) {
    const box = document.getElementById('reqBox');
    box.style.display = val.length > 0 ? 'block' : 'none';
    document.getElementById('req-len').className = 'req ' + (val.length >= 8 ? 'ok' : '');
    document.getElementById('req-may').className = 'req ' + (/[A-Z]/.test(val) ? 'ok' : '');
    document.getElementById('req-num').className = 'req ' + (/[0-9]/.test(val) ? 'ok' : '');
}
function togglePass() {
    const i = document.getElementById('passInput');
    i.type = i.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>