 <?php
include("../TOOLS/conexion.php");

$conn = conectarse();
$conn->set_charset('utf8mb4');
date_default_timezone_set('America/La_Paz');

function e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function obtenerAulas(mysqli $c): array {
    $aulas = [];

    $sql = "SELECT id, nombre FROM lk_aulas ORDER BY nombre ASC";

    if ($r = $c->query($sql)) {
        while ($f = $r->fetch_assoc()) {
            $aulas[] = $f;
        }
    }

    return $aulas;
}

function obtenerTurnos(mysqli $c): array {
    $turnos = [];

    $sql = "SELECT id, nombre FROM lk_turnos ORDER BY id ASC";

    if ($r = $c->query($sql)) {
        while ($f = $r->fetch_assoc()) {
            $turnos[] = $f;
        }
    }

    return $turnos;
}

function obtenerResumenAsistencia(mysqli $c, string $fecha, int $turnoId, string $aulaId): array {

    $resumen = [
        'total_estudiantes' => 0,
        'presentes' => 0,
        'ausentes' => 0,
        'gps_activo' => 0,
        'gps_inactivo' => 0
    ];

    $sql = "
        SELECT
            COUNT(*) AS total_estudiantes,
            SUM(CASE WHEN asi.usuario_id IS NOT NULL THEN 1 ELSE 0 END) AS presentes,
            SUM(CASE WHEN asi.usuario_id IS NULL THEN 1 ELSE 0 END) AS ausentes,
            SUM(CASE WHEN asi.usuario_id IS NOT NULL AND asi.ubicacion_gps IS NOT NULL THEN 1 ELSE 0 END) AS gps_activo,
            SUM(CASE WHEN asi.usuario_id IS NOT NULL AND asi.ubicacion_gps IS NULL THEN 1 ELSE 0 END) AS gps_inactivo
        FROM usuarios u
        LEFT JOIN (
            SELECT
                usuario_id,
                aula_id,
                ubicacion_gps
            FROM asistencias
            WHERE fecha = ? AND turno_id = ?
        ) asi ON asi.usuario_id = u.id
        WHERE u.rol = 'estudiante'
    ";

    if ($aulaId !== 'todos') {
        $sql .= " AND asi.aula_id = ?";
    }

    if ($stmt = $c->prepare($sql)) {

        if ($aulaId !== 'todos') {
            $aulaIdInt = (int)$aulaId;
            $stmt->bind_param("sii", $fecha, $turnoId, $aulaIdInt);
        } else {
            $stmt->bind_param("si", $fecha, $turnoId);
        }

        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();

        if ($row) {
            $resumen['total_estudiantes'] = (int)$row['total_estudiantes'];
            $resumen['presentes'] = (int)$row['presentes'];
            $resumen['ausentes'] = (int)$row['ausentes'];
            $resumen['gps_activo'] = (int)$row['gps_activo'];
            $resumen['gps_inactivo'] = (int)$row['gps_inactivo'];
        }

        $stmt->close();
    }

    return $resumen;
}

function obtenerEstudiantesConAsistencia(mysqli $c, string $fecha, int $turnoId, string $aulaId): array {

    $datos = [];

    $sql = "
        SELECT
            u.id,

            CONCAT(
                COALESCE(u.nombre,''),
                ' ',
                COALESCE(u.paterno,''),
                ' ',
                COALESCE(u.materno,'')
            ) AS nombre_completo,

            COALESCE(asi.aula_id,0) AS aula_id,
            COALESCE(a.nombre,'Sin aula') AS aula_nombre,

            CASE
                WHEN asi.usuario_id IS NULL THEN 'ausente'
                ELSE 'presente'
            END AS estado_asistencia,

            CASE
                WHEN asi.ubicacion_gps IS NOT NULL THEN 1
                ELSE 0
            END AS gps_activo,

            asi.ubicacion_gps,

            CONCAT(
                DATE_FORMAT(asi.fecha,'%Y-%m-%d'),
                ' ',
                TIME_FORMAT(asi.hora,'%H:%i:%s')
            ) AS fecha_hora,

            ST_Y(asi.ubicacion_gps) AS latitud,
            ST_X(asi.ubicacion_gps) AS longitud

        FROM usuarios u

        LEFT JOIN (

            SELECT
                usuario_id,
                aula_id,
                ubicacion_gps,
                fecha,
                hora

            FROM asistencias

            WHERE fecha = ?
            AND turno_id = ?
    ";

    if ($aulaId !== 'todos') {
        $sql .= " AND aula_id = ? ";
    }

    $sql .= "

        ) asi ON asi.usuario_id = u.id

        LEFT JOIN lk_aulas a
            ON a.id = asi.aula_id

        WHERE u.rol = 'estudiante'

        ORDER BY
            COALESCE(a.nombre,'ZZZ'),
            u.paterno,
            u.materno,
            u.nombre
    ";

    if ($stmt = $c->prepare($sql)) {

        if ($aulaId !== 'todos') {

            $aulaIdInt = (int)$aulaId;

            $stmt->bind_param(
                'sii',
                $fecha,
                $turnoId,
                $aulaIdInt
            );

        } else {

            $stmt->bind_param(
                'si',
                $fecha,
                $turnoId
            );
        }

        $stmt->execute();

        $res = $stmt->get_result();

        while ($fila = $res->fetch_assoc()) {
            $datos[] = $fila;
        }

        $stmt->close();
    }

    return $datos;
}
 
function estadoEtiqueta(array $e): array {

    $estado = strtolower($e['estado_asistencia'] ?? 'ausente');
    $gps = (int)($e['gps_activo'] ?? 0) === 1;

    if ($estado === 'presente') {

        return [
            'texto' => 'Presente',
            'clase' => 'success',
            'chip' => $gps ? 'GPS ON' : 'GPS OFF'
        ];
    }

    return [
        'texto' => 'Ausente',
        'clase' => 'danger',
        'chip' => 'Sin GPS'
    ];
}

$fecha = $_GET['fecha'] ?? date('Y-m-d');
$turnoId = isset($_GET['turno']) ? (int)$_GET['turno'] : 1;
$aulaId = $_GET['aula'] ?? 'todos';

$aulas = obtenerAulas($conn);
$turnos = obtenerTurnos($conn);

$resumen = obtenerResumenAsistencia(
    $conn,
    $fecha,
    $turnoId,
    $aulaId
);

$estudiantes = obtenerEstudiantesConAsistencia(
    $conn,
    $fecha,
    $turnoId,
    $aulaId
);
$estudiantesCanvas = array_map(function($r) {
    return [
        'id' => (int)$r['id'],
        'nombre_completo' => $r['nombre_completo'],
        'aula_nombre' => $r['aula_nombre'],
        'estado_asistencia' => $r['estado_asistencia'],
        'gps_activo' => (int)$r['gps_activo'],
        'latitud' => $r['latitud'] !== null ? (float)$r['latitud'] : null,
        'longitud' => $r['longitud'] !== null ? (float)$r['longitud'] : null,
    ];
}, $estudiantes);
$docente = [
    'nombre' => 'No asignado'
];

$turnoNombre = 'Turno';

foreach ($turnos as $t) {
    if ((int)$t['id'] === $turnoId) {
        $turnoNombre = $t['nombre'];
        break;
    }
}

$aulaNombre = 'Todas las aulas';

if ($aulaId !== 'todos') {

    foreach ($aulas as $a) {

        if ((string)$a['id'] === (string)$aulaId) {

            $aulaNombre = $a['nombre'];
            break;
        }
    }
}

$baseUrl = strtok($_SERVER['REQUEST_URI'], '?');
$export = $_GET['export'] ?? '';

if ($export == 'xls') {

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="asistencia.xls"');

    echo "<table border='1'>";
    echo "<tr>
            <th>ID</th>
            <th>Estudiante</th>
            <th>Aula</th>
            <th>Estado</th>
          </tr>";

    foreach ($estudiantes as $row) {

        echo "<tr>";
        echo "<td>".$row['id']."</td>";
        echo "<td>".$row['nombre_completo']."</td>";
        echo "<td>".$row['aula_nombre']."</td>";
        echo "<td>".$row['estado_asistencia']."</td>";
        echo "</tr>";
    }

    echo "</table>";
    exit;
}
if ($export == 'pdf') {

    header('Content-Type: text/html; charset=utf-8');

    echo "
    <html>
    <head>
    <meta charset='utf-8'>
    <title>Reporte de Asistencia</title>

    <style>
    body{
        font-family: Arial;
        font-size:12px;
    }

    table{
        width:100%;
        border-collapse:collapse;
    }

    th{
        background:#dddddd;
    }

    th,td{
        border:1px solid #000;
        padding:6px;
        text-align:left;
    }

    h2{
        text-align:center;
    }
    </style>

    </head>
    <body>

    <h2>Reporte de Asistencia</h2>

    <table>

    <tr>
        <th>ID</th>
        <th>Estudiante</th>
        <th>Aula</th>
        <th>Estado</th>
        <th>GPS</th>
    </tr>
    ";

    foreach ($estudiantes as $row) {

        echo "
        <tr>
            <td>{$row['id']}</td>
            <td>{$row['nombre_completo']}</td>
            <td>{$row['aula_nombre']}</td>
            <td>{$row['estado_asistencia']}</td>
            <td>".($row['gps_activo'] ? 'SI' : 'NO')."</td>
        </tr>
        ";
    }

    echo "
    </table>

    <script>
    window.print();
    </script>

    </body>
    </html>
    ";

    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Croquis de Estudiantes</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
<style>
:root{
  --bg:#070b12;
  --bg2:#0c1320;
  --card:#0f1726;
  --card2:#111a2c;
  --line:rgba(255,255,255,.08);
  --text:#e8eefc;
  --muted:#93a4c3;
  --primary:#5b8cff;
  --primary2:#7c5cff;
  --ok:#28d17c;
  --bad:#ff5c7c;
  --warn:#ffcb45;
  --cyan:#35e6ff;
  --shadow:0 20px 70px rgba(0,0,0,.35);
  --radius:24px;
  --box-size:100px;
}
*{box-sizing:border-box}
body{
  margin:0;
  min-height:100vh;
  font-family:Inter,Segoe UI,system-ui,-apple-system,sans-serif;
  color:var(--text);
  background:
    radial-gradient(circle at top left, rgba(91,140,255,.20), transparent 30%),
    radial-gradient(circle at top right, rgba(53,230,255,.14), transparent 26%),
    linear-gradient(180deg, #04070d 0%, var(--bg) 100%);
}
.container-shell{
  max-width:1500px;
  margin:0 auto;
  padding:22px 14px 40px;
}
.hero{
  background:linear-gradient(135deg, rgba(15,23,38,.95), rgba(10,16,28,.92));
  border:1px solid var(--line);
  border-radius:32px;
  box-shadow:var(--shadow);
  overflow:hidden;
}
.hero-top{
  padding:24px;
  border-bottom:1px solid var(--line);
  background:linear-gradient(135deg, rgba(91,140,255,.14), rgba(124,92,255,.08));
}
.hero-title{
  font-size:clamp(1.4rem, 2vw, 2.2rem);
  font-weight:800;
  margin:0;
}
.hero-sub{
  color:var(--muted);
  margin:.35rem 0 0;
}
.pill{
  display:inline-flex;
  align-items:center;
  gap:.45rem;
  padding:.55rem .9rem;
  border-radius:999px;
  border:1px solid var(--line);
  background:rgba(255,255,255,.04);
  color:var(--text);
  font-weight:600;
  font-size:.92rem;
}
.content{
  padding:22px;
}
.grid-stats{
  display:grid;
  grid-template-columns:repeat(5, minmax(0, 1fr));
  gap:14px;
  margin-bottom:16px;
}
.stat-card{
  background:linear-gradient(180deg, rgba(255,255,255,.04), rgba(255,255,255,.025));
  border:1px solid var(--line);
  border-radius:22px;
  padding:18px;
  box-shadow:0 10px 28px rgba(0,0,0,.16);
}
.stat-label{color:var(--muted);font-size:.88rem}
.stat-value{font-size:1.9rem;font-weight:800;line-height:1.05;margin-top:6px}
.stat-note{color:var(--muted);font-size:.85rem;margin-top:4px}
.icon-badge{
  width:48px;height:48px;border-radius:16px;display:grid;place-items:center;
  background:linear-gradient(135deg, rgba(91,140,255,.25), rgba(53,230,255,.18));
  border:1px solid var(--line);
}
.toolbar{
  background:rgba(255,255,255,.03);
  border:1px solid var(--line);
  border-radius:24px;
  padding:18px;
  margin-bottom:16px;
}
.form-label{color:var(--text);font-weight:700}
.form-control,.form-select{
  background:#0c1320;
  border:1px solid rgba(255,255,255,.12);
  color:var(--text);
  border-radius:16px;
  padding:.8rem 1rem;
}
.form-control:focus,.form-select:focus{
  background:#0c1320;
  color:var(--text);
  border-color:rgba(91,140,255,.8);
  box-shadow:0 0 0 .2rem rgba(91,140,255,.12);
}
.btn{
  border-radius:14px;
  padding:.75rem 1rem;
  font-weight:700;
}
.btn-primary{
  background:linear-gradient(135deg, var(--primary), var(--primary2));
  border:none;
}
.btn-soft{
  background:rgba(255,255,255,.05);
  border:1px solid var(--line);
  color:var(--text);
}
.btn-soft:hover{background:rgba(255,255,255,.08);color:var(--text)}
.filter-links{
  display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:14px;
}
.badge-soft{
  background:rgba(255,255,255,.06);
  border:1px solid var(--line);
  color:var(--text);
  font-weight:700;
  border-radius:999px;
}
.board-wrap{
  background:linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.02));
  border:1px solid var(--line);
  border-radius:24px;
  padding:16px;
}
.board-head{
  display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;
  margin-bottom:14px;
}
.board-title{font-size:1.1rem;font-weight:800;margin:0}
.board-meta{color:var(--muted);font-size:.9rem;margin-top:4px}
.legend{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
.legend-item{
  display:inline-flex;align-items:center;gap:8px;color:var(--text);font-size:.9rem;
  background:rgba(255,255,255,.04);border:1px solid var(--line);border-radius:999px;padding:.45rem .7rem;
}
.dot{width:12px;height:12px;border-radius:999px;display:inline-block}
.dot.ok{background:var(--ok)}
.dot.bad{background:var(--bad)}
.dot.cyan{background:var(--cyan)}
.canvas-shell{
  background:radial-gradient(circle at top, rgba(53,230,255,.10), transparent 26%), #070d16;
  border:1px solid var(--line);
  border-radius:22px;
  overflow:auto;
  padding:14px;
  min-height:560px;
}
canvas{
  display:block;
  margin:0 auto;
  background:linear-gradient(180deg, #0a1020, #070b12);
  border-radius:18px;
  box-shadow:inset 0 0 0 1px rgba(255,255,255,.04);
  width:100%;
  max-width:1250px;
}
.responsive-note{color:var(--muted);font-size:.9rem}
.student-card{
  background:rgba(255,255,255,.04);
  border:1px solid var(--line);
  border-radius:18px;
  padding:14px;
}
.table{
  color:var(--text);
}
.table > :not(caption) > * > *{background:transparent;color:var(--text);border-color:var(--line)}
.table thead th{color:#fff;background:rgba(255,255,255,.05);font-size:.9rem;white-space:nowrap}
.table tbody tr:hover{background:rgba(255,255,255,.03)}
.table-responsive{
  border:1px solid var(--line);
  border-radius:20px;
  overflow:auto;
}
.modal-content{
  background:#0d1422;
  color:var(--text);
  border:1px solid var(--line);
  border-radius:22px;
}
.modal-header,.modal-footer{border-color:var(--line)}
@media (max-width: 1200px){ .grid-stats{grid-template-columns:repeat(2, minmax(0,1fr));} }
@media (max-width: 768px){
  .content{padding:16px}
  .hero-top{padding:18px}
  .grid-stats{grid-template-columns:1fr}
  .canvas-shell{min-height:420px}
}
</style>
</head>
<body>
<div class="container-shell">
  <div class="hero">
    <div class="hero-top">
      <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
        <div>
          <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="javascript:history.back()" class="btn btn-soft btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
            <span class="pill"><i class="bi bi-geo-alt-fill"></i> Croquis de estudiantes</span>
            <span class="pill"><i class="bi bi-calendar3"></i> <?= e(date('d/m/Y', strtotime($fecha))) ?></span>
          </div>
          <h1 class="hero-title">Reporte visual de asistencia y ubicación</h1>
          <p class="hero-sub"><?= e($turnoNombre) ?> · Docente: <?= e($docente['nombre']) ?> · <?= e($aulaNombre) ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-soft" href="<?= e($baseUrl) ?>?<?= http_build_query(['fecha' => $fecha, 'turno' => $turnoId, 'aula' => $aulaId, 'export' => 'pdf']) ?>"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
          <a class="btn btn-soft" href="<?= e($baseUrl) ?>?<?= http_build_query(['fecha' => $fecha, 'turno' => $turnoId, 'aula' => $aulaId, 'export' => 'xls']) ?>"><i class="bi bi-file-earmark-excel"></i> Excel</a>
          <button class="btn btn-primary" id="btnActualizar"><i class="bi bi-arrow-repeat"></i> Actualizar</button>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="grid-stats">
        <div class="stat-card d-flex justify-content-between align-items-start gap-3">
          <div>
            <div class="stat-label">Total estudiantes</div>
            <div class="stat-value"><?= (int)$resumen['total_estudiantes'] ?></div>
            <div class="stat-note">En el rango filtrado</div>
          </div>
          <div class="icon-badge"><i class="bi bi-people-fill"></i></div>
        </div>
        <div class="stat-card d-flex justify-content-between align-items-start gap-3">
          <div>
            <div class="stat-label">Presentes</div>
            <div class="stat-value text-success"><?= (int)$resumen['presentes'] ?></div>
            <div class="stat-note">Marcados con asistencia</div>
          </div>
          <div class="icon-badge"><i class="bi bi-check2-circle"></i></div>
        </div>
        <div class="stat-card d-flex justify-content-between align-items-start gap-3">
          <div>
            <div class="stat-label">Ausentes</div>
            <div class="stat-value text-danger"><?= (int)$resumen['ausentes'] ?></div>
            <div class="stat-note">Sin asistencia registrada</div>
          </div>
          <div class="icon-badge"><i class="bi bi-x-circle"></i></div>
        </div>
        <div class="stat-card d-flex justify-content-between align-items-start gap-3">
          <div>
            <div class="stat-label">GPS activo</div>
            <div class="stat-value" style="color:var(--cyan)"><?= (int)$resumen['gps_activo'] ?></div>
            <div class="stat-note">Ubicación detectada</div>
          </div>
          <div class="icon-badge"><i class="bi bi-broadcast-pin"></i></div>
        </div>
        <div class="stat-card d-flex justify-content-between align-items-start gap-3">
          <div>
            <div class="stat-label">Docente</div>
            <div class="stat-value" style="font-size:1.12rem;line-height:1.25"><?= e($docente['nombre']) ?></div>
            <div class="stat-note">Asignado al turno</div>
          </div>
          <div class="icon-badge"><i class="bi bi-person-badge"></i></div>
        </div>
      </div>

      <div class="toolbar">
        <form id="filtros" class="row g-3 align-items-end" method="GET">
          <div class="col-12 col-md-4 col-lg-3">
            <label class="form-label">Fecha</label>
            <input type="date" name="fecha" id="fecha" class="form-control" value="<?= e($fecha) ?>">
          </div>
          <div class="col-12 col-md-4 col-lg-3">
            <label class="form-label">Turno</label>
            <select name="turno" id="turno" class="form-select">
              <?php foreach ($turnos as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= (int)$t['id'] === $turnoId ? 'selected' : '' ?>><?= e(ucfirst($t['nombre'])) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-4 col-lg-3">
            <label class="form-label">Aula</label>
            <select name="aula" id="aula" class="form-select">
              <option value="todos" <?= $aulaId === 'todos' ? 'selected' : '' ?>>Todas</option>
              <?php foreach ($aulas as $a): ?>
                <option value="<?= (int)$a['id'] ?>" <?= (string)$a['id'] === (string)$aulaId ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-lg-3 d-grid">
            <button class="btn btn-primary" type="submit"><i class="bi bi-funnel-fill"></i> Aplicar filtros</button>
          </div>
        </form>

        <div class="filter-links">
          <span class="badge badge-soft px-3 py-2">Turno: <?= e($turnoNombre) ?></span>
          <span class="badge badge-soft px-3 py-2">Aula: <?= e($aulaNombre) ?></span>
          <span class="badge badge-soft px-3 py-2">Docente: <?= e($docente['nombre']) ?></span>
        </div>
      </div>

      <div class="board-wrap mb-3">
        <div class="board-head">
          <div>
            <h2 class="board-title mb-1">Croquis visual</h2>
            <div class="board-meta">Los cuadros cambian de tamaño con el deslizador. El borde cyan marca GPS activo.</div>
          </div>
          <div class="legend">
            <span class="legend-item"><span class="dot ok"></span> Presente</span>
            <span class="legend-item"><span class="dot bad"></span> Ausente</span>
            <span class="legend-item"><span class="dot cyan"></span> GPS activo</span>
          </div>
        </div>

        <div class="row g-3 align-items-center mb-3">
          <div class="col-12 col-lg-4">
            <label for="sizeControl" class="form-label mb-2">Tamaño de cuadros: <span id="sizeValue">100</span> px</label>
            <input type="range" id="sizeControl" class="form-range" min="70" max="180" value="100">
          </div>
          <div class="col-12 col-lg-8">
            <div class="responsive-note">Sugerencia: en el código de GPS en tiempo real puedes enviar latitud/longitud y estado para pintar un aro animado cuando el estudiante esté dentro del radio permitido.</div>
          </div>
        </div>

        <div class="canvas-shell">
          <canvas id="croquis" width="1250" height="720"></canvas>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 text-center">
          <thead>
            <tr>
              <th>ID</th>
              <th class="text-start">Estudiante</th>
              <th>Aula</th>
              <th>Estado</th>
              <th>GPS</th>
              <th>Último registro</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($estudiantes): ?>
              <?php foreach ($estudiantes as $row): ?>
                <?php $et = estadoEtiqueta($row); ?>
                <tr>
                  <td><?= (int)$row['id'] ?></td>
                  <td class="text-start"><?= e($row['nombre_completo']) ?></td>
                  <td><?= e($row['aula_nombre']) ?></td>
                  <td><span class="badge text-bg-<?= e($et['clase']) ?> px-3 py-2"><?= e($et['texto']) ?></span></td>
                  <td><?= ((int)$row['gps_activo'] === 1) ? '<span class="badge text-bg-info px-3 py-2">' . e($et['chip']) . '</span>' : '<span class="badge text-bg-secondary px-3 py-2">' . e($et['chip']) . '</span>' ?></td>
                  <td><?= e($row['fecha_hora'] ?? '-') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="6" class="py-5">No hay estudiantes para mostrar con ese filtro</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<pre style="background:black;color:lime;padding:10px">
<?php
echo "TOTAL ESTUDIANTES: ".count($estudiantes)."\n";
print_r($estudiantes);
?>
</pre>
<script>
const estudiantes = <?= json_encode($estudiantesCanvas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?>;

console.log("ESTUDIANTES:", estudiantes);
console.log("TOTAL:", estudiantes.length);

const canvas = document.getElementById('croquis');
const ctx = canvas.getContext('2d');

ctx.fillStyle = "red";
ctx.fillRect(50, 50, 200, 100);

const sizeControl = document.getElementById('sizeControl');
const sizeValue = document.getElementById('sizeValue');
const fechaInput = document.getElementById('fecha');
const turnoSelect = document.getElementById('turno');
const aulaSelect = document.getElementById('aula');
const btnActualizar = document.getElementById('btnActualizar');

let boxSize = parseInt(sizeControl.value, 10) || 100;
let frame = 0;

function fitCanvas() {
  const parentWidth = canvas.parentElement.clientWidth - 2;
  canvas.width = Math.min(1250, Math.max(900, parentWidth));
}

function getCols() {
  return Math.max(1, Math.floor((canvas.width - 40) / (boxSize + 28)));
}

function drawRoundedRect(x, y, w, h, r) {
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.arcTo(x + w, y, x + w, y + h, r);
  ctx.arcTo(x + w, y + h, x, y + h, r);
  ctx.arcTo(x, y + h, x, y, r);
  ctx.arcTo(x, y, x + w, y, r);
  ctx.closePath();
}

function wrapText(text, maxWidth) {
  const words = text.split(' ');
  const lines = [];
  let current = '';
  for (const word of words) {
    const test = current ? current + ' ' + word : word;
    if (ctx.measureText(test).width > maxWidth && current) {
      lines.push(current);
      current = word;
    } else {
      current = test;
    }
  }
  if (current) lines.push(current);
  return lines;
}

function drawBoard() {
  fitCanvas();
  const cols = getCols();
  const gapX = 22;
  const gapY = 30;
  const left = 22;
  const top = 22;
  const cardH = boxSize + 28;
  const rows = Math.ceil((estudiantes.length || 1) / cols);
  canvas.height = Math.max(520, top + rows * (cardH + gapY) + 30);

  ctx.clearRect(0, 0, canvas.width, canvas.height);
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';

  let presentes = 0;
  let ausentes = 0;

  estudiantes.forEach((e, i) => {
    const estado = (e.estado_asistencia || 'ausente').toLowerCase();
    const presente = estado === 'presente';
    const gps = parseInt(e.gps_activo || 0, 10) === 1;
    if (presente) presentes++; else ausentes++;

    const col = i % cols;
    const row = Math.floor(i / cols);
    const x = left + col * (boxSize + gapX);
    const y = top + row * (cardH + gapY);

    // Sombra suave
    ctx.save();
    ctx.shadowColor = presente ? 'rgba(40,209,124,.32)' : 'rgba(255,92,124,.28)';
    ctx.shadowBlur = 18;
    drawRoundedRect(x, y, boxSize, cardH, 20);
    ctx.fillStyle = presente ? 'rgba(40,209,124,.96)' : 'rgba(255,92,124,.96)';
    ctx.fill();
    ctx.restore();

    // Borde GPS
    if (gps) {
      const pulse = 1 + (Math.sin(frame / 14) * 0.04);
      ctx.save();
      ctx.strokeStyle = 'rgba(53,230,255,.95)';
      ctx.lineWidth = 4;
      ctx.shadowColor = 'rgba(53,230,255,.75)';
      ctx.shadowBlur = 18 + (Math.sin(frame / 10) + 1) * 6;
      drawRoundedRect(x - 3, y - 3, boxSize + 6, cardH + 6, 22);
      ctx.stroke();
      ctx.restore();
    }

    // Encabezado del card
    ctx.fillStyle = 'rgba(255,255,255,.2)';
    ctx.font = `700 ${Math.max(12, boxSize / 7)}px Segoe UI`;
    ctx.fillText(presente ? 'PRESENTE' : 'AUSENTE', x + boxSize / 2, y + 18);

    // Nombre
    ctx.fillStyle = '#07101d';
    ctx.font = `800 ${Math.max(12, boxSize / 6.2)}px Segoe UI`;
    const lines = wrapText(e.nombre_completo || '', boxSize - 18);
    const startY = y + cardH / 2 - ((lines.length - 1) * (boxSize / 9));
    lines.slice(0, 3).forEach((line, idx) => {
      ctx.fillText(line, x + boxSize / 2, startY + idx * (boxSize / 6.2));
    });

    // Pie
    ctx.fillStyle = gps ? '#001e26' : '#2b0a13';
    ctx.font = `700 ${Math.max(10, boxSize / 8)}px Segoe UI`;
    ctx.fillText(gps ? 'GPS ON' : 'GPS OFF', x + boxSize / 2, y + cardH - 16);
  });

  // Resumen flotante
  const resumenTexto = `Presentes: ${presentes} | Ausentes: ${ausentes} | Total: ${estudiantes.length}`;
  const infoBoxW = Math.min(520, canvas.width - 30);
  const infoBoxX = 15;
  const infoBoxY = canvas.height - 52;
  ctx.save();
  ctx.fillStyle = 'rgba(8,12,20,.82)';
  ctx.strokeStyle = 'rgba(255,255,255,.09)';
  ctx.lineWidth = 1;
  drawRoundedRect(infoBoxX, infoBoxY, infoBoxW, 38, 16);
  ctx.fill();
  ctx.stroke();
  ctx.fillStyle = '#eaf2ff';
  ctx.font = '700 14px Segoe UI';
  ctx.textAlign = 'left';
  ctx.fillText(resumenTexto, infoBoxX + 16, infoBoxY + 19);
  ctx.restore();
}

function animate() {
  frame++;
  drawBoard();
  requestAnimationFrame(animate);
}

sizeControl.addEventListener('input', (e) => {
  boxSize = parseInt(e.target.value, 10);
  sizeValue.textContent = boxSize;
  drawBoard();
});

function recargarFiltros() {
  const url = new URL(window.location.href);
  url.searchParams.set('fecha', fechaInput.value);
  url.searchParams.set('turno', turnoSelect.value);
  url.searchParams.set('aula', aulaSelect.value);
  window.location.href = url.toString();
}

btnActualizar.addEventListener('click', recargarFiltros);
fechaInput.addEventListener('change', recargarFiltros);
turnoSelect.addEventListener('change', recargarFiltros);
aulaSelect.addEventListener('change', recargarFiltros);
window.addEventListener('resize', drawBoard);

animate();
</script>
</body>
</html>
