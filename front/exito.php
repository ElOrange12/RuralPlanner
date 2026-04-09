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
        /* =========================================
           PALETA RURAL Y VARIABLES
           ========================================= */
        :root {
            --forest-green: #2d5a27;
            --forest-light: #3a7533; 
            --wood-brown: #4b3621;
            --wood-light: #6e5033; 
            --black-matte: #1a1a1a;
            --cream-paper: #fdfbf7;
            --cream-dark: #e8e3d5; 
            --accent-gold: #c5a059;
            --accent-light: #e6c587; 
        }

        /* =========================================
           FONDO, TYPOGRAFÍA Y ESTRUCTURA BASE
           ========================================= */
        body {
            font-family: 'Nunito', 'Arial Rounded MT Bold', sans-serif;
            background: radial-gradient(circle at top right, var(--cream-paper) 0%, var(--cream-dark) 100%);
            color: var(--black-matte);
            margin: 0; padding: 0; width: 100vw; min-height: 100vh; overflow-x: hidden;
        }

        .feed-container { width: 100%; min-height: 100vh; padding: 3vw 5vw; box-sizing: border-box; display: flex; flex-direction: column; }

        h1 {
            text-align: center; color: var(--forest-green); text-transform: uppercase;
            letter-spacing: 2px; font-weight: 900; font-size: 2.5rem;
            border-bottom: 3px solid rgba(75, 54, 33, 0.2); padding-bottom: 15px;
            margin-top: 0; position: relative; text-shadow: 1px 1px 0px rgba(255,255,255,0.8);
        }

        .btn-logout {
            position: absolute; right: 0; top: 50%; transform: translateY(-50%);
            background: #e74c3c; color: white; padding: 8px 15px; border-radius: 10px;
            text-decoration: none; font-size: 1rem; font-weight: bold;
            box-shadow: 0 4px 0 #c0392b; transition: all 0.2s;
        }
        .btn-logout:hover { background: #c0392b; transform: translateY(-50%) scale(1.05); }
        .btn-logout:active { transform: translateY(calc(-50% + 4px)); box-shadow: 0 0 0 transparent; }

        /* =========================================
           ☕ PANTALLA DE CARGA (LOADER CAFÉ) ☕
           ========================================= */
        #page-loader {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: var(--cream-paper);
            z-index: 9999;
            display: flex; justify-content: center; align-items: center;
            transition: opacity 0.6s ease, visibility 0.6s ease;
        }

        .loader {
            width: 120px; height: 120px;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            position: relative;
            animation: shake 3s infinite ease-in-out;
        }

        .cup {
            position: relative;
            width: 50px; height: 40px;
            background-color: var(--wood-brown);
            border: 2px solid var(--deep-forest, #1a2e18);
            border-radius: 3px 3px 20px 20px;
            z-index: 2;
            animation: cupPulse 2s infinite ease-in-out;
        }

        .cup-handle {
            position: absolute; right: -14px; top: 5px;
            width: 12px; height: 20px;
            border: 3px solid var(--deep-forest, #1a2e18);
            border-left: none;
            border-radius: 0 15px 15px 0;
        }

        .smoke {
            position: absolute; bottom: 45px;
            width: 4px; height: 20px;
            background: rgba(100, 100, 100, 0.3);
            border-radius: 5px;
            animation: smokeRise 2s infinite ease-in-out;
            opacity: 0;
        }
        .smoke.one { left: 12px; animation-delay: 0s; }
        .smoke.two { left: 24px; animation-delay: 0.4s; }
        .smoke.three { left: 36px; animation-delay: 0.8s; }

        .load {
            margin-top: 20px; font-weight: 900;
            color: var(--wood-brown); letter-spacing: 2px; text-transform: uppercase;
            font-size: 0.9rem; animation: pulseText 1.5s infinite;
            text-align: center;
        }

        @keyframes smokeRise {
            0% { transform: translateY(0) scale(1); opacity: 0; }
            50% { opacity: 1; }
            100% { transform: translateY(-25px) scale(1.5); opacity: 0; filter: blur(2px); }
        }
        @keyframes cupPulse {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        @keyframes pulseText {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        @keyframes shake {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(2deg); }
            75% { transform: rotate(-2deg); }
        }

        /* =========================================
           EFECTO GLASSMORPHISM (CRISTAL)
           ========================================= */
        .card, .members-card, .calendar-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.4) 100%);
            backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.8); border-radius: 20px; padding: 30px;
            box-shadow: 0 20px 40px rgba(45, 90, 39, 0.08), inset 0 2px 0 rgba(255,255,255,0.5);
        }

        .ranking-card, .footer-budget {
            background: linear-gradient(135deg, rgba(26, 26, 26, 0.9) 0%, rgba(75, 54, 33, 0.8) 100%);
            backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(197, 160, 89, 0.2); color: white; border-radius: 25px;
            padding: 40px 30px; text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3), inset 0 2px 0 rgba(255,255,255,0.05);
        }

        .footer-budget { margin-top: 80px; margin-bottom: 20px; } /* Márgenes extra para separar */

        /* =========================================
           BOTONES 3D CON DEGRADADOS
           ========================================= */
        .btn-nav, .btn-choose, .btn-add {
            border: 1px solid rgba(255,255,255,0.2); border-radius: 20px; font-weight: 900;
            text-transform: uppercase; letter-spacing: 1px; text-align: center; text-decoration: none;
            cursor: pointer; transition: all 0.2s ease;
            box-shadow: 0 8px 0 #1e3d1a, 0 15px 20px rgba(45, 90, 39, 0.3), inset 0 2px 0 rgba(255,255,255,0.2);
        }

        .btn-nav, .btn-choose { background: linear-gradient(145deg, var(--forest-light), var(--forest-green)); color: white; padding: 25px; font-size: 1.2rem; text-shadow: 1px 1px 2px rgba(0,0,0,0.3); }
        .btn-nav.alt { background: linear-gradient(145deg, var(--wood-light), var(--wood-brown)); box-shadow: 0 8px 0 #2a1f12, 0 15px 20px rgba(75, 54, 33, 0.3), inset 0 2px 0 rgba(255,255,255,0.1); }
        .btn-nav.dark, .btn-add { background: linear-gradient(145deg, #333, var(--black-matte)); box-shadow: 0 8px 0 #000, 0 15px 20px rgba(0, 0, 0, 0.4), inset 0 2px 0 rgba(255,255,255,0.1); color: white; }
        .btn-nav.fech { background: linear-gradient(145deg, var(--accent-light), var(--accent-gold)); color: var(--wood-brown); box-shadow: 0 8px 0 #9c7b41, 0 15px 20px rgba(197, 160, 89, 0.4), inset 0 2px 0 rgba(255,255,255,0.4); text-shadow: 1px 1px 0px rgba(255,255,255,0.4); }

        .btn-nav:active, .btn-choose:active, .btn-add:active { transform: translateY(8px); box-shadow: 0 0 0 transparent, 0 5px 10px rgba(0, 0, 0, 0.3), inset 0 4px 5px rgba(0,0,0,0.2); }

        /* =========================================
           DISEÑO DE LOS BLOQUES DE CONTENIDO
           ========================================= */
        .top-section { display: grid; grid-template-columns: 2fr 1fr; gap: 40px; margin-top: 20px; flex-grow: 1; }

        .house-selector {
            position: relative; min-height: 400px; border-radius: 25px; overflow: hidden;
            border: 6px solid white; background-size: cover; background-position: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }

        .house-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(to bottom, rgba(45,90,39,0.1) 0%, rgba(26,26,26,0.8) 100%);
            display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center;
        }
        .house-overlay h2 { color: white; font-size: 2.8rem; text-shadow: 0px 4px 15px rgba(0,0,0,0.9); margin-bottom: 25px; }

        .member-input-group { display: flex; gap: 10px; margin-bottom: 20px; }
        input[type="text"] {
            flex: 1; padding: 15px; font-family: inherit; font-size: 1.1rem; background: rgba(255,255,255,0.6);
            border: 2px solid rgba(197, 160, 89, 0.4); border-radius: 15px; outline: none; transition: 0.3s;
        }
        input[type="text"]:focus { background: white; border-color: var(--accent-gold); box-shadow: 0 0 15px rgba(197, 160, 89, 0.2); }

        #list-members { list-style: none; padding: 0; margin: 0; flex-grow: 1; overflow-y: auto; }
        #list-members li {
            padding: 15px; font-size: 1.2rem; margin-bottom: 10px;
            background: linear-gradient(90deg, rgba(255,255,255,0.8) 0%, rgba(255,255,255,0.4) 100%);
            border: 1px solid rgba(255,255,255,0.9); border-radius: 12px; display: flex; justify-content: space-between; align-items: center;
        }

        .nav-buttons { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 25px; margin-top: 50px; }
        .budget-grid { display: flex; justify-content: space-around; align-items: center; margin-top: 15px; }
        .budget-grid > div { flex: 1; }
        .price-big {
            font-size: 4rem; font-weight: 900; background: linear-gradient(to right, var(--accent-light), var(--accent-gold));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; display: block; margin-top: 10px; filter: drop-shadow(0px 2px 4px rgba(0,0,0,0.5));
        }

        .hero {
            margin-top: 18px; border-radius: 26px; padding: clamp(22px, 4vw, 42px); color: #f8f4e9; border: 1px solid rgba(255,255,255,0.2);
            background: linear-gradient(110deg, rgba(27, 45, 28, 0.85), rgba(40, 66, 42, 0.75)), url("https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&w=1600&q=80") center/cover;
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
        }
        .hero h2 { margin: 0 0 10px; font-family: "Abril Fatface", serif, cursive; font-size: clamp(1.6rem, 4.5vw, 2.9rem); line-height: 1.08; }
        .hero p { margin: 0; max-width: 720px; font-size: 1.05rem; opacity: 0.95; }

        .grid.cols-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 40px auto 60px auto; max-width: 1200px; padding: 0 20px; }

        /* =========================================
           BOTONES SECUNDARIOS Y BOTÓN ROJO DE BORRAR
           ========================================= */
        .card h3 { font-size: 1.1rem; color: var(--wood-brown, #4b3621); font-weight: 900; text-transform: uppercase; margin-top: 0; margin-bottom: 10px; }
        .card .muted { font-size: 0.9rem; color: #666666; margin-top: 0; margin-bottom: 20px; flex-grow: 1; }
        .card .row { display: flex; gap: 10px; width: 100%; }

        .card button {
            font-family: inherit; padding: 12px 15px; border-radius: 12px; font-weight: 800; cursor: pointer; transition: all 0.2s ease; width: 100%; font-size: 0.9rem;
        }
        .card .btn-primary { background: linear-gradient(145deg, var(--forest-light), var(--forest-green)); color: white; border: none; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .card .btn-secondary { background: linear-gradient(145deg, var(--accent-light), var(--accent-gold)); color: var(--wood-brown); border: none; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }

        /* 🔥 EL NUEVO BOTÓN DE BORRAR BLANCO/ROJO 🔥 */
        .card .btn-danger { 
            background: #ffffff; 
            color: #e74c3c; 
            border: 2px solid #e74c3c; 
            box-shadow: 0 6px 0 #c0392b, 0 10px 15px rgba(231, 76, 60, 0.1);
        }
        .card .btn-danger:hover { 
            background: #e74c3c; 
            color: #ffffff; 
            transform: translateY(2px);
            box-shadow: 0 4px 0 #962d22, 0 8px 10px rgba(231, 76, 60, 0.3);
        }
        .card .btn-danger:active {
            transform: translateY(6px);
            box-shadow: 0 0 0 transparent;
        }

        /* =========================================
           ✨ ANIMACIONES Y MICRO-INTERACCIONES ✨
           ========================================= */
        ::-webkit-scrollbar { width: 12px; }
        ::-webkit-scrollbar-track { background: var(--cream-paper); }
        ::-webkit-scrollbar-thumb { background: var(--wood-brown); border-radius: 10px; border: 3px solid var(--cream-paper); }
        ::-webkit-scrollbar-thumb:hover { background: var(--forest-green); }

        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .top-section { animation: fadeInUp 0.6s ease-out forwards; }
        .nav-buttons { animation: fadeInUp 0.6s ease-out 0.2s forwards; opacity: 0; }
        .footer-budget { animation: fadeInUp 0.6s ease-out 0.4s forwards; opacity: 0; }
        .grid.cols-3 { animation: fadeInUp 0.6s ease-out 0.6s forwards; opacity: 0; }

        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-8px); } 100% { transform: translateY(0px); } }
        .house-selector { animation: float 6s ease-in-out infinite; }

        #list-members li { transition: all 0.3s ease; }
        #list-members li:hover {
            transform: translateX(10px);
            border-color: var(--accent-gold); background: white;
            box-shadow: 0 5px 15px rgba(197, 160, 89, 0.2);
        }

        /* RESPONSIVE MÓVIL */
        @media (max-width: 800px) {
            .top-section { grid-template-columns: 1fr; }
            .nav-buttons { grid-template-columns: 1fr 1fr; gap: 15px; } 
            .budget-grid { flex-direction: column; gap: 20px; }
            .budget-grid > div:nth-child(2) { border-left: none; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px; }
            .btn-nav { font-size: 1rem; padding: 15px; } 
            .btn-logout { position: static; display: block; width: fit-content; margin: 10px auto 0; transform: none; }
        }
</style>
</head>
<body>

    <div id="page-loader">
        <div class="loader">
            <div class="cup">
                <div class="cup-handle"></div>
                <div class="smoke one"></div>
                <div class="smoke two"></div>
                <div class="smoke three"></div>
            </div>
            <div class="load">Cargando Plan...</div>
        </div>
    </div>

<div class="feed-container">
    <h1>
        🌲 Plan Rural Amigos 🌲
        
        <?php if ($_SESSION['rol'] === 'admin'): ?>
            <a href="admin.php" style="position:absolute; left:0; top:50%; transform:translateY(-50%); background:var(--accent-gold); color:white; padding:8px 15px; border-radius:10px; text-decoration:none; font-size:1rem;">⚙️ Admin</a>
        <?php endif; ?>
        
        <a href="controladores/logout.php" class="btn-logout">Salir</a>
    </h1>

      <section class="hero">
      <h2>La aventura que vamos a recordar todo el año</h2>
      <p>Coordina asistentes, decide casa, reparte gastos y llena el plan de juegos y actividades sin volver loco al grupo de WhatsApp.</p>
    </section>

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
        <a href="fechas.php" class="btn-nav fech">📅 Fechas</a>
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

<section class="grid cols-3">
    <article class="card">
        <h3>Reto aleatorio del finde</h3>
        <p class="muted" id="challengeText">Pulsa para sacar un mini juego grupal.</p>
        <button class="btn-primary" id="challengeBtn">Sacar reto</button>
    </article>

    <article class="card">
        <h3>Informe del plan</h3>
        <p class="muted">Genera un informe compacto (1 página) o detallado para compartir.</p>
        <div class="row">
            <button class="btn-secondary" id="exportPdfCompactBtn">PDF compacto</button>
            <button class="btn-secondary" id="exportPdfDetailedBtn">PDF detallado</button>
        </div>
    </article>

    <article class="card">
        <h3>Reset rapido</h3>
        <p class="muted">Si queréis empezar de cero para otro viaje.</p>
        <button class="btn-danger" id="resetBtn">Borrar todo</button>
    </article>
</section>

<script>
    // --- 1. DATOS TRAÍDOS DESDE PHP ---
    const esAdmin = <?= json_encode($es_admin) ?>;
    const totalPresupuesto = <?= (float)$total_final_bd ?>;
    
    const datosPDF = {
        casaNombre: <?= json_encode($nombre_casa) ?>,
        precioCasa: <?= (float)$precio_casa ?>,
        transporte: <?= (float)$precio_transporte ?>,
        compra: <?= (float)$precio_compra ?>,
        actividades: <?= (float)$precio_actividades ?>,
        totalFinal: totalPresupuesto
    };

    let miembros = [];

    document.addEventListener("DOMContentLoaded", () => {
        cargarMiembros();
        calcularPresupuestoTotal();
    });

    // --- 2. GESTIÓN DE ASISTENTES ---
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
        if (event.key === 'Enter') { addMember(); }
    }

    function calcularPresupuestoTotal() {
        const numeroAmigos = miembros.length > 0 ? miembros.length : 1; 
        document.getElementById('total-price').innerText = totalPresupuesto.toFixed(2) + "€";
        const precioPorPersona = totalPresupuesto / numeroAmigos;
        document.getElementById('per-person').innerText = precioPorPersona.toFixed(2) + "€";
    }

    // --- 3. RETOS ALEATORIOS ---
    const challenges = [
        "Reto fogata: 2 mentiras 1 verdad hay que adivinar cual es la verdad.",
        "Reto cocina: cena de 3 ingredientes por equipos.",
        "Reto campo: gymkana express en 20 minutos.",
        "Reto foto: mejor foto del finde con votacion al volver.",
        "Reto juegos: torneo de cartas al mejor de 3 rondas.",
        "Reto coctelero: inventar la mezcla más rara con lo que quede en la nevera, el grupo cata y puntúa.",
        "Reto mímica: actuar anécdotas míticas del grupo sin usar palabras.",
        "Reto desconexión: móviles al centro de la mesa en la cena, el primero que lo mire friega los platos.",
        "Reto explorador: encontrar en el objeto mas random que puedas.",
        "Reto imitador: hablar y comportarse como otro miembro del grupo durante 5 minutos sin reírse.",
        "Reto musical: adivinar 5 canciones seguidas escuchando solo los primeros 3 segundos.",
        "Reto pasarela: el desfile con el pijama o el outfit de andar por casa más ridículo.",
        "Reto chef ciego: hacer un bocadillo con los ojos vendados mientras tu equipo te guía solo con la voz.",
        "Reto paparazzi: el que consiga la foto infraganti más graciosa del finde no paga la primera ronda.",
        "Reto confesionario: ronda rápida de 'Yo nunca', el que más beba elige la película o la música de hoy.",
        "Reto supervivencia: Aguanta toda la tarde sin usar el telefono, portatil, etc para nada, el perdedor debe dejar su movil desbloqueado, apps y todo a los otros durante 2 min.",
        "Reto acento: hablar con un acento inventado o extranjero durante la próxima media hora."
    ];

    document.getElementById("challengeBtn").addEventListener("click", () => {
        const text = challenges[Math.floor(Math.random() * challenges.length)];
        document.getElementById("challengeText").textContent = text;
    });

    // --- 4. RESET ---
    document.getElementById("resetBtn").addEventListener("click", () => {
        if (confirm("ATENCIÓN: Esto borrará la lista de amigos de tu navegador. Los gastos y casas están en la base de datos y solo los puede borrar el Admin. ¿Continuar?")) {
            localStorage.removeItem('miembrosViajeRural');
            miembros = [];
            renderizarLista(); 
        }
    });

    // --- 5. GENERACIÓN DEL PDF ---
    function exportPlanToPdf(mode) {
        const numAmigos = miembros.length > 0 ? miembros.length : 1;
        const tocamosA = (datosPDF.totalFinal / numAmigos).toFixed(2);
        const fecha = new Date().toLocaleDateString("es-ES");
        
        const html = `
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Informe Plan Rural</title>
            <style>
                body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #1a1a1a; padding: 20px; }
                .cover { background: #2d5a27; color: white; padding: 30px; border-radius: 15px; text-align: center; }
                .cover h1 { margin: 0; font-size: 28px; letter-spacing: 1px; }
                .summary { margin-top: 30px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                .box { border: 2px solid #f4f0e6; padding: 20px; border-radius: 12px; }
                h2 { color: #4b3621; border-bottom: 2px dashed #ccc; padding-bottom: 10px; font-size: 18px; }
                table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                th, td { border-bottom: 1px solid #eee; padding: 12px 5px; text-align: left; }
                th { color: #2d5a27; font-size: 14px; text-transform: uppercase; }
                .total-row { font-weight: bold; font-size: 18px; background: #fdfbf7; }
                .big-price { font-size: 24px; color: #c5a059; font-weight: bold; }
                .footer { text-align: center; margin-top: 50px; font-size: 12px; color: #888; }
            </style>
        </head>
        <body>
            <div class="cover">
                <h1>🌲 Informe Plan Rural Amigos</h1>
                <p>Generado el ${fecha}</p>
            </div>

            <div class="summary">
                <div class="box">
                    <h2>👥 Asistentes (${miembros.length})</h2>
                    <p style="font-size: 14px; line-height: 1.6;">${miembros.length > 0 ? miembros.join(', ') : 'Aún no se ha añadido a nadie.'}</p>
                </div>
                <div class="box" style="text-align: center;">
                    <h2>💰 A Escote</h2>
                    <p>Siendo ${numAmigos} personas, tocamos a:</p>
                    <div class="big-price">${tocamosA}€ / persona</div>
                </div>
            </div>

            <div class="box" style="margin-top: 20px;">
                <h2>📊 Desglose de Gastos</h2>
                <table>
                    <tr><th>Concepto</th><th>Importe Total</th></tr>
                    <tr><td>🏠 Casa elegida: <b>${datosPDF.casaNombre}</b></td><td>${datosPDF.precioCasa.toFixed(2)}€</td></tr>
                    <tr><td>🚗 Bote de Transporte</td><td>${datosPDF.transporte.toFixed(2)}€</td></tr>
                    <tr><td>🛒 Fondo de Comida/Bebida</td><td>${datosPDF.compra.toFixed(2)}€</td></tr>
                    <tr><td>🏹 Actividades Programadas</td><td>${datosPDF.actividades.toFixed(2)}€</td></tr>
                    <tr class="total-row"><td style="text-align: right;">PRESUPUESTO GLOBAL:</td><td>${datosPDF.totalFinal.toFixed(2)}€</td></tr>
                </table>
            </div>

            <div class="footer">
                Documento preparado automáticamente por el sistema Planner Rural.
            </div>
        </body>
        </html>`;

        printReportHtml(html, "Informe_Plan_Rural");
    }

    function printReportHtml(html, title) {
        const frame = document.createElement("iframe");
        frame.style.position = "fixed"; frame.style.right = "0"; frame.style.bottom = "0";
        frame.style.width = "0"; frame.style.height = "0"; frame.style.border = "0";
        document.body.appendChild(frame);

        const frameDoc = frame.contentWindow?.document;
        if (!frameDoc || !frame.contentWindow) {
            frame.remove();
            const tab = window.open("", "_blank");
            if (!tab) { alert("Ventana bloqueada. Permite los pop-ups para generar el PDF."); return; }
            tab.document.write(html); tab.document.title = title; tab.document.close();
            setTimeout(() => { tab.focus(); tab.print(); }, 700);
            return;
        }

        frameDoc.open(); frameDoc.write(html); frameDoc.close();
        setTimeout(() => {
            frame.contentWindow.focus();
            frame.contentWindow.print();
            setTimeout(() => frame.remove(), 1500);
        }, 700);
    }

    const exportPdfCompactBtn = document.getElementById("exportPdfCompactBtn");
    const exportPdfDetailedBtn = document.getElementById("exportPdfDetailedBtn");

    if (exportPdfCompactBtn) { exportPdfCompactBtn.addEventListener("click", () => exportPlanToPdf("compact")); }
    if (exportPdfDetailedBtn) { exportPdfDetailedBtn.addEventListener("click", () => exportPlanToPdf("detailed")); }

    // --- 6. OCULTAR PANTALLA DE CARGA AL ABRIR LA WEB ---
    window.addEventListener("load", () => {
        const loader = document.getElementById("page-loader");
        setTimeout(() => {
            loader.style.opacity = "0";
            loader.style.visibility = "hidden";
        }, 3000); // <-- ¡Aquí está la magia! 5000 milisegundos = 5 segundos
    });
</script>

</body>
</html>