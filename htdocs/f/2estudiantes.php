<?php
include("../TOOLS/conexion.php");
$conn = conectarse();

function obtenerAulas($c){
    $aulas = [];
    $rA = $c->query("SELECT id, nombre FROM lk_aulas ORDER BY nombre");
    if ($rA) while ($f = $rA->fetch_assoc()) $aulas[] = $f;
    return $aulas;
}

function obtenerTurnos($c){
    $turnos = [];
    $rT = $c->query("SELECT id, nombre FROM lk_turnos ORDER BY id");
    if ($rT) while ($f = $rT->fetch_assoc()) $turnos[] = $f;
    return $turnos;
}
function obtenerEstudiantesConAsistencia($c, $fecha, $turnoId, $aulaId){
    $d = [];
    $sql = "SELECT 
              u.id,
              CONCAT(u.nombre, ' ', u.paterno, ' ', u.materno) AS nombre_completo,
              COALESCE(asi.aula_id, 0) AS aula_id,
              COALESCE(a.nombre, 'Sin aula') AS aula_nombre,
              COALESCE(asi.estado_asistencia, 'ausente') AS estado_asistencia,
              COALESCE(asi.gps_activo, 0) AS gps_activo
            FROM usuarios u
            LEFT JOIN (
                SELECT usuario_id, aula_id,
                       'presente' AS estado_asistencia, 
                       IF(ubicacion_gps IS NOT NULL, 1, 0) AS gps_activo
                FROM asistencias
                WHERE fecha = ? AND turno_id = ?
            ) asi ON asi.usuario_id = u.id
            LEFT JOIN lk_aulas a ON a.id = asi.aula_id
            WHERE u.rol = 'estudiante'";

    if ($aulaId !== 'todos') {
        $sql .= " AND asi.aula_id = ?";
    }

    $sql .= " ORDER BY a.nombre, u.paterno";

    if ($stmt = $c->prepare($sql)) {
        if ($aulaId !== 'todos') {
            $stmt->bind_param("sis", $fecha, $turnoId, $aulaId);
        } else {
            $stmt->bind_param("si", $fecha, $turnoId);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($f = $res->fetch_assoc()) {
            $d[] = $f;
        }
        $stmt->close();
    }
    return $d;
}


// Parámetros filtro
$fecha = $_GET['fecha'] ?? date('Y-m-d');
$turnoId = isset($_GET['turno']) ? (int)$_GET['turno'] : 1;
$aulaId = $_GET['aula'] ?? 'todos';

$aulas = obtenerAulas($conn);
$turnos = obtenerTurnos($conn);
$estudiantes = obtenerEstudiantesConAsistencia($conn, $fecha, $turnoId, $aulaId);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Croquis Estudiantes</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
<style>
:root{--neon:#0ff;--bg:#000;--text:#e0e0e0;--box-size:80px;}
body{margin:20px auto;max-width:1000px;background:var(--bg);color:var(--text);font-family:'Segoe UI',sans-serif}
h2{color:var(--neon);text-align:center;text-shadow:0 0 10px var(--neon);margin-bottom:30px}
.neon-box{background:#111;border:2px solid var(--neon);box-shadow:0 0 15px var(--neon);border-radius:10px;padding:15px;font-weight:700;text-align:center;margin:0 auto 20px}
.btn-neon,select.select-neon,input[type=date]{background:transparent;border:2px solid var(--neon);color:var(--neon);border-radius:30px;padding:6px 12px;font-weight:700;box-shadow:0 0 8px var(--neon);cursor:pointer;transition:.3s ease}
.btn-neon:hover,select.select-neon:hover,select.select-neon:focus,input[type=date]:focus{background:var(--neon);color:var(--bg);box-shadow:0 0 20px var(--neon),0 0 30px var(--neon);outline:none}
#filtros{display:flex;flex-wrap:wrap;gap:1rem;justify-content:center;margin-bottom:20px}
#leyenda{text-align:center;margin-bottom:20px}
#leyenda p{display:inline-block;margin-right:15px;user-select:none}
#leyenda span{display:inline-block;width:18px;height:18px;border-radius:4px;box-shadow:0 0 5px var(--neon);margin-right:6px}
#leyenda .gps-activo{border:2px solid #0ff;display:inline-block;width:18px;height:18px;border-radius:50%;vertical-align:middle;margin-right:6px;box-shadow:0 0 8px #0ff;}
#croquisContainer{max-width:900px;height:650px;margin:0 auto;background:#111;border-radius:12px;box-shadow:0 0 20px var(--neon);overflow-y:auto}
canvas{display:block;margin:20px auto 10px;background:#111;border-radius:12px;user-select:none;width:900px;height:auto!important}
#sizeControl{width:180px;margin:0 auto 20px;display:block;accent-color:var(--neon);cursor:pointer}
@media(max-width:940px){body,#croquisContainer,canvas{width:95%}#croquisContainer{height:500px}}
@media(max-width:576px){#filtros{flex-direction:column;align-items:center}select.select-neon,input[type=date]{width:100%;max-width:300px}#sizeControl{width:100%;max-width:300px}}
</style>
</head>
<body>

<h2>Croquis de Estudiantes</h2>

<div id="filtros">
  <div>
    <label for="fecha" style="font-weight:700;">Fecha:</label><br>
    <input type="date" id="fecha" value="<?= htmlspecialchars($fecha) ?>" />
  </div>
  <div>
    <label for="turno" style="font-weight:700;">Turno:</label><br>
    <select id="turno" class="select-neon">
      <?php foreach ($turnos as $t): ?>
        <option value="<?= $t['id'] ?>" <?= $t['id'] == $turnoId ? 'selected' : '' ?>><?= ucfirst($t['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label for="aula" style="font-weight:700;">Aula:</label><br>
    <select id="aula" class="select-neon">
      <option value="todos" <?= $aulaId === 'todos' ? 'selected' : '' ?>>Todos</option>
      <?php foreach ($aulas as $a): ?>
        <option value="<?= $a['id'] ?>" <?= $a['id'] == $aulaId ? 'selected' : '' ?>><?= $a['nombre'] ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<label for="sizeControl" style="color: var(--neon);text-align:center;display:block;margin-bottom:10px;">
  Tamaño cuadros: <span id="sizeValue">80</span> px
</label>
<input type="range" id="sizeControl" min="60" max="200" value="100"/>


<div id="resumen" class="neon-box"></div>

<div id="leyenda">
  <p><span style="background:#0f0;"></span> Presente</p>
  <p><span style="background:#f00;"></span> Ausente</p>
  <p><span class="gps-activo"></span> GPS activo</p>
</div>

<div id="croquisContainer">
  <canvas id="croquis" width="900" height="600"></canvas>
</div>

<script>
const estudiantes = <?= json_encode($estudiantes) ?>;
const canvas = document.getElementById('croquis');
const ctx = canvas.getContext('2d');
const fechaInput = document.getElementById('fecha');
const turnoSelect = document.getElementById('turno');
const aulaSelect = document.getElementById('aula');
const sizeControl = document.getElementById('sizeControl');
const sizeValue = document.getElementById('sizeValue');
const resumenDiv = document.getElementById('resumen');

let boxSize = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--box-size')) || 80;

const colores = {
  presente: '#0f0',
  ausente: '#f00'
};

let auraFrame = 0; // contador para animación

function cargarCroquis() {
  ctx.clearRect(0, 0, canvas.width, canvas.height);

  const porFila = Math.floor(canvas.width / (boxSize + 40)); 
  const gapX = boxSize + 30;
  const gapY = boxSize + 40;
  const filas = Math.ceil(estudiantes.length / porFila) || 1;

  canvas.height = 60 + filas * gapY;

  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';

  let x = 40;
  let y = 40;
  let presentes = 0;
  let ausentes = 0;

  estudiantes.forEach((e, i) => {
    const estado = e.estado_asistencia.toLowerCase();
    const color = colores[estado] || '#555';
    if (estado === 'presente') presentes++;
    else if (estado === 'ausente') ausentes++;

    // Fondo cuadro
    ctx.shadowColor = color;
    ctx.shadowBlur = 15;
    ctx.fillStyle = color;
    ctx.fillRect(x, y, boxSize, boxSize);

    // Animación aura si GPS activo
if (parseInt(e.gps_activo) === 1) {
  const intensidad = (Math.sin(auraFrame / 10) + 1) / 2;
  const glow = 10 + intensidad * 20;

  ctx.shadowColor = '#0ff';
  ctx.shadowBlur = glow;
  ctx.strokeStyle = `rgba(0,255,255,${0.7 + 0.3 * intensidad})`;
  ctx.lineWidth = 4;
  ctx.strokeRect(x - 3, y - 3, boxSize + 6, boxSize + 6);

  // Texto "GPS ON"
  ctx.shadowBlur = 0;
  ctx.fillStyle = '#0ff';
  ctx.font = `${Math.max(boxSize / 6, 10)}px 'Segoe UI'`;
  ctx.fillText("GPS ON", x + boxSize / 2, y + boxSize - 12);

} else {
  // Texto "GPS OFF"
  ctx.shadowBlur = 0;
  ctx.fillStyle = '#888';
  ctx.font = `${Math.max(boxSize / 6, 10)}px 'Segoe UI'`;
  ctx.fillText("GPS OFF", x + boxSize / 2, y + boxSize - 12);
}


    // Texto nombre dentro del cuadro
    ctx.shadowBlur = 0;
    ctx.fillStyle = '#000';
    let fontSize = Math.max(boxSize / 6, 12);
    ctx.font = `${fontSize}px 'Segoe UI'`;

    const palabras = e.nombre_completo.split(" ");
    let lineY = y + boxSize / 2 - (palabras.length - 1) * (fontSize / 2);

    palabras.forEach(p => {
      ctx.fillText(p, x + boxSize / 2, lineY);
      lineY += fontSize;
    });

    // Posición siguiente
    x += gapX;
    if ((i + 1) % porFila === 0) {
      x = 40;
      y += gapY;
    }
  });

  resumenDiv.textContent = `Presentes: ${presentes} | Ausentes: ${ausentes}`;
}
function animar() {
  auraFrame++;
  cargarCroquis();
  requestAnimationFrame(animar);
}

animar();

// Cambiar tamaño cuadro
sizeControl.addEventListener('input', (e) => {
  boxSize = parseInt(e.target.value);
  sizeValue.textContent = boxSize;
  document.documentElement.style.setProperty('--box-size', boxSize + 'px');
  cargarCroquis();
});

// Recargar página con filtros nuevos
function recargar() {
  const fecha = fechaInput.value;
  const turno = turnoSelect.value;
  const aula = aulaSelect.value;
  const url = new URL(window.location.href);
  url.searchParams.set('fecha', fecha);
  url.searchParams.set('turno', turno);
  url.searchParams.set('aula', aula);
  window.location.href = url.toString();
}

fechaInput.addEventListener('change', recargar);
turnoSelect.addEventListener('change', recargar);
aulaSelect.addEventListener('change', recargar);

cargarCroquis();
</script>

</body>
</html>
