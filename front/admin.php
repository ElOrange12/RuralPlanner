<?php
// admin.php
session_start();

// 1. Candado de seguridad DOBLE (Solo Admins)
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: exito.php");
    exit();
}

require_once 'inc/bd.php';
$user_id = $_SESSION['user_id'];

try {
    // 2. Traer estadísticas generales
    $total_usuarios = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    $total_casas = $pdo->query("SELECT COUNT(*) FROM casas")->fetchColumn();
    $total_actividades = $pdo->query("SELECT COUNT(*) FROM actividades")->fetchColumn();
    
    // 3. Traer las listas de datos (Usamos LEFT JOIN para ver quién propuso cada cosa)
    $lista_usuarios = $pdo->query("SELECT id_usuario, nombre, fecha_registro, rol FROM usuarios ORDER BY id_usuario DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    $lista_casas = $pdo->query("
        SELECT c.id_casa, c.nombre, c.precio, u.nombre as creador 
        FROM casas c 
        LEFT JOIN usuarios u ON c.id_creador = u.id_usuario 
        ORDER BY c.id_casa DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $lista_actividades = $pdo->query("
        SELECT a.id_actividad, a.nombre, a.categoria, a.precio, u.nombre as creador 
        FROM actividades a 
        LEFT JOIN usuarios u ON a.id_creador = u.id_usuario 
        ORDER BY a.id_actividad DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error crítico: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="img/Logo RuralPlanner.png">
    <title>Panel de Control | Rural Planner</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --forest-green: #2d5a27;
            --wood-brown: #4b3621;
            --black-matte: #1a1a1a;
            --cream-paper: #fdfbf7;
            --accent-gold: #c5a059;
            --danger-red: #e74c3c;
        }

        body {
            font-family: 'Nunito', sans-serif;
            background-color: var(--cream-paper);
            color: var(--black-matte);
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .container { max-width: 1100px; margin: 0 auto; padding: 3vw 5vw; }

        header {
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 3px solid var(--forest-green); padding-bottom: 20px; margin-bottom: 30px;
        }
        h1 { margin: 0; font-weight: 900; text-transform: uppercase; color: var(--wood-brown); }

        .btn-back {
            background: var(--forest-green); color: white; padding: 10px 25px;
            text-decoration: none; border-radius: 50px; font-weight: 700; transition: 0.3s;
        }
        .btn-back:hover { background: var(--wood-brown); }

        /* Tarjetas de Estadísticas */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card {
            background: white; border: 2px solid var(--accent-gold); border-radius: 15px;
            padding: 20px; text-align: center; box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }
        .stat-card .num { font-size: 2.5rem; font-weight: 900; color: var(--forest-green); margin-bottom: 5px; }
        .stat-card .label { font-size: 1rem; color: var(--wood-brown); font-weight: bold; text-transform: uppercase; }

        /* Secciones y Tablas */
        .admin-section {
            background: white; border-radius: 15px; padding: 25px; margin-bottom: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); border-left: 8px solid var(--wood-brown);
        }
        .admin-section h2 { margin-top: 0; color: var(--wood-brown); border-bottom: 2px dashed #eee; padding-bottom: 10px; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; text-align: left; }
        th { padding: 12px; border-bottom: 2px solid var(--forest-green); color: var(--forest-green); text-transform: uppercase; font-size: 0.9rem; }
        td { padding: 15px 12px; border-bottom: 1px solid #eee; color: var(--black-matte); }
        tr:hover td { background-color: #fcfaf5; }

        .tag-admin { background: var(--wood-brown); color: white; padding: 4px 8px; border-radius: 5px; font-size: 0.8rem; font-weight: bold; }
        .tag-user { background: var(--forest-green); color: white; padding: 4px 8px; border-radius: 5px; font-size: 0.8rem; font-weight: bold; }

        .btn-delete {
            background: rgba(231, 76, 60, 0.1); color: var(--danger-red); border: 1px solid var(--danger-red);
            padding: 6px 12px; border-radius: 5px; cursor: pointer; font-weight: bold; transition: 0.2s;
        }
        .btn-delete:hover { background: var(--danger-red); color: white; }

        .msg-success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; text-align: center; }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>⚙️ Sala de Máquinas</h1>
        <a href="exito.php" class="btn-back">⬅ Volver al Plan</a>
    </header>

    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'borrado_ok'): ?>
        <div class="msg-success">✅ Elemento borrado correctamente de la base de datos.</div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="num"><?= $total_usuarios ?></div>
            <div class="label">Amigos Registrados</div>
        </div>
        <div class="stat-card">
            <div class="num"><?= $total_casas ?></div>
            <div class="label">Casas Propuestas</div>
        </div>
        <div class="stat-card">
            <div class="num"><?= $total_actividades ?></div>
            <div class="label">Planes Creados</div>
        </div>
    </div>

    <div class="admin-section">
        <h2>👥 Control de Usuarios</h2>
        <div style="overflow-x: auto;">
            <table>
                <tr><th>ID</th><th>Nombre</th><th>Registro</th><th>Rol</th><th>Acción</th></tr>
                <?php foreach($lista_usuarios as $u): ?>
                <tr>
                    <td style="color: #aaa;">#<?= $u['id_usuario'] ?></td>
                    <td><strong><?= htmlspecialchars($u['nombre']) ?></strong></td>
                    <td><?= date('d/m/Y', strtotime($u['fecha_registro'])) ?></td>
                    <td>
                        <span class="<?= $u['rol'] == 'admin' ? 'tag-admin' : 'tag-user' ?>"><?= strtoupper($u['rol']) ?></span>
                    </td>
                    <td>
                        <?php if($u['id_usuario'] != $user_id): // No te puedes borrar a ti mismo ?>
                        <form action="controladores/admin_procesar.php" method="POST" style="margin:0;" onsubmit="return confirm('¿Borrar a este usuario y TODOS sus votos/propuestas?');">
                            <input type="hidden" name="accion" value="borrar_usuario">
                            <input type="hidden" name="id" value="<?= $u['id_usuario'] ?>">
                            <button type="submit" class="btn-delete">Expulsar</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <div class="admin-section">
        <h2>🏡 Casas Candidatas</h2>
        <div style="overflow-x: auto;">
            <table>
                <tr><th>ID</th><th>Casa</th><th>Precio</th><th>Propuesta por</th><th>Acción</th></tr>
                <?php foreach($lista_casas as $c): ?>
                <tr>
                    <td style="color: #aaa;">#<?= $c['id_casa'] ?></td>
                    <td><strong><?= htmlspecialchars($c['nombre']) ?></strong></td>
                    <td style="color: var(--accent-gold); font-weight: bold;"><?= $c['precio'] ?>€</td>
                    <td><?= htmlspecialchars($c['creador'] ?? 'Desconocido') ?></td>
                    <td>
                        <form action="controladores/admin_procesar.php" method="POST" style="margin:0;" onsubmit="return confirm('¿Borrar esta casa y sus votos?');">
                            <input type="hidden" name="accion" value="borrar_casa">
                            <input type="hidden" name="id" value="<?= $c['id_casa'] ?>">
                            <button type="submit" class="btn-delete">Borrar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <div class="admin-section">
        <h2>🏹 Actividades Propuestas</h2>
        <div style="overflow-x: auto;">
            <table>
                <tr><th>ID</th><th>Actividad</th><th>Categoría</th><th>Precio</th><th>Propuesta por</th><th>Acción</th></tr>
                <?php foreach($lista_actividades as $a): ?>
                <tr>
                    <td style="color: #aaa;">#<?= $a['id_actividad'] ?></td>
                    <td><strong><?= htmlspecialchars($a['nombre']) ?></strong></td>
                    <td><?= ucfirst($a['categoria']) ?></td>
                    <td style="color: var(--accent-gold); font-weight: bold;"><?= $a['precio'] ?>€</td>
                    <td><?= htmlspecialchars($a['creador'] ?? 'Desconocido') ?></td>
                    <td>
                        <form action="controladores/admin_procesar.php" method="POST" style="margin:0;" onsubmit="return confirm('¿Borrar esta actividad?');">
                            <input type="hidden" name="accion" value="borrar_actividad">
                            <input type="hidden" name="id" value="<?= $a['id_actividad'] ?>">
                            <button type="submit" class="btn-delete">Borrar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

</div>

</body>
</html>
