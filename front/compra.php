<?php
session_start();
require_once 'inc/bd.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

// Procesar nuevo producto
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nuevo_item'])) {
    $stmt = $pdo->prepare("INSERT INTO lista_compra (nombre, precio_estimado, id_pagador, pagador_manual) VALUES (?, ?, ?, ?)");
    // Si no hay ID de usuario (porque es un asistente manual), guardamos el nombre en una nueva columna o lo ignoramos
    $stmt->execute([$_POST['nombre'], $_POST['precio'], null, $_POST['pagador_nombre']]);
}

$items = $pdo->query("SELECT * FROM lista_compra ORDER BY id_item DESC")->fetchAll();
$total_compra = $pdo->query("SELECT SUM(precio_estimado) FROM lista_compra")->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Compra | Rural Planner</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos_compra.css"> <style>
        /* Estilos rápidos para que coincida con tu diseño */
        :root { --forest-green: #2d5a27; --accent-gold: #c5a059; --dark-wood: #2c1e14; }
        body { font-family: 'Nunito'; background: #1a2e18; color: white; padding: 20px; }
        .summary-bar { display: grid; grid-template-columns: 1fr 1fr 1fr; background: #2c1e14; padding: 20px; border-radius: 20px; text-align: center; margin-top: 20px; border: 2px solid var(--accent-gold); }
        .summary-item b { font-size: 1.5rem; color: var(--accent-gold); display: block; }
        .form-add { background: #233621; padding: 20px; border-radius: 20px; margin-bottom: 20px; display: flex; gap: 10px; align-items: flex-end; }
        input, select { padding: 10px; border-radius: 10px; border: none; }
        .btn-add { background: var(--accent-gold); padding: 10px 20px; border-radius: 10px; border: none; font-weight: 900; cursor: pointer; }
    </style>
</head>
<body>
    <header style="display:flex; justify-content:space-between;">
        <h1>🛒 Lista de la Compra</h1>
        <a href="exito.php" style="color:white; text-decoration:none;">⬅ Volver</a>
    </header>

    <div class="form-add">
        <form action="compra.php" method="POST" style="display:flex; gap:10px; width:100%;">
            <input type="hidden" name="nuevo_item" value="1">
            <div style="flex:2; display:flex; flex-direction:column;">
                <label><small>¿Qué compramos?</small></label>
                <input type="text" name="nombre" required>
            </div>
            <div style="flex:1; display:flex; flex-direction:column;">
                <label><small>Precio (€)</small></label>
                <input type="number" name="precio" step="0.01" required>
            </div>
            <div style="flex:1; display:flex; flex-direction:column;">
                <label><small>Responsable</small></label>
                <select name="pagador_nombre" id="selector-asistentes">
                    <option value="Fondo Común">Fondo Común</option>
                    </select>
            </div>
            <button type="submit" class="btn-add">AÑADIR</button>
        </form>
    </div>

    <table style="width:100%; background: #233621; border-radius: 20px; padding: 10px;">
        <?php foreach($items as $item): ?>
        <tr>
            <td style="padding: 10px;"><?= htmlspecialchars($item['nombre']) ?></td>
            <td style="color: var(--accent-gold); font-weight: 900;"><?= $item['precio_estimado'] ?>€</td>
            <td><small>👤 <?= htmlspecialchars($item['pagador_manual'] ?: 'Fondo Común') ?></small></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <div class="summary-bar">
        <div class="summary-item"><span>TOTAL COMPRA</span><b><?= number_format($total_compra, 2) ?>€</b></div>
        <div class="summary-item"><span>ASISTENTES</span><b id="txt-asistentes">0</b></div>
        <div class="summary-item"><span>TOCAMOS A</span><b id="txt-por-persona">0.00€</b></div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const asistentes = JSON.parse(localStorage.getItem('miembrosViajeRural')) || [];
            const totalCompra = <?= $total_compra ?>;
            
            // 1. Actualizar contador de asistentes
            const numAsistentes = asistentes.length || 1;
            document.getElementById('txt-asistentes').innerText = asistentes.length;

            // 2. Calcular "Tocamos a"
            const porPersona = totalCompra / numAsistentes;
            document.getElementById('txt-por-persona').innerText = porPersona.toFixed(2) + '€';

            // 3. Llenar el selector de responsables con los nombres de localStorage
            const selector = document.getElementById('selector-asistentes');
            asistentes.forEach(nombre => {
                const opt = document.createElement('option');
                opt.value = nombre;
                opt.innerText = nombre;
                selector.appendChild(opt);
            });
        });
    </script>
</body>
</html>
