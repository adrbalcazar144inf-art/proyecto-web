<?php
session_start();
require '../TOOLS/conexion.php';

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $usuario = trim($_POST["usuario"]);
    $password = $_POST["password"];

    $stmt = $conn->prepare(
        "SELECT id,nombre,usuario,password
         FROM usuarios1
         WHERE usuario=?"
    );

    $stmt->bind_param("s", $usuario);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows == 1) {

        $fila = $resultado->fetch_assoc();

        if (password_verify($password, $fila["password"])) {

            $_SESSION["id"] = $fila["id"];
            $_SESSION["nombre"] = $fila["nombre"];
            $_SESSION["usuario"] = $fila["usuario"];

            header("Location: panel.php");
            exit;
        }
    }

    $mensaje = "Usuario o contraseña incorrectos";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Login</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
body{
    min-height:100vh;
    background: linear-gradient(135deg,#0d6efd,#6610f2);
}

.login-card{
    border:none;
    border-radius:20px;
    overflow:hidden;
}

.logo{
    width:80px;
    height:80px;
    background:#0d6efd;
    color:white;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:35px;
    margin:auto;
}
</style>

</head>
<body>

<div class="container">

<div class="row justify-content-center align-items-center vh-100">

<div class="col-md-5 col-lg-4">

<div class="card login-card shadow-lg">

<div class="card-body p-5">

<div class="text-center mb-4">

<div class="logo mb-3">
<i class="bi bi-person-fill-lock"></i>
</div>

<h2 class="fw-bold">Bienvenido</h2>
<p class="text-muted">Ingresa tus credenciales</p>

</div>

<?php if($mensaje!=""){ ?>
<div class="alert alert-danger text-center">
<i class="bi bi-exclamation-triangle-fill"></i>
<?php echo $mensaje; ?>
</div>
<?php } ?>

<form method="POST">

<div class="mb-3">
<label class="form-label">
<i class="bi bi-person-fill"></i> Usuario
</label>
<input
type="text"
name="usuario"
class="form-control form-control-lg"
placeholder="Ingrese su usuario"
required>
</div>

<div class="mb-4">
<label class="form-label">
<i class="bi bi-lock-fill"></i> Contraseña
</label>
<input
type="password"
name="password"
class="form-control form-control-lg"
placeholder="Ingrese su contraseña"
required>
</div>

<button class="btn btn-primary btn-lg w-100">
<i class="bi bi-box-arrow-in-right"></i>
Ingresar
</button>

</form>

</div>

</div>

</div>

</div>

</div>

</body>
</html>