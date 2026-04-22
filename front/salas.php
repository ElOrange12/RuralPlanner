<?php
session_start();
require_once 'inc/bd.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Viajes - Rural Planner</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        :root { --forest: #2d5a27; --wood: #4b3621; --cream: #fdfbf7; --gold: #c5a059; }
        body { 
            font-family: 'Nunito', sans-serif; background-color: var(--cream); 
            display: flex; justify-content: center; align-items: center; height: 100vh; margin:0;
            background-image: linear-gradient(rgba(253, 251, 247, 0.9), rgba(253, 251, 247, 0.9)), url('https://images.unsplash.com/photo-1449158743715-0a90ebb6d2d8?auto=format&fit=crop&w=1600&q=80');
            background-size: cover;
        }
        .rooms-card { background: white; padding: 40px; border-radius: 30px; box-shadow: 0 15px 50px rgba(0,0,0,0.15); text-align: center; width: 100%; max-width: 450px; border-top: 10px solid var(--forest); }
        h1 { color: var(--wood); font-weight: 900; }
        .btn-room { display: block; width: 100%; padding: 18px; margin: 15px 0; border-radius: 15px; border: none; font-weight: 900; cursor: pointer; transition: 0.3s; font-size: 1.1rem; text-transform: uppercase; letter-spacing: 1px; }
        .btn-create { background: var(--forest); color: white; }
        .btn-join { background: var(--wood); color: white; }
        .btn-room:hover { transform: scale(1.03); filter: brightness(1.1); }
        .hidden-form { display: none; margin-top: 20px; padding: 20px; background: #f9f6f0; border-radius: 15px; animation: slideDown 0.4s ease; }
        input { width: 100%; padding: 15px; margin-bottom: 15px; border-radius: 12px; border: 2px solid #ddd; font-family: inherit; font-size: 1rem; box-sizing: border-box; }
        input:focus { border-color: var(--gold); outline: none; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <div class="rooms-card">
        <h1>¡Hola, <?= htmlspecialchars($_SESSION['nombre']) ?>! 🌲</h1>
        <p style="opacity: 0.7; margin-bottom: 30px;">Selecciona un plan existente o crea uno nuevo para empezar la aventura.</p>

        <button class="btn-room btn-create" onclick="toggleForm('form-crear')">➕ Crear Nueva Sala</button>
        <div id="form-crear" class="hidden-form">
            <form action="controladores/procesasala.php" method="POST">
                <input type="hidden" name="accion" value="crear">
                <input type="text" name="nombre_sala" placeholder="Nombre del viaje (ej: Asturias 2026)" required>
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <input type="text" name="codigo_sala" id="codigo_gen" placeholder="Código de sala" style="margin-bottom: 0;" required>
                    <button type="button" onclick="generarCodigo()" style="background: var(--gold); border: none; padding: 10px 15px; border-radius: 12px; cursor: pointer; font-size: 1.2rem;" title="Generar código aleatorio">🎲</button>
                </div>
                <button type="submit" class="btn-room btn-create" style="padding: 12px;">Empezar el Plan</button>
            </form>
        </div>

        <button class="btn-room btn-join" onclick="toggleForm('form-unirse')">🔑 Unirse con Código</button>
        <div id="form-unirse" class="hidden-form">
            <form action="controladores/procesasala.php" method="POST">
                <input type="hidden" name="accion" value="unirse">
                <input type="text" name="codigo_sala" placeholder="Introduce el código aquí..." required>
                <button type="submit" class="btn-room btn-join" style="padding: 12px;">Entrar a la sala</button>
            </form>
        </div>
        
        <a href="controladores/logout.php" style="display: block; margin-top: 20px; color: #999; text-decoration: none; font-size: 0.9rem;">Cerrar Sesión</a>
    </div>

    <script>
        function toggleForm(id) {
            document.querySelectorAll('.hidden-form').forEach(f => f.style.display = 'none');
            document.getElementById(id).style.display = 'block';
        }
        function generarCodigo() {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            let code = '';
            for (let i = 0; i < 6; i++) {
                code += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('codigo_gen').value = code;
        }
    </script>
</body>
</html>