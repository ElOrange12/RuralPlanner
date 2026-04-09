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

// 2. Procesar Nueva Casa
if (isset($_POST['accion']) && $_POST['accion'] === 'nueva_casa') {
    $nombre = trim($_POST['nombre'] ?? '');
    $precio = floatval($_POST['precio'] ?? 0);
    $url_web = trim($_POST['url_web'] ?? '');
    $foto_base64 = $_POST['foto_base64'] ?? '';

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
        /* =========================================
           PALETA RURAL Y VARIABLES
           ========================================= */
        :root {
            --forest-green: #2d5a27;
            --forest-dark: #163013;
            --forest-light: #3a7533; 
            --wood-brown: #4b3621;
            --black-matte: #1a1a1a;
            --cream-paper: #fdfbf7;
            --accent-gold: #c5a059;
            --accent-light: #e6c587; 
        }

        /* =========================================
           FONDO DE BOSQUE DEGRADADO
           ========================================= */
        body { 
            font-family: 'Nunito', sans-serif; 
            background: linear-gradient(135deg, var(--forest-green) 0%, var(--forest-dark) 100%);
            color: var(--cream-paper); 
            margin: 0; padding: 0; min-height: 100vh; overflow-x: hidden; 
        }

        .container { 
            width: 100%; max-width: 1200px; margin: 0 auto; 
            padding: 3vw 5vw; box-sizing: border-box; 
            display: flex; flex-direction: column; min-height: 100vh; 
        }

        header { 
            display: flex; justify-content: space-between; align-items: center; 
            border-bottom: 2px solid rgba(255, 255, 255, 0.1); 
            padding-bottom: 20px; margin-bottom: 30px; 
            animation: fadeInDown 0.6s ease-out forwards;
        }

        h1 { 
            margin: 0; font-weight: 900; text-transform: uppercase; 
            letter-spacing: 2px; color: var(--accent-gold); 
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }

        .btn-back { 
            background: linear-gradient(145deg, var(--accent-light), var(--accent-gold)); 
            color: var(--wood-brown); padding: 12px 25px; text-decoration: none; 
            border-radius: 20px; font-weight: 900; text-transform: uppercase;
            box-shadow: 0 6px 0 #9c7b41, 0 10px 15px rgba(0, 0, 0, 0.3); 
            transition: all 0.2s ease;
        }
        .btn-back:hover { transform: translateY(3px); box-shadow: 0 3px 0 #9c7b41; }
        .btn-back:active { transform: translateY(6px); box-shadow: 0 0 0 transparent; }

        /* =========================================
           GRID CASAS Y GLASSMORPHISM
           ========================================= */
        .houses-grid { 
            display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); 
            gap: 40px; margin-bottom: 60px; margin-top: 20px; 
        }

        .house-card { 
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
            backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2); 
            border-radius: 25px; overflow: hidden; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.3), inset 0 2px 0 rgba(255,255,255,0.1); 
            display: flex; flex-direction: column; position: relative; 
            transition: all 0.3s ease; animation: fadeInUp 0.6s ease-out forwards;
        }

        .house-card:hover { transform: translateY(-10px); box-shadow: 0 25px 50px rgba(0,0,0,0.5); border-color: var(--accent-gold); }

        .house-img { width: 100%; height: 250px; background-size: cover; background-position: center; position: relative; border-bottom: 1px solid rgba(255,255,255,0.1); }

        .likes-badge { 
            position: absolute; top: 15px; right: 15px; 
            background: linear-gradient(145deg, var(--accent-light), var(--accent-gold));
            color: var(--wood-brown); padding: 8px 18px; border-radius: 50px; 
            font-weight: 900; font-size: 1.1rem; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.5); border: 2px solid white; 
        }

        .delete-house { 
            position: absolute; top: 15px; left: 15px; 
            background: #ffffff; color: #e74c3c; 
            padding: 8px 15px; border-radius: 12px; font-weight: 900; 
            cursor: pointer; border: 2px solid #e74c3c; 
            box-shadow: 0 4px 0 #c0392b; transition: 0.2s; z-index: 10; 
        }
        .delete-house:hover { background: #e74c3c; color: white; transform: translateY(2px); box-shadow: 0 2px 0 #962d22; }
        .delete-house:active { transform: translateY(6px); box-shadow: 0 0 0 transparent; }

        .house-info { padding: 30px; flex-grow: 1; display: flex; flex-direction: column; }
        .house-info h3 { margin: 0 0 5px 0; font-size: 1.6rem; color: white; font-weight: 900; }
        .house-price { font-size: 2rem; font-weight: 900; color: var(--accent-gold); margin-bottom: 15px; text-shadow: 1px 1px 0px rgba(0,0,0,0.5); }
        .voters-list { font-size: 0.95rem; color: #ccc; margin-bottom: 25px; flex-grow: 1; font-style: italic; }
        
        .vote-section { display: flex; justify-content: space-between; align-items: center; margin-top: auto; gap: 10px; }
        
        .btn-vote { 
            flex-grow: 1; background: rgba(0,0,0,0.3); border: 2px solid var(--accent-gold); 
            color: var(--accent-gold); padding: 15px 10px; border-radius: 15px; 
            cursor: pointer; font-weight: 900; font-size: 1.1rem; transition: 0.2s; 
            text-transform: uppercase; box-shadow: 0 4px 0 #9c7b41;
        }
        .btn-vote:hover { transform: translateY(2px); box-shadow: 0 2px 0 #9c7b41; background: rgba(197, 160, 89, 0.1); }
        .btn-vote:active { transform: translateY(4px); box-shadow: 0 0 0 transparent; }
        
        .btn-vote.active { 
            background: linear-gradient(145deg, var(--accent-light), var(--accent-gold)); 
            color: var(--wood-brown); border: none; box-shadow: 0 6px 0 #9c7b41, 0 10px 15px rgba(0,0,0,0.3);
        }
        .btn-vote.active:hover { transform: translateY(3px); box-shadow: 0 3px 0 #9c7b41; }
        .btn-vote.active:active { transform: translateY(6px); box-shadow: 0 0 0 transparent; }

        .btn-link { 
            padding: 15px; background: rgba(255,255,255,0.1); color: white; text-decoration: none; 
            border-radius: 15px; font-weight: 900; text-transform: uppercase;
            box-shadow: 0 4px 0 rgba(0,0,0,0.5); transition: 0.2s; border: 1px solid rgba(255,255,255,0.2);
        }
        .btn-link:active { transform: translateY(4px); box-shadow: 0 0 0 transparent; }

        /* =========================================
           FORMULARIO AÑADIR CASA 
           ========================================= */
        .add-house-section { 
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.6) 0%, rgba(0, 0, 0, 0.4) 100%);
            backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px);
            padding: 40px; border-radius: 25px; border: 1px solid rgba(255, 255, 255, 0.1); 
            margin-top: auto; color: white; box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            animation: fadeInUp 0.8s ease-out forwards;
        }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-top: 25px; }
        .form-grid input { 
            padding: 15px 20px; border-radius: 15px; border: 2px solid rgba(255,255,255,0.1); 
            font-family: inherit; background: rgba(255,255,255,0.05); color: white; font-size: 1rem; transition: 0.3s;
        }
        .form-grid input::placeholder { color: rgba(255,255,255,0.5); }
        .form-grid input:focus { background: rgba(255,255,255,0.1); border-color: var(--accent-gold); outline: none; box-shadow: 0 0 15px rgba(197, 160, 89, 0.2); }
        
       /* 1. Caja contenedora (Sincronizada con los otros inputs) */
        .file-upload-wrapper { 
            background: rgba(255, 255, 255, 0.05); 
            border-radius: 15px; 
            display: flex; 
            align-items: center; 
            padding: 0 10px; 
            border: 2px solid rgba(255, 255, 255, 0.1); 
            transition: 0.3s;
            height: 54px; /* Altura fija para que mida igual que los demás inputs */
            box-sizing: border-box;
            overflow: hidden; /* Evita que el botón se salga */
        }

        .file-upload-wrapper:hover { 
            border-color: var(--accent-gold); 
            background: rgba(255, 255, 255, 0.1);
        }

        /* 2. El input real (invisible pero clickeable) */
        .file-upload-wrapper input[type="file"] { 
            background: transparent; 
            width: 100%; 
            color: rgba(255, 255, 255, 0.6); 
            border: none; 
            cursor: pointer;
            font-size: 0.85rem; /* Texto de 'Ningún archivo' más pequeño */
        }

        /* 3. 🔥 EL BOTÓN "HACKEADO" (Más pequeño y estético) */
        .file-upload-wrapper input[type="file"]::file-selector-button {
            background: linear-gradient(145deg, var(--wood-light), var(--wood-brown));
            color: white;
            border: none;
            padding: 8px 12px; /* Más compacto para que no choque arriba/abajo */
            border-radius: 10px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.75rem; /* Texto pequeño para que no se vea 'raro' */
            cursor: pointer;
            margin-right: 10px;
            box-shadow: 0 3px 0 #2a1f12; /* Sombra 3D más sutil */
            transition: all 0.2s ease;
            font-family: 'Nunito', sans-serif;
        }
        
        /* Efecto Hover: Dorado */
        .file-upload-wrapper input[type="file"]::file-selector-button:hover {
            background: linear-gradient(145deg, var(--accent-light), var(--accent-gold));
            color: var(--wood-brown);
            box-shadow: 0 2px 0 #9c7b41;
            transform: translateY(1px);
        }
        
        /* Efecto Click: Se hunde */
        .file-upload-wrapper input[type="file"]::file-selector-button:active {
            transform: translateY(3px);
            box-shadow: 0 0 0 transparent;
        }
        
        /* 🔥 EL BOTÓN ALARGADO Y CENTRADO 🔥 */
        .btn-add { 
            background: linear-gradient(145deg, var(--accent-light), var(--accent-gold)); 
            color: var(--wood-brown); border: none; padding: 15px; border-radius: 15px; 
            font-weight: 900; font-size: 1.1rem; text-transform: uppercase; letter-spacing: 1px; cursor: pointer; transition: 0.2s;
            box-shadow: 0 6px 0 #9c7b41, 0 10px 15px rgba(0,0,0,0.3); 
            
            /* Nuevas propiedades para centrar y alargar */
            grid-column: 1 / -1; 
            justify-self: center; 
            width: 100%; 
            max-width: 450px; 
            margin-top: 15px;
        }
        .btn-add:hover { transform: translateY(3px); box-shadow: 0 3px 0 #9c7b41; }
        .btn-add:active { transform: translateY(6px); box-shadow: 0 0 0 transparent; }

        /* =========================================
           ANIMACIÓN DEL CAMPAMENTO (Empty State)
           ========================================= */
        .empty-state {
            grid-column: 1/-1;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 20px 0 60px 0; text-align: center;
        }

        @keyframes stageBackground { 0%, 10%, 90%, 100% { background-color: #00B6BB; } 25%, 75% { background-color: #0094bd; } }
        @keyframes earthRotation { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        @keyframes sunrise { 0%, 10%, 90%, 100% { box-shadow: 0 0 0 25px #5ad6bd, 0 0 0 40px #4acead, 0 0 0 60px rgba(74, 206, 173, 0.6), 0 0 0 90px rgba(74, 206, 173, 0.3); } 25%, 75% { box-shadow: 0 0 0 0 #5ad6bd, 0 0 0 0 #4acead, 0 0 0 0 rgba(74, 206, 173, 0.6), 0 0 0 0 rgba(74, 206, 173, 0.3); } }
        @keyframes moonOrbit { 25% { transform: rotate(-60deg); } 50% { transform: rotate(-60deg); } 75% { transform: rotate(-120deg); } 0%, 100% { transform: rotate(-180deg); } }
        @keyframes nightTime { 0%, 90% { opacity: 0; } 50%, 75% { opacity: 1; } }
        @keyframes hotPan { 0%, 90% { background-color: #74667e; } 50%, 75% { background-color: #b2241c; } }
        @keyframes heat { 0%, 90% { box-shadow: inset 0 0 0 0 rgba(255, 255, 255, 0.3); } 50%, 75% { box-shadow: inset 0 -2px 0 0 white; } }
        @keyframes smoke { 0%, 50%, 90%, 100% { opacity: 0; } 50%, 75% { opacity: 0.7; } }
        @keyframes fire { 0%, 90%, 100% { opacity: 0; } 50%, 75% { opacity: 1; } }
        @keyframes treeShake { 0% { transform: rotate(0deg); } 25% { transform: rotate(-2deg); } 40% { transform: rotate(4deg); } 50% { transform: rotate(-4deg); } 60% { transform: rotate(6deg); } 75% { transform: rotate(-6deg); } 100% { transform: rotate(0deg); } }
        @keyframes fireParticles { 0% { height: 30%; opacity: 1; top: 75%; } 25% { height: 25%; opacity: 0.8; top: 40%; } 50% { height: 15%; opacity: 0.6; top: 20%; } 75% { height: 10%; opacity: 0.3; top: 0; } 100% { opacity: 0; } }
        @keyframes fireLines { 0%, 25%, 75%, 100% { bottom: 0; } 50% { bottom: 5%; } }

        .scene { display: flex; margin: 0 auto; justify-content: center; align-items: flex-end; width: 400px; height: 300px; position: relative; }
        .forest { display: flex; width: 75%; height: 90%; position: relative; }
        .tree { display: block; width: 50%; position: absolute; bottom: 0; opacity: 0.4; }
        .tree .branch { width: 80%; height: 0; margin: 0 auto; padding-left: 40%; padding-bottom: 50%; overflow: hidden; }
        .tree .branch:before { content: ""; display: block; width: 0; height: 0; margin-left: -600px; border-left: 600px solid transparent; border-right: 600px solid transparent; border-bottom: 950px solid #000; }
        .tree .branch.branch-top { transform-origin: 50% 100%; animation: treeShake 0.5s linear infinite; }
        .tree .branch.branch-middle { width: 90%; padding-left: 45%; padding-bottom: 65%; margin: 0 auto; margin-top: -25%; }
        .tree .branch.branch-bottom { width: 100%; padding-left: 50%; padding-bottom: 80%; margin: 0 auto; margin-top: -40%; }

        .tree1 { width: 31%; } .tree1 .branch-top { transition-delay: 0.3s; }
        .tree2 { width: 39%; left: 9%; } .tree2 .branch-top { transition-delay: 0.4s; }
        .tree3 { width: 32%; left: 24%; } .tree3 .branch-top { transition-delay: 0.5s; }
        .tree4 { width: 37%; left: 34%; } .tree4 .branch-top { transition-delay: 0.6s; }
        .tree5 { width: 44%; left: 44%; } .tree5 .branch-top { transition-delay: 0.7s; }
        .tree6 { width: 34%; left: 61%; } .tree6 .branch-top { transition-delay: 0.2s; }
        .tree7 { width: 24%; left: 76%; } .tree7 .branch-top { transition-delay: 0.1s; }

        .tent { width: 60%; height: 25%; position: absolute; bottom: -0.5%; right: 15%; z-index: 1; text-align: right; }
        .roof { display: inline-block; width: 45%; height: 100%; margin-right: 10%; position: relative; z-index: 1; border-top: 4px solid #4D4454; border-right: 4px solid #4D4454; border-left: 4px solid #4D4454; border-top-right-radius: 6px; transform: skew(30deg); box-shadow: inset -3px 3px 0px 0px #F7B563; background: #f6d484; }
        .roof:before { content: ""; width: 70%; height: 70%; position: absolute; top: 15%; left: 15%; z-index: 0; border-radius: 10%; background-color: #E78C20; }
        .roof:after { content: ""; height: 75%; width: 100%; position: absolute; bottom: 0; right: 0; z-index: 1; background: linear-gradient(to bottom, rgba(231, 140, 32, 0.4) 0%, rgba(231, 140, 32, 0.4) 64%, rgba(231, 140, 32, 0.8) 65%, rgba(231, 140, 32, 0.8) 100%); }
        .roof-border-left { display: flex; justify-content: space-between; flex-direction: column; width: 1%; height: 125%; position: absolute; top: 0; left: 35.7%; z-index: 1; transform-origin: 50% 0%; transform: rotate(35deg); }
        .roof-border-left .roof-border { display: block; width: 100%; border-radius: 2px; border: 2px solid #4D4454; }
        .roof-border-left .roof-border1 { height: 40%; } .roof-border-left .roof-border2 { height: 10%; } .roof-border-left .roof-border3 { height: 40%; }
        .door { width: 55px; height: 92px; position: absolute; bottom: 2%; overflow: hidden; z-index: 0; transform-origin: 0 105%; }
        .left-door { transform: rotate(35deg); position: absolute; left: 13.5%; bottom: -3%; z-index: 0; }
        .left-door .left-door-inner { width: 100%; height: 100%; transform-origin: 0 105%; transform: rotate(-35deg); position: absolute; top: 0; overflow: hidden; background-color: #EDDDC2; }
        .left-door .left-door-inner:before { content: ""; width: 15%; height: 100%; position: absolute; top: 0; right: 0; background: repeating-linear-gradient(#D4BC8B, #D4BC8B 4%, #E0D2A8 5%, #E0D2A8 10%); }
        .left-door .left-door-inner:after { content: ""; width: 50%; height: 100%; position: absolute; top: 15%; left: 10%; transform: rotate(25deg); background-color: #fff; }
        .right-door { height: 89px; right: 21%; transform-origin: 0 105%; transform: rotate(-30deg) scaleX(-1); position: absolute; bottom: -3%; z-index: 0; }
        .right-door .right-door-inner { width: 100%; height: 100%; transform-origin: 0 120%; transform: rotate(-30deg); position: absolute; bottom: 0px; overflow: hidden; background-color: #EFE7CF; }
        .right-door .right-door-inner:before { content: ""; width: 50%; height: 100%; position: absolute; top: 15%; right: -28%; z-index: 1; transform: rotate(15deg); background-color: #524A5A; }
        .right-door .right-door-inner:after { content: ""; width: 50%; height: 100%; position: absolute; top: 15%; right: -20%; transform: rotate(20deg); background-color: #fff; }

        .floor { width: 80%; position: absolute; right: 10%; bottom: 0; z-index: 1; }
        .floor .ground { position: absolute; border-radius: 2px; border: 2px solid #4D4454; }
        .floor .ground.ground1 { width: 65%; left: 0; } .floor .ground.ground2 { width: 30%; right: 0; }

        .fireplace { display: block; width: 24%; height: 20%; position: absolute; left: 5%; }
        .fireplace:before { content: ""; display: block; width: 8%; position: absolute; bottom: -4px; left: 2%; border-radius: 2px; border: 2px solid #4D4454; background: #4D4454; }
        .fireplace .support { display: block; height: 105%; width: 2px; position: absolute; bottom: -5%; left: 10%; border: 2px solid #4D4454; }
        .fireplace .support:before { content: ""; width: 100%; height: 15%; position: absolute; top: -18%; left: -4px; border-radius: 2px; border: 2px solid #4D4454; transform-origin: 100% 100%; transform: rotate(45deg); }
        .fireplace .support:after { content: ""; width: 100%; height: 15%; position: absolute; top: -18%; left: 0px; border-radius: 2px; border: 2px solid #4D4454; transform-origin: 0 100%; transform: rotate(-45deg); }
        .fireplace .support:nth-child(1) { left: 85%; }
        .fireplace .bar { width: 100%; height: 2px; border-radius: 2px; border: 2px solid #4D4454; }
        .fireplace .hanger { display: block; width: 2px; height: 25%; margin-left: -4px; position: absolute; left: 50%; border: 2px solid #4D4454; }
        .fireplace .pan { display: block; width: 25%; height: 50%; border-radius: 50%; border: 4px solid #4D4454; position: absolute; top: 25%; left: 35%; overflow: hidden; animation: heat 5s linear infinite; }
        .fireplace .pan:before { content: ""; display: block; height: 53%; width: 100%; position: absolute; bottom: 0; z-index: -1; border-top: 4px solid #4D4454; background-color: #74667e; animation: hotPan 5s linear infinite; }
        .fireplace .smoke { display: block; width: 20%; height: 25%; position: absolute; top: 25%; left: 37%; background-color: white; filter: blur(5px); animation: smoke 5s linear infinite; }
        .fireplace .fire { display: block; width: 25%; height: 120%; position: absolute; bottom: 0; left: 33%; z-index: 1; animation: fire 5s linear infinite; }
        .fireplace .fire:before { content: ""; display: block; width: 100%; height: 2px; position: absolute; bottom: -4px; z-index: 1; border-radius: 2px; border: 1px solid #efb54a; background-color: #efb54a; }
        .fireplace .fire .line { display: block; width: 2px; height: 100%; position: absolute; bottom: 0; animation: fireLines 1s linear infinite; }
        .fireplace .fire .line2 { left: 50%; margin-left: -1px; animation-delay: 0.3s; } .fireplace .fire .line3 { right: 0; animation-delay: 0.5s; }
        .fireplace .fire .line .particle { height: 10%; position: absolute; top: 100%; z-index: 1; border-radius: 2px; border: 2px solid #efb54a; animation: fireParticles 0.5s linear infinite; }
        .fireplace .fire .line .particle1 { animation-delay: 0.1s; } .fireplace .fire .line .particle2 { animation-delay: 0.3s; }
        .fireplace .fire .line .particle3 { animation-delay: 0.6s; } .fireplace .fire .line .particle4 { animation-delay: 0.9s; }

        .time-wrapper { display: block; width: 100%; height: 100%; position: absolute; overflow: hidden; }
        .time { display: block; width: 100%; height: 200%; position: absolute; transform-origin: 50% 50%; transform: rotate(270deg); animation: earthRotation 5s linear infinite; }
        .time .day { display: block; width: 20px; height: 20px; position: absolute; top: 20%; left: 40%; border-radius: 50%; box-shadow: 0 0 0 25px #5ad6bd, 0 0 0 40px #4acead, 0 0 0 60px rgba(74, 206, 173, 0.6), 0 0 0 90px rgba(74, 206, 173, 0.3); animation: sunrise 5s ease-in-out infinite; background-color: #ef9431; }
        .time .night { animation: nightTime 5s ease-in-out infinite; }
        .time .night .star { display: block; width: 4px; height: 4px; position: absolute; bottom: 10%; border-radius: 50%; background-color: #fff; }
        .time .night .star-big { width: 6px; height: 6px; }
        .time .night .star1 { right: 23%; bottom: 25%; } .time .night .star2 { right: 35%; bottom: 18%; }
        .time .night .star3 { right: 47%; bottom: 25%; } .time .night .star4 { right: 22%; bottom: 20%; }
        .time .night .star5 { right: 18%; bottom: 30%; } .time .night .star6 { right: 60%; bottom: 20%; } .time .night .star7 { right: 70%; bottom: 23%; }
        .time .night .moon { display: block; width: 25px; height: 25px; position: absolute; bottom: 22%; right: 33%; border-radius: 50%; transform: rotate(-60deg); box-shadow: 9px 9px 3px 0 white; filter: blur(1px); animation: moonOrbit 5s ease-in-out infinite; }
        .time .night .moon:before { content: ""; display: block; width: 100%; height: 100%; position: absolute; bottom: -9px; left: 9px; border-radius: 50%; box-shadow: 0 0 0 5px rgba(255, 255, 255, 0.05), 0 0 0 15px rgba(255, 255, 255, 0.05), 0 0 0 25px rgba(255, 255, 255, 0.05), 0 0 0 35px rgba(255, 255, 255, 0.05); background-color: rgba(255, 255, 255, 0.2); }

        @keyframes fadeInUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeInDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        ::-webkit-scrollbar { width: 12px; } ::-webkit-scrollbar-track { background: var(--forest-dark); } ::-webkit-scrollbar-thumb { background: var(--accent-gold); border-radius: 10px; border: 3px solid var(--forest-dark); }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>🏆 Votación de Casas</h1>
        <a href="exito.php" class="btn-back">⬅ Volver al Plan</a>
    </header>

    <div class="houses-grid">
        <?php if (count($casas) === 0): ?>
            <div class="empty-state">
                <div class="scene">
                  <div class="forest">
                    <div class="tree tree1"><div class="branch branch-top"></div><div class="branch branch-middle"></div></div>
                    <div class="tree tree2"><div class="branch branch-top"></div><div class="branch branch-middle"></div><div class="branch branch-bottom"></div></div>
                    <div class="tree tree3"><div class="branch branch-top"></div><div class="branch branch-middle"></div><div class="branch branch-bottom"></div></div>
                    <div class="tree tree4"><div class="branch branch-top"></div><div class="branch branch-middle"></div><div class="branch branch-bottom"></div></div>
                    <div class="tree tree5"><div class="branch branch-top"></div><div class="branch branch-middle"></div><div class="branch branch-bottom"></div></div>
                    <div class="tree tree6"><div class="branch branch-top"></div><div class="branch branch-middle"></div><div class="branch branch-bottom"></div></div>
                    <div class="tree tree7"><div class="branch branch-top"></div><div class="branch branch-middle"></div><div class="branch branch-bottom"></div></div>
                  </div>
                  
                  <div class="tent">
                      <div class="roof"></div>
                      <div class="roof-border-left"><div class="roof-border roof-border1"></div><div class="roof-border roof-border2"></div><div class="roof-border roof-border3"></div></div>
                      <div class="entrance">
                        <div class="door left-door"><div class="left-door-inner"></div></div>
                        <div class="door right-door"><div class="right-door-inner"></div></div>
                      </div>
                    </div>

                  <div class="floor"><div class="ground ground1"></div><div class="ground ground2"></div></div>
                  
                  <div class="fireplace">
                    <div class="support"></div><div class="support"></div><div class="bar"></div><div class="hanger"></div><div class="smoke"></div><div class="pan"></div>
                    <div class="fire">
                      <div class="line line1"><div class="particle particle1"></div><div class="particle particle2"></div><div class="particle particle3"></div><div class="particle particle4"></div></div>
                      <div class="line line2"><div class="particle particle1"></div><div class="particle particle2"></div><div class="particle particle3"></div><div class="particle particle4"></div></div>
                      <div class="line line3"><div class="particle particle1"></div><div class="particle particle2"></div><div class="particle particle3"></div><div class="particle particle4"></div></div>
                    </div>
                  </div>
                  
                  <div class="time-wrapper">
                    <div class="time">
                      <div class="day"></div>
                      <div class="night">
                        <div class="moon"></div>
                        <div class="star star1 star-big"></div><div class="star star2 star-big"></div><div class="star star3 star-big"></div>
                        <div class="star star4"></div><div class="star star5"></div><div class="star star6"></div><div class="star star7"></div>
                      </div>
                    </div>
                  </div>
                </div>

                <h2 style="color: var(--accent-gold); margin-top: 20px; font-weight: 900; font-size: 2rem; letter-spacing: 2px;">Esperando casas...</h2>
                <p style="opacity: 0.8; font-size: 1.1rem;">Nadie ha propuesto ninguna casa aún. ¡Sé el primero usando el formulario de abajo!</p>
            </div>
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
                            <?= $casa['total_votos'] > 0 ? "Votado por: " . htmlspecialchars($casa['votantes']) : "Aún nadie ha votado por esta opción." ?>
                        </p>
                        
                        <div class="vote-section">
                            <form action="votaciones.php" method="POST" style="flex-grow: 1; margin: 0;">
                                <input type="hidden" name="id_casa" value="<?= $casa['id_casa'] ?>">
                                <button type="submit" name="votar_casa" class="btn-vote <?= $casa['ha_votado'] ? 'active' : '' ?>">
                                    <?= $casa['ha_votado'] ? 'Diste Like' : 'Votar Casa' ?>
                                </button>
                            </form>
                            
                            <?php if (!empty($casa['url_web']) && $casa['url_web'] !== '#'): ?>
                                <a href="<?= htmlspecialchars($casa['url_web']) ?>" target="_blank" class="btn-link">🔗 Info</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="add-house-section">
        <h2 style="margin:0; color: var(--accent-gold); font-size: 1.8rem; font-weight: 900;">➕ Añadir Nueva Casa Candidata</h2>
        <p style="margin: 5px 0 0 0; opacity: 0.8; font-size: 0.95rem;">Busca en Airbnb o Booking y súbela para que el grupo opine. (Las fotos muy pesadas pueden dar error, usa capturas).</p>
        
        <form action="votaciones.php" method="POST" class="form-grid" id="form-nueva-casa">
            <input type="hidden" name="accion" value="nueva_casa">
            <input type="hidden" name="foto_base64" id="foto_base64" value="">

            <input type="text" id="h-name" name="nombre" placeholder="Nombre (ej: Villa Bosque)" required>
            <input type="number" id="h-price" name="precio" placeholder="Precio Total de la casa (€)" required step="0.01">
            
            <div class="file-upload-wrapper">
                <input type="file" id="h-file" accept="image/*" onchange="convertirImagen()">
            </div>

            <input type="text" id="h-url" name="url_web" placeholder="Link web (Opcional)">
            
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
            alert("El nombre y el precio son obligatorios para añadir la casa.");
            return;
        }
        
        // Enviamos el formulario. PHP hará el INSERT y recargará la página.
        document.getElementById('form-nueva-casa').submit();
    }
</script>

</body>
</html>