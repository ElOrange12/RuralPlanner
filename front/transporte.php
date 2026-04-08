<?php
session_start();
require_once 'inc/bd.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$es_admin = ($_SESSION['rol'] === 'admin');

// --- 1. PROCESAR NUEVO TRAYECTO ---
if (isset($_POST['accion']) && $_POST['accion'] === 'nuevo_trayecto') {
    $tipo = $_POST['tipo'];
    $ruta = trim($_POST['ruta'] ?? 'Trayecto sin nombre');
    $coste = floatval($_POST['coste_total'] ?? 0);

    if ($coste > 0) {
        $stmt = $pdo->prepare("INSERT INTO transporte (tipo, ruta, coste_total) VALUES (?, ?, ?)");
        $stmt->execute([$tipo, $ruta, $coste]);
    }
    header("Location: transporte.php");
    exit();
}

// --- 2. BORRAR TRAYECTO ---
if (isset($_POST['accion']) && $_POST['accion'] === 'borrar_trayecto') {
    $stmt = $pdo->prepare("DELETE FROM transporte WHERE id_trayecto = ?");
    $stmt->execute([$_POST['id_trayecto']]);
    header("Location: transporte.php");
    exit();
}

// --- 3. TRAER LISTA DE TRAYECTOS ---
$trayectos = $pdo->query("SELECT * FROM transporte ORDER BY id_trayecto DESC")->fetchAll();
$gasto_total_transporte = $pdo->query("SELECT SUM(coste_total) FROM transporte")->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transporte - Rural Planner</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        :root { --deep-forest: #1a2e18; --dark-wood: #2c1e14; --forest-green: #2d5a27; --cream-paper: #fdfbf7; --accent-gold: #c5a059; --card-bg: #233621; }
        body { font-family: 'Nunito', sans-serif; background-color: var(--deep-forest); color: var(--cream-paper); margin: 0; padding: 0; width: 100vw; min-height: 100vh; overflow-x: hidden; }
        .container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 3vw 5vw; box-sizing: border-box; display: flex; flex-direction: column; }
        header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--forest-green); padding-bottom: 20px; margin-bottom: 30px; }
        h1 { margin: 0; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; color: var(--accent-gold); }
        .btn-back { background: var(--forest-green); color: white; padding: 10px 25px; text-decoration: none; border-radius: 50px; font-weight: 700; transition: 0.3s; }
        .btn-back:hover { background: var(--dark-wood); }

        .transport-layout { display: grid; grid-template-columns: 1fr 1.2fr; gap: 40px; background: var(--card-bg); border-radius: 25px; box-shadow: 0 15px 40px rgba(0,0,0,0.5); overflow: hidden; border: 1px solid rgba(255,255,255,0.05); margin-bottom: 40px; }
        .image-panel { background-size: cover; background-position: center; transition: background-image 0.5s ease-in-out; min-height: 450px; position: relative; display: flex; justify-content: center; align-items: flex-end; padding-bottom: 20px; }
        .image-overlay { position: absolute; bottom: 0; left: 0; width: 100%; background: linear-gradient(to top, var(--card-bg) 0%, transparent 100%); height: 120px; }
        .form-panel { padding: 40px 40px 40px 0; display: flex; flex-direction: column; }

        .selector-group { margin-bottom: 30px; }
        .selector-group label { display: block; color: var(--accent-gold); font-weight: 900; margin-bottom: 10px; font-size: 1.2rem; }
        select { width: 100%; padding: 15px; border-radius: 15px; border: 2px solid var(--accent-gold); background: var(--dark-wood); color: var(--cream-paper); font-family: inherit; font-size: 1.2rem; font-weight: 700; cursor: pointer; outline: none; }

        .dynamic-form { display: none; animation: fadeIn 0.4s ease; }
        .dynamic-form.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .input-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .input-group { display: flex; flex-direction: column; margin-bottom: 15px; }
        .input-group label { font-size: 0.9rem; opacity: 0.8; margin-bottom: 5px; }
        .input-group input { padding: 12px; border-radius: 10px; border: none; background: rgba(255,255,255,0.9); color: var(--dark-wood); font-family: inherit; font-size: 1rem; }
        .full-width { grid-column: 1 / -1; }

        .total-box { margin-top: auto; background: rgba(0,0,0,0.3); padding: 20px; border-radius: 15px; border-left: 4px solid var(--accent-gold); display: flex; justify-content: space-between; align-items: center; }
        .total-price { font-size: 2.5rem; font-weight: 900; color: var(--accent-gold); }

        .btn-save { width: 100%; background: var(--accent-gold); color: var(--dark-wood); border: none; padding: 18px; border-radius: 15px; font-weight: 900; font-size: 1.2rem; cursor: pointer; transition: 0.3s; margin-top: 20px; text-align: center; }
        .btn-save:hover { background: white; transform: scale(1.02); }

        /* Estilos de la tabla de lista */
        .transport-table { width: 100%; background: var(--card-bg); border-radius: 25px; overflow: hidden; border-collapse: collapse; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .transport-table th { background: rgba(0,0,0,0.3); padding: 20px; text-align: left; color: var(--accent-gold); text-transform: uppercase; font-size: 0.9rem; }
        .transport-table td { padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; }
        .delete-btn { color: #ff5e5e; cursor: pointer; font-weight: bold; background: rgba(255,0,0,0.1); padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(255,0,0,0.3); border: none; font-size: 1rem;}

        @media (max-width: 800px) {
            .transport-layout { grid-template-columns: 1fr; }
            .image-panel { min-height: 250px; }
            .form-panel { padding: 30px; }
            .transport-table { display: block; overflow-x: auto; }
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>🚗 Gestión de Transporte</h1>
        <a href="exito.php" class="btn-back">⬅ Volver al Feed</a>
    </header>

    <div class="transport-layout">
        <div class="image-panel" id="transport-img">
            <div class="image-overlay"></div>
        </div>

        <div class="form-panel">
            <div class="selector-group">
                <label>¿Cómo vamos a llegar?</label>
                <select id="transport-type" onchange="cambiarTransporte()">
                    <option value="coche">🚗 Coche / Furgoneta</option>
                    <option value="tren">🚆 Tren / AVE</option>
                    <option value="avion">✈️ Avión</option>
                </select>
            </div>

            <div id="form-coche" class="dynamic-form active">
                <div class="input-grid">
                    <div class="input-group full-width">
                        <label>Ruta (Origen - Destino)</label>
                        <input type="text" id="c-ruta" placeholder="Ej: Madrid - Asturias" oninput="actualizarPantallaTotal()">
                    </div>
                    <div class="input-group">
                        <label>Kilómetros (Solo ida)</label>
                        <input type="number" id="c-km" placeholder="Ej: 450" oninput="calcularCoche()">
                    </div>
                    <div class="input-group">
                        <label>Consumo (L/100km)</label>
                        <input type="number" id="c-consumo" value="7" step="0.1" oninput="calcularCoche()">
                    </div>
                    <div class="input-group">
                        <label>Precio Gasolina (€/L)</label>
                        <input type="number" id="c-precioGas" value="1.65" step="0.01" oninput="calcularCoche()">
                    </div>
                    <div class="input-group">
                        <label>Peajes Totales (€)</label>
                        <input type="number" id="c-peajes" value="0" oninput="calcularCoche()">
                    </div>
                </div>
            </div>

            <div id="form-billetes" class="dynamic-form">
                <div class="input-grid">
                    <div class="input-group full-width">
                        <label>Estación / Aeropuerto de Salida</label>
                        <input type="text" id="b-origen" placeholder="Ej: Atocha - Asturias" oninput="actualizarPantallaTotal()">
                    </div>
                    <div class="input-group">
                        <label>Precio Billete (Por persona)</label>
                        <input type="number" id="b-precioPersona" placeholder="Ej: 45" oninput="calcularBilletes()">
                    </div>
                    <div class="input-group">
                        <label>Nº Personas en este trayecto</label>
                        <input type="number" id="b-personas" value="1" min="1" oninput="calcularBilletes()">
                    </div>
                </div>
            </div>

            <div class="total-box">
                <div>
                    <span style="display:block; font-size:1.1rem; opacity:0.8;">Coste Estimado</span>
                </div>
                <div class="total-price"><span id="precio-final">0.00</span>€</div>
            </div>

            <form action="transporte.php" method="POST" id="form-enviar">
                <input type="hidden" name="accion" value="nuevo_trayecto">
                <input type="hidden" name="tipo" id="hidden_tipo" value="coche">
                <input type="hidden" name="ruta" id="hidden_ruta" value="">
                <input type="hidden" name="coste_total" id="hidden_coste" value="0">
                <button type="submit" class="btn-save">💾 Añadir Trayecto a la Lista</button>
            </form>
        </div>
    </div>

    <h2 style="color: var(--accent-gold); text-transform: uppercase;">📍 Rutas Guardadas (Total: <?= number_format($gasto_total_transporte, 2) ?>€)</h2>
    
    <table class="transport-table">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Descripción de la Ruta</th>
                <th>Coste Total</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($trayectos) === 0): ?>
                <tr>
                    <td colspan="4" style="text-align:center; opacity: 0.5;">No hay rutas guardadas aún.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($trayectos as $t): ?>
                    <tr>
                        <td style="font-size: 1.5rem;">
                            <?= $t['tipo'] === 'coche' ? '🚗' : ($t['tipo'] === 'tren' ? '🚆' : '✈️') ?>
                        </td>
                        <td style="font-weight: bold; font-size: 1.1rem;">
                            <?= htmlspecialchars($t['ruta']) ?>
                        </td>
                        <td style="color: var(--accent-gold); font-weight: 900; font-size: 1.2rem;">
                            <?= number_format($t['coste_total'], 2) ?>€
                        </td>
                        <td>
                            <?php if ($es_admin): ?>
                                <form action="transporte.php" method="POST" style="margin:0;" onsubmit="return confirm('¿Borrar este trayecto?');">
                                    <input type="hidden" name="accion" value="borrar_trayecto">
                                    <input type="hidden" name="id_trayecto" value="<?= $t['id_trayecto'] ?>">
                                    <button type="submit" class="delete-btn">🗑️ Borrar</button>
                                </form>
                            <?php else: ?>
                                <span style="font-size: 0.8rem; opacity: 0.5;">Solo Admin</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    let costeTotal = 0;

    const imagenesDefecto = {
        'coche': 'https://lumiere-a.akamaihd.net/v1/images/p_cars_19643_4405006d.jpeg',
        'tren': 'https://sm.ign.com/t/ign_es/screenshot/default/sin-titulo-1_7gkm.1280.jpg',
        'avion': 'https://www.twincities.com/wp-content/uploads/2015/11/20130806__130809m-Planes-2.jpg?w=1200&resize=1200,900'
    };

    document.addEventListener("DOMContentLoaded", () => {
        cambiarTransporte();
    });

    function cambiarTransporte() {
        const seleccion = document.getElementById('transport-type').value;
        document.getElementById('transport-img').style.backgroundImage = `url('${imagenesDefecto[seleccion]}')`;
        document.getElementById('hidden_tipo').value = seleccion;

        document.querySelectorAll('.dynamic-form').forEach(f => f.classList.remove('active'));
        if (seleccion === 'coche') {
            document.getElementById('form-coche').classList.add('active');
            calcularCoche();
        } else {
            document.getElementById('form-billetes').classList.add('active');
            calcularBilletes();
        }
    }

    function calcularCoche() {
        const km = parseFloat(document.getElementById('c-km').value) || 0;
        const consumo = parseFloat(document.getElementById('c-consumo').value) || 0;
        const precioGas = parseFloat(document.getElementById('c-precioGas').value) || 0;
        const peajes = parseFloat(document.getElementById('c-peajes').value) || 0;

        const kmTotales = km * 2; 
        const litrosNecesarios = (kmTotales / 100) * consumo;
        const gastoGasolina = litrosNecesarios * precioGas;
        
        costeTotal = gastoGasolina + (peajes * 2);
        actualizarPantallaTotal();
    }

    function calcularBilletes() {
        const precioPersona = parseFloat(document.getElementById('b-precioPersona').value) || 0;
        const asistentes = parseInt(document.getElementById('b-personas').value) || 1;
        costeTotal = precioPersona * asistentes;
        actualizarPantallaTotal();
    }

    // Esta función inyecta los valores en el formulario oculto antes de mandarlos a PHP
    function actualizarPantallaTotal() {
        document.getElementById('precio-final').innerText = costeTotal.toFixed(2);
        document.getElementById('hidden_coste').value = costeTotal.toFixed(2);
        
        const tipo = document.getElementById('transport-type').value;
        let ruta = "";
        if(tipo === 'coche') {
            ruta = document.getElementById('c-ruta').value || "Ruta en coche";
        } else {
            ruta = document.getElementById('b-origen').value || "Viaje en billetes";
        }
        document.getElementById('hidden_ruta').value = ruta;
    }
</script>

</body>
</html>
