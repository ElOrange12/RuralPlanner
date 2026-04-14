<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$es_admin = ($_SESSION['rol'] === 'admin');
require_once 'inc/bd.php';

// --- 1. GESTIÓN DE ASISTENTES EN LA BASE DE DATOS ---
if (isset($_POST['accion'])) {
    if ($_POST['accion'] === 'add_asistente' && $es_admin && !empty($_POST['nombre'])) {
        $stmt = $pdo->prepare("INSERT INTO asistentes (nombre) VALUES (?)");
        $stmt->execute([trim($_POST['nombre'])]);
    } elseif ($_POST['accion'] === 'delete_asistente' && $es_admin) {
        $stmt = $pdo->prepare("DELETE FROM asistentes WHERE id_asistente = ?");
        $stmt->execute([$_POST['id_asistente']]);
    }
    header("Location: exito.php");
    exit();
}

try {
    // 2. Traer Asistentes de MySQL
    $asistentes = $pdo->query("SELECT * FROM asistentes ORDER BY id_asistente ASC")->fetchAll();
    $num_asistentes = count($asistentes) > 0 ? count($asistentes) : 1;

    // 3. Casa más votada
    $stmt_casa = $pdo->query("SELECT c.*, COUNT(v.id_casa) as votos FROM casas c LEFT JOIN votos_casas v ON c.id_casa = v.id_casa GROUP BY c.id_casa ORDER BY votos DESC LIMIT 1");
    $casa_top = $stmt_casa->fetch();
    
    $precio_casa = $casa_top ? (float)$casa_top['precio'] : 0;
    $img_casa = ($casa_top && !empty($casa_top['url_imagen'])) ? $casa_top['url_imagen'] : 'https://images.unsplash.com/photo-1449158743715-0a90ebb6d2d8?auto=format&fit=crop&w=800&q=80';
    $nombre_casa = $casa_top ? $casa_top['nombre'] : '¿A dónde vamos?';

    // 4. Extraemos gastos
    $precio_transporte = (float)($pdo->query("SELECT SUM(coste_total) FROM transporte")->fetchColumn() ?: 0);
    $precio_compra = (float)($pdo->query("SELECT SUM(precio_estimado) FROM lista_compra")->fetchColumn() ?: 0);
    $precio_actividades = (float)($pdo->query("SELECT SUM(a.precio) FROM actividades a JOIN votos_actividades v ON a.id_actividad = v.id_actividad")->fetchColumn() ?: 0);

    $total_final_bd = $precio_casa + $precio_transporte + $precio_compra + $precio_actividades;
    $precio_por_persona = $total_final_bd / $num_asistentes;

    // 5. Fechas más votadas
    $fechas_top = $pdo->query("SELECT fecha, COUNT(id_usuario) as total_votos FROM votos_fechas GROUP BY fecha ORDER BY total_votos DESC, fecha ASC LIMIT 3")->fetchAll();

} catch (PDOException $e) {
    $total_final_bd = 0; $precio_por_persona = 0;
    $img_casa = 'https://images.unsplash.com/photo-1449158743715-0a90ebb6d2d8?auto=format&fit=crop&w=800&q=80';
    $nombre_casa = 'Error al cargar';
    $fechas_top = []; $asistentes = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan Rural Amigos</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        :root { --forest-green: #2d5a27; --forest-light: #3a7533; --wood-brown: #4b3621; --wood-light: #6e5033; --black-matte: #1a1a1a; --cream-paper: #fdfbf7; --cream-dark: #e8e3d5; --accent-gold: #c5a059; --accent-light: #e6c587; }
        body { font-family: 'Nunito', 'Arial Rounded MT Bold', sans-serif; background: radial-gradient(circle at top right, var(--cream-paper) 0%, var(--cream-dark) 100%); color: var(--black-matte); margin: 0; padding: 0; width: 100vw; min-height: 100vh; overflow-x: hidden; }
        .feed-container { width: 100%; min-height: 100vh; padding: 3vw 5vw; box-sizing: border-box; display: flex; flex-direction: column; }
        h1 { text-align: center; color: var(--forest-green); text-transform: uppercase; letter-spacing: 2px; font-weight: 900; font-size: 2.5rem; border-bottom: 3px solid rgba(75, 54, 33, 0.2); padding-bottom: 15px; margin-top: 0; position: relative; text-shadow: 1px 1px 0px rgba(255,255,255,0.8); }
        .btn-logout { position: absolute; right: 0; top: 50%; transform: translateY(-50%); background: #e74c3c; color: white; padding: 8px 15px; border-radius: 10px; text-decoration: none; font-size: 1rem; font-weight: bold; box-shadow: 0 4px 0 #c0392b; transition: all 0.2s; }
        .btn-logout:hover { background: #c0392b; transform: translateY(-50%) scale(1.05); }
        .btn-logout:active { transform: translateY(calc(-50% + 4px)); box-shadow: 0 0 0 transparent; }
        
        .card, .members-card { background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.4) 100%); backdrop-filter: blur(15px); border: 1px solid rgba(255, 255, 255, 0.8); border-radius: 20px; padding: 30px; box-shadow: 0 20px 40px rgba(45, 90, 39, 0.08), inset 0 2px 0 rgba(255,255,255,0.5); display: flex; flex-direction: column; }
        .ranking-card, .footer-budget { background: linear-gradient(135deg, rgba(26, 26, 26, 0.9) 0%, rgba(75, 54, 33, 0.8) 100%); backdrop-filter: blur(15px); border: 1px solid rgba(197, 160, 89, 0.2); color: white; border-radius: 25px; padding: 40px 30px; text-align: center; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3), inset 0 2px 0 rgba(255,255,255,0.05); }
        .footer-budget { margin-top: 80px; margin-bottom: 20px; }

        .btn-nav, .btn-choose, .btn-add { border: 1px solid rgba(255,255,255,0.2); border-radius: 20px; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; text-align: center; text-decoration: none; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 8px 0 #1e3d1a, 0 15px 20px rgba(45, 90, 39, 0.3), inset 0 2px 0 rgba(255,255,255,0.2); }
        .btn-nav, .btn-choose { background: linear-gradient(145deg, var(--forest-light), var(--forest-green)); color: white; padding: 25px; font-size: 1.2rem; text-shadow: 1px 1px 2px rgba(0,0,0,0.3); }
        .btn-nav.alt { background: linear-gradient(145deg, var(--wood-light), var(--wood-brown)); box-shadow: 0 8px 0 #2a1f12, 0 15px 20px rgba(75, 54, 33, 0.3); }
        .btn-nav.dark, .btn-add { background: linear-gradient(145deg, #333, var(--black-matte)); box-shadow: 0 8px 0 #000, 0 15px 20px rgba(0, 0, 0, 0.4); color: white; }
        .btn-nav.fech { background: linear-gradient(145deg, var(--accent-light), var(--accent-gold)); color: var(--wood-brown); box-shadow: 0 8px 0 #9c7b41, 0 15px 20px rgba(197, 160, 89, 0.4); }
        .btn-nav:active, .btn-choose:active, .btn-add:active { transform: translateY(8px); box-shadow: 0 0 0 transparent; }

        .top-section { display: grid; grid-template-columns: 2fr 1fr; gap: 40px; margin-top: 20px; flex-grow: 1; }
        .house-selector { position: relative; min-height: 400px; border-radius: 25px; overflow: hidden; border: 6px solid white; background-size: cover; background-position: center; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
        .house-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to bottom, rgba(45,90,39,0.1) 0%, rgba(26,26,26,0.8) 100%); display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; }
        .house-overlay h2 { color: white; font-size: 2.8rem; text-shadow: 0px 4px 15px rgba(0,0,0,0.9); margin-bottom: 25px; }

        input[type="text"] { flex: 1; padding: 15px; font-family: inherit; font-size: 1.1rem; background: rgba(255,255,255,0.6); border: 2px solid rgba(197, 160, 89, 0.4); border-radius: 15px; outline: none; transition: 0.3s; }
        input[type="text"]:focus { background: white; border-color: var(--accent-gold); }
        
        #list-members { list-style: none; padding: 0; margin: 0; flex-grow: 1; overflow-y: auto; }
        #list-members li { padding: 15px; font-size: 1.2rem; margin-bottom: 10px; background: linear-gradient(90deg, rgba(255,255,255,0.8) 0%, rgba(255,255,255,0.4) 100%); border: 1px solid rgba(255,255,255,0.9); border-radius: 12px; display: flex; justify-content: space-between; align-items: center; transition: all 0.3s ease; }
        #list-members li:hover { transform: translateX(10px); border-color: var(--accent-gold); background: white; }

        .nav-buttons { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 25px; margin-top: 50px; }
        .budget-grid { display: flex; justify-content: space-around; align-items: center; margin-top: 15px; }
        .budget-grid > div { flex: 1; }
        .price-big { font-size: 4rem; font-weight: 900; background: linear-gradient(to right, var(--accent-light), var(--accent-gold)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; display: block; margin-top: 10px; filter: drop-shadow(0px 2px 4px rgba(0,0,0,0.5)); }

        .grid.cols-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 40px auto 60px auto; max-width: 1200px; padding: 0 20px; }
        .card h3 { font-size: 1.1rem; color: var(--wood-brown); font-weight: 900; text-transform: uppercase; margin-top: 0; margin-bottom: 10px; }
        .card .muted { font-size: 0.9rem; color: #666666; margin-top: 0; margin-bottom: 20px; flex-grow: 1; }
        .card .row { display: flex; gap: 10px; width: 100%; margin-top: auto; }
        .card button { font-family: inherit; padding: 12px 15px; border-radius: 12px; font-weight: 800; cursor: pointer; transition: all 0.2s ease; width: 100%; font-size: 0.9rem; border: none; }
        .card .btn-primary { background: linear-gradient(145deg, var(--forest-light), var(--forest-green)); color: white; }
        .card .btn-secondary { background: linear-gradient(145deg, var(--accent-light), var(--accent-gold)); color: var(--wood-brown); }
        .date-rank-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; margin-bottom: 8px; border-radius: 10px; background: rgba(255,255,255,0.6); border: 1px solid rgba(255,255,255,0.8); font-size: 1.1rem; }

        @media (max-width: 800px) { .top-section { grid-template-columns: 1fr; } .nav-buttons { grid-template-columns: 1fr 1fr; gap: 15px; } .budget-grid { flex-direction: column; gap: 20px; } .budget-grid > div:nth-child(2) { border-left: none; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px; } .btn-nav { font-size: 1rem; padding: 15px; } .btn-logout { position: static; display: block; width: fit-content; margin: 10px auto 0; transform: none; } }
    </style>
</head>
<body>

<div class="feed-container">
    <h1>🌲 Plan Rural Amigos 🌲
        <?php if ($es_admin): ?>
            <a href="admin.php" style="position:absolute; left:0; top:50%; transform:translateY(-50%); background:var(--accent-gold); color:white; padding:8px 15px; border-radius:10px; text-decoration:none; font-size:1rem;">⚙️ Admin</a>
        <?php endif; ?>
        <a href="controladores/logout.php" class="btn-logout">Salir</a>
    </h1>

    <div class="top-section">
        <div class="house-selector" style="background-image: url('<?= $img_casa ?>');">
            <div class="house-overlay">
                <h2>🏆 <?= htmlspecialchars($nombre_casa) ?></h2>
                <a href="votaciones.php" class="btn-choose">Votar Casa</a>
            </div>
        </div>

        <div class="members-card">
            <h3>👥 Asistentes (<span id="count-members"><?= count($asistentes) ?></span>)</h3>
            
            <?php if ($es_admin): ?>
                <form method="POST" style="display:flex; gap:10px; margin-bottom:20px;">
                    <input type="hidden" name="accion" value="add_asistente">
                    <input type="text" name="nombre" placeholder="Añade un amigo..." required>
                    <button type="submit" class="btn-add">+</button>
                </form>
            <?php endif; ?>

            <ul id="list-members">
                <?php foreach($asistentes as $a): ?>
                    <li>
                        <span><?= htmlspecialchars($a['nombre']) ?></span>
                        <?php if ($es_admin): ?>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="accion" value="delete_asistente">
                                <input type="hidden" name="id_asistente" value="<?= $a['id_asistente'] ?>">
                                <button type="submit" style="background:none; border:none; color:#e74c3c; font-weight:bold; font-size:1.5rem; cursor:pointer;" title="Borrar">×</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
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
                <span class="price-big"><?= number_format($total_final_bd, 2) ?>€</span>
            </div>
            <div style="border-left: 2px solid #333;">
                <span style="font-size: 1.2rem; opacity: 0.8;">TOCAMOS A</span>
                <span class="price-big" style="color: white;"><?= number_format($precio_por_persona, 2) ?>€</span>
            </div>
        </div>
    </div>
</div>

<section class="grid cols-3">
    <article class="card">
        <h3>📅 Fechas Favoritas</h3>
        <?php if (empty($fechas_top)): ?>
            <p class="muted">Aún no hay fechas con votos.</p>
        <?php else: ?>
            <div style="margin-bottom: 15px; flex-grow: 1;">
                <?php foreach($fechas_top as $f): ?>
                    <div class="date-rank-item">
                        <strong><?= date('d/m/Y', strtotime($f['fecha'])) ?></strong>
                        <span style="color:var(--wood-brown); font-weight:900; background:rgba(197, 160, 89, 0.3); padding:2px 8px; border-radius:5px; font-size:0.9rem;"><?= $f['total_votos'] ?> v.</span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="row">
            <button class="btn-primary" onclick="window.location.href='fechas.php'">Ver calendario</button>
        </div>
    </article>

    <article class="card">
        <h3>Reto aleatorio del finde</h3>
        <p class="muted" id="challengeText">Pulsa para sacar un mini juego grupal.</p>
        <div class="row"><button class="btn-primary" id="challengeBtn">Sacar reto</button></div>
    </article>

    <article class="card">
        <h3>Informe del plan</h3>
        <p class="muted">Genera un informe para compartir.</p>
        <div class="row">
            <button class="btn-secondary" id="exportPdfCompactBtn">Compacto</button>
            <button class="btn-secondary" id="exportPdfDetailedBtn">Detallado</button>
        </div>
    </article>
</section>

<script>
    // Variables para el PDF
    const arrayMiembros = <?= json_encode(array_column($asistentes, 'nombre')) ?>;
    const datosPDF = {
        casaNombre: <?= json_encode($nombre_casa) ?>,
        precioCasa: <?= (float)$precio_casa ?>,
        transporte: <?= (float)$precio_transporte ?>,
        compra: <?= (float)$precio_compra ?>,
        actividades: <?= (float)$precio_actividades ?>,
        totalFinal: <?= (float)$total_final_bd ?>
    };

    // Retos aleatorios
    const challenges = [
        "Reto fogata: 2 mentiras 1 verdad.",
        "Reto cocina: cena de 3 ingredientes.",
        "Reto campo: gymkana express en 20 min.",
        "Reto foto: mejor foto del finde.",
        "Reto coctelero: inventar la mezcla más rara."
    ];
    document.getElementById("challengeBtn").addEventListener("click", () => {
        document.getElementById("challengeText").textContent = challenges[Math.floor(Math.random() * challenges.length)];
    });

    // Generador de PDF
    function exportPlanToPdf(mode) {
        const numAmigos = arrayMiembros.length > 0 ? arrayMiembros.length : 1;
        const tocamosA = (datosPDF.totalFinal / numAmigos).toFixed(2);
        const fecha = new Date().toLocaleDateString("es-ES");
        
        const html = `
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Informe Plan Rural</title>
            <style>
                body { font-family: Arial, sans-serif; color: #1a1a1a; padding: 20px; }
                .cover { background: #2d5a27; color: white; padding: 30px; border-radius: 15px; text-align: center; }
                .summary { margin-top: 30px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                .box { border: 2px solid #f4f0e6; padding: 20px; border-radius: 12px; }
                table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                th, td { border-bottom: 1px solid #eee; padding: 12px; text-align: left; }
                .total-row { font-weight: bold; font-size: 18px; background: #fdfbf7; }
                .big-price { font-size: 24px; color: #c5a059; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="cover"><h1>🌲 Informe Plan Rural</h1><p>Generado el ${fecha}</p></div>
            <div class="summary">
                <div class="box"><h2>👥 Asistentes</h2><p>${arrayMiembros.length > 0 ? arrayMiembros.join(', ') : 'Vacío'}</p></div>
                <div class="box" style="text-align: center;"><h2>💰 A Escote</h2><div class="big-price">${tocamosA}€ / p.</div></div>
            </div>
            <div class="box" style="margin-top: 20px;">
                <h2>📊 Desglose</h2>
                <table>
                    <tr><th>Concepto</th><th>Importe Total</th></tr>
                    <tr><td>🏠 Casa: <b>${datosPDF.casaNombre}</b></td><td>${datosPDF.precioCasa.toFixed(2)}€</td></tr>
                    <tr><td>🚗 Transporte</td><td>${datosPDF.transporte.toFixed(2)}€</td></tr>
                    <tr><td>🛒 Fondo de Comida</td><td>${datosPDF.compra.toFixed(2)}€</td></tr>
                    <tr><td>🏹 Actividades</td><td>${datosPDF.actividades.toFixed(2)}€</td></tr>
                    <tr class="total-row"><td style="text-align: right;">PRESUPUESTO GLOBAL:</td><td>${datosPDF.totalFinal.toFixed(2)}€</td></tr>
                </table>
            </div>
        </body>
        </html>`;
        
        const frame = document.createElement("iframe");
        frame.style.display = "none";
        document.body.appendChild(frame);
        frame.contentWindow.document.open();
        frame.contentWindow.document.write(html);
        frame.contentWindow.document.close();
        setTimeout(() => { frame.contentWindow.focus(); frame.contentWindow.print(); setTimeout(() => frame.remove(), 1500); }, 700);
    }

    document.getElementById("exportPdfCompactBtn").addEventListener("click", () => exportPlanToPdf("compact"));
    document.getElementById("exportPdfDetailedBtn").addEventListener("click", () => exportPlanToPdf("detailed"));
</script>
</body>
</html>
