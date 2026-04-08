<?php
// Arrancamos sesión y ponemos el candado
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Guardamos los datos de la sesión en variables de PHP para usarlas luego
$nombre_usuario = $_SESSION['nombre'];
$es_admin = ($_SESSION['rol'] === 'admin');
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
            margin: 0;
            padding: 0;
            width: 100vw;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 3vw 5vw;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--forest-green);
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        h1 { margin: 0; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; color: var(--accent-gold); }

        .btn-back {
            background: var(--forest-green);
            color: white;
            padding: 10px 25px;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 700;
            transition: 0.3s;
        }
        .btn-back:hover { background: var(--dark-wood); }

        /* GRID DE CASAS */
        .houses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 40px;
            margin-bottom: 60px;
            margin-top: 20px;
        }

        .house-card {
            background: var(--card-bg);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 30px rgba(0,0,0,0.4);
            transition: 0.3s;
            border: 1px solid rgba(255,255,255,0.05);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .house-card:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(0,0,0,0.6); }

        .house-img {
            width: 100%;
            height: 250px;
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .likes-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            padding: 8px 15px;
            border-radius: 50px;
            font-weight: 900;
            font-size: 1.1rem;
            color: white;
            display: flex;
            align-items: center;
            gap: 5px;
            border: 1px solid rgba(255,255,255,0.2);
        }

        /* BOTÓN DE BORRAR */
        .delete-house { 
            position: absolute; 
            top: 15px; 
            left: 15px; 
            background: rgba(255, 60, 60, 0.9); 
            color: white; 
            padding: 8px 15px; 
            border-radius: 8px; 
            font-weight: bold;
            font-size: 0.9rem; 
            cursor: pointer; 
            border: 2px solid rgba(255,255,255,0.5); 
            transition: 0.2s;
            z-index: 10;
        }
        .delete-house:hover { background: red; transform: scale(1.05); }

        .house-info { padding: 25px; flex-grow: 1; display: flex; flex-direction: column; }
        .house-info h3 { margin: 0 0 5px 0; font-size: 1.6rem; color: var(--cream-paper); }
        .house-price { font-size: 1.8rem; font-weight: 900; color: var(--accent-gold); margin-bottom: 15px; }
        .voters-list { font-size: 0.9rem; opacity: 0.6; margin-bottom: 20px; flex-grow: 1; }

        .vote-section { display: flex; justify-content: space-between; align-items: center; margin-top: auto; }

        .btn-vote {
            flex-grow: 1;
            background: transparent;
            border: 2px solid var(--accent-gold);
            color: var(--accent-gold);
            padding: 12px 20px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 900;
            font-size: 1.1rem;
            transition: 0.2s;
            text-align: center;
        }
        .btn-vote.active { background: var(--accent-gold); color: var(--dark-wood); }
        
        .btn-link {
            margin-left: 10px;
            padding: 12px;
            background: var(--dark-wood);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            border: 1px solid #444;
        }

        /* FORMULARIO PARA AÑADIR CASA */
        .add-house-section {
            background: var(--dark-wood);
            padding: 30px;
            border-radius: 25px;
            border: 1px dashed var(--accent-gold);
            margin-top: auto;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .form-grid input {
            padding: 15px;
            border-radius: 12px;
            border: none;
            font-family: inherit;
            background: rgba(255,255,255,0.9);
            color: var(--dark-wood);
            font-size: 1rem;
        }

        .file-upload-wrapper {
            background: rgba(255,255,255,0.9);
            border-radius: 12px;
            display: flex;
            align-items: center;
            padding: 0 10px;
        }
        .file-upload-wrapper input[type="file"] {
            background: transparent;
            padding: 12px 5px;
            width: 100%;
        }

        .btn-add {
            background: var(--accent-gold);
            color: var(--dark-wood);
            border: none;
            padding: 15px;
            border-radius: 12px;
            font-weight: 900;
            font-size: 1.1rem;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-add:hover { background: white; transform: scale(1.02); }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>🏆 Votación de Casas</h1>
        <a href="exito.php" class="btn-back">⬅ Volver al Feed</a>
    </header>

    <div class="houses-grid" id="houses-container"></div>

    <?php if ($es_admin): ?>
    <div class="add-house-section">
        <h2 style="margin:0; color: var(--accent-gold);">➕ Añadir Nueva Casa Candidata</h2>
        <p style="margin: 5px 0 0 0; opacity: 0.7;">Solo tú (Administrador) puedes proponer opciones.</p>
        
        <div class="form-grid">
            <input type="text" id="h-name" placeholder="Nombre (ej: Villa Bosque)">
            <input type="number" id="h-price" placeholder="Precio Total (€)">
            
            <div class="file-upload-wrapper">
                <input type="file" id="h-file" accept="image/*">
            </div>

            <input type="text" id="h-url" placeholder="Link web (Booking/Airbnb)">
            <button class="btn-add" onclick="procesarNuevaCasa()">Añadir a la lista</button>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    let casas = [];
    
    // Inyectamos las variables de PHP directamente en JavaScript
    const usuarioLogueado = "<?= htmlspecialchars($nombre_usuario) ?>";
    const esAdministrador = <?= $es_admin ? 'true' : 'false' ?>;

    document.addEventListener("DOMContentLoaded", () => {
        const cGuardadas = localStorage.getItem('casasViajeRural');
        if(cGuardadas) {
            casas = JSON.parse(cGuardadas);
        }
        renderizarCasas();
    });

    function procesarNuevaCasa() {
        const nombre = document.getElementById('h-name').value;
        const precio = document.getElementById('h-price').value;
        const archivo = document.getElementById('h-file').files[0];
        const url = document.getElementById('h-url').value || '#';

        if (!nombre || !precio) {
            alert("El nombre y el precio son obligatorios.");
            return;
        }

        if (archivo) {
            const reader = new FileReader();
            reader.onload = function(e) {
                agregarCasaALaLista(nombre, precio, e.target.result, url);
            };
            reader.readAsDataURL(archivo);
        } else {
            const imgPorDefecto = 'https://images.unsplash.com/photo-1542718610-a1d656d1884c?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80';
            agregarCasaALaLista(nombre, precio, imgPorDefecto, url);
        }
    }

    function agregarCasaALaLista(nombre, precio, imgData, url) {
        const nuevaCasa = {
            id: Date.now(),
            nombre,
            precio,
            img: imgData,
            url,
            votos: [] 
        };
        
        casas.push(nuevaCasa);
        
        try {
            guardarYRenderizar();
            document.getElementById('h-name').value = '';
            document.getElementById('h-price').value = '';
            document.getElementById('h-file').value = '';
            document.getElementById('h-url').value = '';
        } catch (error) {
            casas.pop(); 
            alert("❌ La foto es demasiado pesada. Prueba con una captura.");
        }
    }

    function votar(casaId) {
        // Ya no buscamos el select, usamos la variable que nos dio PHP
        const casa = casas.find(c => c.id === casaId);
        const indexVoto = casa.votos.indexOf(usuarioLogueado);

        if(indexVoto === -1) {
            casa.votos.push(usuarioLogueado); // Dar like
        } else {
            casa.votos.splice(indexVoto, 1); // Quitar like
        }
        guardarYRenderizar();
    }

    function borrarCasa(id) {
        if(confirm("¿Seguro que quieres borrar esta casa candidata?")) {
            casas = casas.filter(c => c.id !== id);
            guardarYRenderizar();
        }
    }

    function guardarYRenderizar() {
        casas.sort((a, b) => b.votos.length - a.votos.length);
        localStorage.setItem('casasViajeRural', JSON.stringify(casas));
        renderizarCasas();
    }

    function renderizarCasas() {
        const container = document.getElementById('houses-container');
        container.innerHTML = '';

        if (casas.length === 0) {
            container.innerHTML = '<p style="text-align:center; grid-column: 1/-1; opacity:0.5; font-size:1.2rem;">Aún no hay casas. El administrador debe proponer opciones.</p>';
            return;
        }

        casas.forEach(casa => {
            // Usamos la variable inyectada para comprobar el estado del voto
            const haVotado = casa.votos.includes(usuarioLogueado);
            const totalLikes = casa.votos.length;
            
            const textoVotantes = totalLikes > 0 
                ? `Votado por: ${casa.votos.join(', ')}` 
                : 'Sé el primero en votar';

            // Solo inyectamos el botón de borrar si el JS sabe que es admin
            const botonBorrar = esAdministrador ? `<button class="delete-house" onclick="borrarCasa(${casa.id})">🗑️ Borrar</button>` : '';

            container.innerHTML += `
                <div class="house-card">
                    ${botonBorrar}
                    
                    <div class="house-img" style="background-image: url('${casa.img}')">
                        <div class="likes-badge">❤️ ${totalLikes}</div>
                    </div>
                    <div class="house-info">
                        <h3>${casa.nombre}</h3>
                        <span class="house-price">${casa.precio}€</span>
                        <p class="voters-list">${textoVotantes}</p>
                        <div class="vote-section">
                            <button class="btn-vote ${haVotado ? 'active' : ''}" onclick="votar(${casa.id})">
                                ${haVotado ? 'Diste Like' : 'Votar Casa'}
                            </button>
                            <a href="${casa.url}" target="_blank" class="btn-link">🔗 Web</a>
                        </div>
                    </div>
                </div>
            `;
        });
    }
</script>

</body>
</html>
