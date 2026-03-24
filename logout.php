<?php
// logout.php — Cierra la sesion del alumno
session_start();
session_destroy();
header("Location: login.php");
exit();
?>
 