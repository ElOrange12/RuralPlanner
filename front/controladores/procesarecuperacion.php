<?php
session_start();
require_once '../inc/bd.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $nombre = trim($_POST['nombre']);
    $nueva_password = trim($_POST['nueva_password']);
    $confirmar_password = trim($_POST['confirmar_password']);

    // 1. Validar campos vacíos
    if (empty($nombre) || empty($nueva_password) || empty($confirmar_password)) {
        header("Location: ../recuperar.php?error=Por favor, rellena todos los campos.");
        exit();
    }

    // 2. Validar que las contraseñas coinciden
    if ($nueva_password !== $confirmar_password) {
        header("Location: ../recuperar.php?error=Las contraseñas no coinciden.");
        exit();
    }

    try {
        // 3. Comprobar si el usuario existe realmente en la base de datos
        $stmt_check = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE nombre = ?");
        $stmt_check->execute([$nombre]);
        $usuario = $stmt_check->fetch();

        if (!$usuario) {
            // Error disimulado (por seguridad no se suele decir "el usuario no existe", pero aquí nos viene bien)
            header("Location: ../recuperar.php?error=No hemos encontrado a ese usuario.");
            exit();
        }

        // 4. Si existe, hasheamos la nueva contraseña y actualizamos
        $hash_nuevo = password_hash($nueva_password, PASSWORD_DEFAULT);
        
        $stmt_update = $pdo->prepare("UPDATE usuarios SET password = ? WHERE nombre = ?");
        
        if ($stmt_update->execute([$hash_nuevo, $nombre])) {
            // Éxito: Lo mandamos al index con un mensaje verde
            header("Location: ../index.php?success=¡Contraseña cambiada! Ya puedes entrar.");
            exit();
        } else {
            header("Location: ../recuperar.php?error=Hubo un problema al actualizar la base de datos.");
            exit();
        }

    } catch (PDOException $e) {
        header("Location: ../recuperar.php?error=Error crítico en la base de datos.");
        exit();
    }
} else {
    // Si alguien intenta entrar por URL directamente
    header("Location: ../recuperar.php");
    exit();
}
?>
