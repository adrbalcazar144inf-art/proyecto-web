<?php
session_start();
ini_set('display_errors',1); error_reporting(E_ALL);
require_once '../php/conexion.php';

if(!isset($_SESSION['rol'])){ header('Location: ../index.php'); exit; }

$nombre = htmlspecialchars($_SESSION['nombre'],ENT_QUOTES);
$rol    = ucfirst(htmlspecialchars($_SESSION['rol'],ENT_QUOTES));

if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Escanear QR - Gestión de Sillas</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{--primary-color:#0ff;--bg-dark:#0a0f1a;--bg-light:#05080f;--accent:#08f}
*{box-sizing:border-box}
body{margin:0;font-family:'Segoe UI',sans-serif;background:linear-gradient(135deg,#0a0f1a 0%,#05080f 100%);color:var(--primary-color);display:flex;flex-direction:column;min-height:100vh}
.topbar{background:var(--bg-light);display:flex;justify-content:space-between;align-items:center;padding:1rem 2rem;box-shadow:0 4px 15px rgba(0,255,255,0.3);border-bottom:1px solid var(--primary-color)}
.topbar .btn-logout{border:2px solid var(--primary-color);color:var(--primary-color);background:transparent;border-radius:10px;padding:.5rem 1.2rem;font-weight:bold;transition:.3s}
.topbar .btn-logout:hover{background:var(--primary-color);color:var(--bg-dark);box-shadow:0 0 15px var(--primary-color),0 0 30px rgba(0,255,255,.5)}
.container{flex:1;padding:3rem 1rem;max-width:900px;margin:0 auto;text-align:center}
h2{font-size:2.5rem;margin-bottom:2rem;text-shadow:0 0 10px var(--primary-color),0 0 20px rgba(0,255,255,0.4)}
#reader{width:100%;aspect-ratio:1/1;max-width:600px;margin:0 auto 2rem;border-radius:20px;overflow:hidden;box-shadow:0 0 40px var(--primary-color),0 0 60px rgba(0,255,255,0.3);background:#000}
#controls{display:flex;flex-wrap:wrap;justify-content:center;gap:1rem;margin-bottom:1.5rem}
#zoomControl{width:160px}
.result{margin-top:1.5rem;font-size:1.3rem;padding:1.2rem 1rem;border-radius:15px;background:rgba(0,255,255,0.1);border:1px solid var(--primary-color);text-align:center;text-shadow:0 0 5px var(--primary-color)}
.btn-neon{border:2px solid var(--primary-color);color:var(--primary-color);background:transparent;border-radius:12px;padding:.7rem 1.5rem;font-weight:bold;transition:.3s;text-shadow:0 0 5px var(--primary-color)}
.btn-neon:hover{background:var(--primary-color);color:var(--bg-dark);box-shadow:0 0 15px var(--primary-color),0 0 30px rgba(0,255,255,0.5)}
.spinner-border{width:3rem;height:3rem;border-width:.4rem;color:var(--primary-color)}
#loading{text-align:center;margin-top:1rem;font-size:1rem;text-shadow:0 0 5px var(--primary-color)}
@media(min-width:768px){h2{font-size:3rem}#zoomControl{width:220px}}
@media(min-width:1200px){h2{font-size:3.5rem}#reader{max-width:800px}}
</style>
</head>
<body>
<div class="topbar">
  <div><?= $nombre ?> (<?= $rol ?>)</div>
  <a href="../logout.php" class="btn-logout">Cerrar Sesión</a>
</div>

<div class="container">
  <h2>Escanear QR de la Silla</h2>
  <div id="controls">
    <button id="fullscreenBtn" class="btn-neon">🖥️ Fullscreen</button>
    <input type="range" id="zoomControl" min="1" max="3" step="0.1" value="1">
  </div>
  <div id="reader"></div>
  <div id="loading"><div class="spinner-border" role="status"></div><p>Escaneando, por favor espere...</p></div>
  <div id="resultBox" class="result d-none"></div>
  <button id="restartBtn" class="btn-neon d-none mt-3">🔄 Escanear Otro</button>
</div>
<audio id="beep-sound" src="https://cdn.pixabay.com/download/audio/2022/03/15/audio_5e5b2da7c6.mp3?filename=beep-5-96243.mp3" preload="auto"></audio>
<script src="https://unpkg.com/html5-qrcode"></script>
<script>
const resultBox=document.getElementById('resultBox'),
      restartBtn=document.getElementById('restartBtn'),
      loading=document.getElementById('loading'),
      beepSound=document.getElementById('beep-sound'),
      zoomControl=document.getElementById('zoomControl'),
      fullscreenBtn=document.getElementById('fullscreenBtn');
let escaneado=false;
function sonarBeep(){beepSound.play().catch(()=>{});}
function onScanSuccess(texto){
  if(escaneado) return;
  escaneado=true; sonarBeep();
  loading.classList.add('d-none');
  resultBox.textContent=texto.trim() ? `✅ QR Escaneado: ${texto}` : "❌ Código no válido";
  resultBox.classList.remove('d-none');
  html5QrcodeScanner.clear().then(()=>{
    if(!texto.trim()){restartBtn.classList.remove('d-none'); return;}
    const url=texto.startsWith('http') ? texto : 'prestamo_qr_scan.php?id='+encodeURIComponent(texto);
    setTimeout(()=>window.location.href=url,1200);
  }).catch(err=>{console.error(err); resultBox.textContent="❌ Error al detener el escáner."; restartBtn.classList.remove('d-none');});
}
restartBtn.onclick=()=>{
  escaneado=false;
  resultBox.classList.add('d-none');
  restartBtn.classList.add('d-none');
  loading.classList.remove('d-none');
  html5QrcodeScanner.render(onScanSuccess,()=>{});
};
fullscreenBtn.onclick=()=>{!document.fullscreenElement?document.documentElement.requestFullscreen().catch(()=>{}):document.exitFullscreen();}
zoomControl.oninput=()=>{
  const video=document.querySelector('#reader video');
  if(!video||!video.srcObject) return;
  const [track]=video.srcObject.getVideoTracks();
  const caps=track.getCapabilities();
  if(!caps.zoom) return;
  const z=Math.min(Math.max(zoomControl.value,caps.zoom.min),caps.zoom.max);
  track.applyConstraints({advanced:[{zoom:z}]}).catch(()=>{});
};
const html5QrcodeScanner=new Html5QrcodeScanner("reader",{fps:10,qrbox:{width:800,height:800},experimentalFeatures:{useBarCodeDetectorIfSupported:true}});
html5QrcodeScanner.render(onScanSuccess,()=>{});
</script>
</body>
</html>
