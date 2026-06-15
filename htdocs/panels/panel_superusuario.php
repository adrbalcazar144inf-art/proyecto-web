<?php
session_start();
require_once '../TOOLS/conexion.php';

date_default_timezone_set('America/La_Paz');

$conn = conectarse();
$conn->set_charset('utf8mb4');
 

function e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function fetchOne(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if ($types !== '') {
        $refs = [$types];
        foreach ($params as $k => $v) {
            $refs[] = &$params[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }

    $stmt->execute();
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
        $refs = [$types];
        foreach ($params as $k => $v) {
            $refs[] = &$params[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();
    return $rows;
}

function safeColumnExists(mysqli $conn, string $table, string $column): bool {
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
    $row = fetchOne($conn, $sql, 'ss', [$table, $column]);
    return !empty($row);
}

function numeroMeses(): array {
    return [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
}

function statusBadge(bool $ok, string $okText = 'Conectado', string $badText = 'Sin conexión'): array {
    return $ok ? ['text' => $okText, 'class' => 'success', 'icon' => 'check-circle-fill'] : ['text' => $badText, 'class' => 'danger', 'icon' => 'x-circle-fill'];
}

// --- Verificación de conexión ---
$dbOk = true;
$dbName = 'Base de datos';
$dbServer = 'Servidor';
try {
    $probe = fetchOne($conn, 'SELECT DATABASE() AS dbname, @@hostname AS host');
    $dbName = $probe['dbname'] ?? $dbName;
    $dbServer = $probe['host'] ?? $dbServer;
} catch (Throwable $e) {
    $dbOk = false;
}

$connectionState = statusBadge($dbOk);

// --- Filtros ---
$anioActual = (int)date('Y');
$anio = isset($_GET['anio']) && $_GET['anio'] !== '' ? (int)$_GET['anio'] : $anioActual;
$anioOpciones = [];
for ($y = $anioActual - 3; $y <= $anioActual + 1; $y++) {
    $anioOpciones[] = $y;
}

// --- KPI globales ---
$totalEstudiantes = (int)fetchOne($conn, "SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'estudiante'")['total'] ?? 0;
$totalAsistencias = (int)fetchOne($conn, "SELECT COUNT(*) AS total FROM asistencias")['total'] ?? 0;
$asistenciasHoy = (int)fetchOne($conn, "SELECT COUNT(*) AS total FROM asistencias WHERE fecha = CURDATE()")['total'] ?? 0;
$asistenciasMes = (int)fetchOne($conn, "SELECT COUNT(*) AS total FROM asistencias WHERE fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")['total'] ?? 0;
$aulasActivas = (int)fetchOne($conn, "SELECT COUNT(*) AS total FROM lk_aulas")['total'] ?? 0;
$turnosActivos = (int)fetchOne($conn, "SELECT COUNT(*) AS total FROM lk_turnos")['total'] ?? 0;
$materiasActivas = (int)fetchOne($conn, "SELECT COUNT(*) AS total FROM lk_materias")['total'] ?? 0;
$metodosActivos = (int)fetchOne($conn, "SELECT COUNT(*) AS total FROM lk_metodos_asistencia")['total'] ?? 0;

$gpsActivos = (int)fetchOne($conn, "SELECT COUNT(*) AS total FROM asistencias WHERE ubicacion_gps IS NOT NULL")['total'] ?? 0;
$fotosCargadas = (int)fetchOne($conn, "SELECT COUNT(*) AS total FROM asistencias WHERE foto_asistencia IS NOT NULL AND foto_asistencia <> ''")['total'] ?? 0;
$observaciones = (int)fetchOne($conn, "SELECT COUNT(*) AS total FROM asistencias WHERE observacion IS NOT NULL AND observacion <> ''")['total'] ?? 0;

// --- Gráficas ---
$mensual = fetchAllRows($conn, "
    SELECT MONTH(fecha) AS mes, COUNT(*) AS total
    FROM asistencias
    WHERE YEAR(fecha) = ?
    GROUP BY MONTH(fecha)
    ORDER BY MONTH(fecha)
", 'i', [$anio]);

$mensualMap = [];
$meses = numeroMeses();
foreach ($meses as $num => $nom) {
    $mensualMap[$num] = ['label' => $nom, 'total' => 0];
}
foreach ($mensual as $r) {
    $m = (int)$r['mes'];
    if (isset($mensualMap[$m])) {
        $mensualMap[$m]['total'] = (int)$r['total'];
    }
}
$chartMesLabels = array_column($mensualMap, 'label');
$chartMesData = array_column($mensualMap, 'total');

$porAula = fetchAllRows($conn, "
    SELECT COALESCE(a.nombre,'Sin aula') AS label, COUNT(*) AS total
    FROM asistencias asis
    LEFT JOIN lk_aulas a ON a.id = asis.aula_id
    WHERE YEAR(asis.fecha) = ?
    GROUP BY a.id, a.nombre
    ORDER BY total DESC, label ASC
", 'i', [$anio]);

$porTurno = fetchAllRows($conn, "
    SELECT COALESCE(t.nombre,'Sin turno') AS label, COUNT(*) AS total
    FROM asistencias asis
    LEFT JOIN lk_turnos t ON t.id = asis.turno_id
    WHERE YEAR(asis.fecha) = ?
    GROUP BY t.id, t.nombre
    ORDER BY total DESC, label ASC
", 'i', [$anio]);

$porMateria = fetchAllRows($conn, "
    SELECT COALESCE(m.nombre,'Sin materia') AS label, COUNT(*) AS total
    FROM asistencias asis
    LEFT JOIN lk_materias m ON m.id = asis.materia_id
    WHERE YEAR(asis.fecha) = ?
    GROUP BY m.id, m.nombre
    ORDER BY total DESC, label ASC
    LIMIT 8
", 'i', [$anio]);

$porMetodo = fetchAllRows($conn, "
    SELECT COALESCE(mt.nombre,'Sin método') AS label, COUNT(*) AS total
    FROM asistencias asis
    LEFT JOIN lk_metodos_asistencia mt ON mt.id = asis.metodo_id
    WHERE YEAR(asis.fecha) = ?
    GROUP BY mt.id, mt.nombre
    ORDER BY total DESC, label ASC
", 'i', [$anio]);

$topEstudiantes = fetchAllRows($conn, "
    SELECT
        u.id,
        CONCAT(COALESCE(u.nombre,''),' ',COALESCE(u.paterno,''),' ',COALESCE(u.materno,'')) AS nombre_completo,
        COUNT(*) AS total,
        SUM(CASE WHEN asis.ubicacion_gps IS NOT NULL THEN 1 ELSE 0 END) AS gps,
        SUM(CASE WHEN asis.foto_asistencia IS NOT NULL AND asis.foto_asistencia <> '' THEN 1 ELSE 0 END) AS fotos
    FROM asistencias asis
    INNER JOIN usuarios u ON u.id = asis.usuario_id
    WHERE u.rol = 'estudiante' AND YEAR(asis.fecha) = ?
    GROUP BY u.id, nombre_completo
    ORDER BY total DESC, nombre_completo ASC
    LIMIT 10
", 'i', [$anio]);

$ultimosRegistros = fetchAllRows($conn, "
    SELECT
        asis.fecha,
        asis.hora,
        CONCAT(COALESCE(u.nombre,''),' ',COALESCE(u.paterno,''),' ',COALESCE(u.materno,'')) AS estudiante,
        COALESCE(a.nombre,'Sin aula') AS aula,
        COALESCE(t.nombre,'Sin turno') AS turno,
        COALESCE(m.nombre,'Sin materia') AS materia,
        COALESCE(mt.nombre,'Sin método') AS metodo,
        CASE WHEN asis.ubicacion_gps IS NOT NULL THEN 1 ELSE 0 END AS gps_activo,
        asis.foto_asistencia,
        asis.observacion
    FROM asistencias asis
    INNER JOIN usuarios u ON u.id = asis.usuario_id
    LEFT JOIN lk_aulas a ON a.id = asis.aula_id
    LEFT JOIN lk_turnos t ON t.id = asis.turno_id
    LEFT JOIN lk_materias m ON m.id = asis.materia_id
    LEFT JOIN lk_metodos_asistencia mt ON mt.id = asis.metodo_id
    ORDER BY asis.fecha DESC, asis.hora DESC, asis.id DESC
    LIMIT 12
");

$alertas = [];
$alertas[] = ['label' => 'Aulas', 'value' => $aulasActivas, 'icon' => 'door-open', 'class' => 'primary'];
$alertas[] = ['label' => 'Turnos', 'value' => $turnosActivos, 'icon' => 'clock-history', 'class' => 'info'];
$alertas[] = ['label' => 'Materias', 'value' => $materiasActivas, 'icon' => 'journal-bookmark-fill', 'class' => 'warning'];
$alertas[] = ['label' => 'Métodos', 'value' => $metodosActivos, 'icon' => 'fingerprint', 'class' => 'success'];

$baseUrl = strtok($_SERVER['REQUEST_URI'], '?');
$csvLink = $baseUrl . '?' . http_build_query(['anio' => $anio, 'export' => 'csv']);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="panel_superusuario_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Fecha', 'Hora', 'Estudiante', 'Aula', 'Turno', 'Materia', 'Método', 'GPS', 'Foto', 'Observación']);
    foreach ($ultimosRegistros as $r) {
        fputcsv($out, [
            $r['fecha'],
            $r['hora'],
            $r['estudiante'],
            $r['aula'],
            $r['turno'],
            $r['materia'],
            $r['metodo'],
            ((int)$r['gps_activo'] === 1) ? 'Sí' : 'No',
            !empty($r['foto_asistencia']) ? 'Sí' : 'No',
            $r['observacion'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

$turnosLabels = array_map(function($x) { return $x['label']; }, $porTurno);
$turnosData   = array_map(function($x) { return (int)$x['total']; }, $porTurno);

$materiasLabels = array_map(function($x) { return $x['label']; }, $porMateria);
$materiasData   = array_map(function($x) { return (int)$x['total']; }, $porMateria);

$topLabels = array_map(function($x) { return $x['nombre_completo']; }, $topEstudiantes);
$topData   = array_map(function($x) { return (int)$x['total']; }, $topEstudiantes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel de Superusuario</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<style>
:root{
    --bg:#f3f6fb;
    --shadow:0 18px 50px rgba(15,23,42,.08);
    --radius:28px;
}
body{
    background:
        radial-gradient(circle at top right, rgba(14,165,233,.12), transparent 28%),
        radial-gradient(circle at top left, rgba(34,197,94,.10), transparent 25%),
        linear-gradient(180deg, #f8fafc 0%, #edf2f7 100%);
    font-family:Segoe UI, sans-serif;
    min-height:100vh;
    color:#0f172a;
}
.sidebar{
    min-height:100vh;
    background:linear-gradient(180deg, #111827 0%, #0f172a 100%);
    color:#fff;
    position:sticky;
    top:0;
}
.logo{font-size:1.45rem;font-weight:800;}
.side-link{
    display:flex;align-items:center;gap:.75rem;color:#e2e8f0;text-decoration:none;
    padding:14px 16px;border-radius:18px;margin-bottom:10px;transition:.25s ease;
    background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.05);
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
.kpi{color:#fff;border:none;border-radius:28px;box-shadow:var(--shadow);min-height:145px;position:relative;overflow:hidden;}
.kpi .value{font-size:2.35rem;font-weight:900;line-height:1;}
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
.small-card{background:#fff;border-radius:24px;box-shadow:var(--shadow);padding:18px;border:1px solid rgba(148,163,184,.15);}
.chart-wrap{height:340px;}
.chart-wrap-sm{height:290px;}
.chart-wrap-md{height:320px;}
.progress{height:12px;border-radius:999px;}
.table thead th{background:#f8fafc;color:#334155;font-weight:700;border-bottom:0;white-space:nowrap;}
.text-soft{color:#64748b;}
.badge-soft{background:#eff6ff;color:#1e3a8a;border:1px solid #bfdbfe;}
.tool-btn{border-radius:16px;}
.section-title{font-weight:800;}
@media (max-width: 991px){.sidebar{min-height:auto; position:relative;}}
</style>
</head>
<body>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-lg-2 sidebar p-4">
            <div class="logo mb-5"><i class="bi bi-mortarboard-fill me-2"></i>SUPERUSUARIO</div>
            <a class="side-link" href="#"><i class="bi bi-grid-fill"></i>INICIO</a>
            <a class="side-link" href="crud1.php"><i class="bi bi-people-fill"></i>ALUMNOS</a>
            <a class="side-link" href="aulas.php"><i class="bi bi-door-open-fill"></i>AULAS</a>
            <a class="side-link" href="password.php"><i class="bi bi-shield-lock-fill"></i>PASSWORD</a>
            <a class="side-link" href="../Reports/r.php"><i class="bi bi-graph-up-arrow"></i>REPORTES GRÁFICOS</a>
            
            <a class="side-link" href="../Reports/r_a.php"><i class="bi bi-clipboard-data-fill"></i>REPORTE ASISTENCIA</a>
            <a class="side-link" href="../Reports/r_estadisticas.php"><i class="bi bi-bar-chart-line-fill"></i>ESTADÍSTICAS</a>
            <a class="side-link" href="../asistencia/coordenada++.php"><i class="bi bi-geo-alt-fill"></i>GPS</a>
            <a class="side-link" href="../TOOLS/backup.php"><i class="bi bi-hdd-fill"></i>BACKUP</a>
            <a class="side-link" href="logout.php"><i class="bi bi-box-arrow-right"></i>SALIR</a>
        </div>

        <div class="col-lg-10 p-4 p-lg-5">
            <div class="hero mb-4">
                <div class="row align-items-center position-relative">
                    <div class="col-lg-8">
                        <div class="badge text-bg-light text-dark rounded-pill mb-3 px-3 py-2">Panel académico</div>
                        <h2 class="fw-bold mb-2">Hola <?= $nombre ?></h2>
                        <p class="mb-0 text-white-50">Vista administrativa completa con estado de base de datos, actividad general, reportes y accesos rápidos.</p>
                    </div>
                    <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                        <div class="display-4 fw-bold"><i class="bi bi-person-gear"></i></div>
                        <div class="text-white-50">Vista Superusuario</div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card kpi s1 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon"><i class="bi bi-people-fill"></i></div>
                            <span class="small text-white-50">Estudiantes</span>
                        </div>
                        <div class="value"><?= (int)$totalEstudiantes ?></div>
                        <div class="mt-2">Registrados en el sistema</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card kpi s2 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon"><i class="bi bi-calendar-check"></i></div>
                            <span class="small text-white-50">Hoy</span>
                        </div>
                        <div class="value"><?= (int)$asistenciasHoy ?></div>
                        <div class="mt-2">Asistencias del día</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card kpi s3 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon"><i class="bi bi-calendar-month"></i></div>
                            <span class="small text-white-50">Mes</span>
                        </div>
                        <div class="value"><?= (int)$asistenciasMes ?></div>
                        <div class="mt-2">Registros del mes</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card kpi s4 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon"><i class="bi bi-broadcast-pin"></i></div>
                            <span class="small text-white-50">GPS</span>
                        </div>
                        <div class="value"><?= (int)$gpsActivos ?></div>
                        <div class="mt-2">Registros con ubicación</div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card kpi s5 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon"><i class="bi bi-camera-fill"></i></div>
                            <span class="small text-white-50">Fotos</span>
                        </div>
                        <div class="value"><?= (int)$fotosCargadas ?></div>
                        <div class="mt-2">Evidencias cargadas</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card kpi s6 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon"><i class="bi bi-journal-text"></i></div>
                            <span class="small text-white-50">Observ.</span>
                        </div>
                        <div class="value"><?= (int)$observaciones ?></div>
                        <div class="mt-2">Con observación</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card kpi s1 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon"><i class="bi bi-check2-square"></i></div>
                            <span class="small text-white-50">Turnos</span>
                        </div>
                        <div class="value"><?= (int)$turnosActivos ?></div>
                        <div class="mt-2">Turnos activos</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card kpi s2 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon"><i class="bi bi-door-open-fill"></i></div>
                            <span class="small text-white-50">Aulas</span>
                        </div>
                        <div class="value"><?= (int)$aulasActivas ?></div>
                        <div class="mt-2">Aulas registradas</div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="panel h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h4 class="mb-1 section-title">Actividad mensual</h4>
                                <div class="text-soft">Asistencias del año <?= (int)$anio ?></div>
                            </div>
                            <form method="GET" class="d-flex gap-2 align-items-center">
                                <select name="anio" class="form-select form-select-sm" style="min-width:120px">
                                    <?php foreach ($anioOpciones as $y): ?>
                                        <option value="<?= (int)$y ?>" <?= (int)$y === (int)$anio ? 'selected' : '' ?>><?= (int)$y ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-primary btn-sm tool-btn" type="submit"><i class="bi bi-funnel-fill"></i></button>
                            </form>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="diasChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="panel h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h4 class="mb-1 section-title">Estado del sistema</h4>
                                <div class="text-soft">Información rápida</div>
                            </div>
                            <i class="bi bi-cpu fs-2 text-primary"></i>
                        </div>

                        <div class="small-card mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>Base de datos</strong>
                                <span class="badge text-bg-<?= e($connectionState['class']) ?>"><i class="bi bi-<?= e($connectionState['icon']) ?> me-1"></i><?= e($connectionState['text']) ?></span>
                            </div>
                            <div class="text-soft small"><?= e((string)$dbServer) ?> · <?= e((string)$dbName) ?></div>
                        </div>

                        <div class="small-card mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Total asistencias</strong>
                                <span class="badge text-bg-dark"><?= (int)$totalAsistencias ?></span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-primary" style="width: <?= min(100, max(10, (int)round(($totalAsistencias / max(1, $totalEstudiantes)) * 10))) ?>%"></div>
                            </div>
                        </div>

                        <div class="small-card mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Registros este mes</strong>
                                <span class="badge text-bg-success"><?= (int)$asistenciasMes ?></span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-success" style="width: <?= min(100, max(5, (int)round(($asistenciasMes / max(1, $totalAsistencias)) * 100))) ?>%"></div>
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
                                <h4 class="mb-1 section-title">Top estudiantes</h4>
                                <div class="text-soft">Más registros en el año seleccionado</div>
                            </div>
                            <i class="bi bi-trophy-fill fs-2 text-warning"></i>
                        </div>
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
                                <div class="text-soft">Herramientas del superusuario</div>
                            </div>
                            <i class="bi bi-lightning-charge-fill fs-2 text-danger"></i>
                        </div>

                        <div class="d-grid gap-2">
                            <a class="btn btn-primary tool-btn" href="../Reports/r.php"><i class="bi bi-graph-up-arrow me-2"></i>Reportes gráficos</a>
                            <a class="btn btn-outline-primary tool-btn" href="../Reports/r_a.php"><i class="bi bi-clipboard-data-fill me-2"></i>Reporte asistencia</a>
                            <a class="btn btn-outline-secondary tool-btn" href="../Reports/r_estadisticas.php"><i class="bi bi-bar-chart-line-fill me-2"></i>Estadísticas</a>
                            <a class="btn btn-outline-success tool-btn" href="../TOOLS/backup.php"><i class="bi bi-hdd-fill me-2"></i>Backup de base</a>
                            <a class="btn btn-outline-info tool-btn" href="../asistencia/coordenada++.php"><i class="bi bi-geo-alt-fill me-2"></i>GPS / coordenadas</a>
                            <a class="btn btn-outline-dark tool-btn" href="<?= e($csvLink) ?>"><i class="bi bi-file-earmark-excel me-2"></i>Exportar CSV</a>
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
                    <?php foreach ($alertas as $a): ?>
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
                        <h4 class="mb-1 section-title">Últimos registros</h4>
                        <div class="text-soft">Actividad reciente del sistema</div>
                    </div>
                    <span class="badge text-bg-dark"><?= count($ultimosRegistros) ?> registros</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Estudiante</th>
                                <th>Materia</th>
                                <th>Turno</th>
                                <th>Aula</th>
                                <th>Método</th>
                                <th>GPS</th>
                                <th>Foto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($ultimosRegistros)): ?>
                                <?php foreach ($ultimosRegistros as $fila): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($fila['fecha'])) ?></td>
                                        <td><?= date('H:i', strtotime($fila['hora'])) ?></td>
                                        <td><?= e($fila['estudiante']) ?></td>
                                        <td><?= e($fila['materia']) ?></td>
                                        <td><?= e($fila['turno']) ?></td>
                                        <td><?= e($fila['aula']) ?></td>
                                        <td><?= e($fila['metodo']) ?></td>
                                        <td>
                                            <?php if ((int)$fila['gps_activo'] === 1): ?>
                                                <span class="badge text-bg-success px-3 py-2">GPS ON</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-secondary px-3 py-2">GPS OFF</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($fila['foto_asistencia'])): ?>
                                                <span class="badge text-bg-info px-3 py-2">Sí</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-dark px-3 py-2">No</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="9" class="text-center text-soft py-4">Aún no hay registros.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="panel h-100">
                        <h4 class="mb-1 section-title">Notas operativas</h4>
                        <p class="text-soft mb-3">Este panel ya está conectado a la base de datos y usa datos reales de asistencias, aulas, turnos, materias y métodos.</p>
                        <ul class="mb-0">
                            <li>Reemplazado el uso de columnas dudosas por consultas seguras.</li>
                            <li>Incluye exportación CSV de la bitácora reciente.</li>
                            <li>Integra accesos rápidos a reportes, backup y GPS.</li>
                            <li>Agrega estado de conexión y resumen del sistema.</li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="panel h-100">
                        <h4 class="mb-1 section-title">Acción sugerida</h4>
                        <p class="text-soft mb-3">Puedes convertir este panel en el inicio oficial del superusuario y dejar los reportes como módulos secundarios.</p>
                        <div class="d-grid gap-2 d-md-flex">
                            <a class="btn btn-primary tool-btn" href="../Reports/r_estadisticas.php"><i class="bi bi-bar-chart-line-fill me-2"></i>Ir a estadísticas</a>
                            <a class="btn btn-outline-primary tool-btn" href="../php/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Salir</a>
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

const diasLabels = <?= json_encode($chartMesLabels, JSON_UNESCAPED_UNICODE) ?>;
const diasData   = <?= json_encode($chartMesData, JSON_UNESCAPED_UNICODE) ?>;
const turnosLabels = <?= json_encode($turnosLabels, JSON_UNESCAPED_UNICODE) ?>;
const turnosData   = <?= json_encode($turnosData, JSON_UNESCAPED_UNICODE) ?>;
const materiasLabels = <?= json_encode($materiasLabels, JSON_UNESCAPED_UNICODE) ?>;
const materiasData   = <?= json_encode($materiasData, JSON_UNESCAPED_UNICODE) ?>;
const topLabels = <?= json_encode($topLabels, JSON_UNESCAPED_UNICODE) ?>;
const topData   = <?= json_encode($topData, JSON_UNESCAPED_UNICODE) ?>;

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
            borderColor: '#2563eb',
            pointBackgroundColor: '#111827',
            backgroundColor: function(context) {
                const chart = context.chart;
                const {ctx, chartArea} = chart;
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
        plugins: { legend: { position: 'bottom' } },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,.14)' } },
            x: { grid: { display: false } }
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
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,.14)' } },
            x: { grid: { display: false } }
        }
    }
});

new Chart(document.getElementById('turnosChart'), {
    type: 'doughnut',
    data: {
        labels: turnosLabels,
        datasets: [{
            data: turnosData,
            backgroundColor: ['#0f172a','#2563eb','#0ea5e9','#22c55e','#f59e0b','#a855f7']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '62%',
        plugins: { legend: { position: 'bottom' } }
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
            x: { beginAtZero: true, grid: { color: 'rgba(148,163,184,.14)' } }
        }
    }
});
</script>
</body>
</html>
