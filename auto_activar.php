<?php
require 'db.php';
 
// Buscar periodos cuya fecha de cierre ya paso
$stmt = $conn->prepare("
    SELECT * FROM periodo_preinscripcion
    WHERE activo = 1 AND fecha_fin < CURDATE()
");
$stmt->execute();
$periodos_vencidos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
 
$activados = 0;
$notificados = 0;
 
foreach ($periodos_vencidos as $periodo) {
    // Buscar alumnos con preinscripcion en proceso (no activada)
    $stmt = $conn->prepare("
        SELECT p.*, a.nombre, a.correo, a.matricula
        FROM preinscripciones p
        JOIN alumnos a ON p.alumno_id = a.id
        WHERE p.semestre = ? AND p.estado = 'en_proceso'
    ");
    $stmt->bind_param("s", $periodo['semestre']);
    $stmt->execute();
    $pendientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
 
    foreach ($pendientes as $p) {
        // Verificar que tenga materias seleccionadas
        $stmt2 = $conn->prepare("SELECT COUNT(*) as total FROM preinscripcion_materias WHERE preinscripcion_id = ?");
        $stmt2->bind_param("i", $p['id']);
        $stmt2->execute();
        $cnt = $stmt2->get_result()->fetch_assoc()['total'];
 
        if ($cnt > 0) {
            // Activar automaticamente
            $stmt3 = $conn->prepare("UPDATE preinscripciones SET estado='auto_activada', fecha_activacion=NOW() WHERE id=?");
            $stmt3->bind_param("i", $p['id']);
            $stmt3->execute();
            $activados++;
 
            // Obtener materias de la preinscripcion
            $stmt4 = $conn->prepare("SELECT pm.*, m.nombre, m.clave, m.creditos FROM preinscripcion_materias pm JOIN materias m ON pm.materia_id = m.id WHERE pm.preinscripcion_id = ?");
            $stmt4->bind_param("i", $p['id']);
            $stmt4->execute();
            $materias = $stmt4->get_result()->fetch_all(MYSQLI_ASSOC);
            $total_cred = array_sum(array_column($materias, 'creditos'));
 
            // Enviar correo de notificacion
            $asunto = "Tu tira de materias fue activada automaticamente — Semestre {$periodo['semestre']}";
            $cuerpo = "Hola {$p['nombre']},\n\n";
            $cuerpo .= "El periodo de preinscripcion ha cerrado y tu tira de materias fue activada automaticamente con las materias que tenias seleccionadas.\n\n";
            $cuerpo .= "Semestre: {$periodo['semestre']}\n";
            $cuerpo .= "Matricula: {$p['matricula']}\n\n";
            $cuerpo .= "Materias inscritas:\n";
            foreach ($materias as $m) {
                $cuerpo .= "- {$m['nombre']} ({$m['clave']}) — {$m['creditos']} creditos\n";
            }
            $cuerpo .= "\nTotal de creditos: $total_cred\n\n";
            $cuerpo .= "Si tienes dudas, comunicate con tu tutor academico.\n\n";
            $cuerpo .= "Atentamente,\nSistema de Preinscripcion UABC — FCQI";
 
            // mail($p['correo'], $asunto, $cuerpo); // Descomentar en produccion
            echo "Activado: {$p['nombre']} ({$p['correo']}) — $cnt materias, $total_cred creditos\n";
            $notificados++;
        }
    }
 
    // Desactivar el periodo
    $stmt_up = $conn->prepare("UPDATE periodo_preinscripcion SET activo = 0 WHERE id = ?");
    $stmt_up->bind_param("i", $periodo['id']);
    $stmt_up->execute();
}
 
echo "\n=== Proceso completado ===\n";
echo "Tiras activadas automaticamente: $activados\n";
echo "Correos enviados: $notificados\n";
?>