<?php
session_start();
require_once 'inc/bd.php'; // Conexión centralizada

// Bloqueo de seguridad
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// --- LÓGICA DE ACCIONES (POST/GET) ---

// 1. Añadir nuevo producto
if (isset($_POST['btn-add'])) {
    $nombre = trim($_POST['i-name']);
    $precio = floatval($_POST['i-price']);
    $id_pagador = ($_POST['i-payer'] == "0") ? null : $_POST['i-payer'];

    if (!empty($nombre) && $precio > 0) {
        $stmt = $pdo->prepare("INSERT INTO lista_compra (nombre, precio_estimado, id_pagador) VALUES (?, ?, ?)");
        $stmt->execute([$nombre, $precio, $id_pagador]);
        header("Location: compra.php");
        exit();
    }
}

// 2. Marcar/Desmarcar como comprado
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $stmt = $pdo->prepare("UPDATE lista_compra SET comprado = NOT comprado WHERE id_item = ?");
    $stmt->execute([$id]);
    header("Location: compra.php");
    exit();
}

// 3. Eliminar producto
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM lista_compra WHERE id_item = ?");
    $stmt->execute([$id]);
    header("Location: compra.php");
    exit();
}

// --- CONSULTAS PARA LA VISTA ---

// Obtener todos los productos (unimos con usuarios para saber el nombre del responsable)
$items = $pdo->query("
    SELECT l.*, u.nombre as responsable 
    FROM lista_compra l 
    LEFT JOIN usuarios u ON l.id_pagador = u.id_usuario 
    ORDER BY l.id_item DESC
")->fetchAll();

// Obtener lista de usuarios para el desplegable de responsables
$usuarios = $pdo->query("SELECT id_usuario, nombre FROM usuarios ORDER BY nombre ASC")->fetchAll();

// Cálculos del resumen
$total_compra = $pdo->query("SELECT SUM(precio_estimado) FROM lista_compra")->fetchColumn() ?: 0;
$num_amigos = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn() ?: 1;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de la Compra - Rural Planner</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --deep-forest: #1a2e18; 
            --dark-wood: #2c1e14;   
            --forest-green: #2d5a27;
            --cream-paper: #fdfbf7;
            --accent-gold: #c5a059;
            --card-bg: #233621;
        }

        body {
            font-family: 'Nunito', sans-serif;
            background-color: var(--deep-forest);
            color: var(--cream-paper);
            margin: 0; padding: 0; min-height: 100vh;
        }

        .container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 3vw 5vw; box-sizing: border-box; }

        header {
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 2px solid var(--forest-green); padding-bottom: 20px; margin-bottom: 30px;
        }

        h1 { margin: 0; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; color: var(--accent-gold); }

        .btn-back {
            background: var(--forest-green); color: white; padding: 10px 25px;
            text-decoration: none; border-radius: 50px; font-weight: 700;
        }

        .add-item-card {
            background: var(--dark-wood); padding: 30px; border-radius: 25px;
            margin-bottom: 40px; border: 1px solid var(--forest-green);
        }

        .form-grid {
            display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 15px; align-items: flex-end;
        }

        .input-group { display: flex; flex-direction: column; gap: 5px; }
        .input-group label { font-size: 0.8rem; font-weight: 700; color: var(--accent-gold); }

        input, select {
            padding: 12px; border-radius: 12px; border: none; font-family: inherit;
            background: var(--cream-paper); color: var(--dark-wood);
        }

        .btn-add {
            background: var(--accent-gold); color: var(--dark-wood); border: none;
            padding: 12px 25px; border-radius: 12px; font-weight: 900; cursor: pointer;
        }

        .shopping-table {
            width: 100%; background: var(--card-bg); border-radius: 25px;
            overflow: hidden; border-collapse: collapse; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .shopping-table th {
            background: rgba(0,0,0,0.3); padding: 20px; text-align: left;
            color: var(--accent-gold); text-transform: uppercase; font-size: 0.9rem;
        }

        .shopping-table td { padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; }

        .item-row.comprado { opacity: 0.5; background-color: rgba(0, 0, 0, 0.2); }
        .item-row.comprado .product-name { text-decoration: line-through; color: #aaa; }

        .check-comprado { width: 25px; height: 25px; cursor: pointer; accent-color: var(--accent-gold); }

        .delete-btn { 
            color: #ff5e5e; text-decoration: none; font-weight: bold; background: rgba(255,0,0,0.1);
            padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(255,0,0,0.3);
        }

        .summary-bar {
            margin-top: 40px; background: var(--dark-wood); padding: 30px; border-radius: 25px;
            display: grid; grid-template-columns: 1fr 1fr 1fr; text-align: center; border: 2px solid var(--accent-gold);
        }

        .summary-item span { display: block; opacity: 0.7; font-size: 0.9rem; }
        .summary-item b { font-size: 2rem; color: var(--accent-gold); }

        @media (max-width: 900px) {
            .form-grid { grid-template-columns: 1fr; }
            .summary-bar { grid-template-columns: 1fr; gap: 20px; }
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>🛒 Lista de la Compra</h1>
        <a href="exito.php" class="btn-back">⬅ Dashboard</a>
    </header>

    <div class="add-item-card">
        <form action="compra.php" method="POST" class="form-grid">
            <div class="input-group">
                <label>¿Qué compramos?</label>
                <input type="text" name="i-name" placeholder="Ej: Carne para barbacoa" required>
            </div>
            <div class="input-group">
                <label>Precio Estimado (€)</label>
                <input type="number" name="i-price" step="0.01" placeholder="0.00" required>
            </div>
            <div class="input-group">
                <label>Responsable:</label>
                <select name="i-payer">
                    <option value="0">Fondo Común</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= $u['id_usuario'] ?>"><?= htmlspecialchars($u['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="btn-add" class="btn-add">AÑADIR</button>
        </form>
    </div>

    <table class="shopping-table">
        <thead>
            <tr>
                <th>Cesta</th>
                <th>Producto</th>
                <th>Precio</th>
                <th>Responsable</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr class="item-row <?= $item['comprado'] ? 'comprado' : '' ?>">
                    <td>
                        <input type="checkbox" class="check-comprado" 
                               onclick="window.location.href='compra.php?toggle=<?= $item['id_item'] ?>'"
                               <?= $item['comprado'] ? 'checked' : '' ?>>
                    </td>
                    <td class="product-name" style="font-weight:bold; font-size:1.1rem;">
                        <?= htmlspecialchars($item['nombre']) ?>
                    </td>
                    <td style="color:var(--accent-gold); font-weight:900; font-size:1.2rem;">
                        <?= number_format($item['precio_estimado'], 2) ?>€
                    </td>
                    <td>
                        <small style="background: rgba(255,255,255,0.1); padding: 5px 10px; border-radius: 10px;">
                            👤 <?= htmlspecialchars($item['responsable'] ?? 'Fondo Común') ?>
                        </small>
                    </td>
                    <td>
                        <a href="compra.php?delete=<?= $item['id_item'] ?>" 
                           class="delete-btn" 
                           onclick="return confirm('¿Borrar este producto?')">🗑️ Borrar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="summary-bar">
        <div class="summary-item">
            <span>COSTE TOTAL COMPRA</span>
            <b><?= number_format($total_compra, 2) ?>€</b>
        </div>
        <div class="summary-item" style="border-left: 1px solid #444; border-right: 1px solid #444;">
            <span>ASISTENTES</span>
            <b><?= $num_amigos ?></b>
        </div>
        <div class="summary-item">
            <span>TOCAMOS A</span>
            <b><?= number_format($total_compra / $num_amigos, 2) ?>€</b>
        </div>
    </div>
</div>

</body>
</html>
