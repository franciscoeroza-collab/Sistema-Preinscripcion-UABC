<?php
// db.php — Conexion a MySQL
// Coloca todos los archivos en: C:/xampp/htdocs/preinscripcion/
$host     = "localhost";
$usuario  = "root";
$password = "";
$base     = "preinscripcion_uabc";
$conn = new mysqli($host, $usuario, $password, $base);
if ($conn->connect_error) { die("Error de conexion: " . $conn->connect_error); }
$conn->set_charset("utf8");
?>
 