<?php
session_start();
require_once '../TOOLS/conexion.php';

date_default_timezone_set('America/La_Paz');

$conn = conectarse();
$conn->set_charset("utf8mb4");

$nombre = htmlspecialchars($_SESSION['nombre'] ?? 'Administrador', ENT_QUOTES, 'UTF-8');

function fetchOne($conn, $sql, $default = 0) {
    $r = $conn->query($sql);
    if ($r && ($row = $r->fetch_assoc())) {
        return isset($row['total']) ? (int)$row['total'] : $default;
    }
    return $default;
}

function fetchAllRows($conn, $sql) {
    $r = $conn->query($sql);
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}

$totalUsuarios     = fetchOne($conn, "SELECT COUNT(*) total FROM usuarios");
$totalAsistencias  = fetchOne($conn, "SELECT COUNT(*) total FROM asistencias");
$totalDocentes     = fetchOne($conn, "SELECT COUNT(*) total FROM usuarios WHERE rol='docente'");
$totalEstudiantes  = fetchOne($conn, "SELECT COUNT(*) total FROM usuarios WHERE rol='estudiante'");
$totalInvitados    = fetchOne($conn, "SELECT COUNT(*) total FROM usuarios WHERE rol='invitado'");
$totalAdmin        = fetchOne($conn, "SELECT COUNT(*) total FROM usuarios WHERE rol='superusuario'");
$totalPendientes   = fetchOne($conn, "SELECT COUNT(*) total FROM solicitudes_registro WHERE estado='pendiente'");
$totalHoy          = fetchOne($conn, "SELECT COUNT(*) total FROM asistencias WHERE fecha = CURDATE()");
$totalSemana       = fetchOne($conn, "SELECT COUNT(*) total FROM asistencias WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)");

$roles = fetchAllRows($conn, "
    SELECT rol, COUNT(*) total
    FROM usuarios
    GROUP BY rol
    ORDER BY total DESC
");

$turnos = fetchAllRows($conn, "
    SELECT COALESCE(t.nombre,'Sin turno') AS nombre, COUNT(*) AS total
    FROM asistencias a
    LEFT JOIN lk_turnos t ON t.id = a.turno_id
    GROUP BY t.id, t.nombre
    ORDER BY total DESC
");

$metodos = fetchAllRows($conn, "
    SELECT COALESCE(m.nombre,'Sin método') AS nombre, COUNT(*) AS total
    FROM asistencias a
    LEFT JOIN lk_metodos_asistencia m ON m.id = a.metodo_id
    GROUP BY m.id, m.nombre
    ORDER BY total DESC
");

$materiasTop = fetchAllRows($conn, "
    SELECT COALESCE(m.nombre,'Sin materia') AS nombre, COUNT(*) AS total
    FROM asistencias a
    LEFT JOIN lk_materias m ON m.id = a.materia_id
    GROUP BY m.id, m.nombre
    ORDER BY total DESC
    LIMIT 6
");

$aulasTop = fetchAllRows($conn, "
    SELECT COALESCE(aul.nombre,'Sin aula') AS nombre, COUNT(*) AS total
    FROM asistencias a
    LEFT JOIN lk_aulas aul ON aul.id = a.aula_id
    GROUP BY aul.id, aul.nombre
    ORDER BY total DESC
    LIMIT 6
");

$ultimosDias = fetchAllRows($conn, "
    SELECT DATE(fecha) AS fecha, COUNT(*) AS total
    FROM asistencias
    WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(fecha)
    ORDER BY fecha
");

$ultimasAsistencias = fetchAllRows($conn, "
    SELECT
        a.fecha,
        a.hora,
        u.nombre,
        u.paterno,
        u.materno,
        COALESCE(m.nombre,'Sin materia') AS materia,
        COALESCE(t.nombre,'Sin turno') AS turno,
        COALESCE(aul.nombre,'Sin aula') AS aula,
        COALESCE(met.nombre,'manual') AS metodo,
        a.observacion
    FROM asistencias a
    INNER JOIN usuarios u ON u.id = a.usuario_id
    LEFT JOIN lk_materias m ON m.id = a.materia_id
    LEFT JOIN lk_turnos t ON t.id = a.turno_id
    LEFT JOIN lk_aulas aul ON aul.id = a.aula_id
    LEFT JOIN lk_metodos_asistencia met ON met.id = a.metodo_id
    ORDER BY a.fecha DESC, a.hora DESC
    LIMIT 8
");

$rolesLabels = array_map(function($x){ return $x['rol']; }, $roles);
$rolesData   = array_map(function($x){ return (int)$x['total']; }, $roles);

$turnosLabels = array_map(function($x){ return $x['nombre']; }, $turnos);
$turnosData   = array_map(function($x){ return (int)$x['total']; }, $turnos);

$metodosLabels = array_map(function($x){ return $x['nombre']; }, $metodos);
$metodosData   = array_map(function($x){ return (int)$x['total']; }, $metodos);

$materiasLabels = array_map(function($x){ return $x['nombre']; }, $materiasTop);
$materiasData   = array_map(function($x){ return (int)$x['total']; }, $materiasTop);

$aulasLabels = array_map(function($x){ return $x['nombre']; }, $aulasTop);
$aulasData   = array_map(function($x){ return (int)$x['total']; }, $aulasTop);

$diasLabels = array_map(function($x){ return date('d/m', strtotime($x['fecha'])); }, $ultimosDias);
$diasData   = array_map(function($x){ return (int)$x['total']; }, $ultimosDias);

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>ESTUDIANTE</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
:root{
    --bg:#eef2f7;
    --dark:#0f172a;
    --card:#ffffff;
    --muted:#64748b;
    --shadow:0 18px 50px rgba(15,23,42,.08);
    --radius:28px;
}

body{
    background:
        radial-gradient(circle at top left, rgba(99,102,241,.10), transparent 30%),
        radial-gradient(circle at top right, rgba(34,197,94,.10), transparent 24%),
        linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
    font-family:Segoe UI, sans-serif;
    min-height:100vh;
    color:#0f172a;
}

.sidebar{
    min-height:100vh;
    background:linear-gradient(180deg, #0b1220 0%, #111827 55%, #0f172a 100%);
    color:#fff;
    position:sticky;
    top:0;
}

.brand{
    font-size:1.45rem;
    font-weight:800;
    letter-spacing:.3px;
}

.side-link{
    display:flex;
    align-items:center;
    gap:.75rem;
    color:#e2e8f0;
    text-decoration:none;
    padding:14px 16px;
    border-radius:18px;
    margin-bottom:10px;
    transition:.25s ease;
    background:rgba(255,255,255,.03);
    border:1px solid rgba(255,255,255,.05);
}

.side-link:hover{
    background:rgba(255,255,255,.10);
    transform:translateX(4px);
    color:#fff;
}

.hero{
    background:linear-gradient(135deg, #0f172a 0%, #1e293b 45%, #334155 100%);
    color:#fff;
    border-radius:32px;
    padding:30px;
    box-shadow:0 25px 70px rgba(2,6,23,.25);
    position:relative;
    overflow:hidden;
}

.hero:before,
.hero:after{
    content:"";
    position:absolute;
    border-radius:50%;
    filter:blur(10px);
    opacity:.22;
}

.hero:before{
    width:220px;height:220px;
    background:#60a5fa;
    right:-50px; top:-50px;
}

.hero:after{
    width:160px;height:160px;
    background:#22c55e;
    right:110px; bottom:-60px;
}

.kpi{
    position:relative;
    overflow:hidden;
    border:none;
    border-radius:28px;
    box-shadow:var(--shadow);
    color:#fff;
    min-height:150px;
    transform:translateZ(0);
}

.kpi .value{
    font-size:2.5rem;
    font-weight:900;
    line-height:1;
}

.kpi .icon{
    font-size:2rem;
    opacity:.9;
}

.kpi::after{
    content:"";
    position:absolute;
    inset:auto -25% -45% auto;
    width:180px;
    height:180px;
    border-radius:50%;
    background:rgba(255,255,255,.10);
}

.g1{background:linear-gradient(135deg,#111827,#374151);}
.g2{background:linear-gradient(135deg,#4f46e5,#818cf8);}
.g3{background:linear-gradient(135deg,#0f766e,#2dd4bf);}
.g4{background:linear-gradient(135deg,#be123c,#fb7185);}
.g5{background:linear-gradient(135deg,#0369a1,#38bdf8);}
.g6{background:linear-gradient(135deg,#7c3aed,#c084fc);}

.panel{
    background:var(--card);
    border-radius:var(--radius);
    box-shadow:var(--shadow);
    padding:24px;
    border:1px solid rgba(148,163,184,.20);
    transform:perspective(1200px) rotateX(1deg);
}

.panel h4, .panel h5{
    font-weight:800;
}

.chart-wrap{
    height:360px;
}

.chart-wrap-sm{
    height:290px;
}

.table thead th{
    background:#f8fafc;
    color:#334155;
    font-weight:700;
    border-bottom:0;
}

.badge-soft{
    background:#e2e8f0;
    color:#0f172a;
    border-radius:999px;
    padding:.45rem .8rem;
    font-weight:700;
}

.text-soft{
    color:var(--muted);
}

.small-card{
    background:#fff;
    border-radius:24px;
    box-shadow:var(--shadow);
    padding:20px;
    border:1px solid rgba(148,163,184,.15);
}

.progress{
    height:12px;
    border-radius:999px;
}

@media (max-width: 991px){
    .sidebar{min-height:auto; position:relative;}
    .hero{padding:24px;}
}
</style>
</head>

<body>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 sidebar p-4">
            <div class="brand mb-5">
                <i class="bi bi-shield-lock-fill me-2"></i>ESTUDIANTE
            </div>

            <a class="side-link" href="#">
                <i class="bi bi-grid-fill"></i>Dashboard
            </a>
            <a class="side-link" href="#">
                <i class="bi bi-people-fill"></i>Usuarios
            </a>
            <a class="side-link" href="#">
                <i class="bi bi-bar-chart-fill"></i>Reportes
            </a>
            <a class="side-link" href="cam.php">
                <i class="bi bi-key-fill"></i>Contraseñas
            </a>
            
                 <a class="nav-link" href="../asistencia/coordenada++.php"><i class="bi bi-graph-up-arrow me-2"></i>GPS</a>
            <a class="side-link" href="logout.php">
                <i class="bi bi-box-arrow-right"></i>Salir
            </a>

            <div class="mt-4 p-3 rounded-4" style="background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.06);">
                <div class="fw-bold mb-1">Sesión activa</div>
                <div class="text-white-50 small"><?= $nombre ?></div>
            </div>
        </div>

        <div class="col-lg-10 p-4 p-lg-5">
            <div class="hero mb-4">
                <div class="row align-items-center position-relative">
                    <div class="col-lg-8">
                        <div class="badge-soft d-inline-flex mb-3">Administración completa</div>
                        <h2 class="fw-black mb-2">Bienvenido, <?= $nombre ?></h2>
                        <p class="mb-0 text-white-50">
                            Control total del sistema académico, usuarios, asistencias, turnos, materias y reportes.
                        </p>
                    </div>
                    <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                        <div class="display-4 fw-bold">
                            <i class="bi bi-cpu-fill"></i>
                        </div>
                        <div class="text-white-50">Panel ejecutivo</div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-4 col-xl-2">
                    <div class="card kpi g1 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon"><i class="bi bi-people-fill"></i></div>
                            <span class="small text-white-50">Total</span>
                        </div>
                        <div class="value"><?= $totalUsuarios ?></div>
                        <div class="mt-2">Usuarios</div>
                    </div>
                </div>

                <div class="col-md-4 col-xl-2">
                    <div class="card kpi g2 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon"><i class="bi bi-calendar-check-fill"></i></div>
                            <span class="small text-white-50">Hoy</span>
                        </div>
                        <div class="value"><?= $totalHoy ?></div>
                        <div class="mt-2">Asistencias</div>
                    </div>
                </div>

                <div class="col-md-4 col-xl-2">
                    <div class="card kpi g3 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon"><i class="bi bi-mortarboard-fill"></i></div>
                            <span class="small text-white-50">Docentes</span>
                        </div>
                        <div class="value"><?= $totalDocentes ?></div>
                        <div class="mt-2">Registrados</div>
                    </div>
                </div>

                <div class="col-md-4 col-xl-2">
                    <div class="card kpi g4 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon"><i class="bi bi-person-badge-fill"></i></div>
                            <span class="small text-white-50">Est.</span>
                        </div>
                        <div class="value"><?= $totalEstudiantes ?></div>
                        <div class="mt-2">Estudiantes</div>
                    </div>
                </div>

                <div class="col-md-4 col-xl-2">
                    <div class="card kpi g5 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon"><i class="bi bi-person-gear"></i></div>
                            <span class="small text-white-50">Admin</span>
                        </div>
                        <div class="value"><?= $totalAdmin ?></div>
                        <div class="mt-2">Superusuarios</div>
                    </div>
                </div>

                <div class="col-md-4 col-xl-2">
                    <div class="card kpi g6 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon"><i class="bi bi-hourglass-split"></i></div>
                            <span class="small text-white-50">Pend.</span>
                        </div>
                        <div class="value"><?= $totalPendientes ?></div>
                        <div class="mt-2">Solicitudes</div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="panel h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h4 class="mb-1">Distribución de roles</h4>
                                <div class="text-soft">Vista circular con efecto de profundidad</div>
                            </div>
                            <i class="bi bi-pie-chart-fill fs-2 text-primary"></i>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="rolesChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="panel h-100">
                        <h4 class="mb-3">Estado del sistema</h4>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Servidor</strong><span class="badge text-bg-success">Online</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-success" style="width: 96%"></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Base de datos</strong><span class="badge text-bg-primary">Conectada</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-primary" style="width: 92%"></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Seguridad</strong><span class="badge text-bg-dark">Activa</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-dark" style="width: 88%"></div>
                            </div>
                        </div>

                        <div class="small-card mt-4">
                            <div class="text-soft small">Último acceso</div>
                            <div class="fw-bold fs-5"><?= date('d/m/Y H:i') ?></div>
                        </div>

                        <div class="small-card mt-3">
                            <div class="text-soft small">Actividad semanal</div>
                            <div class="fw-bold fs-5"><?= $totalSemana ?> asistencias</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-4">
                    <div class="panel h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="mb-1">Asistencias por turno</h5>
                                <div class="text-soft">Muestra horarios más usados</div>
                            </div>
                            <i class="bi bi-clock-history fs-3 text-primary"></i>
                        </div>
                        <div class="chart-wrap-sm">
                            <canvas id="turnosChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="panel h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="mb-1">Métodos de asistencia</h5>
                                <div class="text-soft">Facial, manual y otros</div>
                            </div>
                            <i class="bi bi-fingerprint fs-3 text-success"></i>
                        </div>
                        <div class="chart-wrap-sm">
                            <canvas id="metodosChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="panel h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="mb-1">Top aulas</h5>
                                <div class="text-soft">Espacios con mayor uso</div>
                            </div>
                            <i class="bi bi-building fs-3 text-warning"></i>
                        </div>
                        <div class="chart-wrap-sm">
                            <canvas id="aulasChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-7">
                    <div class="panel h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h4 class="mb-1">Actividad de los últimos 7 días</h4>
                                <div class="text-soft">Tendencia general del sistema</div>
                            </div>
                            <i class="bi bi-graph-up-arrow fs-2 text-danger"></i>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="diasChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="panel h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h4 class="mb-1">Materias más activas</h4>
                                <div class="text-soft">Top 6 por número de asistencias</div>
                            </div>
                            <i class="bi bi-journal-bookmark-fill fs-2 text-info"></i>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="materiasChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="mb-1">Últimas asistencias registradas</h4>
                        <div class="text-soft">Vista rápida de actividad reciente</div>
                    </div>
                    <span class="badge text-bg-dark"><?= count($ultimasAsistencias) ?> registros</span>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Usuario</th>
                                <th>Materia</th>
                                <th>Turno</th>
                                <th>Aula</th>
                                <th>Método</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($ultimasAsistencias) > 0): ?>
                                <?php foreach ($ultimasAsistencias as $fila): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($fila['fecha'])) ?></td>
                                        <td><?= date('H:i', strtotime($fila['hora'])) ?></td>
                                        <td><?= htmlspecialchars($fila['nombre'].' '.$fila['paterno'].' '.$fila['materno'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($fila['materia'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($fila['turno'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($fila['aula'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($fila['metodo'], ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-soft py-4">No hay asistencias registradas todavía.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
Chart.defaults.font.family = 'Segoe UI, sans-serif';
Chart.defaults.color = '#334155';
Chart.defaults.animation.duration = 1600;
Chart.defaults.animation.easing = 'easeOutQuart';

const shadowPlugin = {
    id: 'shadowPlugin',
    beforeDatasetDraw(chart) {
        const ctx = chart.ctx;
        ctx.save();
        ctx.shadowColor = 'rgba(15, 23, 42, .18)';
        ctx.shadowBlur = 18;
        ctx.shadowOffsetY = 10;
    },
    afterDatasetDraw(chart) {
        chart.ctx.restore();
    }
};

Chart.register(shadowPlugin);

function gradient(ctx, chartArea, topColor, bottomColor) {
    if (!chartArea) return bottomColor;
    const g = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
    g.addColorStop(0, bottomColor);
    g.addColorStop(1, topColor);
    return g;
}

const palette = [
    '#111827','#4f46e5','#0f766e','#be123c','#0369a1','#7c3aed',
    '#16a34a','#ea580c','#db2777','#0284c7'
];

function colorList(n) {
    return Array.from({length:n}, (_,i)=>palette[i % palette.length]);
}

const rolesLabels = <?= json_encode($rolesLabels, JSON_UNESCAPED_UNICODE) ?>;
const rolesData   = <?= json_encode($rolesData, JSON_UNESCAPED_UNICODE) ?>;
const turnosLabels = <?= json_encode($turnosLabels, JSON_UNESCAPED_UNICODE) ?>;
const turnosData   = <?= json_encode($turnosData, JSON_UNESCAPED_UNICODE) ?>;
const metodosLabels = <?= json_encode($metodosLabels, JSON_UNESCAPED_UNICODE) ?>;
const metodosData   = <?= json_encode($metodosData, JSON_UNESCAPED_UNICODE) ?>;
const materiasLabels = <?= json_encode($materiasLabels, JSON_UNESCAPED_UNICODE) ?>;
const materiasData   = <?= json_encode($materiasData, JSON_UNESCAPED_UNICODE) ?>;
const aulasLabels = <?= json_encode($aulasLabels, JSON_UNESCAPED_UNICODE) ?>;
const aulasData   = <?= json_encode($aulasData, JSON_UNESCAPED_UNICODE) ?>;
const diasLabels = <?= json_encode($diasLabels, JSON_UNESCAPED_UNICODE) ?>;
const diasData   = <?= json_encode($diasData, JSON_UNESCAPED_UNICODE) ?>;

new Chart(document.getElementById('rolesChart'), {
    type: 'polarArea',
    data: {
        labels: rolesLabels,
        datasets: [{
            data: rolesData,
            backgroundColor: colorList(rolesData.length).map(function(c){ return c + 'CC'; }),
            borderColor: '#fff',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        },
        scales: {
            r: {
                grid: { color: 'rgba(148,163,184,.20)' },
                angleLines: { color: 'rgba(148,163,184,.20)' }
            }
        }
    }
});

new Chart(document.getElementById('turnosChart'), {
    type: 'doughnut',
    data: {
        labels: turnosLabels,
        datasets: [{
            data: turnosData,
            backgroundColor: ['#0f172a','#4f46e5','#0ea5e9','#22c55e','#f59e0b']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '62%',
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

new Chart(document.getElementById('metodosChart'), {
    type: 'doughnut',
    data: {
        labels: metodosLabels,
        datasets: [{
            data: metodosData,
            backgroundColor: ['#111827','#10b981','#06b6d4','#f43f5e','#8b5cf6']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '58%',
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

new Chart(document.getElementById('aulasChart'), {
    type: 'bar',
    data: {
        labels: aulasLabels,
        datasets: [{
            label: 'Uso',
            data: aulasData,
            borderRadius: 14,
            borderSkipped: false,
            backgroundColor: function(context) {
                const chart = context.chart;
                const {ctx, chartArea} = chart;
                if (!chartArea) return '#0369a1';
                return gradient(ctx, chartArea, '#38bdf8', '#0369a1');
            }
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: {
            legend: { display: false }
        },
        scales: {
            x: {
                grid: { color: 'rgba(148,163,184,.14)' }
            },
            y: {
                grid: { display: false }
            }
        }
    }
});

new Chart(document.getElementById('diasChart'), {
    type: 'line',
    data: {
        labels: diasLabels,
        datasets: [{
            label: 'Asistencias',
            data: diasData,
            tension: .35,
            fill: true,
            pointRadius: 5,
            pointHoverRadius: 7,
            borderWidth: 3,
            borderColor: '#4f46e5',
            pointBackgroundColor: '#111827',
            backgroundColor: function(context) {
                const chart = context.chart;
                const {ctx, chartArea} = chart;
                if (!chartArea) return 'rgba(79,70,229,.15)';
                const g = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                g.addColorStop(0, 'rgba(79,70,229,.35)');
                g.addColorStop(1, 'rgba(79,70,229,.02)');
                return g;
            }
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(148,163,184,.14)' }
            },
            x: {
                grid: { display: false }
            }
        }
    }
});

new Chart(document.getElementById('materiasChart'), {
    type: 'bar',
    data: {
        labels: materiasLabels,
        datasets: [{
            label: 'Asistencias',
            data: materiasData,
            borderRadius: 16,
            borderSkipped: false,
            backgroundColor: function(context) {
                const chart = context.chart;
                const {ctx, chartArea} = chart;
                if (!chartArea) return '#0f766e';
                return gradient(ctx, chartArea, '#2dd4bf', '#0f766e');
            }
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(148,163,184,.14)' }
            },
            x: {
                grid: { display: false }
            }
        }
    }
});
</script>
</body>
</html>