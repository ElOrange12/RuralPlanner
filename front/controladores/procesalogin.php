<?php
// controladores/procesalogin.php
session_start();

// 1. Llamamos a nuestro controlador central de base de datos
require_once '../inc/bd.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $nombre = trim($_POST['nombre']);
    $password = trim($_POST['password']);

    if (empty($nombre) || empty($password)) {
        header("Location: ../index.php?error=Por favor, rellena todos los campos.");
        exit();
    }

    try {
        // 2. Buscamos al usuario (usando la variable $pdo que viene de bd.php)
        $stmt = $pdo->prepare("SELECT id_usuario, nombre, password, rol FROM usuarios WHERE nombre = ?");
        $stmt->execute([$nombre]);
        $usuario = $stmt->fetch();

        // 3. Verificamos contraseñas
        if ($usuario && password_verify($password, $usuario['password'])) {
            $_SESSION['user_id'] = $usuario['id_usuario'];
            $_SESSION['nombre']  = $usuario['nombre'];
            $_SESSION['rol']     = $usuario['rol'];

            header("Location: ../exito.php");
            exit();
        } else {
            header("Location: ../index.php?error=Usuario o contraseña incorrectos.");
            exit();
        }
    } catch (PDOException $e) {
        header("Location: ../index.php?error=Error en la base de datos.");
        exit();
    }
} else {
    header("Location: ../index.php");
    exit();
}
?>
