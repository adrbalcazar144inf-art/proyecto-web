<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../models/conexion.php';
$conexion = conectarse();
require_once '../controllers/control_sesion.php';
iniciar_sesion_con_control(240);

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'superusuario') {
    header('Location: ../../../index.php');
    exit;
}

$nombre = htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8');
$usuario_id = $_SESSION['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    if ($nombre !== '') {
        if (isset($_POST['registrar'])) {
            $existe = $conexion->prepare("SELECT id FROM lk_aulas WHERE nombre = ?");
            $existe->bind_param("s", $nombre);
            $existe->execute();
            $existe->store_result();
            if ($existe->num_rows === 0) {
                $stmt = $conexion->prepare("INSERT INTO lk_aulas (nombre) VALUES (?)");
                $stmt->bind_param("s", $nombre);
                $stmt->execute();
                $stmt->close();
            }
            $existe->close();
        } elseif (isset($_POST['actualizar'])) {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $conexion->prepare("UPDATE lk_aulas SET nombre = ? WHERE id = ?");
                $stmt->bind_param("si", $nombre, $id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    header("Location: aulas.php");
    exit;
}

if (!empty($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    if ($id) {
        $stmt = $conexion->prepare("DELETE FROM lk_aulas WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: aulas.php");
    exit;
}

$porPagina = 5;
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$inicio = ($pagina - 1) * $porPagina;
$total = $conexion->query("SELECT COUNT(*) AS total FROM lk_aulas")->fetch_assoc()['total'];
$totalPaginas = ceil($total / $porPagina);
$aulas = $conexion->query("SELECT * FROM lk_aulas ORDER BY nombre LIMIT $inicio, $porPagina");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Aulas - Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #010a18;
      color: #00eaff;
      font-family: 'Segoe UI', sans-serif;
      padding-bottom: 50px;
    }
    table {
      border: 1px solid #00eaff;
    }
    th, td {
      border: 1px solid #00eaff !important;
      color: #ffffff;
    }
    table tbody tr:hover {
      background-color: rgba(0, 234, 255, 0.2);
    }
    .neon-btn {
      background: linear-gradient(145deg, #00eaff, #00aaff);
      border: none;
      color: #000;
      font-weight: bold;
      transition: all 0.3s ease-in-out;
      box-shadow: 0 0 10px #00eaff, 0 0 20px #00aaff;
    }
    .neon-btn:hover {
      transform: scale(1.05);
      box-shadow: 0 0 15px #00eaff, 0 0 30px #00aaff;
    }
    .neon-form {
      border: 2px solid #00eaff;
      padding: 20px;
      border-radius: 12px;
      background-color: rgba(1, 10, 24, 0.95);
      box-shadow: 0 0 15px #00eaff;
    }
    .pagination a {
      color: #00eaff;
      margin: 0 5px;
      text-decoration: none;
    }
    .pagination a:hover {
      background: #00eaff;
      color: #000;
      padding: 4px 10px;
      border-radius: 6px;
    }
    .table-responsive {
      overflow-x: auto;
    }
    #modalFondo {
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0, 0, 0, 0.8);
      display: none;
      z-index: 999;
    }
    #modalEditar {
      position: fixed;
      top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      background: #010a18;
      color: #00eaff;
      padding: 25px;
      border: 2px solid #00eaff;
      border-radius: 15px;
      display: none;
      z-index: 1000;
      width: 90%;
      max-width: 400px;
      box-shadow: 0 0 25px #00eaff;
    }
    @media (max-width: 768px) {
      h2 {
        font-size: 1.5rem;
      }
      .neon-btn {
        font-size: 0.9rem;
      }
    }
  </style>
</head>
<body class="container py-4">

  <h2 class="text-center mb-4">Gestión de Aulas</h2>

  <div class="row g-4">
    <div class="col-lg-8 col-md-7">
      <div class="table-responsive">
        <table class="table table-dark table-bordered text-center align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Nombre</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($a = $aulas->fetch_assoc()): ?>
              <tr>
                <td><?= $a['id'] ?></td>
                <td><?= htmlspecialchars($a['nombre']) ?></td>
                <td>
                  <button class="neon-btn btn-sm" onclick="editar(<?= $a['id'] ?>, '<?= htmlspecialchars(addslashes($a['nombre'])) ?>')">Editar</button>
                  <a class="btn btn-danger btn-sm" href="?eliminar=<?= $a['id'] ?>" onclick="return confirm('¿Eliminar esta aula?')">Eliminar</a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>

      <div class="pagination text-center mt-3">
        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
          <a href="?pagina=<?= $i ?>"<?= $i === $pagina ? ' style="background:#00eaff;color:#000;border-radius:5px;padding:4px 10px;"' : '' ?>><?= $i ?></a>
        <?php endfor; ?>
      </div>
    </div>

    <div class="col-lg-4 col-md-5">
      <div class="neon-form">
        <form method="POST" onsubmit="return validar(this)">
          <div class="mb-3">
            <label for="nombreAgregar" class="form-label">Nombre de Aula:</label>
            <input type="text" class="form-control" id="nombreAgregar" name="nombre" required>
          </div>
          <button type="submit" name="registrar" class="neon-btn btn w-100">Agregar Aula</button>
        </form>
      </div>
    </div>
  </div>

  <div id="modalFondo" onclick="cerrarModal()"></div>

  <div id="modalEditar">
    <h4 class="text-center mb-3">Editar Aula</h4>
    <form method="POST" onsubmit="return validar(this)">
      <input type="hidden" id="idEditar" name="id" />
      <div class="mb-3">
        <label for="nombreEditar" class="form-label">Nombre de Aula:</label>
        <input type="text" class="form-control" id="nombreEditar" name="nombre" required />
      </div>
      <div class="d-flex justify-content-between">
        <button type="submit" name="actualizar" class="neon-btn btn">Actualizar</button>
        <button type="button" class="btn btn-secondary" onclick="cerrarModal()">Cancelar</button>
      </div>
    </form>
  </div>

  <script>
    function validar(f) {
      if (!f.nombre.value.trim()) {
        alert('El nombre de aula no puede estar vacío.');
        f.nombre.focus();
        return false;
      }
      return true;
    }

    function editar(id, nombre) {
      document.getElementById('idEditar').value = id;
      document.getElementById('nombreEditar').value = nombre;
      document.getElementById('modalEditar').style.display = 'block';
      document.getElementById('modalFondo').style.display = 'block';
    }

    function cerrarModal() {
      document.getElementById('modalEditar').style.display = 'none';
      document.getElementById('modalFondo').style.display = 'none';
    }
  </script>
</body>
</html>
