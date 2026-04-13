<?php
session_start();
if (isset($_SESSION['admin_id'])) { header("Location: admin_panel.php"); exit(); }
 
require 'db.php';
 
// Crear tabla admin si no existe
$conn->query("
    CREATE TABLE IF NOT EXISTS admin_usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario VARCHAR(100) NOT NULL UNIQUE,
        contrasena VARCHAR(255) NOT NULL
    )
");
// Insertar admin por defecto si no existe ninguno
$check = $conn->query("SELECT COUNT(*) as total FROM admin_usuarios")->fetch_assoc();
if ($check['total'] == 0) {
    $conn->query("INSERT INTO admin_usuarios (usuario, contrasena) VALUES ('admin', 'admin1234')");
}
 
$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario    = trim($_POST['usuario'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');
    $stmt = $conn->prepare("SELECT * FROM admin_usuarios WHERE usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    if ($admin && $contrasena === $admin['contrasena']) {
        $_SESSION['admin_id']      = $admin['id'];
        $_SESSION['admin_usuario'] = $admin['usuario'];
        header("Location: admin_panel.php"); exit();
    } else {
        $error = "Usuario o contraseña incorrectos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin — UABC FCQI</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
    --navy: #0f1e2e;
    --blue: #1A3A5C;
    --accent: #e8a020;
    --accent2: #2E6DA4;
    --light: #f5f7fa;
    --border: #2a3f55;
    --text: #c8d8e8;
    --muted: #6a8aaa;
}
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: 'IBM Plex Sans', sans-serif;
    background: var(--navy);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}
body::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        repeating-linear-gradient(0deg, transparent, transparent 40px, rgba(46,109,164,.04) 40px, rgba(46,109,164,.04) 41px),
        repeating-linear-gradient(90deg, transparent, transparent 40px, rgba(46,109,164,.04) 40px, rgba(46,109,164,.04) 41px);
}
.card {
    background: rgba(26,58,92,.25);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 2.5rem;
    width: 380px;
    position: relative;
    backdrop-filter: blur(12px);
    box-shadow: 0 24px 60px rgba(0,0,0,.4);
}
.card::before {
    content: '';
    position: absolute;
    top: 0; left: 2rem; right: 2rem;
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--accent), transparent);
    border-radius: 2px;
}
.badge {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 10px;
    color: var(--accent);
    letter-spacing: .15em;
    text-transform: uppercase;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 8px;
}
.badge::before {
    content: '';
    display: inline-block;
    width: 6px; height: 6px;
    background: var(--accent);
    border-radius: 50%;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%,100% { opacity:1; } 50% { opacity:.3; }
}
h1 {
    font-size: 22px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 4px;
}
.sub {
    font-size: 13px;
    color: var(--muted);
    margin-bottom: 2rem;
}
label {
    font-size: 11px;
    font-family: 'IBM Plex Mono', monospace;
    color: var(--muted);
    letter-spacing: .08em;
    text-transform: uppercase;
    display: block;
    margin-bottom: 6px;
}
input {
    width: 100%;
    background: rgba(255,255,255,.05);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 11px 14px;
    font-size: 14px;
    color: var(--text);
    font-family: 'IBM Plex Sans', sans-serif;
    margin-bottom: 1.2rem;
    outline: none;
    transition: border-color .2s, background .2s;
}
input:focus {
    border-color: var(--accent2);
    background: rgba(46,109,164,.12);
}
.btn {
    width: 100%;
    background: var(--blue);
    color: white;
    border: 1px solid var(--accent2);
    border-radius: 8px;
    padding: 12px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    font-family: 'IBM Plex Sans', sans-serif;
    transition: all .2s;
    letter-spacing: .02em;
}
.btn:hover { background: var(--accent2); }
.error {
    background: rgba(220,38,38,.12);
    border: 1px solid rgba(220,38,38,.3);
    color: #fca5a5;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 13px;
    margin-bottom: 1.2rem;
}
.nota {
    font-size: 11px;
    color: var(--muted);
    text-align: center;
    margin-top: 1.2rem;
    font-family: 'IBM Plex Mono', monospace;
}
</style>
</head>
<body>
<div class="card">
    <div class="badge">Sistema de Administración</div>
    <h1>Panel de Control</h1>
    <p class="sub">UABC — Facultad de Ciencias Químicas e Ingeniería</p>
    <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
        <label>Usuario</label>
        <input type="text" name="usuario" placeholder="admin" required autocomplete="off">
        <label>Contraseña</label>
        <input type="password" name="contrasena" placeholder="••••••••" required>
        <button type="submit" class="btn">Ingresar al panel</button>
    </form>
    <p class="nota">Acceso restringido — Solo personal autorizado</p>
</div>
</body>
</html>
 