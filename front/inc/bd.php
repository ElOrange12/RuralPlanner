<?php
// inc/bd.php

$host = 'localhost';
$dbname = 'rural_planner';
$user = 'AdminPlanner';      // Tu nuevo usuario exclusivo
$pass = 'PlannerRural2026$'; // La contraseña segura

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    // Configuramos PDO para que lance excepciones si hay errores y nos devuelva arrays asociativos
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si falla, detenemos la ejecución y mostramos el error
    die("¡Error crítico de conexión!: " . $e->getMessage());
}
?>
