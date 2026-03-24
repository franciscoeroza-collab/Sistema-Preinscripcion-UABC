PHP
Copy

<?php
// ============================================================
// login.php — Pantalla de inicio de sesion
// ============================================================
session_start();
 
// Si ya hay sesion activa, redirige al panel
if (isset($_SESSION['alumno_id'])) {
    header("Location: panel.php");
    exit();
}
 
$error = "";
 
// Procesar el formulario cuando el alumno hace clic en Ingresar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'db.php';
 
    $correo     = trim($_POST['correo']);
    $contrasena = trim($_POST['contrasena']);
 
    // Buscar al alumno por correo
    $stmt = $conn->prepare("SELECT id, nombre, contrasena, inscripcion_activa FROM alumnos WHERE correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $resultado = $stmt->get_result();
 
    if ($resultado->num_rows === 1) {
        $alumno = $resultado->fetch_assoc();
 
        // Verificar contrasena
        if ($contrasena === $alumno['contrasena']) {
 
            // Verificar inscripcion activa
            if (!$alumno['inscripcion_activa']) {
                $error = "Tu inscripcion no esta activa. Acude a Control Escolar.";
            } else {
                // Guardar datos en sesion
                $_SESSION['alumno_id']     = $alumno['id'];
                $_SESSION['alumno_nombre'] = $alumno['nombre'];
 
                header("Location: panel.php");
                exit();
            }
        } else {
            $error = "Correo o contrasena incorrectos.";
        }
    } else {
        $error = "Correo o contrasena incorrectos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preinscripcion UABC — Iniciar sesion</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            background: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .card {
            background: white;
            border-radius: 12px;
            border: 1px solid #dde1e7;
            padding: 2.5rem;
            width: 360px;
        }
        .logo {
            width: 48px;
            height: 48px;
            background: #1A3A5C;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }
        .logo span { color: white; font-size: 22px; font-weight: bold; }
        h1 { font-size: 16px; font-weight: 600; text-align: center; color: #1a1a2e; margin-bottom: 4px; }
        .subtitulo { font-size: 12px; color: #6b7280; text-align: center; margin-bottom: 1.8rem; }
        label { font-size: 12px; color: #6b7280; display: block; margin-bottom: 4px; }
        input[type="email"], input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #dde1e7;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 1rem;
            font-family: Arial, sans-serif;
            outline: none;
            transition: border-color 0.2s;
        }
        input:focus { border-color: #2E6DA4; }
        .btn {
            width: 100%;
            background: #1A3A5C;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 11px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: Arial, sans-serif;
        }
        .btn:hover { background: #2E6DA4; }
        .error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 13px;
            margin-bottom: 1rem;
        }
        .nota { font-size: 11px; color: #9ca3af; text-align: center; margin-top: 1rem; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo"><span>U</span></div>
    <h1>Sistema de preinscripcion</h1>
    <p class="subtitulo">UABC — Facultad de Ciencias Quimicas e Ingenieria</p>
 
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
 
    <form method="POST">
        <label for="correo">Correo institucional</label>
        <input type="email" id="correo" name="correo" placeholder="tu.nombre@uabc.edu.mx" required>
 
        <label for="contrasena">Contrasena</label>
        <input type="password" id="contrasena" name="contrasena" placeholder="••••••••" required>
 
        <button type="submit" class="btn">Ingresar</button>
    </form>
    <p class="nota">Usa tus credenciales institucionales UABC</p>
</div>
</body>
</html>