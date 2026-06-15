<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $schoolLat = -16.475498;
    $schoolLng = -68.1515059;
    $radiusMeters = 867;
    $userLat = isset($_POST['lat']) ? floatval($_POST['lat']) : null;
    $userLng = isset($_POST['lng']) ? floatval($_POST['lng']) : null;
    if ($userLat === null || $userLng === null) {
        echo json_encode(['error' => 'Faltan coordenadas']);
        exit;
    }
    function distanceMeters($lat1, $lng1, $lat2, $lng2) {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLng/2)**2;
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }
    $dist = distanceMeters($schoolLat, $schoolLng, $userLat, $userLng);
    $inside = $dist <= $radiusMeters;
    echo json_encode([
        'school' => ['lat' => $schoolLat, 'lng' => $schoolLng],
        'user' => ['lat' => $userLat, 'lng' => $userLng],
        'distance' => round($dist, 2),
        'radius' => $radiusMeters,
        'inside' => $inside,
        'message' => $inside ? "Estás dentro del radio permitido." : "Estás fuera del radio permitido."
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>HUD Ubicación en Vivo - Estilo Minecraft F3</title>
 <style>

@import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;800&display=swap');

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    overflow:hidden;
    min-height:100vh;

    font-family:'Orbitron',sans-serif;

    background:
        radial-gradient(circle at top left,#00ffff22,transparent 25%),
        radial-gradient(circle at bottom right,#00ff8822,transparent 25%),
        linear-gradient(135deg,#020617,#0f172a,#111827);

    color:#fff;
}

/* GRID ANIMADO */

body::before{
    content:'';
    position:fixed;
    inset:0;

    background:
        linear-gradient(rgba(255,255,255,.03) 1px,transparent 1px),
        linear-gradient(90deg,rgba(255,255,255,.03) 1px,transparent 1px);

    background-size:45px 45px;

    animation:gridMove 12s linear infinite;

    pointer-events:none;
}

@keyframes gridMove{
    from{
        transform:translateY(0);
    }
    to{
        transform:translateY(45px);
    }
}

/* HUD */

#hud-container{
    position:fixed;

    top:20px;
    left:20px;

    width:430px;
    max-width:92%;

    padding:24px;

    border-radius:28px;

    background:
        linear-gradient(145deg,
            rgba(15,23,42,.96),
            rgba(17,24,39,.90));

    border:1px solid rgba(0,255,255,.16);

    backdrop-filter:blur(18px);

    box-shadow:
        0 0 20px rgba(0,255,255,.08),
        0 0 50px rgba(0,255,255,.05),
        0 20px 60px rgba(0,0,0,.55);

    overflow:hidden;

    transform:
        perspective(1400px)
        rotateX(3deg)
        rotateY(-2deg);
}

/* EFECTOS */

#hud-container::before{
    content:'';
    position:absolute;

    width:240px;
    height:240px;

    border-radius:50%;

    background:#00ffff18;

    top:-100px;
    right:-100px;

    filter:blur(35px);
}

#hud-container::after{
    content:'';
    position:absolute;

    width:180px;
    height:180px;

    border-radius:50%;

    background:#00ff8815;

    bottom:-80px;
    left:-80px;

    filter:blur(35px);
}

/* TITULOS */

.section-title{
    position:relative;
    z-index:2;

    font-size:1.2rem;
    font-weight:800;

    margin-bottom:18px;

    letter-spacing:2px;

    color:#00ffff;

    text-shadow:
        0 0 8px #00ffff,
        0 0 18px #00ffff;
}

/* CARDS */

.data-line{
    position:relative;
    z-index:2;

    display:flex;
    justify-content:space-between;
    align-items:center;

    gap:12px;

    padding:14px 16px;

    margin-bottom:12px;

    border-radius:18px;

    background:rgba(255,255,255,.04);

    border:1px solid rgba(255,255,255,.05);

    transition:.25s ease;
}

.data-line:hover{
    transform:translateY(-2px) scale(1.01);

    background:rgba(255,255,255,.06);

    box-shadow:
        0 0 20px rgba(0,255,255,.08);
}

.data-label{
    color:#7dd3fc;

    font-weight:700;

    letter-spacing:.5px;

    text-transform:uppercase;

    font-size:.78rem;
}

.data-value{
    color:#fff;

    font-weight:700;

    text-align:right;

    text-shadow:
        0 0 10px rgba(255,255,255,.15);
}

/* ESTADOS */

.data-value.inside{
    color:#00ff88;

    text-shadow:
        0 0 8px #00ff88,
        0 0 18px #00ff88;
}

.data-value.outside{
    color:#ff4d6d;

    text-shadow:
        0 0 8px #ff4d6d,
        0 0 18px #ff4d6d;
}

/* STATUS */

#location-status{
    position:relative;
    z-index:2;

    margin-top:10px;

    padding:14px;

    border-radius:16px;

    background:rgba(255,255,0,.08);

    border:1px solid rgba(255,255,0,.16);

    color:#ffe066;

    font-size:.85rem;

    font-weight:700;

    text-shadow:
        0 0 8px rgba(255,255,0,.4);
}

/* BOTON */

#toggle-details{
    width:100%;

    margin-top:18px;

    padding:14px;

    border:none;
    outline:none;

    border-radius:18px;

    font-family:'Orbitron',sans-serif;

    font-weight:800;

    letter-spacing:1px;

    color:#fff;

    cursor:pointer;

    background:
        linear-gradient(135deg,#00c6ff,#0072ff);

    box-shadow:
        0 10px 25px rgba(0,114,255,.35);

    transition:.3s ease;
}

#toggle-details:hover{
    transform:translateY(-2px);

    box-shadow:
        0 15px 35px rgba(0,114,255,.45);
}

/* DETALLES */

#details{
    display:none;

    margin-top:20px;

    animation:fade .35s ease;
}

@keyframes fade{
    from{
        opacity:0;
        transform:translateY(10px);
    }
    to{
        opacity:1;
        transform:translateY(0);
    }
}

#details-scroll{
    max-height:260px;

    overflow-y:auto;

    padding-right:5px;
}

#details-scroll::-webkit-scrollbar{
    width:6px;
}

#details-scroll::-webkit-scrollbar-thumb{
    background:#00ffff66;
    border-radius:30px;
}

/* TOP */

.top-header{
    display:flex;
    justify-content:space-between;
    align-items:center;

    margin-bottom:20px;
}

.live-badge{
    padding:8px 14px;

    border-radius:999px;

    background:rgba(0,255,255,.08);

    border:1px solid rgba(0,255,255,.18);

    color:#00ffff;

    font-size:.72rem;

    font-weight:700;

    letter-spacing:1px;

    box-shadow:
        inset 0 0 10px rgba(0,255,255,.10);
}

/* MOBILE */

@media(max-width:600px){

    #hud-container{
        width:95%;
        left:2.5%;
        top:15px;
        padding:18px;
    }

    .section-title{
        font-size:1rem;
    }

    .data-line{
        flex-direction:column;
        align-items:flex-start;
    }

    .data-value{
        text-align:left;
    }

}

</style>
</head>
<body>
  <div id="hud-container" role="main" aria-label="Panel de ubicación estilo Minecraft F3">
    <div id="basic-info">
      <div class="section-title">Ubicación Básica</div>
      <div class="data-line"><span class="data-label">Escuela:</span> <span id="school-coords" class="data-value">Cargando...</span></div>
      <div class="data-line"><span class="data-label">Posición:</span> <span id="pos-x" class="data-value">---</span> (X), <span id="pos-y" class="data-value">---</span> (Y), <span id="pos-z" class="data-value">---</span> (Z)</div>
      <div class="data-line"><span class="data-label">Velocidad:</span> <span id="speed-ms" class="data-value">---</span> m/s / <span id="speed-kmh" class="data-value">---</span> km/h - <span id="movement-status" class="data-value">---</span></div>
      <div class="data-line"><span class="data-label">Distancia a escuela:</span> <span id="distance" class="data-value">---</span> m</div>
      <div class="data-line">Estado: <span id="inside-status" class="data-value">---</span></div>
      <div class="data-line" id="location-status" aria-live="polite" aria-atomic="true" style="color:#ff0; text-shadow: 0 0 5px #ff0;">Solicitando permiso para ubicación...</div>
      <button id="toggle-details" aria-expanded="false" aria-controls="details">Mostrar Más Detalles</button>
    </div>

    <div id="details" aria-hidden="true">
      <div class="section-title">Detalles de Ubicación</div>
      <div id="details-scroll">
        <div class="data-line"><span class="data-label" title="Latitud">Latitud (Z):</span> <span id="detail-latitude" class="data-value">---</span></div>
        <div class="data-line"><span class="data-label" title="Longitud">Longitud (X):</span> <span id="detail-longitude" class="data-value">---</span></div>
        <div class="data-line"><span class="data-label" title="Altitud (altura sobre el nivel del mar)">Altitud (Y):</span> <span id="detail-altitude" class="data-value">---</span></div>
        <div class="data-line"><span class="data-label" title="Precisión horizontal en metros">Precisión horizontal:</span> <span id="detail-accuracy" class="data-value">---</span> m</div>
        <div class="data-line"><span class="data-label" title="Precisión vertical (altitud)">Precisión altitud:</span> <span id="detail-altitude-accuracy" class="data-value">---</span> m</div>
        <div class="data-line"><span class="data-label" title="Rumbo / Dirección de movimiento en grados">Rumbo (grados):</span> <span id="detail-heading" class="data-value">---</span></div>
        <div class="data-line"><span class="data-label" title="Dirección cardinal">Dirección cardinal:</span> <span id="detail-cardinal" class="data-value">---</span></div>
        <div class="data-line"><span class="data-label" title="Timestamp de la lectura">Timestamp:</span> <span id="detail-timestamp" class="data-value">---</span></div>
      </div>
    </div>
  </div>

<script>
  const schoolLat = -16.475498;
  const schoolLng = -68.1515059;

  const schoolCoordsEl = document.getElementById('school-coords');
  const posXEl = document.getElementById('pos-x');
  const posYEl = document.getElementById('pos-y');
  const posZEl = document.getElementById('pos-z');
  const speedMsEl = document.getElementById('speed-ms');
  const speedKmhEl = document.getElementById('speed-kmh');
  const movementStatusEl = document.getElementById('movement-status');
  const distanceEl = document.getElementById('distance');
  const insideStatusEl = document.getElementById('inside-status');
  const locationStatusEl = document.getElementById('location-status');

  // Detalles:
  const detailsEl = document.getElementById('details');
  const toggleDetailsBtn = document.getElementById('toggle-details');
  const detailLatitudeEl = document.getElementById('detail-latitude');
  const detailLongitudeEl = document.getElementById('detail-longitude');
  const detailAltitudeEl = document.getElementById('detail-altitude');
  const detailAccuracyEl = document.getElementById('detail-accuracy');
  const detailAltitudeAccuracyEl = document.getElementById('detail-altitude-accuracy');
  const detailHeadingEl = document.getElementById('detail-heading');
  const detailCardinalEl = document.getElementById('detail-cardinal');
  const detailTimestampEl = document.getElementById('detail-timestamp');

  schoolCoordsEl.textContent = `${schoolLat.toFixed(6)}, ${schoolLng.toFixed(6)}`;

  // Función para convertir grados en dirección cardinal
  function degreesToCardinal(deg) {
    if (deg === null || deg === undefined || isNaN(deg)) return 'No disponible';
    const directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW', 'N'];
    const index = Math.round(deg / 45);
    return directions[index];
  }

  function success(position) {
    const coords = position.coords;
    const userLat = coords.latitude;
    const userLng = coords.longitude;
    const userAlt = coords.altitude;
    const speedMs = coords.speed;
    const accuracy = coords.accuracy;
    const altitudeAccuracy = coords.altitudeAccuracy;
    const heading = coords.heading;
    const timestamp = new Date(position.timestamp);

    // Posiciones X, Y, Z (longitude, altitude, latitude)
    posXEl.textContent = userLng.toFixed(6);
    posYEl.textContent = userAlt !== null ? userAlt.toFixed(2) : 'No disponible';
    posZEl.textContent = userLat.toFixed(6);

    // Velocidad
    if (speedMs !== null) {
      const speedKmh = speedMs * 3.6;
      speedMsEl.textContent = speedMs.toFixed(2);
      speedKmhEl.textContent = speedKmh.toFixed(2);
      movementStatusEl.textContent = speedMs > 0.1 ? 'En movimiento' : 'Parado';
    } else {
      speedMsEl.textContent = 'No disponible';
      speedKmhEl.textContent = 'No disponible';
      movementStatusEl.textContent = 'No disponible';
    }

    // Enviar coordenadas al servidor para calcular distancia y radio
    fetch('', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: `lat=${userLat}&lng=${userLng}`
    })
    .then(res => res.json())
    .then(data => {
      if (data.error) {
        insideStatusEl.textContent = 'Error: ' + data.error;
        insideStatusEl.className = 'data-value outside';
        distanceEl.textContent = '---';
        locationStatusEl.textContent = '';
        return;
      }
      distanceEl.textContent = data.distance;
      insideStatusEl.textContent = data.message;
      insideStatusEl.className = data.inside ? 'data-value inside' : 'data-value outside';
      locationStatusEl.textContent = 'Ubicación validada';
      locationStatusEl.style.color = '#ff0';
      locationStatusEl.style.textShadow = '0 0 5px #ff0';
    })
    .catch(() => {
      insideStatusEl.textContent = 'Error de comunicación con servidor.';
      insideStatusEl.className = 'data-value outside';
      distanceEl.textContent = '---';
      locationStatusEl.textContent = '';
    });

    // Detalles
    detailLatitudeEl.textContent = userLat.toFixed(6);
    detailLongitudeEl.textContent = userLng.toFixed(6);
    detailAltitudeEl.textContent = userAlt !== null ? userAlt.toFixed(2) : 'No disponible';
    detailAccuracyEl.textContent = accuracy !== null ? accuracy.toFixed(2) : 'No disponible';
    detailAltitudeAccuracyEl.textContent = altitudeAccuracy !== null ? altitudeAccuracy.toFixed(2) : 'No disponible';
    detailHeadingEl.textContent = heading !== null ? heading.toFixed(2) : 'No disponible';
    detailCardinalEl.textContent = degreesToCardinal(heading);
    detailTimestampEl.textContent = timestamp.toLocaleString();
  }

  function error(err) {
    const msgMap = {
      1: 'Permiso denegado. Por favor, activa la ubicación en tu navegador.',
      2: 'Ubicación no disponible.',
      3: 'Tiempo agotado.'
    };
    const msg = msgMap[err.code] || 'Error desconocido.';
    locationStatusEl.textContent = msg;
    insideStatusEl.textContent = '---';
    insideStatusEl.className = 'data-value';
    distanceEl.textContent = '---';
  }

  function startWatchingLocation() {
    if (!navigator.geolocation) {
      locationStatusEl.textContent = 'Geolocalización no soportada.';
      insideStatusEl.textContent = '---';
      return;
    }
    navigator.geolocation.watchPosition(success, error, {
      enableHighAccuracy: true,
      maximumAge: 10000,
      timeout: 5000
    });
  }

  toggleDetailsBtn.addEventListener('click', () => {
    const shown = detailsEl.style.display === 'block';
    detailsEl.style.display = shown ? 'none' : 'block';
    toggleDetailsBtn.textContent = shown ? 'Mostrar Más Detalles' : 'Ocultar Detalles';
    toggleDetailsBtn.setAttribute('aria-expanded', !shown);
    detailsEl.setAttribute('aria-hidden', shown);
  });

  startWatchingLocation();
</script>
</body>
</html>
