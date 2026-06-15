<?php
session_start();
ini_set('display_errors',1); error_reporting(E_ALL);

if (!isset($_SESSION['user_id'], $_SESSION['rol'])) { header('Location: ../index.php'); exit; }

$nombre = htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8');
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

if (isset($_GET['qr'])) {
    require_once __DIR__ . '/../lib/phpqrcode/qrlib.php';
    header('Content-Type: image/png');
    QRcode::png("https://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['SCRIPT_NAME']) . "/form_asistencia.php", null, QR_ECLEVEL_L, 6, 2);
    exit;
}
if (isset($_GET['set_geo'])) { $_SESSION['geo_activada'] = true; exit; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Activar Geolocalización</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root { --neon-primary: #0ff; --neon-secondary: #5ff; --bg-dark: #000010; }
body { background: var(--bg-dark); color: var(--neon-primary); font-family: 'Segoe UI', sans-serif; }
.top-bar { display:flex; justify-content:space-between; align-items:center; padding:1rem 2rem; background:rgba(0,0,0,0.7); border-bottom:2px solid var(--neon-primary); box-shadow:0 0 10px var(--neon-primary); }
.top-bar span { font-weight:bold; text-shadow:0 0 5px var(--neon-primary); }
.logout-btn { background:transparent; color:var(--neon-primary); border:1px solid var(--neon-primary); padding:0.4rem 1rem; border-radius:.5rem; text-decoration:none; transition:.3s; }
.logout-btn:hover { background:var(--neon-primary); color:#000; box-shadow:0 0 10px var(--neon-primary); }
.card { background:rgba(0,0,0,0.85); border:2px solid var(--neon-primary); border-radius:1rem; box-shadow:0 0 20px var(--neon-primary); max-width:360px; margin:3rem auto; padding:2rem; text-align:center; }
.card h2 { margin-bottom:.5rem; text-shadow:0 0 10px var(--neon-primary); }
.card p { color:var(--neon-secondary); margin-bottom:1.5rem; }
#qr-box { position:relative; width:100%; padding-top:100%; border:3px dashed var(--neon-primary); border-radius:.75rem; background:rgba(0,0,0,0.5); opacity:.3; transition:.4s; }
#qr-box.unlocked { opacity:1; }
#enable-loc { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:var(--neon-primary); color:#000; border:none; padding:.75rem 1.5rem; border-radius:.75rem; font-weight:bold; box-shadow:0 0 10px var(--neon-primary); cursor:pointer; }
#qr-img { position:absolute; top:0; left:0; width:100%; height:100%; object-fit:contain; display:none; cursor:pointer; border-radius:.75rem; }
@media (max-width:576px) { .card { margin:2rem 1rem; padding:1.5rem; } .top-bar { padding:0.8rem 1rem; } }
</style>
</head>
<body>
<div class="top-bar">
  <span>👤 <?= $nombre; ?></span>
  <a href="../php/logout.php" class="logout-btn">Cerrar Sesión</a>
</div>

<div class="card">
  <h2>Generar QR de Asistencia</h2>
  <p>Activa tu ubicación para continuar</p>
  <div id="qr-box">
    <button id="enable-loc">Activar Ubicación</button>
    <img id="qr-img" src="?qr=1" alt="QR Dinámico">
  </div>
</div>

<script>
const btn = document.getElementById('enable-loc'), box = document.getElementById('qr-box'), qr = document.getElementById('qr-img');
btn.addEventListener('click', () => {
  if (!navigator.geolocation) return alert('Navegador no soporta geolocalización.');
  navigator.geolocation.getCurrentPosition(() => {
    qr.style.display = 'block'; box.classList.add('unlocked'); btn.style.display = 'none';
  }, () => alert('Debes permitir la ubicación para continuar.'), { enableHighAccuracy:true });
});
qr.addEventListener('click', () => { fetch('?set_geo=1').then(() => location.href = 'form_asistencia.php'); });
</script>
</body>
</html>
