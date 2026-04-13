<?php
$current = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sb-logo">
        <div class="mark">UABC · FCQI</div>
        <h2>Panel de Administración</h2>
        <p>Control Escolar</p>
    </div>
    <nav class="sb-nav">
        <div class="sb-section">Principal</div>
        <a href="admin_panel.php" class="sb-link <?= $current=='admin_panel.php'?'active':'' ?>">
            <span class="ico">◈</span> Dashboard
        </a>
        <div class="sb-section">Gestión</div>
        <a href="admin_periodos.php" class="sb-link <?= $current=='admin_periodos.php'?'active':'' ?>">
            <span class="ico">◷</span> Periodos
        </a>
        <a href="admin_alumnos.php" class="sb-link <?= $current=='admin_alumnos.php'?'active':'' ?>">
            <span class="ico">◉</span> Alumnos
        </a>
        <a href="admin_preinscripciones.php" class="sb-link <?= $current=='admin_preinscripciones.php'?'active':'' ?>">
            <span class="ico">◎</span> Preinscripciones
        </a>
        <div class="sb-section">Configuración</div>
        <a href="admin_cuenta.php" class="sb-link <?= $current=='admin_cuenta.php'?'active':'' ?>">
            <span class="ico">◐</span> Mi cuenta
        </a>
    </nav>
    <div class="sb-footer">
        <div class="sb-user">
            Sesión activa<strong><?= htmlspecialchars($_SESSION['admin_usuario'] ?? '') ?></strong>
        </div>
        <a href="admin_logout.php" class="sb-out">← Cerrar sesión</a>
    </div>
</div>