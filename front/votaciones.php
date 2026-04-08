<?php
session_start();
require_once 'inc/bd.php'; // Conexión a AdminPlanner

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$nombre_usuario = $_SESSION['nombre'];
$es_admin = ($_SESSION['rol'] === 'admin');

// --- 1. LÓGICA DE ACCIONES (POST) ---

// Acción: Añadir nueva casa
if (isset($_POST['accion']) && $_POST['accion'] == 'nueva_casa') {
    $nombre = trim($_POST['nombre']);
    $precio = floatval($_POST['precio']);
    $url_web = trim($_POST['url_web']);
    // Por ahora usamos una imagen por defecto para evitar errores de carga de archivos
    $url_img = 'https://images.unsplash.com/photo-1542718610-a1d656d1884c?auto=format&fit=crop&w=800&q=80';

    if (!empty($nombre) && $precio > 0) {
        $stmt = $pdo->prepare("INSERT INTO casas (nombre, precio, url_web, url_imagen, id_creador) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nombre, $precio, $url_web, $url_img, $user_id]);
        header("Location: votaciones.php");
        exit();
    }
}

// Acción: Votar/Quitar voto
if (isset($_POST['votar_casa'])) {
    $id_casa = $_POST['id_casa'];
    $check = $pdo->prepare("SELECT * FROM votos_casas WHERE id_usuario = ? AND id_casa = ?");
    $check->execute([$user_id, $id_casa]);
    
    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM votos_casas WHERE id_usuario = ? AND id_casa = ?")->execute([$user_id, $id_casa]);
    } else {
        $pdo->prepare("INSERT INTO votos_casas (id_usuario, id_casa) VALUES (?, ?)")->execute([$user_id, $id_casa]);
    }
    header("Location: votaciones.php");
    exit();
}

// --- 2. CONSULTA DE DATOS ---

// Obtenemos las casas con el conteo de votos y si el usuario actual ha votado
$query = "
    SELECT c.*, u.nombre as creador,
    (SELECT COUNT(*) FROM votos_casas WHERE id_casa = c.id_casa) as total_votos,
    (SELECT COUNT(*) FROM votos_casas WHERE id_usuario = $user_id AND id_casa = c.id_casa) as mi_voto
    FROM casas c
    LEFT JOIN usuarios u ON c.id_creador = u.id_usuario
    ORDER BY total_votos DESC
";
$casas = $pdo->query($query)->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votación de Casas - Rural Planner</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        :root { --deep-forest: #1a2e18; --dark-wood: #2c1e14; --forest-green: #2d5a27; --cream-paper: #fdfbf7; --accent-gold: #c5a059; --card-bg: #233621; }
        body { font-family: 'Nunito', sans-serif; background-color: var(--deep-forest); color: var(--cream-paper); margin: 0; padding: 0; min-height: 100vh; }
        .container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 3vw 5vw; box-sizing: border-box; }
        header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--forest-green); padding-bottom: 20px; margin-bottom: 20px; }
        h1 { margin: 0; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; color: var(--accent-gold); }
        .btn-back { background: var(--forest-green); color: white; padding: 10px 25px; text-decoration: none; border-radius: 50px; font-weight: 700; }
        .houses-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 40px; margin-bottom: 60px; margin-top: 20px; }
        .house-card { background: var(--card-bg); border-radius: 20px; overflow: hidden; box-shadow: 0 15px 30px rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; position: relative; }
        .house-img { width: 100%; height: 250px; background-size: cover; background-position: center; }
        .likes-badge { position: absolute; top: 15px; right: 15px; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(5px); padding: 8px 15px; border-radius: 50px; font-weight: 900; color: white; }
        .house-info { padding: 25px; flex-grow: 1; }
        .house-price { font-size: 1.8rem; font-weight: 900; color: var(--accent-gold); margin-bottom: 15px; display: block; }
        .btn-vote { width: 100%; background: transparent; border: 2px solid var(--accent-gold); color: var(--accent-gold); padding: 12px; border-radius: 12px; cursor: pointer; font-weight: 900; }
        .btn-vote.active { background: var(--accent-gold); color: var(--dark-wood); }
        .add-house-section { background: var(--dark-wood); padding: 30px; border-radius: 25px; border: 1px dashed var(--accent-gold); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px; }
        .form-grid input { padding: 15px; border-radius: 12px; border: none; background: rgba(255,255,255,0.9); color: var(--dark-wood); }
        .btn-add { background: var(--accent-gold); color: var(--dark-wood); border: none; padding: 15px; border-radius: 12px; font-weight: 900; cursor: pointer; }
    </style>
</head>
<body>
<div class="container">
    <header>
        <h1>🏆 Votación de Casas</h1>
        <a href="exito.php" class="btn-back">⬅ Volver</a>
    </header>

    <div class="houses-grid">
        <?php foreach ($casas as $casa): ?>
        <div class="house-card">
            <div class="house-img" style="background-image: url('<?= $casa['url_imagen'] ?>')">
                <div class="likes-badge">❤️ <?= $casa['total_votos'] ?></div>
            </div>
            <div class="house-info">
                <h3><?= htmlspecialchars($casa['nombre']) ?></h3>
                <span class="house-price"><?= number_format($casa['precio'], 2) ?>€</span>
                
                <form action="votaciones.php" method="POST">
                    <input type="hidden" name="id_casa" value="<?= $casa['id_casa'] ?>">
                    <button type="submit" name="votar_casa" class="btn-vote <?= $casa['mi_voto'] ? 'active' : '' ?>">
                        <?= $casa['mi_voto'] ? 'Diste Like' : 'Votar Casa' ?>
                    </button>
                </form>
                <a href="<?= $casa['url_web'] ?>" target="_blank" style="color:white; display:block; margin-top:10px; text-align:center;">🔗 Ver Web</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="add-house-section">
        <h2>➕ Añadir Nueva Casa</h2>
        <form action="votaciones.php" method="POST" class="form-grid">
            <input type="hidden" name="accion" value="nueva_casa">
            <input type="text" name="nombre" placeholder="Nombre (ej: Villa Bosque)" required>
            <input type="number" name="precio" step="0.01" placeholder="Precio Total (€)" required>
            <input type="text" name="url_web" placeholder="Link (Booking/Airbnb)">
            <button type="submit" class="btn-add">Añadir a la lista</button>
        </form>
    </div>
</div>
</body>
</html>
