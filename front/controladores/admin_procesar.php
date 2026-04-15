<?php
session_start();
require_once '../inc/bd.php';

// Doble validación de seguridad (Solo Admins)
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../exito.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = $_POST['id'] ?? 0;

    try {
        if ($accion === 'borrar_usuario' && $id != $_SESSION['user_id']) {
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
        // 🔥 EL NUEVO CÓDIGO DEL BOTÓN NUCLEAR 🔥
        elseif ($accion === 'reset_plan') {
            // Vaciamos todas las tablas del viaje. (Las tablas de votos se vacían solas por el CASCADE de tu BD)
            $pdo->exec("DELETE FROM casas");
            $pdo->exec("DELETE FROM actividades");
            $pdo->exec("DELETE FROM lista_compra");
            $pdo->exec("DELETE FROM transporte");
            $pdo->exec("DELETE FROM votos_fechas");
            $pdo->exec("DELETE FROM asistentes");
            
            // Volvemos al feed principal directamente
            header("Location: ../exito.php?msg=reset_ok");
            exit();
        }

        // Si es cualquier otra acción de borrado normal, vuelve al panel admin
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
