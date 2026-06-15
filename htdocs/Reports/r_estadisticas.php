<?php
include("../TOOLS/conexion.php");

$conn = conectarse();
$conn->set_charset('utf8mb4');
date_default_timezone_set('America/La_Paz');

function e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function bindParams(mysqli_stmt $stmt, string $types, array $params): void {
    if ($types === '') {
        return;
    }

    $refs = [];
    $refs[] = $types;

    foreach ($params as $k => $v) {
        $refs[] = &$params[$k];
    }

    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function fetchAllAssoc(mysqli $c, string $sql, string $types = '', array $params = []): array {
    $rows = [];
    $stmt = $c->prepare($sql);

    if (!$stmt) {
        return $rows;
    }

    if ($types !== '') {
        bindParams($stmt, $types, $params);
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

function fetchOne(mysqli $c, string $sql, string $types = '', array $params = []): array {
    $rows = fetchAllAssoc($c, $sql, $types, $params);
    return $rows[0] ?? [];
}

function obtenerAulas(mysqli $c): array {
    return fetchAllAssoc($c, "SELECT id, nombre FROM lk_aulas ORDER BY nombre ASC");
}

function obtenerTurnos(mysqli $c): array {
    return fetchAllAssoc($c, "SELECT id, nombre, hora_inicio, hora_fin FROM lk_turnos ORDER BY id ASC");
}

function obtenerMaterias(mysqli $c): array {
    return fetchAllAssoc($c, "SELECT id, nombre FROM lk_materias ORDER BY nombre ASC");
}

function obtenerMetodos(mysqli $c): array {
    return fetchAllAssoc($c, "SELECT id, nombre FROM lk_metodos_asistencia ORDER BY id ASC");
}

function obtenerEstudiantes(mysqli $c): array {
    return fetchAllAssoc($c, "
        SELECT
            id,
            CONCAT(
                COALESCE(nombre,''),
                ' ',
                COALESCE(paterno,''),
                ' ',
                COALESCE(materno,'')
            ) AS nombre_completo
        FROM usuarios
        WHERE rol = 'estudiante'
        ORDER BY paterno ASC, materno ASC, nombre ASC
    ");
}

function numeroMeses(): array {
    return [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
}

function nombreMes(int $mes): string {
    $m = numeroMeses();
    return $m[$mes] ?? (string)$mes;
}

function buildFilters(array $filters, string &$whereSql, string &$types, array &$params): void {
    $where = [];
    $types = '';
    $params = [];

    if (!empty($filters['fecha_inicio'])) {
        $where[] = 'a.fecha >= ?';
        $types .= 's';
        $params[] = $filters['fecha_inicio'];
    }

    if (!empty($filters['fecha_fin'])) {
        $where[] = 'a.fecha <= ?';
        $types .= 's';
        $params[] = $filters['fecha_fin'];
    }

    if (!empty($filters['anio'])) {
        $where[] = 'YEAR(a.fecha) = ?';
        $types .= 'i';
        $params[] = (int)$filters['anio'];
    }

    if (!empty($filters['mes']) && $filters['mes'] !== 'todos') {
        $where[] = 'MONTH(a.fecha) = ?';
        $types .= 'i';
        $params[] = (int)$filters['mes'];
    }

    if (!empty($filters['turno']) && $filters['turno'] !== 'todos') {
        $where[] = 'a.turno_id = ?';
        $types .= 'i';
        $params[] = (int)$filters['turno'];
    }

    if (!empty($filters['aula']) && $filters['aula'] !== 'todos') {
        $where[] = 'a.aula_id = ?';
        $types .= 'i';
        $params[] = (int)$filters['aula'];
    }

    if (!empty($filters['materia']) && $filters['materia'] !== 'todos') {
        $where[] = 'a.materia_id = ?';
        $types .= 'i';
        $params[] = (int)$filters['materia'];
    }

    if (!empty($filters['metodo']) && $filters['metodo'] !== 'todos') {
        $where[] = 'a.metodo_id = ?';
        $types .= 'i';
        $params[] = (int)$filters['metodo'];
    }

    if (!empty($filters['estudiante']) && $filters['estudiante'] !== 'todos') {
        $where[] = 'a.usuario_id = ?';
        $types .= 'i';
        $params[] = (int)$filters['estudiante'];
    }

    if (!empty($filters['q'])) {
        $where[] = "
            (
                CONCAT(u.nombre,' ',u.paterno,' ',u.materno) LIKE ?
                OR COALESCE(a.observacion,'') LIKE ?
                OR COALESCE(au.nombre,'') LIKE ?
                OR COALESCE(m.nombre,'') LIKE ?
                OR CAST(u.id AS CHAR) LIKE ?
            )
        ";

        $like = '%' . $filters['q'] . '%';
        $types .= 'sssss';

        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $whereSql = !empty($where) ? (' WHERE ' . implode(' AND ', $where)) : '';
}

function totalConFiltro(mysqli $c, array $filters): int {
    $whereSql = '';
    $types = '';
    $params = [];

    buildFilters($filters, $whereSql, $types, $params);

    $sql = "
        SELECT COUNT(*) AS total
        FROM asistencias a
        INNER JOIN usuarios u ON u.id = a.usuario_id
        LEFT JOIN lk_aulas au ON au.id = a.aula_id
        LEFT JOIN lk_turnos t ON t.id = a.turno_id
        LEFT JOIN lk_materias m ON m.id = a.materia_id
        LEFT JOIN lk_metodos_asistencia mt ON mt.id = a.metodo_id
        $whereSql
    ";

    $row = fetchOne($c, $sql, $types, $params);
    return (int)($row['total'] ?? 0);
}

function kpi(mysqli $c, array $filters): array {
    $whereSql = '';
    $types = '';
    $params = [];

    buildFilters($filters, $whereSql, $types, $params);

    $sql = "
        SELECT
            COUNT(*) AS total_registros,
            COUNT(DISTINCT a.usuario_id) AS estudiantes_distintos,
            COUNT(DISTINCT a.aula_id) AS aulas_distintas,
            SUM(CASE WHEN a.ubicacion_gps IS NOT NULL THEN 1 ELSE 0 END) AS gps_activo,
            SUM(CASE WHEN a.foto_asistencia IS NOT NULL AND a.foto_asistencia <> '' THEN 1 ELSE 0 END) AS con_foto,
            SUM(CASE WHEN a.metodo_id = 1 THEN 1 ELSE 0 END) AS facial,
            SUM(CASE WHEN a.metodo_id = 2 THEN 1 ELSE 0 END) AS manual,
            SUM(CASE WHEN a.metodo_id = 3 THEN 1 ELSE 0 END) AS admin
        FROM asistencias a
        INNER JOIN usuarios u ON u.id = a.usuario_id
        LEFT JOIN lk_aulas au ON au.id = a.aula_id
        LEFT JOIN lk_turnos t ON t.id = a.turno_id
        LEFT JOIN lk_materias m ON m.id = a.materia_id
        LEFT JOIN lk_metodos_asistencia mt ON mt.id = a.metodo_id
        $whereSql
    ";

    $row = fetchOne($c, $sql, $types, $params);

    return [
        'total_registros' => (int)($row['total_registros'] ?? 0),
        'estudiantes_distintos' => (int)($row['estudiantes_distintos'] ?? 0),
        'aulas_distintas' => (int)($row['aulas_distintas'] ?? 0),
        'gps_activo' => (int)($row['gps_activo'] ?? 0),
        'con_foto' => (int)($row['con_foto'] ?? 0),
        'facial' => (int)($row['facial'] ?? 0),
        'manual' => (int)($row['manual'] ?? 0),
        'admin' => (int)($row['admin'] ?? 0),
    ];
}

function reporteMensual(mysqli $c, int $anio, array $filters): array {
    $params = [$anio];
    $types = 'i';

    $extra = [];

    if (!empty($filters['aula']) && $filters['aula'] !== 'todos') {
        $extra[] = ['a.aula_id = ?', 'i', (int)$filters['aula']];
    }
    if (!empty($filters['turno']) && $filters['turno'] !== 'todos') {
        $extra[] = ['a.turno_id = ?', 'i', (int)$filters['turno']];
    }
    if (!empty($filters['materia']) && $filters['materia'] !== 'todos') {
        $extra[] = ['a.materia_id = ?', 'i', (int)$filters['materia']];
    }
    if (!empty($filters['metodo']) && $filters['metodo'] !== 'todos') {
        $extra[] = ['a.metodo_id = ?', 'i', (int)$filters['metodo']];
    }
    if (!empty($filters['estudiante']) && $filters['estudiante'] !== 'todos') {
        $extra[] = ['a.usuario_id = ?', 'i', (int)$filters['estudiante']];
    }

    $where = ['YEAR(a.fecha) = ?'];

    foreach ($extra as $f) {
        $where[] = $f[0];
        $types .= $f[1];
        $params[] = $f[2];
    }

    $sql = "
        SELECT MONTH(a.fecha) AS mes, COUNT(*) AS total
        FROM asistencias a
        WHERE " . implode(' AND ', $where) . "
        GROUP BY MONTH(a.fecha)
        ORDER BY MONTH(a.fecha)
    ";

    $rows = fetchAllAssoc($c, $sql, $types, $params);

    $out = [];
    $map = numeroMeses();

    for ($i = 1; $i <= 12; $i++) {
        $out[$i] = ['label' => $map[$i], 'total' => 0];
    }

    foreach ($rows as $r) {
        $m = (int)$r['mes'];
        if (isset($out[$m])) {
            $out[$m]['total'] = (int)$r['total'];
        }
    }

    return array_values($out);
}

function reportePorAula(mysqli $c, array $filters): array {
    $whereSql = '';
    $types = '';
    $params = [];

    buildFilters($filters, $whereSql, $types, $params);

    $sql = "
        SELECT COALESCE(au.nombre,'Sin aula') AS label, COUNT(*) AS total
        FROM asistencias a
        INNER JOIN usuarios u ON u.id = a.usuario_id
        LEFT JOIN lk_aulas au ON au.id = a.aula_id
        LEFT JOIN lk_turnos t ON t.id = a.turno_id
        LEFT JOIN lk_materias m ON m.id = a.materia_id
        LEFT JOIN lk_metodos_asistencia mt ON mt.id = a.metodo_id
        $whereSql
        GROUP BY COALESCE(au.nombre,'Sin aula')
        ORDER BY total DESC, label ASC
    ";

    return fetchAllAssoc($c, $sql, $types, $params);
}

function reportePorTurno(mysqli $c, array $filters): array {
    $whereSql = '';
    $types = '';
    $params = [];

    buildFilters($filters, $whereSql, $types, $params);

    $sql = "
        SELECT COALESCE(t.nombre,'Sin turno') AS label, COUNT(*) AS total
        FROM asistencias a
        INNER JOIN usuarios u ON u.id = a.usuario_id
        LEFT JOIN lk_aulas au ON au.id = a.aula_id
        LEFT JOIN lk_turnos t ON t.id = a.turno_id
        LEFT JOIN lk_materias m ON m.id = a.materia_id
        LEFT JOIN lk_metodos_asistencia mt ON mt.id = a.metodo_id
        $whereSql
        GROUP BY COALESCE(t.nombre,'Sin turno')
        ORDER BY total DESC, label ASC
    ";

    return fetchAllAssoc($c, $sql, $types, $params);
}

function reportePorMateria(mysqli $c, array $filters): array {
    $whereSql = '';
    $types = '';
    $params = [];

    buildFilters($filters, $whereSql, $types, $params);

    $sql = "
        SELECT COALESCE(m.nombre,'Sin materia') AS label, COUNT(*) AS total
        FROM asistencias a
        INNER JOIN usuarios u ON u.id = a.usuario_id
        LEFT JOIN lk_aulas au ON au.id = a.aula_id
        LEFT JOIN lk_turnos t ON t.id = a.turno_id
        LEFT JOIN lk_materias m ON m.id = a.materia_id
        LEFT JOIN lk_metodos_asistencia mt ON mt.id = a.metodo_id
        $whereSql
        GROUP BY COALESCE(m.nombre,'Sin materia')
        ORDER BY total DESC, label ASC
    ";

    return fetchAllAssoc($c, $sql, $types, $params);
}

function reportePorMetodo(mysqli $c, array $filters): array {
    $whereSql = '';
    $types = '';
    $params = [];

    buildFilters($filters, $whereSql, $types, $params);

    $sql = "
        SELECT COALESCE(mt.nombre,'Sin método') AS label, COUNT(*) AS total
        FROM asistencias a
        INNER JOIN usuarios u ON u.id = a.usuario_id
        LEFT JOIN lk_aulas au ON au.id = a.aula_id
        LEFT JOIN lk_turnos t ON t.id = a.turno_id
        LEFT JOIN lk_materias m ON m.id = a.materia_id
        LEFT JOIN lk_metodos_asistencia mt ON mt.id = a.metodo_id
        $whereSql
        GROUP BY COALESCE(mt.nombre,'Sin método')
        ORDER BY total DESC, label ASC
    ";

    return fetchAllAssoc($c, $sql, $types, $params);
}

function topEstudiantes(mysqli $c, array $filters, int $limit = 10): array {
    $whereSql = '';
    $types = '';
    $params = [];

    buildFilters($filters, $whereSql, $types, $params);

    $sql = "
        SELECT
            a.usuario_id,
            CONCAT(u.nombre,' ',u.paterno,' ',u.materno) AS estudiante,
            COUNT(*) AS total,
            SUM(CASE WHEN a.ubicacion_gps IS NOT NULL THEN 1 ELSE 0 END) AS gps,
            SUM(CASE WHEN a.foto_asistencia IS NOT NULL AND a.foto_asistencia <> '' THEN 1 ELSE 0 END) AS con_foto
        FROM asistencias a
        INNER JOIN usuarios u ON u.id = a.usuario_id
        LEFT JOIN lk_aulas au ON au.id = a.aula_id
        LEFT JOIN lk_turnos t ON t.id = a.turno_id
        LEFT JOIN lk_materias m ON m.id = a.materia_id
        LEFT JOIN lk_metodos_asistencia mt ON mt.id = a.metodo_id
        $whereSql
        GROUP BY a.usuario_id, estudiante
        ORDER BY total DESC, estudiante ASC
        LIMIT " . (int)$limit . "
    ";

    return fetchAllAssoc($c, $sql, $types, $params);
}

function tablaLog(mysqli $c, array $filters, int $limit = 300): array {
    $whereSql = '';
    $types = '';
    $params = [];

    buildFilters($filters, $whereSql, $types, $params);

    $sql = "
        SELECT
            a.id,
            a.fecha,
            a.hora,
            a.usuario_id,
            CONCAT(u.nombre,' ',u.paterno,' ',u.materno) AS estudiante,
            COALESCE(CAST(u.id AS CHAR), '') AS nro_ci,
            COALESCE(au.nombre,'Sin aula') AS aula,
            COALESCE(t.nombre,'Sin turno') AS turno,
            COALESCE(m.nombre,'Sin materia') AS materia,
            COALESCE(mt.nombre,'Sin método') AS metodo,
            CASE WHEN a.ubicacion_gps IS NOT NULL THEN 1 ELSE 0 END AS gps_activo,
            a.foto_asistencia,
            a.observacion,
            a.ubicacion_gps,
            ST_Y(a.ubicacion_gps) AS latitud,
            ST_X(a.ubicacion_gps) AS longitud
        FROM asistencias a
        INNER JOIN usuarios u ON u.id = a.usuario_id
        LEFT JOIN lk_aulas au ON au.id = a.aula_id
        LEFT JOIN lk_turnos t ON t.id = a.turno_id
        LEFT JOIN lk_materias m ON m.id = a.materia_id
        LEFT JOIN lk_metodos_asistencia mt ON mt.id = a.metodo_id
        $whereSql
        ORDER BY a.fecha DESC, a.hora DESC, a.id DESC
        LIMIT " . (int)$limit . "
    ";

    return fetchAllAssoc($c, $sql, $types, $params);
}

function csvResponse(array $rows, string $filename): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    if (!$out) {
        exit;
    }

    if (!empty($rows)) {
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
    } else {
        fputcsv($out, ['sin_datos']);
    }

    fclose($out);
    exit;
}

$hoy = date('Y-m-d');
$anioActual = (int)date('Y');

$filters = [
    'anio' => isset($_GET['anio']) && $_GET['anio'] !== '' ? (int)$_GET['anio'] : $anioActual,
    'mes' => $_GET['mes'] ?? 'todos',
    'fecha_inicio' => $_GET['fecha_inicio'] ?? '',
    'fecha_fin' => $_GET['fecha_fin'] ?? '',
    'aula' => $_GET['aula'] ?? 'todos',
    'turno' => $_GET['turno'] ?? 'todos',
    'materia' => $_GET['materia'] ?? 'todos',
    'metodo' => $_GET['metodo'] ?? 'todos',
    'estudiante' => $_GET['estudiante'] ?? 'todos',
    'q' => trim((string)($_GET['q'] ?? '')),
];

$aulas = obtenerAulas($conn);
$turnos = obtenerTurnos($conn);
$materias = obtenerMaterias($conn);
$metodos = obtenerMetodos($conn);
$estudiantes = obtenerEstudiantes($conn);

$kpis = kpi($conn, $filters);
$mensual = reporteMensual($conn, (int)$filters['anio'], $filters);
$porAula = reportePorAula($conn, $filters);
$porTurno = reportePorTurno($conn, $filters);
$porMateria = reportePorMateria($conn, $filters);
$porMetodo = reportePorMetodo($conn, $filters);
$top = topEstudiantes($conn, $filters, 10);
$logRows = tablaLog($conn, $filters, 300);

$baseUrl = strtok($_SERVER['REQUEST_URI'], '?');

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $fname = 'reporte_asistencia_' . date('Ymd_His') . '.csv';
    csvResponse($logRows, $fname);
}

$anioOpciones = [];
for ($y = $anioActual - 3; $y <= $anioActual + 1; $y++) {
    $anioOpciones[] = $y;
}

$chartMesLabels = [];
$chartMesData = [];
foreach ($mensual as $m) {
    $chartMesLabels[] = $m['label'];
    $chartMesData[] = (int)$m['total'];
}

$chartAulaLabels = array_map(fn($x) => $x['label'], $porAula);
$chartAulaData = array_map(fn($x) => (int)$x['total'], $porAula);

$chartTurnoLabels = array_map(fn($x) => $x['label'], $porTurno);
$chartTurnoData = array_map(fn($x) => (int)$x['total'], $porTurno);

$chartMateriaLabels = array_map(fn($x) => $x['label'], $porMateria);
$chartMateriaData = array_map(fn($x) => (int)$x['total'], $porMateria);

$chartMetodoLabels = array_map(fn($x) => $x['label'], $porMetodo);
$chartMetodoData = array_map(fn($x) => (int)$x['total'], $porMetodo);
?> 
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reporte avanzado de asistencia</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2.1.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<style>
:root{
    --bg:#070b12;
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
.container-shell{max-width:1600px;margin:0 auto;padding:22px 14px 40px}
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
.hero-title{font-size:clamp(1.4rem, 2vw, 2.2rem);font-weight:800;margin:0}
.hero-sub{color:var(--muted);margin:.35rem 0 0}
.pill{
    display:inline-flex;align-items:center;gap:.45rem;padding:.55rem .9rem;border-radius:999px;
    border:1px solid var(--line);background:rgba(255,255,255,.04);color:var(--text);font-weight:600;font-size:.92rem;
}
.content{padding:22px}
.grid-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:16px}
.stat-card{
    background:linear-gradient(180deg, rgba(255,255,255,.04), rgba(255,255,255,.025));
    border:1px solid var(--line);border-radius:22px;padding:18px;box-shadow:0 10px 28px rgba(0,0,0,.16)
}
.stat-label{color:var(--muted);font-size:.88rem}
.stat-value{font-size:1.9rem;font-weight:800;line-height:1.05;margin-top:6px}
.stat-note{color:var(--muted);font-size:.85rem;margin-top:4px}
.icon-badge{
    width:48px;height:48px;border-radius:16px;display:grid;place-items:center;
    background:linear-gradient(135deg, rgba(91,140,255,.25), rgba(53,230,255,.18));border:1px solid var(--line);
}
.toolbar{
    background:rgba(255,255,255,.03);border:1px solid var(--line);border-radius:24px;padding:18px;margin-bottom:16px;
}
.form-label{color:var(--text);font-weight:700}
.form-control,.form-select{
    background:#0c1320;border:1px solid rgba(255,255,255,.12);color:var(--text);border-radius:16px;padding:.8rem 1rem;
}
.form-control:focus,.form-select:focus{
    background:#0c1320;color:var(--text);border-color:rgba(91,140,255,.8);box-shadow:0 0 0 .2rem rgba(91,140,255,.12);
}
.btn{border-radius:14px;padding:.75rem 1rem;font-weight:700}
.btn-primary{background:linear-gradient(135deg, var(--primary), var(--primary2));border:none}
.btn-soft{background:rgba(255,255,255,.05);border:1px solid var(--line);color:var(--text)}
.btn-soft:hover{background:rgba(255,255,255,.08);color:var(--text)}
.cardish{
    background:linear-gradient(180deg, rgba(255,255,255,.04), rgba(255,255,255,.02));
    border:1px solid var(--line);border-radius:24px;padding:18px;margin-bottom:16px;
}
.chart-box{min-height:340px}
.small-muted{color:var(--muted);font-size:.9rem}
.table{color:var(--text)}
.table > :not(caption) > * > *{background:transparent;color:var(--text);border-color:var(--line)}
.table thead th{color:#fff;background:rgba(255,255,255,.05);font-size:.9rem;white-space:nowrap}
.table tbody tr:hover{background:rgba(255,255,255,.03)}
.table-responsive{border:1px solid var(--line);border-radius:20px;overflow:auto}
.badge-soft{background:rgba(255,255,255,.06);border:1px solid var(--line);color:var(--text);font-weight:700;border-radius:999px}
.modal-content{background:#0d1422;color:var(--text);border:1px solid var(--line);border-radius:22px}
.modal-header,.modal-footer{border-color:var(--line)}
@media (max-width: 1200px){.grid-stats{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media (max-width: 768px){.content{padding:16px}.hero-top{padding:18px}.grid-stats{grid-template-columns:1fr}}
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
                        <span class="pill"><i class="bi bi-graph-up-arrow"></i> Reporte avanzado</span>
                        <span class="pill"><i class="bi bi-calendar3"></i> <?= e(date('d/m/Y')) ?></span>
                    </div>
                    <h1 class="hero-title">Dashboard de asistencia y bitácora</h1>
                    <p class="hero-sub">Filtros por año, rango de fechas, aula, turno, materia, método y estudiante.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-soft" href="<?= e($baseUrl) ?>?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>"><i class="bi bi-file-earmark-excel"></i> Exportar CSV</a>
                    <button class="btn btn-soft" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
                    <button class="btn btn-primary" id="btnReload"><i class="bi bi-arrow-repeat"></i> Actualizar</button>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="grid-stats">
                <div class="stat-card d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <div class="stat-label">Registros</div>
                        <div class="stat-value"><?= (int)$kpis['total_registros'] ?></div>
                        <div class="stat-note">Total filtrado</div>
                    </div>
                    <div class="icon-badge"><i class="bi bi-journal-text"></i></div>
                </div>
                <div class="stat-card d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <div class="stat-label">Estudiantes únicos</div>
                        <div class="stat-value" style="color:var(--cyan)"><?= (int)$kpis['estudiantes_distintos'] ?></div>
                        <div class="stat-note">Con al menos un registro</div>
                    </div>
                    <div class="icon-badge"><i class="bi bi-people-fill"></i></div>
                </div>
                <div class="stat-card d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <div class="stat-label">GPS activo</div>
                        <div class="stat-value" style="color:var(--ok)"><?= (int)$kpis['gps_activo'] ?></div>
                        <div class="stat-note">Marcaciones con ubicación</div>
                    </div>
                    <div class="icon-badge"><i class="bi bi-broadcast-pin"></i></div>
                </div>
                <div class="stat-card d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <div class="stat-label">Con foto</div>
                        <div class="stat-value" style="color:var(--warn)"><?= (int)$kpis['con_foto'] ?></div>
                        <div class="stat-note">Evidencias cargadas</div>
                    </div>
                    <div class="icon-badge"><i class="bi bi-camera"></i></div>
                </div>
            </div>

            <div class="grid-stats" style="grid-template-columns:repeat(4,minmax(0,1fr));">
                <div class="stat-card"><div class="stat-label">Método facial</div><div class="stat-value text-info"><?= (int)$kpis['facial'] ?></div><div class="stat-note">ID método 1</div></div>
                <div class="stat-card"><div class="stat-label">Método manual</div><div class="stat-value text-warning"><?= (int)$kpis['manual'] ?></div><div class="stat-note">ID método 2</div></div>
                <div class="stat-card"><div class="stat-label">Método admin</div><div class="stat-value text-danger"><?= (int)$kpis['admin'] ?></div><div class="stat-note">ID método 3</div></div>
                <div class="stat-card"><div class="stat-label">Aulas distintas</div><div class="stat-value"><?= (int)$kpis['aulas_distintas'] ?></div><div class="stat-note">Según filtros</div></div>
            </div>

            <div class="toolbar">
                <form id="filtersForm" method="GET" class="row g-3 align-items-end">
                    <div class="col-12 col-md-4 col-lg-2">
                        <label class="form-label">Año</label>
                        <select name="anio" class="form-select">
                            <?php foreach ($anioOpciones as $y): ?>
                                <option value="<?= (int)$y ?>" <?= (int)$filters['anio'] === (int)$y ? 'selected' : '' ?>><?= (int)$y ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-4 col-lg-2">
                        <label class="form-label">Mes</label>
                        <select name="mes" class="form-select">
                            <option value="todos" <?= $filters['mes'] === 'todos' ? 'selected' : '' ?>>Todos</option>
                            <?php foreach (numeroMeses() as $num => $nom): ?>
                                <option value="<?= (int)$num ?>" <?= (string)$filters['mes'] === (string)$num ? 'selected' : '' ?>><?= e($nom) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-4 col-lg-2">
                        <label class="form-label">Fecha inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control" value="<?= e($filters['fecha_inicio']) ?>">
                    </div>
                    <div class="col-12 col-md-4 col-lg-2">
                        <label class="form-label">Fecha fin</label>
                        <input type="date" name="fecha_fin" class="form-control" value="<?= e($filters['fecha_fin']) ?>">
                    </div>
                    <div class="col-12 col-md-4 col-lg-2">
                        <label class="form-label">Aula</label>
                        <select name="aula" class="form-select">
                            <option value="todos" <?= $filters['aula'] === 'todos' ? 'selected' : '' ?>>Todas</option>
                            <?php foreach ($aulas as $a): ?>
                                <option value="<?= (int)$a['id'] ?>" <?= (string)$filters['aula'] === (string)$a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-4 col-lg-2">
                        <label class="form-label">Turno</label>
                        <select name="turno" class="form-select">
                            <option value="todos" <?= $filters['turno'] === 'todos' ? 'selected' : '' ?>>Todos</option>
                            <?php foreach ($turnos as $t): ?>
                                <option value="<?= (int)$t['id'] ?>" <?= (string)$filters['turno'] === (string)$t['id'] ? 'selected' : '' ?>><?= e(ucfirst($t['nombre'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-4 col-lg-2">
                        <label class="form-label">Materia</label>
                        <select name="materia" class="form-select">
                            <option value="todos" <?= $filters['materia'] === 'todos' ? 'selected' : '' ?>>Todas</option>
                            <?php foreach ($materias as $m): ?>
                                <option value="<?= (int)$m['id'] ?>" <?= (string)$filters['materia'] === (string)$m['id'] ? 'selected' : '' ?>><?= e($m['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-4 col-lg-2">
                        <label class="form-label">Método</label>
                        <select name="metodo" class="form-select">
                            <option value="todos" <?= $filters['metodo'] === 'todos' ? 'selected' : '' ?>>Todos</option>
                            <?php foreach ($metodos as $m): ?>
                                <option value="<?= (int)$m['id'] ?>" <?= (string)$filters['metodo'] === (string)$m['id'] ? 'selected' : '' ?>><?= e($m['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="form-label">Estudiante</label>
                        <select name="estudiante" class="form-select">
                            <option value="todos" <?= $filters['estudiante'] === 'todos' ? 'selected' : '' ?>>Todos</option>
                            <?php foreach ($estudiantes as $es): ?>
                                <option value="<?= (int)$es['id'] ?>" <?= (string)$filters['estudiante'] === (string)$es['id'] ? 'selected' : '' ?>><?= e($es['nombre_completo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="q" class="form-control" placeholder="Nombre, CI, observación..." value="<?= e($filters['q']) ?>">
                    </div>
                    <div class="col-12 col-lg-3 d-grid">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-funnel-fill"></i> Aplicar filtros</button>
                    </div>
                </form>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <span class="badge badge-soft px-3 py-2">Año: <?= (int)$filters['anio'] ?></span>
                    <span class="badge badge-soft px-3 py-2">Mes: <?= e((string)$filters['mes']) ?></span>
                    <span class="badge badge-soft px-3 py-2">Turno: <?= e((string)$filters['turno']) ?></span>
                    <span class="badge badge-soft px-3 py-2">Aula: <?= e((string)$filters['aula']) ?></span>
                    <span class="badge badge-soft px-3 py-2">Materia: <?= e((string)$filters['materia']) ?></span>
                    <span class="badge badge-soft px-3 py-2">Método: <?= e((string)$filters['metodo']) ?></span>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-lg-6">
                    <div class="cardish">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="mb-1">Asistencia por mes</h5>
                                <div class="small-muted">Resumen del año filtrado</div>
                            </div>
                            <span class="badge badge-soft">12 meses</span>
                        </div>
                        <div class="chart-box"><canvas id="chartMes"></canvas></div>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="cardish">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="mb-1">Registros por aula</h5>
                                <div class="small-muted">Distribución del filtro activo</div>
                            </div>
                            <span class="badge badge-soft"><?= count($porAula) ?> categorías</span>
                        </div>
                        <div class="chart-box"><canvas id="chartAula"></canvas></div>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="cardish">
                        <h5 class="mb-1">Registros por turno</h5>
                        <div class="small-muted mb-2">Mañana, tarde y noche</div>
                        <div class="chart-box"><canvas id="chartTurno"></canvas></div>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="cardish">
                        <h5 class="mb-1">Registros por materia</h5>
                        <div class="small-muted mb-2">Asignaturas registradas</div>
                        <div class="chart-box"><canvas id="chartMateria"></canvas></div>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="cardish">
                        <h5 class="mb-1">Registros por método</h5>
                        <div class="small-muted mb-2">Facial, manual y admin</div>
                        <div class="chart-box"><canvas id="chartMetodo"></canvas></div>
                    </div>
                </div>
            </div>

            <div class="cardish">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <h5 class="mb-1">Top estudiantes</h5>
                        <div class="small-muted">Más registros dentro del filtro</div>
                    </div>
                    <span class="badge badge-soft">Top 10</span>
                </div>
                <div class="table-responsive mb-0">
                    <table class="table table-hover align-middle text-center mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th class="text-start">Estudiante</th>
                                <th>Registros</th>
                                <th>GPS</th>
                                <th>Con foto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($top): ?>
                                <?php foreach ($top as $i => $r): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td class="text-start"><?= e($r['estudiante']) ?></td>
                                        <td><span class="badge text-bg-primary px-3 py-2"><?= (int)$r['total'] ?></span></td>
                                        <td><span class="badge text-bg-info px-3 py-2"><?= (int)$r['gps'] ?></span></td>
                                        <td><span class="badge text-bg-warning px-3 py-2"><?= (int)$r['con_foto'] ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="py-4">No hay datos con ese filtro</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="cardish">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <h5 class="mb-1">Bitácora / log detallado</h5>
                        <div class="small-muted">Registros ordenados del más reciente al más antiguo</div>
                    </div>
                    <span class="badge badge-soft"><?= count($logRows) ?> filas</span>
                </div>
                <div class="table-responsive">
                    <table id="logTable" class="table table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th class="text-start">Estudiante</th>
                                <th>CI</th>
                                <th>Aula</th>
                                <th>Turno</th>
                                <th>Materia</th>
                                <th>Método</th>
                                <th>GPS</th>
                                <th>Foto</th>
                                <th>Observación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($logRows): ?>
                                <?php foreach ($logRows as $row): ?>
                                    <tr>
                                        <td><?= (int)$row['id'] ?></td>
                                        <td><?= e($row['fecha']) ?></td>
                                        <td><?= e($row['hora']) ?></td>
                                        <td class="text-start"><?= e($row['estudiante']) ?></td>
                                        <td><?= e($row['nro_ci']) ?></td>
                                        <td><?= e($row['aula']) ?></td>
                                        <td><?= e($row['turno']) ?></td>
                                        <td><?= e($row['materia']) ?></td>
                                        <td><?= e($row['metodo']) ?></td>
                                        <td>
                                            <?php if ((int)$row['gps_activo'] === 1): ?>
                                                <span class="badge text-bg-success px-3 py-2">GPS ON</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-secondary px-3 py-2">GPS OFF</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['foto_asistencia'])): ?>
                                                <a class="badge text-bg-info text-decoration-none px-3 py-2" target="_blank" href="<?= e($row['foto_asistencia']) ?>">Ver</a>
                                            <?php else: ?>
                                                <span class="badge text-bg-dark px-3 py-2">Sin foto</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-start" style="min-width:260px"><?= e((string)($row['observacion'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="12" class="py-5 text-center">No hay registros para mostrar</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2.1.8/js/dataTables.bootstrap5.min.js"></script>
<script>
const chartMesLabels = <?= json_encode($chartMesLabels, JSON_UNESCAPED_UNICODE) ?>;
const chartMesData = <?= json_encode($chartMesData, JSON_UNESCAPED_UNICODE) ?>;
const chartAulaLabels = <?= json_encode($chartAulaLabels, JSON_UNESCAPED_UNICODE) ?>;
const chartAulaData = <?= json_encode($chartAulaData, JSON_UNESCAPED_UNICODE) ?>;
const chartTurnoLabels = <?= json_encode($chartTurnoLabels, JSON_UNESCAPED_UNICODE) ?>;
const chartTurnoData = <?= json_encode($chartTurnoData, JSON_UNESCAPED_UNICODE) ?>;
const chartMateriaLabels = <?= json_encode($chartMateriaLabels, JSON_UNESCAPED_UNICODE) ?>;
const chartMateriaData = <?= json_encode($chartMateriaData, JSON_UNESCAPED_UNICODE) ?>;
const chartMetodoLabels = <?= json_encode($chartMetodoLabels, JSON_UNESCAPED_UNICODE) ?>;
const chartMetodoData = <?= json_encode($chartMetodoData, JSON_UNESCAPED_UNICODE) ?>;

function makeChart(ctx, type, labels, data, title) {
    return new Chart(ctx, {
        type,
        data: {
            labels,
            datasets: [{
                label: title,
                data,
                borderWidth: 2,
                fill: type === 'line'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { enabled: true }
            },
            scales: type === 'pie' || type === 'doughnut' ? {} : {
                x: { ticks: { color: '#cfd8ea' }, grid: { color: 'rgba(255,255,255,.06)' } },
                y: { ticks: { color: '#cfd8ea' }, grid: { color: 'rgba(255,255,255,.06)' }, beginAtZero: true }
            }
        }
    });
}

new Chart(document.getElementById('chartMes'), {
    type: 'line',
    data: {
        labels: chartMesLabels,
        datasets: [{
            label: 'Registros',
            data: chartMesData,
            tension: 0.35,
            borderWidth: 3,
            pointRadius: 4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: '#cfd8ea' }, grid: { color: 'rgba(255,255,255,.06)' } },
            y: { ticks: { color: '#cfd8ea' }, grid: { color: 'rgba(255,255,255,.06)' }, beginAtZero: true }
        }
    }
});

new Chart(document.getElementById('chartAula'), {
    type: 'bar',
    data: { labels: chartAulaLabels, datasets: [{ label: 'Aulas', data: chartAulaData, borderWidth: 1 }] },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        indexAxis: 'y',
        scales: {
            x: { ticks: { color: '#cfd8ea' }, grid: { color: 'rgba(255,255,255,.06)' }, beginAtZero: true },
            y: { ticks: { color: '#cfd8ea' }, grid: { color: 'rgba(255,255,255,.06)' } }
        }
    }
});

new Chart(document.getElementById('chartTurno'), {
    type: 'doughnut',
    data: { labels: chartTurnoLabels, datasets: [{ data: chartTurnoData, borderWidth: 0 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: '#e8eefc' } } } }
});

new Chart(document.getElementById('chartMateria'), {
    type: 'pie',
    data: { labels: chartMateriaLabels, datasets: [{ data: chartMateriaData, borderWidth: 0 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: '#e8eefc' } } } }
});

new Chart(document.getElementById('chartMetodo'), {
    type: 'polarArea',
    data: { labels: chartMetodoLabels, datasets: [{ data: chartMetodoData, borderWidth: 0 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: '#e8eefc' } } } }
});

$(function(){
    $('#logTable').DataTable({
        pageLength: 25,
        order: [[1, 'desc'], [2, 'desc']],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json'
        }
    });

    const form = document.getElementById('filtersForm');
    document.getElementById('btnReload').addEventListener('click', function(){
        form.submit();
    });
});
</script>
</body>
</html>