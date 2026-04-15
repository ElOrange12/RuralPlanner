<?php
session_start();
require_once 'inc/bd.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
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

// 2. Procesar Nueva Casa
if (isset($_POST['accion']) && $_POST['accion'] === 'nueva_casa') {
    $nombre = trim($_POST['nombre'] ?? '');
    $precio = floatval($_POST['precio'] ?? 0);
    $url_web = trim($_POST['url_web'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $foto_base64 = $_POST['foto_base64'] ?? '';

    $url_img = !empty($foto_base64) ? $foto_base64 : 'https://images.unsplash.com/photo-1542718610-a1d656d1884c?auto=format&fit=crop&w=800&q=80';

    if (!empty($nombre) && $precio > 0) {
        try {
            $stmt = $pdo->prepare("INSERT INTO casas (nombre, precio, descripcion, url_web, url_imagen, id_creador) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nombre, $precio, $descripcion, $url_web, $url_img, $user_id]);
            header("Location: votaciones.php");
            exit();
        } catch (PDOException $e) {
            die("<div style='background:red; color:white; padding:20px; text-align:center;'>❌ Error de Base de Datos: " . $e->getMessage() . "</div>");
        }
    }
}

// 3. Obtener Lista de Casas
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
        :root {
            --forest-green: #2d5a27;
            --forest-dark: #163013;
            --accent-gold: #c5a059;
            --accent-light: #e6c587; 
            --cream-paper: #fdfbf7;
            --wood-brown: #4b3621;
        }

        body { 
            font-family: 'Nunito', sans-serif; 
            background: linear-gradient(135deg, var(--forest-green) 0%, var(--forest-dark) 100%);
            color: var(--cream-paper); 
            margin: 0; padding: 0; min-height: 100vh;
        }

        .container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 3vw 5vw; box-sizing: border-box; }

        header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid rgba(255, 255, 255, 0.1); padding-bottom: 20px; margin-bottom: 30px; }
        
        .btn-back { 
            background: linear-gradient(145deg, var(--accent-light), var(--accent-gold)); 
            color: var(--wood-brown); padding: 12px 25px; text-decoration: none; 
            border-radius: 20px; font-weight: 900; text-transform: uppercase;
            box-shadow: 0 6px 0 #9c7b41; transition: all 0.2s;
        }

        /* GRID DE CASAS */
        .houses-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 40px; margin-bottom: 60px; }
        .house-card { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(15px); border-radius: 25px; overflow: hidden; border: 1px solid rgba(255, 255, 255, 0.2); transition: 0.3s; }
        .house-img { width: 100%; height: 230px; background-size: cover; background-position: center; position: relative; }
        .likes-badge { position: absolute; top: 15px; right: 15px; background: var(--accent-gold); color: var(--wood-brown); padding: 5px 15px; border-radius: 50px; font-weight: 900; }
        .house-info { padding: 25px; }
        .house-price { font-size: 1.8rem; font-weight: 900; color: var(--accent-gold); display: block; margin-bottom: 10px; }
        .btn-vote { width: 100%; background: transparent; border: 2px solid var(--accent-gold); color: var(--accent-gold); padding: 12px; border-radius: 15px; cursor: pointer; font-weight: 900; transition: 0.2s; }
        .btn-vote.active { background: var(--accent-gold); color: var(--wood-brown); }

        /* FORMULARIO SEGÚN TU IMAGEN */
        .add-house-section { 
            background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(15px);
            padding: 40px; border-radius: 25px; border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .form-grid { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); /* 3 Columnas arriba */
            gap: 20px; 
        }

        .form-grid input, .form-grid textarea { 
            padding: 15px; border-radius: 15px; border: 2px solid rgba(255,255,255,0.1); 
            background: rgba(255,255,255,0.05); color: white; font-family: inherit; font-size: 1rem;
            width: 100%; box-sizing: border-box;
        }

        /* Los elementos de ancho completo */
        .full-width { grid-column: 1 / -1; }

        /* Estilo para la zona de subida (El de en medio de tu imagen) */
        .custom-file-upload label {
            display: flex; justify-content: center; align-items: center;
            min-height: 80px; /* Altura media como en tu dibujo */
            background: rgba(255, 255, 255, 0.05); border: 2px dashed rgba(255, 255, 255, 0.4);
            border-radius: 15px; cursor: pointer; font-weight: bold; transition: 0.3s;
        }
        .custom-file-upload label.uploaded { background: rgba(45, 90, 39, 0.5); border-color: #4acead; border-style: solid; }
        .custom-file-upload input { display: none; }

        /* Estilo para la descripción (El de abajo de tu imagen) */
        textarea { min-height: 150px; resize: vertical; }

        .btn-submit { 
            background: linear-gradient(145deg, var(--accent-light), var(--accent-gold)); 
            color: var(--wood-brown); border: none; padding: 18px; border-radius: 15px; 
            font-weight: 900; cursor: pointer; transition: 0.2s; box-shadow: 0 6px 0 #9c7b41;
        }

        @media (max-width: 800px) { .form-grid { grid-template-columns: 1fr; } }
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
                <div class="house-img" style="background-image: url('<?= htmlspecialchars($casa['url_imagen']) ?>')">
                    <div class="likes-badge">❤️ <?= $casa['total_votos'] ?></div>
                </div>
                <div class="house-info">
                    <h3><?= htmlspecialchars($casa['nombre']) ?></h3>
                    <span class="house-price"><?= number_format($casa['precio'], 2) ?>€</span>
                    
                    <?php if (!empty($casa['descripcion'])): ?>
                        <p style="font-size: 0.9rem; opacity: 0.8;"><?= nl2br(htmlspecialchars($casa['descripcion'])) ?></p>
                    <?php endif; ?>

                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                        <form action="votaciones.php" method="POST" style="flex: 1;">
                            <input type="hidden" name="id_casa" value="<?= $casa['id_casa'] ?>">
                            <button type="submit" name="votar_casa" class="btn-vote <?= $casa['ha_votado'] ? 'active' : '' ?>">
                                <?= $casa['ha_votado'] ? 'Diste Like' : 'Votar Casa' ?>
                            </button>
                        </form>
                        <?php if (!empty($casa['url_web'])): ?>
                            <a href="<?= htmlspecialchars($casa['url_web']) ?>" target="_blank" style="padding: 12px; background: rgba(255,255,255,0.1); border-radius: 15px; color: white; text-decoration: none;">🔗</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="add-house-section">
        <h2 style="margin-top:0; color:var(--accent-gold);">➕ Proponer nueva casa</h2>
        <form action="votaciones.php" method="POST" class="form-grid" id="form-nueva-casa">
            <input type="hidden" name="accion" value="nueva_casa">
            <input type="hidden" name="foto_base64" id="foto_base64">

            <input type="text" name="nombre" placeholder="Nombre de la casa" required>
            <input type="number" name="precio" placeholder="Precio total (€)" required step="0.01">
            <input type="text" name="url_web" placeholder="Link (Airbnb/Booking)">

            <div class="custom-file-upload full-width">
                <label for="h-file" id="label-foto">📸 Haz clic para subir la foto</label>
                <input type="file" id="h-file" accept="image/*" onchange="convertirImagen(this, 'label-foto')">
            </div>

            <textarea name="descripcion" placeholder="Descripción: ¿Qué tiene la casa? (Piscina, barbacoa, habitaciones...)" class="full-width"></textarea>

            <button type="submit" class="btn-submit full-width">Publicar propuesta</button>
        </form>
    </div>
</div>

<script>
    function convertirImagen(input, labelId) {
        const label = document.getElementById(labelId);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = (e) => {
                document.getElementById('foto_base64').value = e.target.result;
                label.classList.add('uploaded');
                label.innerHTML = `✅ Foto lista: ${input.files[0].name.substring(0, 15)}...`;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
</body>
</html>
