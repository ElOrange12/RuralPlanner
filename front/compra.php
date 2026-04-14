<?php
session_start();
require_once 'inc/bd.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

// --- 1. PROCESAR BORRAR PRODUCTO ---
if (isset($_POST['borrar_item'])) {
    $id_borrar = $_POST['id_item'];
    $stmt = $pdo->prepare("DELETE FROM lista_compra WHERE id_item = ?");
    $stmt->execute([$id_borrar]);
    header("Location: compra.php");
    exit();
}

// --- 2. PROCESAR NUEVO PRODUCTO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nuevo_item'])) {
    try {
        // Si el select envía un valor vacío, lo pasamos como NULL (Fondo Común)
        $id_pagador = !empty($_POST['id_pagador']) ? $_POST['id_pagador'] : null;

        $stmt = $pdo->prepare("INSERT INTO lista_compra (nombre, precio_estimado, id_pagador) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['nombre'], $_POST['precio'], $id_pagador]);
        
        header("Location: compra.php");
        exit();
    } catch (PDOException $e) {
        die("<div style='background:#e74c3c; color:white; padding:20px; text-align:center;'>❌ Error BD: " . $e->getMessage() . " <br><a href='compra.php'>Volver</a></div>");
    }
}

// --- 3. TRAER LA LISTA ACTUAL Y CRUZARLA CON USUARIOS ---
$items = $pdo->query("
    SELECT l.*, u.nombre as nombre_pagador 
    FROM lista_compra l 
    LEFT JOIN usuarios u ON l.id_pagador = u.id_usuario 
    ORDER BY l.id_item DESC
")->fetchAll();

$total_compra = $pdo->query("SELECT SUM(precio_estimado) FROM lista_compra")->fetchColumn() ?: 0;

// --- 4. TRAER EL NÚMERO DE ASISTENTES PARA DIVIDIR ---
$num_asistentes = $pdo->query("SELECT COUNT(*) FROM asistentes")->fetchColumn() ?: 1;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Compra | Rural Planner</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        :root { --forest-green: #2d5a27; --accent-gold: #c5a059; --dark-wood: #2c1e14; }
        body { font-family: 'Nunito', sans-serif; background: #1a2e18; color: white; padding: 20px; margin: 0; box-sizing: border-box; }
        .container { max-width: 1000px; margin: 0 auto; }
        
        header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--forest-green); padding-bottom: 20px; margin-bottom: 30px; }
        h1 { margin: 0; color: var(--accent-gold); text-transform: uppercase; letter-spacing: 1px; }
        .btn-back { background: var(--forest-green); color: white; padding: 10px 20px; border-radius: 20px; text-decoration: none; font-weight: bold; }

        .summary-bar { display: grid; grid-template-columns: 1fr 1fr 1fr; background: #2c1e14; padding: 20px; border-radius: 20px; text-align: center; margin-top: 20px; border: 2px solid var(--accent-gold); }
        .summary-item b { font-size: 1.8rem; color: var(--accent-gold); display: block; }
        
        .form-add { background: #233621; padding: 25px; border-radius: 20px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        input, select { padding: 12px; border-radius: 10px; border: none; font-family: inherit; font-size: 1rem; width: 100%; box-sizing: border-box; }
        .input-wrapper { display: flex; flex-direction: column; flex: 1; min-width: 150px; }
        .btn-add { background: var(--accent-gold); color: var(--dark-wood); padding: 12px 25px; border-radius: 10px; border: none; font-weight: 900; font-size: 1rem; cursor: pointer; transition: 0.2s; white-space: nowrap; height: 43px; }
        .btn-add:hover { transform: scale(1.02); background: white; }

        .table-container { background: #233621; border-radius: 20px; padding: 20px; overflow-x: auto; box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        table { width: 100%; border-collapse: collapse; min-width: 500px; }
        th { text-align: left; color: var(--accent-gold); padding: 10px; border-bottom: 2px solid rgba(255,255,255,0.1); text-transform: uppercase; font-size: 0.9rem; }
        td { padding: 15px 10px; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; }
        
        .btn-delete { background: rgba(231, 76, 60, 0.15); color: #e74c3c; border: 1px solid #e74c3c; padding: 8px 12px; border-radius: 8px; cursor: pointer; font-weight: bold; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; }
        .btn-delete:hover { background: #e74c3c; color: white; }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>🛒 Lista de la Compra</h1>
        <a href="exito.php" class="btn-back">⬅ Volver</a>
    </header>

    <div class="form-add">
        <form action="compra.php" method="POST" style="display:flex; flex-wrap:wrap; gap:15px; width:100%;">
            <input type="hidden" name="nuevo_item" value="1">
            <div class="input-wrapper" style="flex: 2;">
                <label style="color: var(--accent-gold); margin-bottom: 5px; font-weight: bold; font-size: 0.85rem;">¿Qué compramos?</label>
                <input type="text" name="nombre" placeholder="Ej: Carbón para la barbacoa" required>
            </div>
            <div class="input-wrapper">
                <label style="color: var(--accent-gold); margin-bottom: 5px; font-weight: bold; font-size: 0.85rem;">Precio (€)</label>
                <input type="number" name="precio" placeholder="0.00" step="0.01" required>
            </div>
            <div class="input-wrapper">
                <label style="color: var(--accent-gold); margin-bottom: 5px; font-weight: bold; font-size: 0.85rem;">Responsable (Pagador)</label>
                <select name="id_pagador">
                    <option value="">Fondo Común</option>
                    <?php foreach($usuarios_registrados as $ur): ?>
                        <option value="<?= $ur['id_usuario'] ?>"><?= htmlspecialchars($ur['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: flex; align-items: flex-end;">
                <button type="submit" class="btn-add">AÑADIR A LA LISTA</button>
            </div>
        </form>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Precio Estimado</th>
                    <th>Responsable</th>
                    <th style="text-align: center;">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($items) === 0): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 30px; opacity: 0.5;">La lista está vacía. ¡Añade lo que falte arriba!</td>
                    </tr>
                <?php else: ?>
                    <?php foreach($items as $item): ?>
                    <tr>
                        <td style="font-size: 1.1rem;"><?= htmlspecialchars($item['nombre']) ?></td>
                        <td style="color: var(--accent-gold); font-weight: 900; font-size: 1.2rem;"><?= number_format($item['precio_estimado'], 2) ?>€</td>
                        
                        <td>
                            <span style="background: rgba(255,255,255,0.1); padding: 5px 10px; border-radius: 8px; font-size: 0.85rem;">
                                👤 <?= htmlspecialchars($item['nombre_pagador'] ?? 'Fondo Común') ?>
                            </span>
                        </td>

                        <td style="text-align: center;">
                            <form action="compra.php" method="POST" style="margin: 0;" onsubmit="return confirm('¿Seguro que quieres borrar <?= htmlspecialchars(addslashes($item['nombre'])) ?>?');">
                                <input type="hidden" name="borrar_item" value="1">
                                <input type="hidden" name="id_item" value="<?= $item['id_item'] ?>">
                                <button type="submit" class="btn-delete" title="Borrar">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="summary-bar">
        <div class="summary-item"><span>TOTAL COMPRA</span><b><?= number_format($total_compra, 2) ?>€</b></div>
        <div class="summary-item"><span>ASISTENTES</span><b><?= $num_asistentes ?></b></div>
        <div class="summary-item"><span>TOCAMOS A</span><b><?= number_format($total_compra / $num_asistentes, 2) ?>€</b></div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        // Leemos cuántos amigos hemos añadido en la pestaña de inicio
        const asistentes = JSON.parse(localStorage.getItem('miembrosViajeRural')) || [];
        const totalCompra = <?= (float)$total_compra ?>;
        
        // Calculamos la división entre la gente real que va al viaje
        const numAsistentes = asistentes.length > 0 ? asistentes.length : 1;
        document.getElementById('txt-asistentes').innerText = asistentes.length;

        const porPersona = totalCompra / numAsistentes;
        document.getElementById('txt-por-persona').innerText = porPersona.toFixed(2) + '€';
    });
</script>

</body>
</html>
