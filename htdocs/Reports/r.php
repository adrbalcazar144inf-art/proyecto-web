<?php
session_start();
require_once '../TOOLS/conexion.php';

date_default_timezone_set('America/La_Paz');

$conn = conectarse();
$conn->set_charset('utf8mb4');

$nombre = htmlspecialchars($_SESSION['nombre'] ?? 'Administrador', ENT_QUOTES, 'UTF-8');

function fetchAllRows(mysqli $conn, string $sql, array $params = []): array {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if ($params) {
        $types = '';
        $bind = [];
        foreach ($params as $p) {
            if (is_int($p)) {
                $types .= 'i';
            } elseif (is_float($p)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $bind[] = $p;
        }
        $stmt->bind_param($types, ...$bind);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function fetchOne(mysqli $conn, string $sql, array $params = [], $default = 0) {
    $rows = fetchAllRows($conn, $sql, $params);
    if (!$rows || !isset($rows[0])) {
        return $default;
    }
    $first = array_values($rows[0]);
    return $first[0] ?? $default;
}

function labelsValues(array $rows, string $labelKey = 'nombre', string $valueKey = 'total'): array {
    $labels = [];
    $values = [];
    foreach ($rows as $row) {
        $labels[] = (string)($row[$labelKey] ?? 'Sin dato');
        $values[] = (int)($row[$valueKey] ?? 0);
    }
    return [$labels, $values];
}

$estudiantes = fetchAllRows($conn, "
    SELECT id, nro_ci, CONCAT(nombre,' ',paterno,' ',materno) AS nombre_completo
    FROM usuarios
    WHERE rol='estudiante'
    ORDER BY nombre, paterno, materno
");

$ciSeleccionado = $_GET['ci'] ?? ($estudiantes[0]['nro_ci'] ?? '');
$tipoSeleccionado = $_GET['tipo'] ?? 'bar';
$vistaSeleccionada = $_GET['vista'] ?? '2d';
$reporteSeleccionado = $_GET['reporte'] ?? 'global_roles';

$ciEncontrado = null;
if ($ciSeleccionado !== '') {
    $tmp = fetchAllRows($conn, "
        SELECT id, nro_ci, CONCAT(nombre,' ',paterno,' ',materno) AS nombre_completo, rol
        FROM usuarios
        WHERE nro_ci = ?
        LIMIT 1
    ", [$ciSeleccionado]);
    $ciEncontrado = $tmp[0] ?? null;
}

// ===== Globales =====
$totalUsuarios    = (int)fetchOne($conn, "SELECT COUNT(*) FROM usuarios");
$totalAsistencias = (int)fetchOne($conn, "SELECT COUNT(*) FROM asistencias");
$totalDocentes    = (int)fetchOne($conn, "SELECT COUNT(*) FROM usuarios WHERE rol='docente'");
$totalEstudiantes = (int)fetchOne($conn, "SELECT COUNT(*) FROM usuarios WHERE rol='estudiante'");
$totalAdmins      = (int)fetchOne($conn, "SELECT COUNT(*) FROM usuarios WHERE rol='superusuario'");
$totalPendientes  = (int)fetchOne($conn, "SELECT COUNT(*) FROM solicitudes_registro WHERE estado='pendiente'");
$totalHoy         = (int)fetchOne($conn, "SELECT COUNT(*) FROM asistencias WHERE fecha = CURDATE()");
$totalSemana      = (int)fetchOne($conn, "SELECT COUNT(*) FROM asistencias WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)");

$roles = fetchAllRows($conn, "SELECT rol AS nombre, COUNT(*) AS total FROM usuarios GROUP BY rol ORDER BY total DESC");
$metodos = fetchAllRows($conn, "
    SELECT COALESCE(m.nombre,'Sin método') AS nombre, COUNT(*) AS total
    FROM asistencias a
    LEFT JOIN lk_metodos_asistencia m ON m.id = a.metodo_id
    GROUP BY m.id, m.nombre
    ORDER BY total DESC
");
$turnos = fetchAllRows($conn, "
    SELECT COALESCE(t.nombre,'Sin turno') AS nombre, COUNT(*) AS total
    FROM asistencias a
    LEFT JOIN lk_turnos t ON t.id = a.turno_id
    GROUP BY t.id, t.nombre
    ORDER BY total DESC
");
$materiasTop = fetchAllRows($conn, "
    SELECT COALESCE(m.nombre,'Sin materia') AS nombre, COUNT(*) AS total
    FROM asistencias a
    LEFT JOIN lk_materias m ON m.id = a.materia_id
    GROUP BY m.id, m.nombre
    ORDER BY total DESC
    LIMIT 8
");
$aulasTop = fetchAllRows($conn, "
    SELECT COALESCE(aul.nombre,'Sin aula') AS nombre, COUNT(*) AS total
    FROM asistencias a
    LEFT JOIN lk_aulas aul ON aul.id = a.aula_id
    GROUP BY aul.id, aul.nombre
    ORDER BY total DESC
    LIMIT 8
");
$dias7 = fetchAllRows($conn, "
    SELECT DATE(fecha) AS nombre, COUNT(*) AS total
    FROM asistencias
    WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(fecha)
    ORDER BY nombre ASC
");

[$rolesLabels, $rolesData] = labelsValues($roles);
[$metodosLabels, $metodosData] = labelsValues($metodos);
[$turnosLabels, $turnosData] = labelsValues($turnos);
[$materiasLabels, $materiasData] = labelsValues($materiasTop);
[$aulasLabels, $aulasData] = labelsValues($aulasTop);
[$diasLabelsRaw, $diasData] = labelsValues($dias7);
$diasLabels = array_map(fn($d) => date('d/m', strtotime($d)), $diasLabelsRaw);

// ===== Reporte detallado por estudiante =====
$detalleEstudiante = [];
$grafDiaEst = $grafMateriaEst = $grafAulaEst = $grafTurnoEst = $grafMetodoEst = [];
$materiasEst = $aulasEst = $turnosEst = $metodosEst = $diasEst = [];
$ultimasEst = [];

if ($ciEncontrado) {
    $detalleEstudiante = fetchAllRows($conn, "
        SELECT
            a.fecha,
            a.hora AS hora_ingreso,
            TIME_FORMAT(t.hora_inicio, '%H:%i') AS inicio_turno,
            TIME_FORMAT(t.hora_fin, '%H:%i') AS salida_turno,
            COALESCE(m.nombre,'Sin materia') AS materia,
            COALESCE(aul.nombre,'Sin aula') AS aula,
            COALESCE(t.nombre,'Sin turno') AS turno,
            COALESCE(met.nombre,'Sin método') AS metodo,
            a.observacion
        FROM asistencias a
        INNER JOIN usuarios u ON u.id = a.usuario_id
        LEFT JOIN lk_materias m ON m.id = a.materia_id
        LEFT JOIN lk_aulas aul ON aul.id = a.aula_id
        LEFT JOIN lk_turnos t ON t.id = a.turno_id
        LEFT JOIN lk_metodos_asistencia met ON met.id = a.metodo_id
        WHERE u.nro_ci = ?
        ORDER BY a.fecha DESC, a.hora DESC
    ", [$ciSeleccionado]);

    $ultimasEst = array_slice($detalleEstudiante, 0, 10);

    $grafDiaEst = fetchAllRows($conn, "
        SELECT DATE(a.fecha) AS nombre, COUNT(*) AS total
        FROM asistencias a
        INNER JOIN usuarios u ON u.id = a.usuario_id
        WHERE u.nro_ci = ?
        GROUP BY DATE(a.fecha)
        ORDER BY nombre ASC
    ", [$ciSeleccionado]);

    $grafMateriaEst = fetchAllRows($conn, "
        SELECT COALESCE(m.nombre,'Sin materia') AS nombre, COUNT(*) AS total
        FROM asistencias a
        INNER JOIN usuarios u ON u.id = a.usuario_id
        LEFT JOIN lk_materias m ON m.id = a.materia_id
        WHERE u.nro_ci = ?
        GROUP BY m.id, m.nombre
        ORDER BY total DESC
    ", [$ciSeleccionado]);

    $grafAulaEst = fetchAllRows($conn, "
        SELECT COALESCE(aul.nombre,'Sin aula') AS nombre, COUNT(*) AS total
        FROM asistencias a
        INNER JOIN usuarios u ON u.id = a.usuario_id
        LEFT JOIN lk_aulas aul ON aul.id = a.aula_id
        WHERE u.nro_ci = ?
        GROUP BY aul.id, aul.nombre
        ORDER BY total DESC
    ", [$ciSeleccionado]);

    $grafTurnoEst = fetchAllRows($conn, "
        SELECT COALESCE(t.nombre,'Sin turno') AS nombre, COUNT(*) AS total
        FROM asistencias a
        INNER JOIN usuarios u ON u.id = a.usuario_id
        LEFT JOIN lk_turnos t ON t.id = a.turno_id
        WHERE u.nro_ci = ?
        GROUP BY t.id, t.nombre
        ORDER BY total DESC
    ", [$ciSeleccionado]);

    $grafMetodoEst = fetchAllRows($conn, "
        SELECT COALESCE(met.nombre,'Sin método') AS nombre, COUNT(*) AS total
        FROM asistencias a
        INNER JOIN usuarios u ON u.id = a.usuario_id
        LEFT JOIN lk_metodos_asistencia met ON met.id = a.metodo_id
        WHERE u.nro_ci = ?
        GROUP BY met.id, met.nombre
        ORDER BY total DESC
    ", [$ciSeleccionado]);
}

$reportesGlobales = [
    'global_roles' => ['titulo' => 'Usuarios por rol', 'tipo' => 'polarArea', 'labels' => $rolesLabels, 'values' => $rolesData],
    'global_metodos' => ['titulo' => 'Asistencias por método', 'tipo' => 'doughnut', 'labels' => $metodosLabels, 'values' => $metodosData],
    'global_turnos' => ['titulo' => 'Asistencias por turno', 'tipo' => 'pie', 'labels' => $turnosLabels, 'values' => $turnosData],
    'global_materias' => ['titulo' => 'Materias con más asistencias', 'tipo' => 'bar', 'labels' => $materiasLabels, 'values' => $materiasData],
    'global_aulas' => ['titulo' => 'Aulas con más asistencias', 'tipo' => 'bar', 'labels' => $aulasLabels, 'values' => $aulasData],
    'global_dias' => ['titulo' => 'Asistencias últimos 7 días', 'tipo' => 'line', 'labels' => $diasLabels, 'values' => $diasData],
    'global_radar' => ['titulo' => 'Comparación general', 'tipo' => 'radar', 'labels' => $materiasLabels, 'values' => $materiasData],
    'global_scatter' => ['titulo' => 'Tendencia dispersa', 'tipo' => 'scatter', 'labels' => $diasLabels, 'values' => $diasData],
];

$reportesEstudiante = [
    'est_dias' => ['titulo' => 'Asistencias por día del estudiante', 'tipo' => 'line', 'labels' => [], 'values' => []],
    'est_materias' => ['titulo' => 'Materias del estudiante', 'tipo' => 'doughnut', 'labels' => [], 'values' => []],
    'est_aulas' => ['titulo' => 'Aulas del estudiante', 'tipo' => 'bar', 'labels' => [], 'values' => []],
    'est_turnos' => ['titulo' => 'Turnos del estudiante', 'tipo' => 'pie', 'labels' => [], 'values' => []],
    'est_metodos' => ['titulo' => 'Métodos del estudiante', 'tipo' => 'polarArea', 'labels' => [], 'values' => []],
];

if ($ciEncontrado) {
    [$reportesEstudiante['est_dias']['labels'], $reportesEstudiante['est_dias']['values']] = labelsValues($grafDiaEst);
    $reportesEstudiante['est_dias']['labels'] = array_map(fn($d) => date('d/m', strtotime($d)), $reportesEstudiante['est_dias']['labels']);

    [$reportesEstudiante['est_materias']['labels'], $reportesEstudiante['est_materias']['values']] = labelsValues($grafMateriaEst);
    [$reportesEstudiante['est_aulas']['labels'], $reportesEstudiante['est_aulas']['values']] = labelsValues($grafAulaEst);
    [$reportesEstudiante['est_turnos']['labels'], $reportesEstudiante['est_turnos']['values']] = labelsValues($grafTurnoEst);
    [$reportesEstudiante['est_metodos']['labels'], $reportesEstudiante['est_metodos']['values']] = labelsValues($grafMetodoEst);
}

$detalleJson = json_encode($detalleEstudiante, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$reportesGlobalesJson = json_encode($reportesGlobales, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$reportesEstudianteJson = json_encode($reportesEstudiante, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$ciInfoJson = json_encode($ciEncontrado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel de reportes</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.0/dist/chart.umd.min.js"></script>
</head>
<body class="bg-dark">
<div class="container-fluid py-4">
    <div class="row g-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h3 class="mb-1"><i class="bi bi-graph-up-arrow me-2"></i>Panel de reportes</h3>
                        <div class="text-muted">Bienvenido, <?php echo $nombre; ?></div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge text-bg-dark p-2"><i class="bi bi-people me-1"></i><?php echo $totalUsuarios; ?> usuarios</span>
                        <span class="badge text-bg-primary p-2"><i class="bi bi-mortarboard me-1"></i><?php echo $totalEstudiantes; ?> estudiantes</span>
                        <span class="badge text-bg-success p-2"><i class="bi bi-person-badge me-1"></i><?php echo $totalDocentes; ?> docentes</span>
                        <span class="badge text-bg-warning p-2"><i class="bi bi-shield-lock me-1"></i><?php echo $totalAdmins; ?> admin</span>
                        <span class="badge text-bg-info p-2"><i class="bi bi-calendar-check me-1"></i><?php echo $totalAsistencias; ?> asistencias</span>
                        <span class="badge text-bg-secondary p-2"><i class="bi bi-hourglass-split me-1"></i><?php echo $totalPendientes; ?> pendientes</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-dark text-white"><i class="bi bi-list-task me-2"></i>Reportes por lista</div>
                <div class="card-body">
                    <div class="list-group">
                        <?php foreach ($reportesGlobales as $key => $rep): ?>
                            <button class="list-group-item list-group-item-action d-flex justify-content-between align-items-center reporte-global-item" data-reporte="<?php echo $key; ?>">
                                <span><i class="bi bi-bar-chart-line me-2"></i><?php echo htmlspecialchars($rep['titulo']); ?></span>
                                <span class="badge text-bg-dark rounded-pill"><?php echo array_sum($rep['values']); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-4">
                            <label class="form-label mb-1">CI del estudiante</label>
                            <select id="ciSelect" class="form-select">
                                <?php foreach ($estudiantes as $est): ?>
                                    <option value="<?php echo htmlspecialchars($est['nro_ci'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($est['nro_ci'] === $ciSeleccionado) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($est['nro_ci'].' - '.$est['nombre_completo'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label mb-1">Reporte</label>
                            <select id="reporteSelect" class="form-select"></select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label mb-1">Tipo</label>
                            <select id="tipoSelect" class="form-select">
                                <option value="bar">Barra</option>
                                <option value="line">Línea</option>
                                <option value="pie">Pie</option>
                                <option value="doughnut">Doughnut</option>
                                <option value="radar">Radar</option>
                                <option value="polarArea">Polar</option>
                                <option value="scatter">Scatter</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label mb-1">Vista</label>
                            <select id="vistaSelect" class="form-select">
                                <option value="2d">2D</option>
                                <option value="3d">3D</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap mb-2 gap-2">
                        <h5 id="tituloGrafico" class="mb-0"></h5>
                        <div id="textoEstudiante" class="text-muted small"></div>
                    </div>
                    <div style="position:relative; height:440px;">
                        <canvas id="graficoPrincipal"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-dark text-white"><i class="bi bi-person-lines-fill me-2"></i>Reporte detallado por estudiante</div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-12 col-md-4"><button class="btn btn-outline-dark w-100 estudiante-btn" data-report="est_dias">Por día</button></div>
                        <div class="col-12 col-md-4"><button class="btn btn-outline-primary w-100 estudiante-btn" data-report="est_materias">Materia</button></div>
                        <div class="col-12 col-md-4"><button class="btn btn-outline-success w-100 estudiante-btn" data-report="est_aulas">Aula</button></div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-12 col-md-6"><button class="btn btn-outline-warning w-100 estudiante-btn" data-report="est_turnos">Turno</button></div>
                        <div class="col-12 col-md-6"><button class="btn btn-outline-info w-100 estudiante-btn" data-report="est_metodos">Método</button></div>
                    </div>
                    <div style="position:relative; height:320px;">
                        <canvas id="graficoEstudiante"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <strong>Detalle completo del estudiante</strong>
                        <div class="text-muted small">Ingreso = hora del registro. Salida = hora_fin del turno asignado.</div>
                    </div>
                    <span class="badge text-bg-dark"><?php echo $ciEncontrado ? count($detalleEstudiante) : 0; ?> registros</span>
                </div>
                <div class="card-body table-responsive" style="max-height: 560px; overflow:auto;">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-light" style="position:sticky; top:0; z-index:1;">
                            <tr>
                                <th>Fecha</th>
                                <th>Día</th>
                                <th>Ingreso</th>
                                <th>Salida</th>
                                <th>Materia</th>
                                <th>Aula</th>
                                <th>Turno</th>
                                <th>Método</th>
                                <th>Observación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($ciEncontrado && count($detalleEstudiante) > 0): ?>
                                <?php foreach ($detalleEstudiante as $fila): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($fila['fecha'])); ?></td>
                                        <td><?php echo date('l', strtotime($fila['fecha'])); ?></td>
                                        <td><?php echo date('H:i', strtotime($fila['hora_ingreso'])); ?></td>
                                        <td><?php echo htmlspecialchars($fila['salida_turno'] ?? '--', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($fila['materia'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($fila['aula'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($fila['turno'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($fila['metodo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($fila['observacion'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">No hay asistencias para el estudiante seleccionado.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white"><strong>Resumen general</strong></div>
                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-3"><div class="border rounded-4 p-3 bg-light"><div class="text-muted small">Hoy</div><div class="fs-3 fw-bold"><?php echo $totalHoy; ?></div></div></div>
                        <div class="col-md-3"><div class="border rounded-4 p-3 bg-light"><div class="text-muted small">Semana</div><div class="fs-3 fw-bold"><?php echo $totalSemana; ?></div></div></div>
                        <div class="col-md-3"><div class="border rounded-4 p-3 bg-light"><div class="text-muted small">Docentes</div><div class="fs-3 fw-bold"><?php echo $totalDocentes; ?></div></div></div>
                        <div class="col-md-3"><div class="border rounded-4 p-3 bg-light"><div class="text-muted small">Pendientes</div><div class="fs-3 fw-bold"><?php echo $totalPendientes; ?></div></div></div>
                    </div>
                    <div class="row g-4">
                        <div class="col-lg-4"><div style="height:280px;"><canvas id="chartRoles"></canvas></div></div>
                        <div class="col-lg-4"><div style="height:280px;"><canvas id="chartTurnos"></canvas></div></div>
                        <div class="col-lg-4"><div style="height:280px;"><canvas id="chartMetodos"></canvas></div></div>
                    </div>
                    <div class="row g-4 mt-2">
                        <div class="col-lg-6"><div style="height:300px;"><canvas id="chartDias"></canvas></div></div>
                        <div class="col-lg-6"><div style="height:300px;"><canvas id="chartMaterias"></canvas></div></div>
                    </div>
                    <div class="row g-4 mt-2">
                        <div class="col-lg-6"><div style="height:300px;"><canvas id="chartAulas"></canvas></div></div>
                        <div class="col-lg-6"><div class="border rounded-4 p-3 h-100 bg-light">
                            <div class="fw-bold mb-2">Indicaciones</div>
                            <ul class="mb-0 small text-muted">
                                <li>Selecciona un estudiante por CI.</li>
                                <li>El detalle muestra fecha, día, ingreso, salida programada, materia, aula, turno y método.</li>
                                <li>Si no aparece nada, revisa que el CI exista en la tabla <code>usuarios</code> con rol <code>estudiante</code>.</li>
                                <li>La salida real no existe en tu estructura; se usa la <code>hora_fin</code> del turno.</li>
                            </ul>
                        </div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const reportesGlobales = <?php echo $reportesGlobalesJson; ?>;
const reportesEstudiante = <?php echo $reportesEstudianteJson; ?>;
const detalleEstudiante = <?php echo $detalleJson; ?>;
const ciEncontrado = <?php echo $ciInfoJson ?: 'null'; ?>;

let chartPrincipal = null;
let chartEstudiante = null;

function colors(n, mode = '2d') {
    const palette2d = ['#0f172a','#1d4ed8','#0f766e','#be123c','#7c3aed','#ea580c','#047857','#0369a1','#db2777','#6b7280'];
    const palette3d = ['#1f1f1f','#333333','#4a4a4a','#606060','#767676','#8d8d8d','#a2a2a2','#b8b8b8','#cfcfcf','#e5e5e5'];
    const pal = mode === '3d' ? palette3d : palette2d;
    return Array.from({length:n}, (_,i)=>pal[i % pal.length]);
}

function rgba(hex, a) {
    const h = hex.replace('#','');
    const r = parseInt(h.substring(0,2),16);
    const g = parseInt(h.substring(2,4),16);
    const b = parseInt(h.substring(4,6),16);
    return `rgba(${r},${g},${b},${a})`;
}

function withOpacity(list, a) {
    return list.map(c => rgba(c, a));
}

const shadowPlugin = {
    id: 'shadowPlugin',
    beforeDatasetDraw(chart, args, opts) {
        if (!opts || !opts.enabled) return;
        const ctx = chart.ctx;
        ctx.save();
        ctx.shadowColor = 'rgba(0,0,0,.25)';
        ctx.shadowBlur = 18;
        ctx.shadowOffsetX = 6;
        ctx.shadowOffsetY = 8;
    },
    afterDatasetDraw(chart, args, opts) {
        if (!opts || !opts.enabled) return;
        chart.ctx.restore();
    }
};
Chart.register(shadowPlugin);

function chartConfig(rep, tipo, vista = '2d') {
    const labels = rep.labels || [];
    const values = rep.values || [];
    const is3d = vista === '3d';
    const realType = (tipo === 'scatter') ? 'scatter' : tipo;
    const bg = is3d ? withOpacity(colors(labels.length, '3d'), 0.92) : withOpacity(colors(labels.length, '2d'), 0.85);
    const br = is3d ? colors(labels.length, '3d') : colors(labels.length, '2d');

    const datasets = [];
    if (realType === 'scatter') {
        datasets.push({
            label: rep.titulo,
            data: values.map((y, i) => ({x: i + 1, y})),
            backgroundColor: bg,
            borderColor: br,
            pointRadius: 6,
            pointHoverRadius: 8
        });
    } else {
        datasets.push({
            label: rep.titulo,
            data: values,
            backgroundColor: bg,
            borderColor: is3d ? '#111827' : '#334155',
            borderWidth: is3d ? 2 : 1,
            hoverOffset: (realType === 'pie' || realType === 'doughnut') ? (is3d ? 16 : 8) : 0,
            fill: realType === 'line' || realType === 'radar',
            tension: realType === 'line' ? 0.38 : 0,
            pointRadius: (realType === 'line' || realType === 'radar') ? 5 : 0,
            pointHoverRadius: (realType === 'line' || realType === 'radar') ? 7 : 0
        });
    }

    const opt = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: ['pie','doughnut','polarArea','radar','scatter'].includes(realType), position: 'bottom' },
            shadowPlugin: { enabled: is3d }
        }
    };

    if (['bar','line','radar','scatter'].includes(realType)) {
        opt.scales = {
            x: { grid: { color: 'rgba(148,163,184,.15)' } },
            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(148,163,184,.15)' } }
        };
    }

    if (realType === 'scatter') {
        opt.scales = {
            x: { type: 'linear', position: 'bottom', title: { display: true, text: 'Índice' } },
            y: { beginAtZero: true, title: { display: true, text: 'Cantidad' } }
        };
    }

    if (realType === 'doughnut') opt.cutout = is3d ? '38%' : '58%';
    if (realType === 'pie') opt.cutout = 0;
    if (realType === 'polarArea') opt.scales = { r: { ticks: { precision: 0 } } };
    if (realType === 'radar') opt.scales = { r: { beginAtZero: true, ticks: { precision: 0 } } };

    return { type: realType, data: { labels: realType === 'scatter' ? [] : labels, datasets }, options: opt };
}

function renderPrincipal() {
    const key = document.getElementById('reporteSelect').value;
    const tipo = document.getElementById('tipoSelect').value;
    const vista = document.getElementById('vistaSelect').value;
    const rep = reportesGlobales[key];

    document.getElementById('tituloGrafico').textContent = rep.titulo + ' — ' + tipo.toUpperCase() + ' ' + vista.toUpperCase();

    if (chartPrincipal) chartPrincipal.destroy();
    chartPrincipal = new Chart(document.getElementById('graficoPrincipal'), chartConfig(rep, tipo, vista));
}

function renderEstudiante(key) {
    const rep = reportesEstudiante[key];
    if (!rep) return;
    if (chartEstudiante) chartEstudiante.destroy();
    chartEstudiante = new Chart(document.getElementById('graficoEstudiante'), chartConfig(rep, rep.tipo, '2d'));
    document.getElementById('textoEstudiante').textContent = ciEncontrado ? `${ciEncontrado.nro_ci} — ${ciEncontrado.nombre_completo}` : 'Seleccione un estudiante';
}

function setGlobalOptions() {
    const sel = document.getElementById('reporteSelect');
    sel.innerHTML = '';
    for (const [key, rep] of Object.entries(reportesGlobales)) {
        const opt = document.createElement('option');
        opt.value = key;
        opt.textContent = rep.titulo;
        sel.appendChild(opt);
    }
    sel.value = '<?php echo htmlspecialchars($reporteSeleccionado, ENT_QUOTES, 'UTF-8'); ?>' in reportesGlobales ? '<?php echo htmlspecialchars($reporteSeleccionado, ENT_QUOTES, 'UTF-8'); ?>' : Object.keys(reportesGlobales)[0];
    document.getElementById('tipoSelect').value = '<?php echo htmlspecialchars($tipoSeleccionado, ENT_QUOTES, 'UTF-8'); ?>';
    document.getElementById('vistaSelect').value = '<?php echo htmlspecialchars($vistaSeleccionada, ENT_QUOTES, 'UTF-8'); ?>';
}

function activeGlobalList(key) {
    document.querySelectorAll('.reporte-global-item').forEach(btn => btn.classList.remove('active'));
    const el = document.querySelector('.reporte-global-item[data-reporte="' + key + '"]');
    if (el) el.classList.add('active');
}

function reloadWithCI() {
    const url = new URL(window.location.href);
    url.searchParams.set('ci', document.getElementById('ciSelect').value);
    url.searchParams.set('reporte', document.getElementById('reporteSelect').value);
    url.searchParams.set('tipo', document.getElementById('tipoSelect').value);
    url.searchParams.set('vista', document.getElementById('vistaSelect').value);
    window.location.href = url.toString();
}

setGlobalOptions();
activeGlobalList(document.getElementById('reporteSelect').value);
renderPrincipal();
renderEstudiante('est_dias');

document.getElementById('reporteSelect').addEventListener('change', () => {
    activeGlobalList(document.getElementById('reporteSelect').value);
    renderPrincipal();
});

document.getElementById('tipoSelect').addEventListener('change', renderPrincipal);
document.getElementById('vistaSelect').addEventListener('change', renderPrincipal);
document.getElementById('ciSelect').addEventListener('change', reloadWithCI);

document.querySelectorAll('.reporte-global-item').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('reporteSelect').value = btn.dataset.reporte;
        activeGlobalList(btn.dataset.reporte);
        renderPrincipal();
    });
});

document.querySelectorAll('.estudiante-btn').forEach(btn => {
    btn.addEventListener('click', () => renderEstudiante(btn.dataset.report));
});
</script>
</body>
</html>
