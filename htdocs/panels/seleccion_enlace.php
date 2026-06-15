<?php session_start();

if(!isset($_SESSION['nombre'],$_SESSION['rol'])){header('Location:login.php');exit;} $rol=$_SESSION['rol'];$paneles=['estudiante'=>'panel_estudiante.php','docente'=>'panel_docente.php','superusuario'=>'panel_superusuario.php'];$panel=$paneles[$rol]??'login.php';?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
body{background:radial-gradient(circle at top,#111,#000);color:#fff}
.card-btn{background:rgba(255,255,255,.10);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.15);transition:.3s}
.card-btn:hover{transform:translateY(-5px) scale(1.02);background:rgba(255,255,255,.18)}
.icon{font-size:2.6rem}
.title{font-weight:800;text-shadow:0 2px 10px rgba(0,0,0,.6)}
.card-title{color:#fff;font-weight:700;text-shadow:0 2px 8px rgba(0,0,0,.8)}
.role-badge{font-size:.9rem;padding:6px 12px}
</style>
</head>

<body>

<div class="container py-5 d-flex justify-content-center align-items-center min-vh-100">
<div class="w-100" style="max-width:900px">

<!-- HEADER -->
<div class="text-center mb-4">
<i class="bi bi-person-circle display-1"></i>
<h2 class="mt-2 title"><?=htmlspecialchars($_SESSION['nombre'])?></h2>
<span class="badge bg-light text-dark role-badge"><?=ucfirst($rol)?></span>
</div>

<!-- GRID -->
<div class="row g-3">

<div class="col-12 col-md-6">
<a href="/panels/<?=$panel?>" class="text-decoration-none">
<div class="card card-btn text-center p-4 rounded-4">
<i class="bi bi-grid-1x2-fill icon text-warning"></i>
<h5 class="card-title mt-2">Panel</h5>
</div></a>
</div>

<div class="col-12 col-md-6">
<a href="/../TOOLS/sms.php" class="text-decoration-none">
<div class="card card-btn text-center p-4 rounded-4">
<i class="bi bi-headset icon text-info"></i>
<h5 class="card-title mt-2">Soporte</h5>
</div></a>
</div>

<div class="col-12 col-md-6">
<a href="/asistencia/activar_geo.php" class="text-decoration-none">
<div class="card card-btn text-center p-4 rounded-4">
<i class="bi bi-geo-alt-fill icon text-success"></i>
<h5 class="card-title mt-2">Asistencia</h5>
</div></a>
</div>

<div class="col-12 col-md-6">
<a href="AV.php" class="text-decoration-none">
<div class="card card-btn text-center p-4 rounded-4">
<i class="bi bi-robot icon text-warning"></i>
<h5 class="card-title mt-2">Chatbot</h5>
</div></a>
</div>

<div class="col-12">
<a href="logout.php" class="text-decoration-none">
<div class="card card-btn text-center p-3 rounded-4 bg-danger bg-opacity-75">
<i class="bi bi-box-arrow-right icon"></i>
<h5 class="card-title mt-2">Salir</h5>
</div></a>
</div>

</div>

</div>
</div>

</body>
</html>