<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: exito.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - Plan Rural</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;900&display=swap" rel="stylesheet">
    
    <style>
        :root { --forest-green: #2d5a27; --wood-brown: #4b3621; --black-matte: #1a1a1a; --cream-paper: #fdfbf7; }
        body {
            font-family: 'Nunito', sans-serif; background-color: var(--cream-paper);
            margin: 0; padding: 0; width: 100vw; height: 100vh;
            display: flex; justify-content: center; align-items: center;
            background-image: linear-gradient(rgba(253, 251, 247, 0.9), rgba(253, 251, 247, 0.9)), url('https://images.unsplash.com/photo-1449158743715-0a90ebb6d2d8?fm=jpg&q=60&w=3000&auto=format&fit=crop');
            background-size: cover; background-position: center;
        }
        .auth-card {
            background: #f4f0e6; padding: 40px 50px; border-radius: 25px;
            border-top: 8px solid var(--forest-green); box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            text-align: center; width: 100%; max-width: 400px; box-sizing: border-box;
        }
        h1 { color: var(--wood-brown); text-transform: uppercase; letter-spacing: 2px; font-weight: 900; margin-top: 0; margin-bottom: 10px; }
        p { color: #666; margin-bottom: 30px; font-size: 0.9rem; }
        
        .input-group { margin-bottom: 20px; text-align: left; }
        input[type="text"], input[type="password"] {
            width: 100%; padding: 15px; font-family: inherit; font-size: 1rem;
            border: 2px solid var(--wood-brown); border-radius: 15px; outline: none; box-sizing: border-box;
        }
        input:focus { border-color: var(--forest-green); }

        .btn-auth {
            background: var(--forest-green); color: white; width: 100%; padding: 15px;
            font-size: 1.1rem; border: none; border-radius: 15px; font-weight: 900; cursor: pointer; transition: 0.3s;
        }
        .btn-auth:hover { background: var(--wood-brown); transform: scale(1.02); }
        
        .error-msg { color: red; font-weight: bold; margin-bottom: 15px; }
        .toggle-link { display: block; margin-top: 20px; color: var(--forest-green); font-weight: bold; cursor: pointer; text-decoration: none; }
        .toggle-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <div class="auth-card">
        <h1>🔑 Reseteo</h1>
        <p>Introduce tu usuario y elige una nueva contraseña.</p>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="error-msg"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>
        
        <form action="controladores/procesarecuperacion.php" method="POST">
            <div class="input-group">
                <input type="text" name="nombre" placeholder="Tu usuario actual" required>
            </div>
            <div class="input-group">
                <input type="password" name="nueva_password" placeholder="Nueva contraseña" required>
            </div>
            <div class="input-group">
                <input type="password" name="confirmar_password" placeholder="Repite la nueva contraseña" required>
            </div>
            <button type="submit" class="btn-auth">Cambiar Contraseña</button>
            <a href="index.php" class="toggle-link">Volver al Login</a>
        </form>
    </div>

</body>
</html>
