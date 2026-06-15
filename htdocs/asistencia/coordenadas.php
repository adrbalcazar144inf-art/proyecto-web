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
<title>HUD Ubicación en Vivo</title>
<style>
  body {
    margin: 0;
    background: #121212;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: #0ff;
    overflow: hidden;
  }
  #left-panel, #right-panel {
    position: fixed;
    top: 15px;
    font-weight: 700;
    font-size: 0.95rem;
    text-shadow:
      0 0 6px #0ff,
      0 0 12px #0ff,
      0 0 18px #0ff;
    user-select: none;
  }
  #left-panel {
    left: 15px;
    text-align: left;
    max-width: 280px;
  }
  #right-panel {
    right: 15px;
    text-align: right;
    max-width: 300px;
  }
  .title {
    display: block;
    color: #0ff;
    text-shadow:
      0 0 4px #0ff,
      0 0 10px #0ff,
      0 0 20px #0ff;
    margin-bottom: 6px;
    font-size: 1.1rem;
  }
  .data-item {
    margin-bottom: 5px;
    color: #0ff;
    text-shadow:
      0 0 5px #0ff,
      0 0 15px #0ff;
  }
  #message {
    margin-top: 8px;
    font-weight: 800;
  }
  #message.inside {
    color: #0f0;
    text-shadow:
      0 0 8px #0f0,
      0 0 15px #0f0;
  }
  #message.outside {
    color: #f00;
    text-shadow:
      0 0 8px #f00,
      0 0 15px #f00;
  }
  #location-status {
    margin-top: 6px;
    font-weight: 700;
    font-size: 0.85rem;
    color: #ff0;
    text-shadow:
      0 0 5px #ff0,
      0 0 10px #ff0;
  }
  #right-panel .data-item-inline {
    white-space: nowrap;
    display: flex;
    gap: 8px;
    align-items: center;
    justify-content: flex-end;
    color: #0ff;
    text-shadow:
      0 0 5px #0ff,
      0 0 15px #0ff;
  }
</style>
</head>
<body>
  <div id="left-panel" aria-label="Información escuela y ubicación">
    <span class="title">Escuela</span>
    <span class="data-item" id="school-coords">Cargando...</span>
    <span class="title" style="margin-top:12px;">Mi Ubicación</span>
    
    <span class="data-item" id="user-coords">Esperando...</span>
    
     <span class="title" style="margin-top:12px;">Velocidad</span>
    <div class="data-item-inline">
      <span id="speed-ms">---</span> m/s /
      <span id="speed-kmh">---</span> km/h -
      <span id="movement-status">---</span>
    </div>
  </div>
  <div id="right-panel" aria-label="Distancia y velocidad">
    <span class="title">Distancia</span>
    <div class="data-item-inline">
      <span id="distance">---</span> m
    </div>
     <ul aria-label="Estados de ubicación" style="list-style:none; padding-left:0; margin-top:10px;">
  <li>
    <span id="location-status" aria-live="polite" aria-atomic="true">Solicitando permiso para ubicación...</span>
  </li>
  <li>
    <span id="message" class="data-item" aria-live="polite" aria-atomic="true">---</span>
  </li>
</ul>
  </div>
<script>
  const schoolLat = -16.475498;
  const schoolLng = -68.1515059;
  const status = document.getElementById('location-status');
  const messageEl = document.getElementById('message');
  document.getElementById('school-coords').textContent = `${schoolLat.toFixed(6)}, ${schoolLng.toFixed(6)}`;

  function success(position) {
    const userLat = position.coords.latitude;
    const userLng = position.coords.longitude;
    const speedMs = position.coords.speed; // velocidad en m/s (puede ser null)

    document.getElementById('user-coords').textContent = `${userLat.toFixed(6)}, ${userLng.toFixed(6)}`;

    if (speedMs !== null) {
      const speedKmh = speedMs * 3.6;
      document.getElementById('speed-ms').textContent = speedMs.toFixed(2);
      document.getElementById('speed-kmh').textContent = speedKmh.toFixed(2);
      document.getElementById('movement-status').textContent = speedMs > 0.1 ? 'En movimiento' : 'Parado';
    } else {
      document.getElementById('speed-ms').textContent = 'No disponible';
      document.getElementById('speed-kmh').textContent = 'No disponible';
      document.getElementById('movement-status').textContent = 'No disponible';
    }

    status.textContent = 'Validando ubicación...';

    fetch('', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: `lat=${userLat}&lng=${userLng}`
    })
    .then(res => res.json())
    .then(data => {
      if (data.error) {
        messageEl.textContent = 'Error: ' + data.error;
        messageEl.className = 'data-item outside';
        return;
      }
      document.getElementById('distance').textContent = data.distance;
      messageEl.textContent = data.message;
      messageEl.className = data.inside ? 'data-item inside' : 'data-item outside';
    })
    .catch(() => {
      messageEl.textContent = 'Error de comunicación con servidor.';
      messageEl.className = 'data-item outside';
    });
  }

  function error(err) {
    let msg = {
      1: 'Permiso denegado. Por favor, activa la ubicación en tu navegador.',
      2: 'Ubicación no disponible.',
      3: 'Tiempo agotado.'
    }[err.code] || 'Error desconocido.';
    status.textContent = msg;
    messageEl.textContent = '---';
    messageEl.className = 'data-item';
  }

  function startWatchingLocation() {
    if (!navigator.geolocation) {
      status.textContent = 'Geolocalización no soportada.';
      messageEl.textContent = '---';
      return;
    }
    navigator.geolocation.watchPosition(success, error, {
      enableHighAccuracy: true,
      maximumAge: 10000,
      timeout: 5000
    });
  }

  startWatchingLocation();Q
</script>
</body>
</html>
