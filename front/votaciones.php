<?php
session_start();
require_once 'inc/bd.php'; // Conexión a la base de datos

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$nombre_usuario = $_SESSION['nombre'];
$es_admin = ($_SESSION['rol'] === 'admin');

// 1. Procesar Votos
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

// 2. Procesar Nueva Casa (Guardando la foto en la Base de Datos)
if (isset($_POST['accion']) && $_POST['accion'] === 'nueva_casa') {
    $nombre = trim($_POST['nombre'] ?? '');
    $precio = floatval($_POST['precio'] ?? 0);
    $url_web = trim($_POST['url_web'] ?? '');
    $foto_base64 = $_POST['foto_base64'] ?? ''; // Aquí recogemos el código de la foto

    // Si no ha subido foto, ponemos la de Unsplash
    $url_img = !empty($foto_base64) ? $foto_base64 : 'https://images.unsplash.com/photo-1542718610-a1d656d1884c?auto=format&fit=crop&w=800&q=80';

    if (!empty($nombre) && $precio > 0) {
        try {
            $stmt = $pdo->prepare("INSERT INTO casas (nombre, precio, url_web, url_imagen, id_creador) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nombre, $precio, $url_web, $url_img, $user_id]);
            header("Location: votaciones.php");
            exit();
        } catch (PDOException $e) {
            die("<div style='background:red; color:white; padding:20px; text-align:center;'>❌ Error de Base de Datos: " . $e->getMessage() . " <br><a href='votaciones.php' style='color:white;'>Volver atrás</a></div>");
        }
    }
}

// 3. Obtener Lista de Casas desde MySQL
$casas = $pdo->query("
    SELECT c.*, 
    (SELECT COUNT(*) FROM votos_casas WHERE id_casa = c.id_casa) as total_votos,
    (SELECT COUNT(*) FROM votos_casas WHERE id_usuario = $user_id AND id_casa = c.id_casa) as ha_votado,
    (SELECT GROUP_CONCAT(u.nombre SEPARATOR ', ') FROM votos_casas vc JOIN usuarios u ON vc.id_usuario = u.id_usuario WHERE vc.id_casa = c.id_casa) as votantes
    FROM casas c
    ORDER BY total_votos DESC
")->fetchAll();
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
        body { font-family: 'Nunito', sans-serif; background-color: var(--deep-forest); color: var(--cream-paper); margin: 0; padding: 0; min-height: 100vh; overflow-x: hidden; }
        .container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 3vw 5vw; box-sizing: border-box; display: flex; flex-direction: column; min-height: 100vh; }
        header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--forest-green); padding-bottom: 20px; margin-bottom: 20px; }
        h1 { margin: 0; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; color: var(--accent-gold); }
        .btn-back { background: var(--forest-green); color: white; padding: 10px 25px; text-decoration: none; border-radius: 50px; font-weight: 700; transition: 0.3s; }
        .btn-back:hover { background: var(--dark-wood); }
        .houses-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 40px; margin-bottom: 60px; margin-top: 20px; }
        .house-card { background: var(--card-bg); border-radius: 20px; overflow: hidden; box-shadow: 0 15px 30px rgba(0,0,0,0.4); transition: 0.3s; border: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; position: relative; }
        .house-card:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(0,0,0,0.6); }
        .house-img { width: 100%; height: 250px; background-size: cover; background-position: center; position: relative; }
        .likes-badge { position: absolute; top: 15px; right: 15px; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(5px); padding: 8px 15px; border-radius: 50px; font-weight: 900; font-size: 1.1rem; color: white; display: flex; align-items: center; gap: 5px; border: 1px solid rgba(255,255,255,0.2); }
        .delete-house { position: absolute; top: 15px; left: 15px; background: rgba(255, 60, 60, 0.9); color: white; padding: 8px 15px; border-radius: 8px; font-weight: bold; font-size: 0.9rem; cursor: pointer; border: 2px solid rgba(255,255,255,0.5); transition: 0.2s; z-index: 10; }
        .delete-house:hover { background: red; transform: scale(1.05); }
        .house-info { padding: 25px; flex-grow: 1; display: flex; flex-direction: column; }
        .house-info h3 { margin: 0 0 5px 0; font-size: 1.6rem; color: var(--cream-paper); }
        .house-price { font-size: 1.8rem; font-weight: 900; color: var(--accent-gold); margin-bottom: 15px; }
        .voters-list { font-size: 0.9rem; opacity: 0.6; margin-bottom: 20px; flex-grow: 1; }
        .vote-section { display: flex; justify-content: space-between; align-items: center; margin-top: auto; }
        .btn-vote { width: 100%; background: transparent; border: 2px solid var(--accent-gold); color: var(--accent-gold); padding: 12px 20px; border-radius: 12px; cursor: pointer; font-weight: 900; font-size: 1.1rem; transition: 0.2s; text-align: center; }
        .btn-vote.active { background: var(--accent-gold); color: var(--dark-wood); }
        .btn-link { margin-left: 10px; padding: 12px; background: var(--dark-wood); color: white; text-decoration: none; border-radius: 12px; border: 1px solid #444; }
        .add-house-section { background: var(--dark-wood); padding: 30px; border-radius: 25px; border: 1px dashed var(--accent-gold); margin-top: auto; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px; }
        .form-grid input { padding: 15px; border-radius: 12px; border: none; font-family: inherit; background: rgba(255,255,255,0.9); color: var(--dark-wood); font-size: 1rem; }
        .file-upload-wrapper { background: rgba(255,255,255,0.9); border-radius: 12px; display: flex; align-items: center; padding: 0 10px; }
        .file-upload-wrapper input[type="file"] { background: transparent; padding: 12px 5px; width: 100%; }
        .btn-add { background: var(--accent-gold); color: var(--dark-wood); border: none; padding: 15px; border-radius: 12px; font-weight: 900; font-size: 1.1rem; cursor: pointer; transition: 0.3s; }
        .btn-add:hover { background: white; transform: scale(1.02); }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>🏆 Votación de Casas</h1>
        <a href="exito.php" class="btn-back">⬅ Volver al Feed</a>
    </header>

    <div class="houses-grid">
        <?php if (count($casas) === 0): ?>
            <p style="text-align:center; grid-column: 1/-1; opacity:0.5; font-size:1.2rem;">Aún no hay casas propuestas. ¡Añade una!</p>
        <?php else: ?>
            <?php foreach ($casas as $casa): ?>
                <div class="house-card">
                    
                    <?php if ($es_admin): ?>
                        <form action="controladores/admin_procesar.php" method="POST" style="margin:0;" onsubmit="return confirm('¿Seguro que quieres borrar esta casa?');">
                            <input type="hidden" name="accion" value="borrar_casa">
                            <input type="hidden" name="id" value="<?= $casa['id_casa'] ?>">
                            <button type="submit" class="delete-house">🗑️ Borrar</button>
                        </form>
                    <?php endif; ?>
                    
                    <div class="house-img" style="background-image: url('<?= htmlspecialchars($casa['url_imagen']) ?>')">
                        <div class="likes-badge">❤️ <?= $casa['total_votos'] ?></div>
                    </div>
                    
                    <div class="house-info">
                        <h3><?= htmlspecialchars($casa['nombre']) ?></h3>
                        <span class="house-price"><?= number_format($casa['precio'], 2) ?>€</span>
                        <p class="voters-list">
                            <?= $casa['total_votos'] > 0 ? "Votado por: " . htmlspecialchars($casa['votantes']) : "Sé el primero en votar" ?>
                        </p>
                        
                        <div class="vote-section">
                            <form action="votaciones.php" method="POST" style="flex-grow: 1;">
                                <input type="hidden" name="id_casa" value="<?= $casa['id_casa'] ?>">
                                <button type="submit" name="votar_casa" class="btn-vote <?= $casa['ha_votado'] ? 'active' : '' ?>">
                                    <?= $casa['ha_votado'] ? 'Diste Like' : 'Votar Casa' ?>
                                </button>
                            </form>
                            
                            <?php if (!empty($casa['url_web']) && $casa['url_web'] !== '#'): ?>
                                <a href="<?= htmlspecialchars($casa['url_web']) ?>" target="_blank" class="btn-link">🔗 Web</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="add-house-section">
        <h2 style="margin:0; color: var(--accent-gold);">➕ Añadir Nueva Casa Candidata</h2>
        <p style="margin: 5px 0 0 0; opacity: 0.7;">Las fotos muy pesadas pueden dar error. Intenta subir capturas.</p>
        
        <form action="votaciones.php" method="POST" class="form-grid" id="form-nueva-casa">
            <input type="hidden" name="accion" value="nueva_casa">
            
            <input type="hidden" name="foto_base64" id="foto_base64" value="">

            <input type="text" id="h-name" name="nombre" placeholder="Nombre (ej: Villa Bosque)" required>
            <input type="number" id="h-price" name="precio" placeholder="Precio Total (€)" required step="0.01">
            
            <div class="file-upload-wrapper">
                <input type="file" id="h-file" accept="image/*" onchange="convertirImagen()">
            </div>

            <input type="text" id="h-url" name="url_web" placeholder="Link web (Booking/Airbnb)">
            
            <button type="button" class="btn-add" onclick="enviarFormulario()">Añadir a la lista</button>
        </form>
    </div>
</div>

<script>
    // 1. Convertir foto elegida a texto Base64
    function convertirImagen() {
        const archivo = document.getElementById('h-file').files[0];
        if (archivo) {
            const reader = new FileReader();
            reader.onload = function(e) {
                // Guardamos el chorro de texto en el input oculto
                document.getElementById('foto_base64').value = e.target.result;
            };
            reader.readAsDataURL(archivo);
        } else {
            document.getElementById('foto_base64').value = '';
        }
    }

    // 2. Enviar a PHP
    function enviarFormulario() {
        const nombre = document.getElementById('h-name').value;
        const precio = document.getElementById('h-price').value;
        
        if (!nombre || !precio) {
            alert("El nombre y el precio son obligatorios.");
            return;
        }
        
        // Enviamos el formulario. PHP hará el INSERT y recargará la página.
        document.getElementById('form-nueva-casa').submit();
    }
</script>

</body>
</html>
