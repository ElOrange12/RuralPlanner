<?php
// ¡El candado de seguridad! Si no hay sesión iniciada, lo echamos al login.
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$es_admin = ($_SESSION['rol'] === 'admin');

// Conectamos a la base de datos para extraer los totales reales
require_once 'inc/bd.php';

try {
    // 1. Buscamos la casa más votada y su foto
    $stmt_casa = $pdo->query("SELECT c.*, COUNT(v.id_casa) as votos FROM casas c LEFT JOIN votos_casas v ON c.id_casa = v.id_casa GROUP BY c.id_casa ORDER BY votos DESC LIMIT 1");
    $casa_top = $stmt_casa->fetch();
    
    $precio_casa = $casa_top ? (float)$casa_top['precio'] : 0;
    $img_casa = ($casa_top && !empty($casa_top['url_imagen'])) ? $casa_top['url_imagen'] : 'https://images.unsplash.com/photo-1449158743715-0a90ebb6d2d8?auto=format&fit=crop&w=800&q=80';
    $nombre_casa = $casa_top ? $casa_top['nombre'] : '¿A dónde vamos?';

    // 2. Extraemos el resto de gastos
    $precio_transporte = (float)($pdo->query("SELECT SUM(coste_total) FROM transporte")->fetchColumn() ?: 0);
    $precio_compra = (float)($pdo->query("SELECT SUM(precio_estimado) FROM lista_compra")->fetchColumn() ?: 0);
    
    // Sumar el precio de la actividad POR CADA persona que le ha dado a "Me apunto"
    $precio_actividades = (float)($pdo->query("
        SELECT SUM(a.precio) 
        FROM actividades a 
        JOIN votos_actividades v ON a.id_actividad = v.id_actividad
    ")->fetchColumn() ?: 0);

    // Suma total calculada desde el servidor
    $total_final_bd = $precio_casa + $precio_transporte + $precio_compra + $precio_actividades;

} catch (PDOException $e) {
    // Valores de seguridad por si algo falla
    $total_final_bd = 0;
    $img_casa = 'https://images.unsplash.com/photo-1449158743715-0a90ebb6d2d8?auto=format&fit=crop&w=800&q=80';
    $nombre_casa = 'Error al cargar';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan Rural Amigos - Pantalla Completa</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;900&display=swap" rel="stylesheet">
    
    <style>
        /* PALETA RURAL (TU CSS ORIGINAL INTACTO) */
        :root {
            --forest-green: #2d5a27;
            --wood-brown: #4b3621;
            --black-matte: #1a1a1a;
            --cream-paper: #fdfbf7;
            --accent-gold: #c5a059;
        }

        body {
            font-family: 'Nunito', 'Arial Rounded MT Bold', sans-serif;
            background-color: var(--cream-paper);
            color: var(--black-matte);
            margin: 0;
            padding: 0;
            width: 100vw;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .feed-container {
            width: 100%;
            min-height: 100vh;
            padding: 3vw 5vw;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
        }

        h1 {
            text-align: center;
            color: var(--forest-green);
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 900;
            font-size: 2.5rem;
            border-bottom: 3px solid var(--wood-brown);
            padding-bottom: 15px;
            margin-top: 0;
            position: relative;
        }

        .btn-logout {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            background: #e74c3c;
            color: white;
            padding: 8px 15px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 1rem;
            font-weight: bold;
        }
        .btn-logout:hover { background: #c0392b; }

        .top-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
            margin-top: 20px;
            flex-grow: 1;
        }

        .house-selector {
            position: relative;
            min-height: 400px;
            border-radius: 25px;
            overflow: hidden;
            border: 5px solid var(--wood-brown);
            /* LA IMAGEN AHORA LA PONE PHP AQUI ABAJO EN EL HTML, ESTE ES EL PLACEHOLDER */
            background-image: url('https://images.unsplash.com/photo-1449158743715-0a90ebb6d2d8');
            background-size: cover;
            background-position: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            transition: background-image 0.5s ease;
        }

        .house-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(to bottom, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.6) 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .house-overlay h2 {
            color: white;
            font-size: 2.5rem;
            text-shadow: 2px 2px 8px rgba(0,0,0,0.8);
            margin-bottom: 25px;
        }

        .btn-choose {
            background: var(--forest-green);
            color: white;
            padding: 15px 40px;
            font-size: 1.2rem;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 900;
            transition: 0.3s;
            border: 2px solid var(--cream-paper);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .btn-choose:hover {
            background: var(--wood-brown);
            transform: scale(1.05);
        }

        .members-card {
            background: #f4f0e6;
            padding: 30px;
            border-radius: 25px;
            border-left: 8px solid var(--wood-brown);
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
        }

        .members-card h3 {
            font-size: 1.8rem;
            margin-top: 0;
            color: var(--wood-brown);
        }

        .member-input-group {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        input[type="text"] {
            flex: 1;
            padding: 15px;
            font-family: inherit;
            font-size: 1.1rem;
            border: 2px solid var(--wood-brown);
            border-radius: 15px;
            outline: none;
        }

        input[type="text"]:focus {
            border-color: var(--forest-green);
        }

        .btn-add {
            background: var(--black-matte);
            color: white;
            border: none;
            padding: 0 20px;
            font-size: 1.5rem;
            cursor: pointer;
            border-radius: 15px;
            transition: 0.2s;
        }

        .btn-add:hover { background: var(--forest-green); }

        #list-members {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1;
            overflow-y: auto;
        }

        #list-members li {
            padding: 15px;
            font-size: 1.2rem;
            background: white;
            border-radius: 12px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .nav-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 25px;
            margin-top: 40px;
        }

        .btn-nav {
            background: var(--forest-green);
            color: white;
            padding: 25px;
            font-size: 1.3rem;
            text-align: center;
            text-decoration: none;
            border-radius: 20px;
            font-weight: 900;
            box-shadow: 0 6px 0 var(--wood-brown);
            transition: all 0.2s;
        }

        .btn-nav:active {
            transform: translateY(6px);
            box-shadow: none;
        }

        .btn-nav.alt { background: var(--wood-brown); box-shadow: 0 6px 0 #2a1f12; }
        .btn-nav.dark { background: var(--black-matte); box-shadow: 0 6px 0 #000; }

        .footer-budget {
            margin-top: 40px;
            background: var(--black-matte);
            color: var(--cream-paper);
            padding: 30px;
            border-radius: 25px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .budget-grid {
            display: flex;
            justify-content: space-around;
            align-items: center;
            margin-top: 15px;
        }

        .budget-grid > div { flex: 1; }

        .price-big {
            font-size: 3.5rem;
            font-weight: 900;
            color: var(--accent-gold);
            display: block;
            margin-top: 10px;
        }

        @media (max-width: 800px) {
            .top-section { grid-template-columns: 1fr; }
            .nav-buttons { grid-template-columns: 1fr; }
            .budget-grid { flex-direction: column; gap: 20px; }
            .budget-grid > div:nth-child(2) { border-left: none; border-top: 1px solid #444; padding-top: 20px; }
            .btn-logout { position: static; display: block; width: fit-content; margin: 10px auto 0; transform: none; }
        }
    </style>
</head>
<body>

<div class="feed-container">
    <h1>
        🌲 Plan Rural Amigos 🌲
        
        <?php if ($_SESSION['rol'] === 'admin'): ?>
            <a href="admin.php" style="position:absolute; left:0; top:50%; transform:translateY(-50%); background:var(--accent-gold); color:white; padding:8px 15px; border-radius:10px; text-decoration:none; font-size:1rem;">⚙️ Admin</a>
        <?php endif; ?>
        
        <a href="controladores/logout.php" class="btn-logout">Salir</a>
    </h1>

    <div class="top-section">
        <div class="house-selector" id="main-house-img" style="background-image: url('<?= $img_casa ?>');">
            <div class="house-overlay">
                <h2 id="main-house-title">🏆 <?= htmlspecialchars($nombre_casa) ?></h2>
                <a href="votaciones.php" class="btn-choose">Votar Casa</a>
            </div>
        </div>

        <div class="members-card">
            <h3>👥 Asistentes (<span id="count-members">0</span>)</h3>
            
            <?php if ($es_admin): ?>
                <div class="member-input-group">
                    <input type="text" id="nameInput" placeholder="Añade un amigo..." onkeypress="handleKeyPress(event)">
                    <button class="btn-add" onclick="addMember()">+</button>
                </div>
            <?php endif; ?>

            <ul id="list-members"></ul>
        </div>
    </div>

    <div class="nav-buttons">
        <a href="transporte.php" class="btn-nav">🚗 Transporte</a>
        <a href="compra.php" class="btn-nav alt">🛒 Compra</a>
        <a href="actividades.php" class="btn-nav dark">🏹 Actividades</a>
    </div>

    <div class="footer-budget">
        <h3 style="margin:0; font-size: 1.5rem; letter-spacing: 2px;">💰 RESUMEN DE GASTOS TOTAL</h3>
        <div class="budget-grid">
            <div>
                <span style="font-size: 1.2rem; opacity: 0.8;">PRESUPUESTO GENERAL</span>
                <span class="price-big" id="total-price">0.00€</span>
            </div>
            <div style="border-left: 2px solid #333;">
                <span style="font-size: 1.2rem; opacity: 0.8;">TOCAMOS A</span>
                <span class="price-big" id="per-person" style="color: white;">0.00€</span>
            </div>
        </div>
    </div>
</div>

<script>
    let miembros = [];
    
    // Le pasamos a JS si el usuario actual es admin o no
    const esAdmin = <?= json_encode($es_admin) ?>;
    
    // PHP inyecta el total calculado directamente de la base de datos
    const totalPresupuesto = <?= (float)$total_final_bd ?>;

    document.addEventListener("DOMContentLoaded", () => {
        cargarMiembros();
        calcularPresupuestoTotal();
    });

    function cargarMiembros() {
        const guardado = localStorage.getItem('miembrosViajeRural');
        if (guardado) {
            miembros = JSON.parse(guardado);
        }
        renderizarLista();
    }

    function guardarMiembros() {
        localStorage.setItem('miembrosViajeRural', JSON.stringify(miembros));
    }

    function renderizarLista() {
        const list = document.getElementById('list-members');
        list.innerHTML = ''; 
        
        miembros.forEach((nombre, index) => {
            const li = document.createElement('li');
            
            // Si es admin, pinta la X. Si no, solo el nombre.
            const botonEliminar = esAdmin ? `<span style="cursor:pointer; color:#e74c3c; font-weight:bold;" onclick="eliminarMiembro(${index})">×</span>` : '';
            
            li.innerHTML = `<span>${nombre}</span> ${botonEliminar}`;
            list.appendChild(li);
        });

        document.getElementById('count-members').innerText = miembros.length;
        calcularPresupuestoTotal();
    }

    function addMember() {
        const input = document.getElementById('nameInput');
        const nombre = input.value.trim();
        
        if (nombre !== "") {
            miembros.push(nombre); 
            guardarMiembros();     
            renderizarLista();     
            input.value = "";      
        }
    }

    function eliminarMiembro(index) {
        miembros.splice(index, 1); 
        guardarMiembros();         
        renderizarLista();         
    }

    function handleKeyPress(event) {
        if (event.key === 'Enter') {
            addMember();
        }
    }

    function calcularPresupuestoTotal() {
        const numeroAmigos = miembros.length > 0 ? miembros.length : 1; 

        // Pintamos el total traído de PHP
        document.getElementById('total-price').innerText = totalPresupuesto.toFixed(2) + "€";
        
        // Calculamos la división
        const precioPorPersona = totalPresupuesto / numeroAmigos;
        document.getElementById('per-person').innerText = precioPorPersona.toFixed(2) + "€";
    }
</script>

</body>
</html>
