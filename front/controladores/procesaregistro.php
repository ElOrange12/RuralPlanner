<?php
// controladores/procesaregistro.php
session_start();

// 1. Llamamos a nuestro controlador central
require_once '../inc/bd.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $nombre = trim($_POST['nombre']);
    $password = trim($_POST['password']);
    $password_confirm = trim($_POST['password_confirm']);

    if (empty($nombre) || empty($password) || empty($password_confirm)) {
        header("Location: ../registro.php?error=Por favor, rellena todos los campos.");
        exit();
    }

    if ($password !== $password_confirm) {
        header("Location: ../registro.php?error=Las contraseñas no coinciden.");
        exit();
    }

    try {
        // 2. Verificamos que el usuario no exista
        $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE nombre = ?");
        $stmt->execute([$nombre]);
        
        if ($stmt->fetch()) {
            header("Location: ../registro.php?error=Ese usuario ya está pillado.");
            exit();
        }

        // 3. Hasheamos e insertamos
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, password) VALUES (?, ?)");

        if ($stmt->execute([$nombre, $hash])) {
            header("Location: ../index.php?success=¡Cuenta creada con éxito! Ya puedes entrar.");
            exit();
        } else {
            header("Location: ../registro.php?error=Hubo un problema al crear la cuenta.");
            exit();
        }

    } catch (PDOException $e) {
        header("Location: ../registro.php?error=Error en la base de datos.");
        exit();
    }
} else {
    header("Location: ../registro.php");
    exit();
}
?>
