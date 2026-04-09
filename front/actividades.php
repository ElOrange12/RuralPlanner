<?php
session_start();
require_once 'inc/bd.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$es_admin = ($_SESSION['rol'] === 'admin');

// --- 1. PROCESAR VOTOS ---
if (isset($_POST['votar_actividad'])) {
    $id_actividad = $_POST['id_actividad'];
    $check = $pdo->prepare("SELECT * FROM votos_actividades WHERE id_usuario = ? AND id_actividad = ?");
    $check->execute([$user_id, $id_actividad]);
    
    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM votos_actividades WHERE id_usuario = ? AND id_actividad = ?")->execute([$user_id, $id_actividad]);
    } else {
        $pdo->prepare("INSERT INTO votos_actividades (id_usuario, id_actividad) VALUES (?, ?)")->execute([$user_id, $id_actividad]);
    }
    header("Location: actividades.php");
    exit();
}

// --- 2. PROCESAR NUEVA ACTIVIDAD ---
if (isset($_POST['accion']) && $_POST['accion'] === 'nueva_actividad') {
    $nombre = trim($_POST['nombre'] ?? '');
    $categoria = $_POST['categoria'] ?? 'aventura';
    $precio = floatval($_POST['precio'] ?? 0);
    
    // Recogemos la hora de inicio y fin (coincidiendo con tu BD)
    $hora_inicio = !empty($_POST['hora_inicio']) ? $_POST['hora_inicio'] : null;
    $hora_finalizacion = !empty($_POST['hora_finalizacion']) ? $_POST['hora_finalizacion'] : null;
    
    $descripcion = trim($_POST['descripcion'] ?? '');
    $url_web = trim($_POST['url_web'] ?? '');
    $foto_base64 = $_POST['foto_base64'] ?? '';

    $url_img = !empty($foto_base64) ? $foto_base64 : 'https://images.unsplash.com/photo-1533560904424-a0c61dc306fc?auto=format&fit=crop&w=800&q=80';

    if (!empty($nombre)) {
        try {
            // INSERT con las columnas correctas
            $stmt = $pdo->prepare("INSERT INTO actividades (nombre, categoria, precio, hora_inicio, hora_finalizacion, descripcion, url_web, url_imagen, id_creador) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nombre, $categoria, $precio, $hora_inicio, $hora_finalizacion, $descripcion, $url_web, $url_img, $user_id]);
            header("Location: actividades.php");
            exit();
        } catch (PDOException $e) {
            die("<div style='background:red; color:white; padding:20px; text-align:center;'>❌ Error de Base de Datos: " . $e->getMessage() . " <br><a href='actividades.php' style='color:white;'>Volver</a></div>");
        }
    }
}

// --- 3. TRAER DATOS DE LA BASE DE DATOS ---
$emojis = [
    'aventura' => '🥾',
    'agua' => '🏄‍♂️',
    'juegos' => '🎲',
    'comida' => '🍷',
    'fiesta' => '🎉'
];

$actividades = $pdo->query("
    SELECT a.*, 
    (SELECT COUNT(*) FROM votos_actividades WHERE id_actividad = a.id_actividad) as total_votos,
    (SELECT COUNT(*) FROM votos_actividades WHERE id_usuario = $user_id AND id_actividad = a.id_actividad) as ha_votado,
    (SELECT GROUP_CONCAT(u.nombre SEPARATOR ', ') FROM votos_actividades va JOIN usuarios u ON va.id_usuario = u.id_usuario WHERE va.id_actividad = a.id_actividad) as votantes
    FROM actividades a
    ORDER BY total_votos DESC
")->fetchAll();

$presupuesto_personal = $pdo->query("
    SELECT SUM(a.precio) 
    FROM actividades a 
    JOIN votos_actividades v ON a.id_actividad = v.id_actividad 
    WHERE v.id_usuario = $user_id
")->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planes y Actividades - Rural Planner</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        :root { --deep-forest: #1a2e18; --dark-wood: #2c1e14; --forest-green: #2d5a27; --cream-paper: #fdfbf7; --accent-gold: #c5a059; --card-bg: #233621; }
        body { font-family: 'Nunito', sans-serif; background-color: var(--deep-forest); color: var(--cream-paper); margin: 0; padding: 0; width: 100vw; min-height: 100vh; overflow-x: hidden; }
        .container { width: 100%; max-width: 1100px; margin: 0 auto; padding: 3vw 5vw; box-sizing: border-box; display: flex; flex-direction: column; min-height: 100vh; }
        header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--forest-green); padding-bottom: 20px; margin-bottom: 30px; }
        h1 { margin: 0; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; color: var(--accent-gold); }
        .btn-back { background: var(--forest-green); color: white; padding: 10px 25px; text-decoration: none; border-radius: 50px; font-weight: 700; transition: 0.3s; }
        .btn-back:hover { background: var(--dark-wood); }
        .plans-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 30px; margin-bottom: 40px; }
        .plan-card { background: var(--card-bg); border-radius: 20px; overflow: hidden; box-shadow: 0 15px 30px rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; position: relative; transition: transform 0.3s; }
        .plan-card:hover { transform: translateY(-8px); }
        .plan-img { width: 100%; height: 200px; background-size: cover; background-position: center; position: relative; background-color: #111; }
        .likes-badge { position: absolute; top: 15px; right: 15px; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(5px); padding: 8px 15px; border-radius: 50px; font-weight: 900; color: white; border: 1px solid rgba(255,255,255,0.2); z-index: 5; }
        .cat-emoji { position: absolute; bottom: -20px; left: 20px; font-size: 2rem; background: var(--dark-wood); width: 50px; height: 50px; display: flex; justify-content: center; align-items: center; border-radius: 50px; border: 2px solid var(--accent-gold); z-index: 5; }
        .delete-btn { position: absolute; top: 15px; left: 15px; background: rgba(255, 60, 60, 0.9); color: white; border: none; padding: 8px 15px; border-radius: 8px; cursor: pointer; font-weight: bold; transition: 0.2s; z-index: 10; border: 2px solid rgba(255,255,255,0.5);}
        .delete-btn:hover { background: red; transform: scale(1.05); }
        .plan-info { padding: 35px 20px 20px 20px; flex-grow: 1; display: flex; flex-direction: column; }
        .plan-info h3 { margin: 0 0 5px 0; font-size: 1.5rem; color: var(--cream-paper); }
        .plan-price { font-size: 1.6rem; font-weight: 900; color: var(--accent-gold); margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; }
        .plan-duration { font-size: 1rem; color: #ccc; font-weight: normal; background: rgba(255,255,255,0.1); padding: 4px 10px; border-radius: 8px; }
        .plan-desc { font-size: 0.9rem; opacity: 0.7; margin-bottom: 15px; flex-grow: 1; }
        .voters-list { font-size: 0.8rem; color: #aaa; margin-bottom: 15px; font-style: italic; }
        .vote-section { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
        .btn-vote { flex-grow: 1; background: transparent; border: 2px solid var(--accent-gold); color: var(--accent-gold); padding: 10px; border-radius: 12px; cursor: pointer; font-weight: 900; transition: 0.2s; }
        .btn-vote.active { background: var(--accent-gold); color: var(--dark-wood); }
        .btn-link { padding: 10px 15px; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 12px; font-size: 0.9rem; border: 1px solid #444; }
        .btn-link:hover { background: rgba(255,255,255,0.2); }
        .add-plan-section { background: var(--dark-wood); padding: 30px; border-radius: 25px; border: 1px dashed var(--accent-gold); margin-top: auto; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-top: 20px; }
        .form-grid input, .form-grid select { padding: 12px; border-radius: 12px; border: none; font-family: inherit; background: rgba(255,255,255,0.9); color: var(--dark-wood); font-size: 1rem; width: 100%; box-sizing: border-box; }
        .file-upload-wrapper { background: rgba(255,255,255,0.9); border-radius: 12px; display: flex; align-items: center; padding: 0 10px; }
        .file-upload-wrapper input[type="file"] { background: transparent; padding: 10px 5px; }
        .btn-add { background: var(--accent-gold); color: var(--dark-wood); border: none; padding: 15px; border-radius: 12px; font-weight: 900; font-size: 1.1rem; cursor: pointer; transition: 0.3s; grid-column: 1 / -1; }
        .btn-add:hover { background: white; transform: scale(1.01); }
        .summary-bar { margin-top: 30px; background: rgba(0,0,0,0.4); padding: 20px; border-radius: 20px; display: flex; justify-content: space-between; align-items: center; border-left: 5px solid var(--accent-gold); }
        .summary-text { font-size: 1.1rem; opacity: 0.8; }
        .summary-price { font-size: 2.5rem; color: var(--accent-gold); font-weight: 900; }
        @media (max-width: 800px) { .summary-bar { flex-direction: column; text-align: center; gap: 10px; } }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>🏄‍♂️ Planes y Reservas</h1>
        <a href="exito.php" class="btn-back">⬅ Dashboard</a>
    </header>

    <div class="plans-grid">
        <?php if (count($actividades) === 0): ?>
            <p style="grid-column: 1/-1; text-align:center; opacity:0.5; font-size:1.2rem;">No hay actividades propuestas. ¡Añade una abajo!</p>
        <?php else: ?>
            <?php foreach ($actividades as $plan): ?>
                <div class="plan-card">
                    
                    <?php if ($es_admin): ?>
                        <form action="controladores/admin_procesar.php" method="POST" style="margin:0;" onsubmit="return confirm('¿Borrar esta actividad para todos?');">
                            <input type="hidden" name="accion" value="borrar_actividad">
                            <input type="hidden" name="id" value="<?= $plan['id_actividad'] ?>">
                            <button type="submit" class="delete-btn" title="Borrar Actividad">🗑️ Borrar</button>
                        </form>
                    <?php endif; ?>
                    
                    <div class="plan-img" style="background-image: url('<?= htmlspecialchars($plan['url_imagen']) ?>')">
                        <div class="likes-badge">❤️ <?= $plan['total_votos'] ?></div>
                        <div class="cat-emoji"><?= $emojis[$plan['categoria']] ?? '🎯' ?></div>
                    </div>
                    
                    <div class="plan-info">
                        <h3><?= htmlspecialchars($plan['nombre']) ?></h3>
                        
                        <div class="plan-price">
                            <span><?= $plan['precio'] > 0 ? number_format($plan['precio'], 2) . '€' : '¡Gratis!' ?></span>
                            <?php if (!empty($plan['hora_inicio']) && !empty($plan['hora_finalizacion'])): ?>
                                <span class="plan-duration">🕒 <?= date('H:i', strtotime($plan['hora_inicio'])) ?> - <?= date('H:i', strtotime($plan['hora_finalizacion'])) ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <p class="plan-desc"><?= htmlspecialchars($plan['descripcion']) ?></p>
                        
                        <p class="voters-list">
                            <?= $plan['total_votos'] > 0 ? "Apuntados: " . htmlspecialchars($plan['votantes']) : "Sin votos aún" ?>
                        </p>
                        
                        <div class="vote-section">
                            <form action="actividades.php" method="POST" style="flex-grow: 1; margin:0;">
                                <input type="hidden" name="id_actividad" value="<?= $plan['id_actividad'] ?>">
                                <button type="submit" name="votar_actividad" class="btn-vote <?= $plan['ha_votado'] ? 'active' : '' ?>">
                                    <?= $plan['ha_votado'] ? 'Confirmado' : 'Me apunto' ?>
                                </button>
                            </form>
                            
                            <?php if (!empty($plan['url_web']) && $plan['url_web'] !== '#'): ?>
                                <a href="<?= htmlspecialchars($plan['url_web']) ?>" target="_blank" class="btn-link">🔗 Info</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="summary-bar">
        <div class="summary-text">
            <b>TU PRESUPUESTO EN ACTIVIDADES</b><br>
            <small>(Suma de los planes a los que estás apuntado)</small>
        </div>
        <div class="summary-price"><?= number_format($presupuesto_personal, 2) ?>€</div>
    </div>

    <div class="add-plan-section">
        <h2 style="margin:0; color: var(--accent-gold);">➕ Proponer Actividad</h2>
        <p style="margin: 5px 0 0 0; opacity: 0.7;">Añade una foto, el link del local, el precio y el horario previsto.</p>
        
        <form action="actividades.php" method="POST" class="form-grid" id="form-nueva-actividad">
            <input type="hidden" name="accion" value="nueva_actividad">
            <input type="hidden" name="foto_base64" id="foto_base64" value="">

            <input type="text" id="p-name" name="nombre" placeholder="Nombre (ej: Clase de Surf)" required>
            
            <select id="p-cat" name="categoria">
                <option value="aventura">🥾 Aventura / Deporte</option>
                <option value="agua">🏄‍♂️ Actividad Acuática</option>
                <option value="juegos">🎲 Juegos / Escape Room</option>
                <option value="comida">🍷 Restaurante / Cata</option>
                <option value="fiesta">🎉 Fiesta / Local</option>
            </select>

            <input type="number" id="p-price" name="precio" placeholder="Precio por persona (€)" step="0.01">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <input type="time" id="p-inicio" name="hora_inicio" title="Hora de inicio" required>
                <input type="time" id="p-fin" name="hora_finalizacion" title="Hora de finalización" required>
            </div>

            <input type="text" id="p-desc" name="descripcion" placeholder="Detalles (Ej: Incluye traje)">
            <input type="text" id="p-url" name="url_web" placeholder="Link de la empresa (Opcional)">
            
            <div class="file-upload-wrapper">
                <input type="file" id="p-file" accept="image/*" title="Sube una foto del plan" onchange="convertirImagen()">
            </div>

            <button type="button" class="btn-add" onclick="enviarFormulario()">Añadir a Votación</button>
        </form>
    </div>
</div>

<script>
    function convertirImagen() {
        const archivo = document.getElementById('p-file').files[0];
        if (archivo) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('foto_base64').value = e.target.result;
            };
            reader.readAsDataURL(archivo);
        } else {
            document.getElementById('foto_base64').value = '';
        }
    }

    function enviarFormulario() {
        const nombre = document.getElementById('p-name').value;
        const inicio = document.getElementById('p-inicio').value;
        const fin = document.getElementById('p-fin').value;
        
        if (!nombre) {
            alert("El nombre de la actividad es obligatorio.");
            return;
        }

        if (!inicio || !fin) {
            alert("Por favor, selecciona una hora de inicio y una hora de finalización para organizar bien el día.");
            return;
        }
        
        document.getElementById('form-nueva-actividad').submit();
    }
</script>

</body>
</html>
