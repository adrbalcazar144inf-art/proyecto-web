<?php
session_start();
require_once '../TOOLS/conexion.php';

date_default_timezone_set('America/La_Paz');

/* ===== ACCESO ===== */
$rol = $_SESSION['rol'] ?? '';
if (!in_array($rol, ['docente', 'superusuario'], true)) {
    header('Location: ../login.php');
    exit;
}

$nombre = (string)($_SESSION['nombre'] ?? 'Docente');

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];

/* ===== CONEXIÓN ===== */
$conn = conectarse();
if (!($conn instanceof mysqli)) {
    http_response_code(500);
    exit('No se pudo establecer conexión con la base de datos.');
}
$conn->set_charset('utf8mb4');

/* ===== HELPERS ===== */
function e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function bindParams(mysqli_stmt $stmt, string $types, array $params): void {
    if ($types === '') {
        return;
    }
    $refs = [$types];
    foreach ($params as $k => $v) {
        $refs[] = &$params[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function fetchOne(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if ($types !== '') {
        bindParams($stmt, $types, $params);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $res = $stmt->get_result();
    $row = $res ? ($res->fetch_assoc() ?: []) : [];
    $stmt->close();
    return $row;
}

function fetchAllRows(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $rows = [];
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $rows;
    }

    if ($types !== '') {
        bindParams($stmt, $types, $params);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return $rows;
    }

    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    $stmt->close();
    return $rows;
}

function numeroMeses(): array {
    return [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
}

function statusBadge(bool $ok, string $okText = 'Conectado', string $badText = 'Sin conexión'): array {
    return $ok
        ? ['text' => $okText, 'class' => 'success', 'icon' => 'check-circle-fill']
        : ['text' => $badText, 'class' => 'danger', 'icon' => 'x-circle-fill'];
}

/* ===== VERIFICACIÓN DE CONEXIÓN ===== */
$dbOk = true;
$dbName = 'Base de datos';
$dbServer = 'Servidor';
try {
    $probe = fetchOne($conn, 'SELECT DATABASE() AS dbname, @@hostname AS host');
    $dbName = $probe['dbname'] ?? $dbName;
    $dbServer = $probe['host'] ?? $dbServer;
} catch (Throwable $ex) {
    $dbOk = false;
}
$connectionState = statusBadge($dbOk);

/* ===== RESUMEN GENERAL ===== */
$resumen = fetchOne($conn, "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN fecha = CURDATE() THEN 1 ELSE 0 END) AS hoy,
        SUM(CASE WHEN metodo_id = 1 THEN 1 ELSE 0 END) AS facial,
        SUM(CASE WHEN metodo_id = 2 THEN 1 ELSE 0 END) AS manual,
        SUM(CASE WHEN metodo_id = 3 THEN 1 ELSE 0 END) AS admin
    FROM asistencias
");

$totalAsistencias = (int)($resumen['total'] ?? 0);
$asistenciasHoy   = (int)($resumen['hoy'] ?? 0);
$facialCount      = (int)($resumen['facial'] ?? 0);
$manualCount      = (int)($resumen['manual'] ?? 0);
$adminCount       = (int)($resumen['admin'] ?? 0);

/* ===== GRÁFICOS 2D ===== */
$turnosRows = fetchAllRows($conn, "
    SELECT COALESCE(t.nombre, 'Sin turno') AS nombre, COUNT(a.id) AS total
    FROM lk_turnos t
    LEFT JOIN asistencias a ON a.turno_id = t.id
    GROUP BY t.id, t.nombre
    ORDER BY t.id
");

$materiasRows = fetchAllRows($conn, "
    SELECT COALESCE(m.nombre, 'Sin materia') AS nombre, COUNT(a.id) AS total
    FROM lk_materias m
    LEFT JOIN asistencias a ON a.materia_id = m.id
    GROUP BY m.id, m.nombre
    ORDER BY total DESC, m.nombre
");

$aulasRows = fetchAllRows($conn, "
    SELECT COALESCE(aul.nombre, 'Sin aula') AS nombre, COUNT(a.id) AS total
    FROM lk_aulas aul
    LEFT JOIN asistencias a ON a.aula_id = aul.id
    GROUP BY aul.id, aul.nombre
    ORDER BY total DESC, aul.nombre
");

$metodosRows = fetchAllRows($conn, "
    SELECT COALESCE(l.nombre, 'Sin método') AS nombre, COUNT(a.id) AS total
    FROM lk_metodos_asistencia l
    LEFT JOIN asistencias a ON a.metodo_id = l.id
    GROUP BY l.id, l.nombre
    ORDER BY l.id
");

$hoy7Rows = fetchAllRows($conn, "
    SELECT fecha, COUNT(*) AS total
    FROM asistencias
    WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY fecha
    ORDER BY fecha
");

$map7 = [];
foreach ($hoy7Rows as $r) {
    $map7[$r['fecha']] = (int)$r['total'];
}

$diasLabels = [];
$diasValores = [];
for ($i = 6; $i >= 0; $i--) {
    $fecha = date('Y-m-d', strtotime("-{$i} days"));
    $diasLabels[] = date('d/m', strtotime($fecha));
    $diasValores[] = $map7[$fecha] ?? 0;
}

/* ===== 3D: materia vs turno vs cantidad ===== */
$pts3dRows = fetchAllRows($conn, "
    SELECT
        COALESCE(m.nombre, 'Sin materia') AS materia,
        COALESCE(t.nombre, 'Sin turno') AS turno,
        COUNT(*) AS total
    FROM asistencias a
    LEFT JOIN lk_materias m ON a.materia_id = m.id
    LEFT JOIN lk_turnos t ON a.turno_id = t.id
    GROUP BY m.id, m.nombre, t.id, t.nombre
    ORDER BY m.nombre, t.nombre
");

$materiaIndex = [];
$turnoIndex = [];
$points3d = [];

foreach ($pts3dRows as $r) {
    $materia = $r['materia'];
    $turno = $r['turno'];
    $total = (int)$r['total'];

    if (!isset($materiaIndex[$materia])) {
        $materiaIndex[$materia] = count($materiaIndex) + 1;
    }
    if (!isset($turnoIndex[$turno])) {
        $turnoIndex[$turno] = count($turnoIndex) + 1;
    }

    $points3d[] = [
        'x' => $materiaIndex[$materia],
        'y' => $turnoIndex[$turno],
        'z' => $total,
        'materia' => $materia,
        'turno' => $turno,
    ];
}

$materiaLabels3d = array_keys($materiaIndex);
$turnoLabels3d   = array_keys($turnoIndex);
$materiaVals3d   = array_values($materiaIndex);
$turnoVals3d     = array_values($turnoIndex);

/* ===== ÚLTIMAS ASISTENCIAS ===== */
$ultimas = fetchAllRows($conn, "
    SELECT
        a.fecha,
        a.hora,
        COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.nombre, u.paterno, u.materno)), ''), 'Sin usuario') AS usuario,
        COALESCE(m.nombre, 'Sin materia') AS materia,
        COALESCE(t.nombre, 'Sin turno') AS turno,
        COALESCE(NULLIF(a.observacion, ''), '') AS observacion
    FROM asistencias a
    LEFT JOIN usuarios u ON u.id = a.usuario_id
    LEFT JOIN lk_materias m ON m.id = a.materia_id
    LEFT JOIN lk_turnos t ON t.id = a.turno_id
    ORDER BY a.fecha DESC, a.hora DESC, a.id DESC
    LIMIT 8
");

$topTurno = $turnosRows[0]['nombre'] ?? 'Sin datos';
$topMateria = $materiasRows[0]['nombre'] ?? 'Sin datos';

$turnosLabels = array_column($turnosRows, 'nombre');
$turnosValores = array_map('intval', array_column($turnosRows, 'total'));

$materiasLabels = array_column($materiasRows, 'nombre');
$materiasValores = array_map('intval', array_column($materiasRows, 'total'));

$aulasLabels = array_column($aulasRows, 'nombre');
$aulasValores = array_map('intval', array_column($aulasRows, 'total'));

$metodosLabels = array_column($metodosRows, 'nombre');
$metodosValores = array_map('intval', array_column($metodosRows, 'total'));

/* ===== EXPORT CSV ===== */
$baseUrl = strtok($_SERVER['REQUEST_URI'], '?');
$csvLink = $baseUrl . '?' . http_build_query(['export' => 'csv']);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="panel_docente_' . date('Ymd_His') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Fecha', 'Hora', 'Usuario', 'Materia', 'Turno', 'Observación']);
    foreach ($ultimas as $r) {
        fputcsv($out, [
            $r['fecha'] ?? '',
            $r['hora'] ?? '',
            $r['usuario'] ?? '',
            $r['materia'] ?? '',
            $r['turno'] ?? '',
            $r['observacion'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel Docente</title>
<meta name="csrf-token" content="<?= e($csrf) ?>">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="https://cdn.plot.ly/plotly-2.35.2.min.js"></script>

<style>
:root{
    --bg:#f3f6fb;
    --shadow:0 18px 50px rgba(15,23,42,.08);
    --radius:28px;
    --radius-sm:20px;
}

html, body { height: 100%; }

body{
    background:
        radial-gradient(circle at top right, rgba(14,165,233,.12), transparent 28%),
        radial-gradient(circle at top left, rgba(34,197,94,.10), transparent 25%),
        linear-gradient(180deg, #f8fafc 0%, #edf2f7 100%);
    font-family: Segoe UI, sans-serif;
    min-height: 100vh;
    color:#0f172a;
}

.sidebar{
    min-height:100vh;
    background:linear-gradient(180deg, #111827 0%, #0f172a 100%);
    color:#fff;
    position:sticky;
    top:0;
}

.logo{font-size:1.45rem;font-weight:800;letter-spacing:-.03em;}

.side-link{
    display:flex;align-items:center;gap:.75rem;color:#e2e8f0;text-decoration:none;
    padding:14px 16px;border-radius:18px;margin-bottom:10px;transition:.25s ease;
    background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.05);
    font-weight:600;
}
.side-link:hover{background:rgba(255,255,255,.10);transform:translateX(4px);color:#fff;}

.hero{
    background:linear-gradient(135deg, #0f172a 0%, #1d4ed8 50%, #0ea5e9 100%);
    color:#fff;border-radius:32px;padding:28px;box-shadow:0 25px 70px rgba(2,6,23,.20);
    position:relative;overflow:hidden;
}
.hero:before,.hero:after{content:"";position:absolute;border-radius:50%;background:rgba(255,255,255,.12);}
.hero:before{width:220px;height:220px;right:-60px;top:-60px;}
.hero:after{width:160px;height:160px;right:120px;bottom:-70px;background:rgba(34,197,94,.18);}

.kpi{
    color:#fff;border:none;border-radius:28px;box-shadow:var(--shadow);min-height:145px;position:relative;overflow:hidden;
}
.kpi .value{font-size:2.25rem;font-weight:900;line-height:1;}
.kpi .icon{font-size:2rem;opacity:.95;}
.kpi::after{content:"";position:absolute;width:180px;height:180px;border-radius:50%;right:-40px;bottom:-70px;background:rgba(255,255,255,.10);}

.s1{background:linear-gradient(135deg,#111827,#374151);}
.s2{background:linear-gradient(135deg,#2563eb,#60a5fa);}
.s3{background:linear-gradient(135deg,#f97316,#fdba74);}
.s4{background:linear-gradient(135deg,#059669,#6ee7b7);}
.s5{background:linear-gradient(135deg,#7c3aed,#c084fc);}
.s6{background:linear-gradient(135deg,#0f766e,#2dd4bf);}

.panel{
    background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:24px;
    border:1px solid rgba(148,163,184,.16);
}
.small-card{
    background:#fff;border-radius:24px;box-shadow:var(--shadow);padding:18px;border:1px solid rgba(148,163,184,.15);
}
.chart-wrap{height:340px;}
.chart-wrap-sm{height:290px;}
.chart-wrap-md{height:320px;}

.progress{height:12px;border-radius:999px;}
.table thead th{
    background:#f8fafc;color:#334155;font-weight:700;border-bottom:0;white-space:nowrap;
}
.text-soft{color:#64748b;}
.badge-soft{
    background:#eff6ff;color:#1e3a8a;border:1px solid #bfdbfe;
}
.tool-btn{border-radius:16px;}
.section-title{font-weight:800;letter-spacing:-.03em;}

.metric-pill{
    border-radius:999px;
    background:#f3f4f6;
    border:1px solid rgba(148,163,184,.20);
    padding:.42rem .8rem;
    font-size:.85rem;
    color:#111;
    font-weight:600;
}

.iframe-container{
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(148,163,184,.20);
    border-radius: var(--radius);
    background: #fff;
    box-shadow: var(--shadow);
}
.iframe-container iframe{
    width: 100%;
    height: 390px;
    border: 0;
    display: block;
    background: #fff;
}
.iframe-container.minimizado{
    height: 46px !important;
}
.iframe-container.minimizado iframe{
    height: 0 !important;
}
.iframe-container.fullscreen{
    position: fixed;
    inset: 2.5%;
    z-index: 9999;
    background: #fff;
    box-shadow: 0 30px 70px rgba(0,0,0,.28);
}
.iframe-btn{
    position: absolute;
    top: .75rem;
    right: .75rem;
    z-index: 2;
    border: 0;
    border-radius: 999px;
    padding: .42rem .85rem;
    font-size: .85rem;
    box-shadow: 0 8px 18px rgba(17,17,17,.15);
}

@media (max-width: 991px){
    .sidebar{
        min-height:auto;
        position:relative;
    }
}
</style>
</head>
<body>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 sidebar p-4">
            <div class="logo mb-5"><i class="bi bi-mortarboard-fill me-2"></i>PANEL DOCENTE</div>

            <a class="side-link" href="panel_docente.php"><i class="bi bi-grid-fill"></i>INICIO</a>
             <a class="side-link" href="../Reports/r.php"><i class="bi bi-graph-up-arrow"></i>REPORTES GRÁFICOS</a>
            
            <a class="side-link" href="../Reports/r_a.php"><i class="bi bi-clipboard-data-fill"></i>REPORTE ASISTENCIA</a>
            <a class="side-link" href="../Reports/r_estadisticas.php"><i class="bi bi-bar-chart-line-fill"></i>ESTADÍSTICAS</a>
            <a class="side-link" href="../asistencia/coordenada++.php"><i class="bi bi-geo-alt-fill"></i>GPS</a>
            <a class="side-link" href="<?= e($csvLink) ?>"><i class="bi bi-file-earmark-excel-fill"></i>EXPORTAR CSV</a>
            <a class="side-link" href="logout.php"><i class="bi bi-box-arrow-right"></i>SALIR</a>

            <div class="mt-4 p-3 small-card">
                <div class="small text-soft mb-1">Sesión activa</div>
                <div class="fw-semibold text-dark"><?= e($nombre) ?></div>
                <span class="badge text-bg-dark rounded-pill mt-2"><?= e(strtoupper($rol)) ?></span>
            </div>
        </div>

        <div class="col-lg-10 p-4 p-lg-5">
            <div class="hero mb-4">
                <div class="row align-items-center position-relative">
                    <div class="col-lg-8">
                        <div class="badge text-bg-light text-dark rounded-pill mb-3 px-3 py-2">Panel académico</div>
                        <h2 class="fw-bold mb-2">Hola <?= e($nombre) ?></h2>
                        <p class="mb-0 text-white-50">
                            Vista visual completa para docentes y superusuario con estadísticas, consultas rápidas, gráficos 2D y el cubo 3D de materias, turnos y cantidad.
                        </p>
                    </div>
                    <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                        <div class="display-4 fw-bold"><i class="bi bi-person-badge"></i></div>
                        <div class="text-white-50">Vista docente / superusuario</div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card kpi s1 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon"><i class="bi bi-clipboard-data"></i></div>
                            <span class="small text-white-50">Total</span>
                        </div>
                        <div class="value"><?= (int)$totalAsistencias ?></div>
                        <div class="mt-2">Asistencias registradas</div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card kpi s2 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon"><i class="bi bi-calendar-check"></i></div>
                            <span class="small text-white-50">Hoy</span>
                        </div>
                        <div class="value"><?= (int)$asistenciasHoy ?></div>
                        <div class="mt-2">Registros del día</div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card kpi s3 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon"><i class="bi bi-book-fill"></i></div>
                            <span class="small text-white-50">Materia top</span>
                        </div>
                        <div class="fs-4 fw-bold text-truncate"><?= e($topMateria) ?></div>
                        <div class="mt-2">Materia con más asistencia</div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card kpi s4 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon"><i class="bi bi-clock-history"></i></div>
                            <span class="small text-white-50">Turno top</span>
                        </div>
                        <div class="fs-4 fw-bold text-truncate"><?= e($topTurno) ?></div>
                        <div class="mt-2">Turno con más asistencia</div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card kpi s5 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon"><i class="bi bi-fingerprint"></i></div>
                            <span class="small text-white-50">Facial</span>
                        </div>
                        <div class="value"><?= (int)$facialCount ?></div>
                        <div class="mt-2">Asistencias por reconocimiento</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card kpi s6 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon"><i class="bi bi-pencil-square"></i></div>
                            <span class="small text-white-50">Manual</span>
                        </div>
                        <div class="value"><?= (int)$manualCount ?></div>
                        <div class="mt-2">Asistencias registradas a mano</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card kpi s2 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon"><i class="bi bi-person-gear"></i></div>
                            <span class="small text-white-50">Admin</span>
                        </div>
                        <div class="value"><?= (int)$adminCount ?></div>
                        <div class="mt-2">Asistencias cargadas por admin</div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="panel h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h4 class="mb-1 section-title">Actividad últimos 7 días</h4>
                                <div class="text-soft">Serie temporal de asistencias</div>
                            </div>
                            <span class="metric-pill"><i class="bi bi-graph-up-arrow me-1"></i>Línea</span>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="chartDias"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="panel h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h4 class="mb-1 section-title">Estado del sistema</h4>
                                <div class="text-soft">Conexión y métricas rápidas</div>
                            </div>
                            <i class="bi bi-cpu fs-2 text-primary"></i>
                        </div>

                        <div class="small-card mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>Base de datos</strong>
                                <span class="badge text-bg-<?= e($connectionState['class']) ?>">
                                    <i class="bi bi-<?= e($connectionState['icon']) ?> me-1"></i><?= e($connectionState['text']) ?>
                                </span>
                            </div>
                            <div class="text-soft small"><?= e((string)$dbServer) ?> · <?= e((string)$dbName) ?></div>
                        </div>

                        <div class="small-card mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Total asistencias</strong>
                                <span class="badge text-bg-dark"><?= (int)$totalAsistencias ?></span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-primary" style="width: <?= min(100, max(10, (int)round(($totalAsistencias / max(1, $totalAsistencias)) * 100))) ?>%"></div>
                            </div>
                        </div>

                        <div class="small-card mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Asistencias de hoy</strong>
                                <span class="badge text-bg-success"><?= (int)$asistenciasHoy ?></span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-success" style="width: <?= min(100, max(5, (int)round(($asistenciasHoy / max(1, $totalAsistencias)) * 100))) ?>%"></div>
                            </div>
                        </div>

                        <div class="small-card">
                            <div class="text-soft small">Fecha y hora actual</div>
                            <div class="fw-bold fs-5"><?= date('d/m/Y H:i') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-7">
                    <div class="panel h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h4 class="mb-1 section-title">Top estudiantes por registros</h4>
                                <div class="text-soft">Vista por año académico</div>
                            </div>
                            <i class="bi bi-trophy-fill fs-2 text-warning"></i>
                        </div>
                        <?php
                        $topEstudiantes = fetchAllRows($conn, "
                            SELECT
                                u.id,
                                COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.nombre, u.paterno, u.materno)), ''), 'Sin nombre') AS nombre_completo,
                                COUNT(*) AS total
                            FROM asistencias asis
                            INNER JOIN usuarios u ON u.id = asis.usuario_id
                            WHERE u.rol = 'estudiante'
                            GROUP BY u.id, nombre_completo
                            ORDER BY total DESC, nombre_completo ASC
                            LIMIT 10
                        ");
                        $topLabels = array_map(static fn($x) => $x['nombre_completo'], $topEstudiantes);
                        $topData   = array_map(static fn($x) => (int)$x['total'], $topEstudiantes);
                        ?>
                        <div class="chart-wrap-md">
                            <canvas id="topChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="panel h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h4 class="mb-1 section-title">Distribución por turno</h4>
                                <div class="text-soft">Carga por horarios</div>
                            </div>
                            <i class="bi bi-clock-history fs-2 text-warning"></i>
                        </div>
                        <div class="chart-wrap-sm">
                            <canvas id="turnosChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-7">
                    <div class="panel h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h4 class="mb-1 section-title">Materias más usadas</h4>
                                <div class="text-soft">Top por volumen de asistencia</div>
                            </div>
                            <i class="bi bi-journal-bookmark-fill fs-2 text-info"></i>
                        </div>
                        <div class="chart-wrap-md">
                            <canvas id="materiasChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="panel h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h4 class="mb-1 section-title">Accesos rápidos</h4>
                                <div class="text-soft">Herramientas disponibles</div>
                            </div>
                            <i class="bi bi-lightning-charge-fill fs-2 text-danger"></i>
                        </div>

                        <div class="d-grid gap-2">
                            <a class="btn btn-primary tool-btn" href="../php/reportes.php"><i class="bi bi-graph-up-arrow me-2"></i>Reportes gráficos</a>
                            <a class="btn btn-outline-primary tool-btn" href="../asistencia/coordenada++.php"><i class="bi bi-geo-alt-fill me-2"></i>GPS / coordenadas</a>
                            <a class="btn btn-outline-success tool-btn" href="<?= e($csvLink) ?>"><i class="bi bi-file-earmark-excel me-2"></i>Exportar CSV</a>
                            <a class="btn btn-outline-dark tool-btn" href="../php/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1 section-title">Resumen de módulos</h4>
                        <div class="text-soft">Conteo de elementos del sistema</div>
                    </div>
                    <span class="badge badge-soft px-3 py-2">Panel en línea</span>
                </div>

                <div class="row g-3">
                    <?php
                    $modulos = [
                        ['label' => 'Turnos', 'value' => count($turnosRows), 'icon' => 'clock-history', 'class' => 'info'],
                        ['label' => 'Materias', 'value' => count($materiasRows), 'icon' => 'journal-bookmark-fill', 'class' => 'warning'],
                        ['label' => 'Aulas', 'value' => count($aulasRows), 'icon' => 'door-open-fill', 'class' => 'primary'],
                        ['label' => 'Métodos', 'value' => count($metodosRows), 'icon' => 'fingerprint', 'class' => 'success'],
                    ];
                    foreach ($modulos as $a):
                    ?>
                        <div class="col-md-3">
                            <div class="small-card h-100">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="text-soft small"><?= e($a['label']) ?></div>
                                        <div class="fs-3 fw-bold"><?= (int)$a['value'] ?></div>
                                    </div>
                                    <i class="bi bi-<?= e($a['icon']) ?> fs-3 text-<?= e($a['class']) ?>"></i>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="panel mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="mb-1 section-title">Últimas asistencias</h4>
                        <div class="text-soft">Actividad reciente del sistema</div>
                    </div>
                    <span class="badge text-bg-dark"><?= count($ultimas) ?> registros</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Usuario</th>
                                <th>Materia</th>
                                <th>Turno</th>
                                <th>Observación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($ultimas)): ?>
                                <?php foreach ($ultimas as $u): ?>
                                    <tr>
                                        <td><?= e(date('d/m/Y', strtotime($u['fecha']))) ?></td>
                                        <td><?= e(date('H:i', strtotime($u['hora']))) ?></td>
                                        <td><?= e($u['usuario']) ?></td>
                                        <td><?= e($u['materia']) ?></td>
                                        <td><?= e($u['turno']) ?></td>
                                        <td><?= e($u['observacion']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center text-soft py-4">Sin registros</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="panel h-100">
                        <h4 class="mb-1 section-title">Notas operativas</h4>
                        <p class="text-soft mb-3">
                            Este panel usa consultas reales de asistencias, aulas, turnos, materias y métodos, con una interfaz más limpia y alineada al panel de superusuario.
                        </p>
                        <ul class="mb-0">
                            <li>Estilo visual unificado con el dashboard principal.</li>
                            <li>Consultas agrupadas y ordenadas para evitar fallas.</li>
                            <li>Exportación CSV de registros recientes.</li>
                            <li>Panel responsive con tarjetas, gráficas y tabla moderna.</li>
                        </ul>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="panel h-100">
                        <h4 class="mb-1 section-title">Acción sugerida</h4>
                        <p class="text-soft mb-3">
                            Puedes dejar este panel como inicio docente y conservar el cubo 3D para análisis rápido entre materias, turnos y volumen de asistencia.
                        </p>
                        <div class="d-grid gap-2 d-md-flex">
                            <a class="btn btn-primary tool-btn" href="../php/reportes.php"><i class="bi bi-bar-chart-line-fill me-2"></i>Ir a reportes</a>
                            <a class="btn btn-outline-dark tool-btn" href="../php/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Salir</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="mb-1 section-title">Reporte 3D: materia vs turno vs cantidad</h4>
                        <div class="text-soft">Se ajustó la vista, el tamaño de puntos y los ejes para que se aprecie mejor</div>
                    </div>
                    <span class="metric-pill"><i class="bi bi-cube me-1"></i>3D</span>
                </div>
                <div id="chart3d" style="height:520px;"></div>
            </div>

            <div class="panel mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="mb-1 section-title">Módulos embebidos</h4>
                        <div class="text-soft">Acceso visual a los dos componentes principales</div>
                    </div>
                    <span class="metric-pill"><i class="bi bi-window-stack me-1"></i>iframes</span>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-xl-6">
                        <div id="iframe3" class="iframe-container">
                            <iframe src="../Reports/r_a.php"></iframe>
                            <button class="btn btn-dark iframe-btn" onclick="toggleIframe('iframe3', this)">Normal</button>
                        </div>
                    </div>

                    <div class="col-12 col-xl-6">
                        <div id="iframe4" class="iframe-container">
                            <iframe src="../asistencia/coordenada++.php"></iframe>
                            <button class="btn btn-dark iframe-btn" onclick="toggleIframe('iframe4', this)">Normal</button>
                        </div>
                    </div>
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

const diasLabels = <?= json_encode($diasLabels, JSON_UNESCAPED_UNICODE) ?>;
const diasValores = <?= json_encode($diasValores, JSON_UNESCAPED_UNICODE) ?>;

const turnosLabels = <?= json_encode($turnosLabels, JSON_UNESCAPED_UNICODE) ?>;
const turnosValores = <?= json_encode($turnosValores, JSON_UNESCAPED_UNICODE) ?>;

const materiasLabels = <?= json_encode($materiasLabels, JSON_UNESCAPED_UNICODE) ?>;
const materiasValores = <?= json_encode($materiasValores, JSON_UNESCAPED_UNICODE) ?>;

const aulasLabels = <?= json_encode($aulasLabels, JSON_UNESCAPED_UNICODE) ?>;
const aulasValores = <?= json_encode($aulasValores, JSON_UNESCAPED_UNICODE) ?>;

const metodosLabels = <?= json_encode($metodosLabels, JSON_UNESCAPED_UNICODE) ?>;
const metodosValores = <?= json_encode($metodosValores, JSON_UNESCAPED_UNICODE) ?>;

const topLabels = <?= json_encode($topLabels, JSON_UNESCAPED_UNICODE) ?>;
const topData   = <?= json_encode($topData, JSON_UNESCAPED_UNICODE) ?>;

const points3d = <?= json_encode($points3d, JSON_UNESCAPED_UNICODE) ?>;
const materiaLabels3d = <?= json_encode($materiaLabels3d, JSON_UNESCAPED_UNICODE) ?>;
const turnoLabels3d   = <?= json_encode($turnoLabels3d, JSON_UNESCAPED_UNICODE) ?>;
const materiaVals3d    = <?= json_encode($materiaVals3d, JSON_UNESCAPED_UNICODE) ?>;
const turnoVals3d      = <?= json_encode($turnoVals3d, JSON_UNESCAPED_UNICODE) ?>;

const COLORS = {
    black: '#111111',
    black2: '#2b2b2b',
    black3: '#444444',
    blue: '#2563eb',
    green: '#16a34a',
    orange: '#f59e0b',
    red: '#dc2626',
    purple: '#7c3aed',
    slate: '#64748b',
    cyan: '#0891b2',
    pink: '#db2777'
};

function palette(n) {
    const base = [
        COLORS.black, COLORS.blue, COLORS.green, COLORS.orange,
        COLORS.purple, COLORS.red, COLORS.cyan, COLORS.pink, COLORS.black2, COLORS.slate
    ];
    return Array.from({ length: n }, (_, i) => base[i % base.length]);
}

function gridColor() {
    return 'rgba(148,163,184,.18)';
}

new Chart(document.getElementById('chartDias'), {
    type: 'line',
    data: {
        labels: diasLabels,
        datasets: [{
            label: 'Asistencias',
            data: diasValores,
            borderColor: '#2563eb',
            borderWidth: 3,
            tension: .35,
            fill: true,
            pointRadius: 5,
            pointHoverRadius: 7,
            pointBackgroundColor: '#111827',
            backgroundColor: function(context) {
                const chart = context.chart;
                const { ctx, chartArea } = chart;
                if (!chartArea) return 'rgba(37,99,235,.15)';
                const g = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                g.addColorStop(0, 'rgba(37,99,235,.35)');
                g.addColorStop(1, 'rgba(37,99,235,.02)');
                return g;
            }
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                backgroundColor: '#111827',
                titleColor: '#fff',
                bodyColor: '#fff'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: gridColor() }
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
            data: materiasValores,
            borderRadius: 16,
            borderSkipped: false,
            backgroundColor: function(context) {
                const chart = context.chart;
                const { ctx, chartArea } = chart;
                if (!chartArea) return '#0f766e';
                const g = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                g.addColorStop(0, '#0f766e');
                g.addColorStop(1, '#2dd4bf');
                return g;
            }
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#111827',
                titleColor: '#fff',
                bodyColor: '#fff'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: gridColor() }
            },
            x: {
                grid: { display: false }
            }
        }
    }
});

new Chart(document.getElementById('turnosChart'), {
    type: 'doughnut',
    data: {
        labels: turnosLabels,
        datasets: [{
            data: turnosValores,
            backgroundColor: ['#0f172a', '#2563eb', '#0ea5e9', '#22c55e', '#f59e0b', '#a855f7'],
            borderColor: '#ffffff',
            borderWidth: 2,
            hoverOffset: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '62%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    pointStyle: 'circle',
                    boxWidth: 10
                }
            },
            tooltip: {
                backgroundColor: '#111827',
                titleColor: '#fff',
                bodyColor: '#fff'
            }
        }
    }
});

new Chart(document.getElementById('topChart'), {
    type: 'bar',
    data: {
        labels: topLabels,
        datasets: [{
            label: 'Registros',
            data: topData,
            borderRadius: 16,
            borderSkipped: false,
            backgroundColor: '#1d4ed8'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: {
            y: { ticks: { color: '#334155' } },
            x: { beginAtZero: true, grid: { color: gridColor() } }
        }
    }
});

/* ===== 3D ===== */
const trace3d = {
    type: 'scatter3d',
    mode: 'markers+text',
    x: points3d.map(p => p.x),
    y: points3d.map(p => p.y),
    z: points3d.map(p => p.z),
    text: points3d.map(p => `${p.materia}<br>${p.turno}`),
    textposition: 'top center',
    hovertemplate:
        '<b>%{text}</b><br>' +
        'Cantidad: %{z}<extra></extra>',
    marker: {
        size: points3d.map(p => Math.max(7, Math.min(22, 6 + p.z * 0.45))),
        color: points3d.map(p => p.z),
        colorscale: [
            [0.00, '#111111'],
            [0.20, '#374151'],
            [0.40, '#2563eb'],
            [0.60, '#16a34a'],
            [0.80, '#f59e0b'],
            [1.00, '#dc2626']
        ],
        cmin: 0,
        opacity: 0.96,
        line: {
            color: '#ffffff',
            width: 1
        }
    }
};

const layout3d = {
    margin: { l: 0, r: 0, b: 0, t: 0 },
    paper_bgcolor: '#ffffff',
    plot_bgcolor: '#ffffff',
    scene: {
        aspectmode: 'cube',
        camera: {
            eye: { x: 1.75, y: 1.55, z: 1.15 }
        },
        xaxis: {
            showbackground: true,
            backgroundcolor: '#dbeafe',
            gridcolor: '#93c5fd',
            zerolinecolor: '#60a5fa',
            tickfont: { color: '#111' }
        },
        yaxis: {
            showbackground: true,
            backgroundcolor: '#dbeafe',
            gridcolor: '#93c5fd',
            zerolinecolor: '#60a5fa',
            tickfont: { color: '#111' }
        },
        zaxis: {
            showbackground: true,
            backgroundcolor: '#dbeafe',
            gridcolor: '#93c5fd',
            zerolinecolor: '#60a5fa',
            tickfont: { color: '#111' }
        }
    },
    showlegend: false,
    font: { color: '#111' }
};

const config3d = {
    displayModeBar: false,
    responsive: true
};

if (points3d.length > 0) {
    Plotly.newPlot('chart3d', [trace3d], layout3d, config3d);
} else {
    document.getElementById('chart3d').innerHTML = '<div class="text-center text-muted py-5">Sin datos para mostrar el 3D</div>';
}

/* ===== Iframes ===== */
function toggleIframe(id, btn) {
    const container = document.getElementById(id);

    if (!container.classList.contains('minimizado') && !container.classList.contains('fullscreen')) {
        container.classList.add('minimizado');
        btn.textContent = 'Minimizado';
        return;
    }

    if (container.classList.contains('minimizado')) {
        container.classList.remove('minimizado');
        container.classList.add('fullscreen');
        btn.textContent = 'Pantalla completa';
        return;
    }

    if (container.classList.contains('fullscreen')) {
        container.classList.remove('fullscreen');
        btn.textContent = 'Normal';
        return;
    }
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.iframe-container.fullscreen').forEach(el => {
            el.classList.remove('fullscreen');
        });
        document.querySelectorAll('.iframe-btn').forEach(btn => {
            btn.textContent = 'Normal';
        });
    }
});
</script>
</body>
</html>