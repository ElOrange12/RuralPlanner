<?php
session_start();
require_once '../inc/bd.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'];
    $user_id = $_SESSION['user_id'];
    $codigo = strtoupper(trim($_POST['codigo_sala']));

    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre_sala']);
        
        try {
            $pdo->beginTransaction();

            // 1. Crear la sala
            $stmt = $pdo->prepare("INSERT INTO salas (nombre_sala, codigo_sala, id_creador) VALUES (?, ?, ?)");
            $stmt->execute([$nombre, $codigo, $user_id]);
            $sala_id = $pdo->lastInsertId();
            
            // 2. Unir al creador a la sala
            $pdo->prepare("INSERT INTO usuarios_salas (id_usuario, id_sala) VALUES (?, ?)")->execute([$user_id, $sala_id]);

            // 3. ¡IMPORTANTE! Convertir al creador en ADMIN
            $pdo->prepare("UPDATE usuarios SET rol = 'admin' WHERE id_usuario = ?")->execute([$user_id]);
            $_SESSION['rol'] = 'admin'; // Actualizamos la sesión para que vea el panel de admin

            $pdo->commit();

            $_SESSION['sala_id'] = $sala_id;
            $_SESSION['sala_nombre'] = $nombre;
            header("Location: ../exito.php");
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            header("Location: ../salas.php?error=El código ya existe o hubo un error.");
            exit();
        }
    } 

    if ($accion === 'unirse') {
        $stmt = $pdo->prepare("SELECT id_sala, nombre_sala FROM salas WHERE codigo_sala = ?");
        $stmt->execute([$codigo]);
        $sala = $stmt->fetch();

        if ($sala) {
            // Unir si no estaba ya unido
            $stmt_check = $pdo->prepare("SELECT * FROM usuarios_salas WHERE id_usuario = ? AND id_sala = ?");
            $stmt_check->execute([$user_id, $sala['id_sala']]);
            
            if (!$stmt_check->fetch()) {
                $pdo->prepare("INSERT INTO usuarios_salas (id_usuario, id_sala) VALUES (?, ?)")->execute([$user_id, $sala['id_sala']]);
            }

            $_SESSION['sala_id'] = $sala['id_sala'];
            $_SESSION['sala_nombre'] = $sala['nombre_sala'];
            header("Location: ../exito.php");
            exit();
        } else {
            header("Location: ../salas.php?error=Código no encontrado");
            exit();
        }
    }
}