<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../../lib/phpqrcode/qrlib.php';
$carpeta = 'uploads/';
$datafile = 'data.json';
$base_url = 'https://dal9900.liveblog365.com/server/es/descargas.php';
if (!is_dir($carpeta)) mkdir($carpeta, 0777, true);
if (!file_exists($datafile)) file_put_contents($datafile, json_encode([]));
$data = json_decode(file_get_contents($datafile), true);
$permitidos = [
  // Audio
  'mp3','wav','ogg','flac','aac','m4a','wma','aiff','alac','opus',
  // Video
  'mp4','avi','mkv','mov','wmv','flv','webm','mpeg','mpg','3gp','mts','m2ts',
  // Imagenes
  'jpg','jpeg','png','gif','bmp','webp','tiff','svg','heic','raw','ico',
  // Documentos
  'pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv','rtf','odt','ods','odp','md','tex',
  // Archivos comprimidos
  'zip','rar','7z','tar','gz','bz2','xz','iso','dmg',
  // Código y scripts
  'php','html','htm','css','js','json','xml','sql','sh','bat','cmd','ps1','py','java','c','cpp','h','cs','go','rb','pl',
  // Fuentes
  'ttf','otf','woff','woff2','eot',
  // Otros
  'exe','dll','apk','bin','dat','db','dbf','log','bak','tmp','torrent'
];

if (isset($_GET['qr'])) {
  $token = $_GET['qr'];
  if (!isset($data[$token])) { header("HTTP/1.0 404 Not Found"); exit; }
  header('Content-Type: image/png');
  QRcode::png("$base_url?token=$token", false, QR_ECLEVEL_L, 5);
  exit;
}

if (isset($_GET['token'])) {
  $token = $_GET['token'];
  if (!isset($data[$token])) exit('❌ Link no válido.');
  $r = $data[$token];
  if ($r['expira'] && time() > $r['expira']) exit('⚠️ Este enlace ha expirado.');
  $archivo = $r['archivo'];
  $ruta = $carpeta . $archivo;
  if (!file_exists($ruta)) exit('❌ Archivo no encontrado.');

  $size = filesize($ruta);
  $tipo = pathinfo($archivo, PATHINFO_EXTENSION);
  $nombre = basename($archivo);
  $preview = preview_archivo($archivo);
  echo "<!DOCTYPE html>
  <html lang='es'><head><meta charset='UTF-8'>
  <title>Vista previa: $nombre</title>
  <meta name='viewport' content='width=device-width, initial-scale=1'>
  <style>
    body { background:#0d1117; color:#0ff; font-family:sans-serif; text-align:center; padding:20px; }
    .card { background:#001f2f; border:1px solid #0ff; padding:20px; max-width:500px; margin:auto; border-radius:10px; }
    .btn { display:inline-block; background:#0ff; color:#000; padding:10px 20px; font-weight:bold; border:none; border-radius:5px; text-decoration:none; margin-top:15px; }
    .btn:hover { background:#0cf; color:#fff; }
  </style></head><body>
  <div class='card'>
    <h2>$nombre</h2>
    <p><strong>Tipo:</strong> .$tipo</p>
    <p><strong>Tamaño:</strong> " . round($size / 1024, 2) . " KB</p>
    $preview
    <a class='btn' href='?descargar=$token'>⬇️ Descargar archivo</a>
  </div></body></html>";
  exit;
}

 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
  $tiempo = $_POST['expira'];
 $archivos = $_FILES['archivo'];
$total_archivos = count($archivos['name']);

if ($total_archivos === 1) {
  // Subida directa sin ZIP
  $nombreOriginal = basename($archivos['name'][0]);
  $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
  if (!in_array($ext, $permitidos)) exit('❌ Archivo no permitido.');
  
  $archivoFinal = 'file_' . substr(md5(uniqid()), 0, 10) . '.' . $ext;
  $rutaFinal = $carpeta . $archivoFinal;

    if (!move_uploaded_file($archivos['tmp_name'][0], $rutaFinal)) {
        exit('❌ No se pudo guardar archivo.');
    }

    $expira = $tiempo === '0' ? 0 : time() + ((int)$tiempo * 3600);
    $token = substr(md5(uniqid()), 0, 10);
    $data[$token] = ['archivo'=>$archivoFinal,'subido'=>time(),'expira'=>$expira,'descargas'=>0];
    file_put_contents($datafile, json_encode($data, JSON_PRETTY_PRINT));
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;

    } else {
    // Empaquetar varios archivos en ZIP
    $zipNombre = 'package_' . substr(md5(uniqid()),0,10) . '.zip';
    $zipRuta = $carpeta . $zipNombre;
    $zip = new ZipArchive();
    if ($zip->open($zipRuta, ZipArchive::CREATE) !== TRUE) exit('❌ No se pudo crear ZIP.');

    $agregados = 0;
    foreach ($archivos['name'] as $k => $n) {
        $ext = strtolower(pathinfo($n, PATHINFO_EXTENSION));
        if (!in_array($ext, $permitidos)) continue;
        $zip->addFile($archivos['tmp_name'][$k], basename($n));
        $agregados++;
    }
    $zip->close();
    if ($agregados === 0) { unlink($zipRuta); exit('❌ Ningún archivo válido.'); }

    $expira = $tiempo === '0' ? 0 : time() + ((int)$tiempo * 3600);
    $token = substr(md5(uniqid()), 0, 10);
    $data[$token] = ['archivo'=>$zipNombre,'subido'=>time(),'expira'=>$expira,'descargas'=>0];
    file_put_contents($datafile, json_encode($data, JSON_PRETTY_PRINT));
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
    }
  $agregados = 0;
  foreach ($_FILES['archivo']['name'] as $k => $n) {
    $ext = strtolower(pathinfo($n, PATHINFO_EXTENSION));
    if (!in_array($ext, $permitidos)) continue;
    $zip->addFile($_FILES['archivo']['tmp_name'][$k], basename($n));
    $agregados++;
  }
  $zip->close();
  if ($agregados === 0) { unlink($zipRuta); exit('❌ Ningún archivo válido.'); }

  $expira = $tiempo === '0' ? 0 : time() + ((int)$tiempo * 3600);
  $token = substr(md5(uniqid()), 0, 10);
  $data[$token] = ['archivo'=>$zipNombre,'subido'=>time(),'expira'=>$expira,'descargas'=>0];
  file_put_contents($datafile, json_encode($data, JSON_PRETTY_PRINT));
  header('Location: ' . $_SERVER['PHP_SELF']);
  exit;
}

function tiempo_restante($e) {
  if ($e == 0) return 'Permanente';
  $s = $e - time(); if ($s <= 0) return 'Expirado';
  $d = floor($s/86400); $h = floor(($s%86400)/3600); $m = floor(($s%3600)/60); $r = $s%60;
  return ($d?"$d d ":"") . ($h?"$h h ":"") . ($m?"$m m ":"") . "$r s";
}
function preview_archivo($a) {
  $e = strtolower(pathinfo($a, PATHINFO_EXTENSION));
  $r = "uploads/" . rawurlencode($a);

  // Extensiones que mostrarán miniatura real
  $imagenes = ['jpg','jpeg','png','gif','bmp','webp','svg','heic','ico'];
  $videos = ['mp4','webm','mov'];

  if (in_array($e, $imagenes)) {
    return "<img src='$r' class='img-fluid rounded mb-2' style='max-height:150px;'>";
  }
  if (in_array($e, $videos)) {
    return "<video controls class='w-100 mb-2' style='max-height:150px;'><source src='$r' type='video/$e'></video>";
  }

  return "<div class='d-flex flex-column align-items-center justify-content-center text-center' style='height: 150px; border: 2px dashed #0ff; border-radius: 8px;'>
    <div style='font-size: 3rem;'>📦</div>
    <small>$a</small>
  </div>";
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>📂 Centro de Descargas Mejorado</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
   body {
  background: #0d1117; color: #0ff; font-family: sans-serif;
}
.card, .form-control, .form-select {
  background: #001f2f; color: #0ff; border: 1px solid #0ff;
}
.btn-neon {
  background: #0ff; color: #000; font-weight: bold;
  transition: background .3s ease;
}
.btn-neon:hover {
  background: #0cf; color: #fff;
}
.qr-img {
  width: 120px; height: 120px;
}
.archivo-item {
  display: flex; flex-direction: column; height: 100%;
}
.preview-container {
  max-height: 150px; overflow: hidden; margin-bottom: 10px;
}
#drop-area {
  border: 2px dashed #0ff; border-radius: 8px; padding: 20px;
  text-align: center; color: #0ff; cursor: pointer;
  margin-bottom: 15px; transition: background-color .3s ease;
}
#drop-area.dragover {
  background-color: #0cf3; color: #004d4d;
}
#file-list {
  list-style: none; padding: 0; max-height: 150px;
  overflow-y: auto; color: #0ff;
}
#file-list li {
  display: flex; justify-content: space-between; align-items: center;
  background: #002a3a; margin-bottom: 5px; padding: 5px 10px;
  border-radius: 4px;
}
#file-list li button {
  background: #0cf; border: none; color: #000;
  font-weight: bold; cursor: pointer; padding: 2px 6px;
  border-radius: 3px; transition: background-color .2s ease;
}
#file-list li button:hover {
  background: #09a; color: #fff;
}
.vista-zip {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  justify-content: center;
  padding: 10px;
}
.vista-zip.lista {
  flex-direction: column;
  align-items: flex-start;
}
.item-archivo {
  background: #002a3a;
  border: 1px solid #0ff;
  border-radius: 6px;
  padding: 10px;
  color: #0ff;
  display: flex;
  align-items: center;
  width: 100%;
}
.vista-zip.cuadricula .item-archivo {
  flex-direction: column;
  align-items: center;
  width: 120px;
  text-align: center;
}
.item-archivo .icono {
  font-size: 2rem;
  margin-right: 10px;
}
.vista-zip.cuadricula .icono {
  margin: 0 0 5px 0;
}
.item-archivo .nombre {
  font-size: 0.9rem;
  word-break: break-all;
}

  </style>
</head>
<body>
<div class="container my-4">
  <h1 class="text-center mb-4">📥 Subir y Compartir Archivos</h1>

  <form id="upload-form" method="POST" enctype="multipart/form-data" class="mb-4">
    <div id="drop-area">
      <p>Arrastra y suelta archivos aquí o haz click para seleccionar</p>
      <input type="file" name="archivo[]" id="fileElem" multiple accept="<?= implode(',', array_map(fn($e) => '.' . $e, $permitidos)) ?>" style="display:none" required />
    </div>
    <ul id="file-list"></ul>

    <div class="row g-3 align-items-center">
      <div class="col-12 col-md-5">
        <!-- Input file oculto, manejado por drop-area -->
      </div>
      <div class="col-6 col-md-3">
        <select name="expira" class="form-select" required>
          <option value="1">Expira en 1 hora</option>
          <option value="6">Expira en 6 horas</option>
          <option value="12">Expira en 12 horas</option>
          <option value="24" selected>Expira en 24 horas</option>
          <option value="48">Expira en 48 horas</option>
          <option value="168">Expira en 7 días</option>
          <option value="0">Permanente</option>
        </select>
      </div>
      <div class="col-6 col-md-2 d-grid">
        <button type="submit" class="btn btn-neon" id="submit-btn" disabled>⬆️ Subir</button>
      </div>
    </div>
  </form>

  <h4 class="text-info mb-3">📋 Enlaces generados:</h4>
  <div class="row" id="lista-archivos">
    <?php foreach ($data as $token => $info):
      $enlace = "$base_url?token=$token";
      $expira = tiempo_restante($info['expira']);
    ?>
    <div class="col-12 col-sm-6 col-lg-4 mb-3 archivo-item">
      <div class="card p-3 text-white h-100 d-flex flex-column justify-content-between">
        <div>
          <h5><?= htmlspecialchars($info['archivo']) ?></h5>
          <div class="preview-container">
            <?= preview_archivo($info['archivo']) ?>
          </div>
          <p><strong>Expira en:</strong> <?= $expira ?></p>
          <p><strong>Descargas:</strong> <?= $info['descargas'] ?></p>
        </div>

        <div class="d-flex flex-column align-items-center mt-3">
          <img class="qr-img" src="?qr=<?= urlencode($token) ?>" alt="QR code" title="Escanea para descargar" />
          <button class="btn btn-neon mt-2 w-100 text-center" onclick="verContenido('<?= htmlspecialchars($info['archivo']) ?>', '<?= $token ?>', '<?= tiempo_restante($info['expira']) ?>', <?= $info['descargas'] ?>)">👁️ Ver contenido</button>
<a href="<?= $enlace ?>" target="_blank" class="btn btn-neon mt-2 w-100 text-center mt-2">⬇️ Descargar</a>

          <input type="text" class="form-control text-dark mt-2" value="<?= $enlace ?>" readonly
                 onclick="this.select(); document.execCommand('copy'); alert('Link copiado al portapapeles');" />
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<!-- Modal -->
<div id="modalArchivo" class="modal fade" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content text-light" style="background:#001f2f; border:2px solid #0ff;">
      <div class="modal-header">
        <h5 class="modal-title">Vista previa del archivo</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center" id="contenidoModal">
        <!-- Aquí se carga la vista previa -->
      </div>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function verContenido(nombre, token, expira, descargas) {
  const ext = nombre.split('.').pop().toLowerCase();
  const ruta = 'uploads/' + encodeURIComponent(nombre);
  let contenido = '';

  if (ext === 'zip') {
    fetch('ver_zip.php?token=' + token)
      .then(res => res.json())
      .then(data => {
        if (data.error) {
          contenido = `<p class="text-danger">${data.error}</p>`;
        } else {
          const vistaId = 'vistaArchivosZIP';
          contenido = `
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="m-0">📂 Contenido del archivo ZIP:</h6>
              <div>
                <button class="btn btn-sm btn-neon me-1" onclick="cambiarVistaZip('lista')">📄 Lista</button>
                <button class="btn btn-sm btn-neon" onclick="cambiarVistaZip('cuadricula')">🔳 Cuadrícula</button>
              </div>
            </div>
            <div id="${vistaId}" class="vista-zip lista">
              ${data.map(nombre => `
                <div class="item-archivo">
                  <div class="icono">📄</div>
                  <div class="nombre">${nombre}</div>
                </div>
              `).join('')}
            </div>
          `;
        }

        contenido += `<p class="mt-3"><strong>Expira en:</strong> ${expira}</p>
                      <p><strong>Descargas:</strong> ${descargas}</p>
                      <a href="?token=${token}" target="_blank" class="btn btn-neon">⬇️ Descargar</a>`;

        document.getElementById('contenidoModal').innerHTML = contenido;
        new bootstrap.Modal(document.getElementById('modalArchivo')).show();
      })
      .catch(err => {
        document.getElementById('contenidoModal').innerHTML = `<p class="text-danger">Error al leer ZIP.</p>`;
        new bootstrap.Modal(document.getElementById('modalArchivo')).show();
      });
    return;
  }

  // Vista previa normal
  if (['jpg','jpeg','png','gif','bmp','webp','svg','heic','ico'].includes(ext)) {
    contenido = `<img src="${ruta}" class="img-fluid rounded mb-3" style="max-height:300px;">`;
  } else if (['mp4','webm','mov'].includes(ext)) {
    contenido = `<video controls class="w-100 mb-3" style="max-height:300px;"><source src="${ruta}" type="video/${ext}"></video>`;
  } else {
    contenido = `<div class="d-flex flex-column align-items-center justify-content-center text-center" style="height: 200px; border: 2px dashed #0ff; border-radius: 8px;">
      <div style='font-size: 3rem;'>📦</div><small>${nombre}</small></div>`;
  }

  contenido += `<p><strong>Expira en:</strong> ${expira}</p>
                <p><strong>Descargas:</strong> ${descargas}</p>
                <a href="?token=${token}" target="_blank" class="btn btn-neon">⬇️ Descargar</a>`;

  document.getElementById('contenidoModal').innerHTML = contenido;
  new bootstrap.Modal(document.getElementById('modalArchivo')).show();
}

// Cambiar vista entre lista y cuadricula
function cambiarVistaZip(tipo) {
  const contenedor = document.getElementById('vistaArchivosZIP');
  contenedor.classList.remove('lista', 'cuadricula');
  contenedor.classList.add(tipo);
}
const drop = document.getElementById('drop-area'),
      fileElem = document.getElementById('fileElem'),
      fileList = document.getElementById('file-list'),
      btn = document.getElementById('submit-btn');
let archivos = [];

function actualizar() {
  fileList.innerHTML = '';
  archivos.forEach((f, i) => {
    let li = document.createElement('li');
    li.textContent = f.name;
    let del = document.createElement('button');
    del.textContent = 'X';
    del.onclick = () => { archivos.splice(i,1); actualizar(); inputSync(); };
    li.appendChild(del);
    fileList.appendChild(li);
  });
  btn.disabled = !archivos.length;
}

function inputSync() {
  let dt = new DataTransfer();
  archivos.forEach(f => dt.items.add(f));
  fileElem.files = dt.files;
}

drop.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('dragover'); });
drop.addEventListener('dragleave', e => { e.preventDefault(); drop.classList.remove('dragover'); });
drop.addEventListener('drop', e => {
  e.preventDefault(); drop.classList.remove('dragover');
  agregar(Array.from(e.dataTransfer.files));
});

drop.addEventListener('click', () => fileElem.click());
fileElem.addEventListener('change', () => agregar(Array.from(fileElem.files)));

function agregar(nuevos) {
  const permitidos = <?= json_encode($permitidos) ?>;
  nuevos.forEach(f => {
    let ext = f.name.split('.').pop().toLowerCase();
    if (!permitidos.includes(ext)) return alert(`❌ No permitido: ${f.name}`);
    if (!archivos.some(x => x.name === f.name && x.size === f.size)) archivos.push(f);
  });
  actualizar(); inputSync();
}

document.getElementById('upload-form').addEventListener('submit', e => {
  if (!archivos.length) { e.preventDefault(); alert('Selecciona archivos válidos.'); }
});
// Bloquear clic derecho
document.addEventListener('contextmenu', e => e.preventDefault());

// Bloquear clic derecho
document.addEventListener('contextmenu', e => e.preventDefault());

// Bloquear combinaciones de teclas
document.addEventListener('keydown', function(e) {
  // Tecla en mayúscula o minúscula, convertimos a mayúscula para comparar
  const key = e.key.toUpperCase();

  if (
    e.key === 'F12' || // F12
    (e.ctrlKey && e.shiftKey && ['I', 'J', 'C'].includes(key)) || // Ctrl+Shift+I/J/C
    (e.ctrlKey && key === 'U') // Ctrl+U
  ) {
    e.preventDefault();
    alert('🚫 Acción no permitida');
  }
});
</script>
