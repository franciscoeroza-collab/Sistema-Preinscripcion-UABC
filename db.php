<?php
// ============================================================
// db.php — Conexion a la base de datos MySQL
// Coloca este archivo en: C:/xampp/htdocs/preinscripcion/
// ============================================================
 
$host     = "localhost";
$usuario  = "root";       // usuario por defecto de XAMPP
$password = "";           // contrasena vacia por defecto en XAMPP
$base     = "preinscripcion_uabc";
 
$conn = new mysqli($host, $usuario, $password, $base);
 
if ($conn->connect_error) {
    die("Error de conexion: " . $conn->connect_error);
}
 
$conn->set_charset("utf8");
?>
