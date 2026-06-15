<?php
session_start();

if (!isset($_SESSION["id"])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Panel</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
body{
    background:#f5f7fb;
}

.hero{
    background: linear-gradient(135deg,#0d6efd,#6610f2);
    color:white;
    border-radius:20px;
}

.info-card{
    border:none;
    border-radius:15px;
}
</style>

</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow">
<div class="container">

<a class="navbar-brand fw-bold" href="#">
<i class="bi bi-speedometer2"></i>
 Panel de Usuario
</a>

<a href="logout.php" class="btn btn-danger">
<i class="bi bi-box-arrow-right"></i>
 Cerrar sesión
</a>

</div>
</nav>

<div class="container mt-5">

<div class="hero p-5 shadow mb-4">

<h1 class="fw-bold">
<i class="bi bi-person-circle"></i>
 Bienvenido <?php echo $_SESSION["nombre"]; ?>
</h1>

<p class="mb-0">
Has iniciado sesión correctamente.
</p>

</div>

<div class="row">

<div class="col-md-6 mb-3">

<div class="card info-card shadow">

<div class="card-body text-center">

<i class="bi bi-person-fill fs-1 text-primary"></i>

<h5 class="mt-3">Usuario</h5>

<h4 class="fw-bold">
<?php echo $_SESSION["usuario"]; ?>
</h4>

</div>

</div>

</div>

<div class="col-md-6 mb-3">

<div class="card info-card shadow">

<div class="card-body text-center">

<i class="bi bi-card-text fs-1 text-success"></i>

<h5 class="mt-3">Nombre Completo</h5>

<h4 class="fw-bold">
<?php echo $_SESSION["nombre"]; ?>
</h4>

</div>

</div>

</div>

</div>

<div class="card shadow border-0 mt-4">

<div class="card-body p-4">

<h4>
<i class="bi bi-house-check-fill text-primary"></i>
 Área Privada
</h4>

<hr>

<p class="text-muted mb-0">
Esta es una zona protegida. Solo los usuarios autenticados pueden acceder a esta información.
</p>

</div>

</div>

</div>

</body>
</html>