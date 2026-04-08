<?php
// controladores/admin_procesar.php
session_start();
require_once '../inc/bd.php';

// Doble validación de seguridad
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../exito.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = $_POST['id'] ?? 0;

    try {
        if ($accion === 'borrar_usuario' && $id != $_SESSION['user_id']) {
            // El CASCADE de la BD se encargará de borrar sus votos
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
            $stmt->execute([$id]);
        } 
        elseif ($accion === 'borrar_casa') {
            $stmt = $pdo->prepare("DELETE FROM casas WHERE id_casa = ?");
            $stmt->execute([$id]);
        }
        elseif ($accion === 'borrar_actividad') {
            $stmt = $pdo->prepare("DELETE FROM actividades WHERE id_actividad = ?");
            $stmt->execute([$id]);
        }

        // Volvemos al panel con mensaje de éxito
        header("Location: ../admin.php?msg=borrado_ok");
        exit();

    } catch (PDOException $e) {
        die("Error en la base de datos: " . $e->getMessage());
    }
} else {
    header("Location: ../admin.php");
    exit();
}
?>
