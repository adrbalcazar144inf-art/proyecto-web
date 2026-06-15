<?php
ob_start();
session_start();

require_once '../TOOLS/conexion.php';
require_once __DIR__ . '/../lib/fpdf/fpdf.php';

$conexion = conectarse();
$conexion->set_charset('utf8mb4');
date_default_timezone_set('America/La_Paz');

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'superusuario') {
    http_response_code(403);
    exit('Acceso denegado');
}

if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function csrf_token(): string
{
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf'] ?? '';
        if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
            http_response_code(403);
            exit('Token inválido');
        }
    }
}

function flash(string $mensaje, string $tipo = 'success'): void
{
    $_SESSION['flash'] = ['mensaje' => $mensaje, 'tipo' => $tipo];
}

function redirect_self(array $params = []): void
{
    $base = strtok($_SERVER['REQUEST_URI'], '?');
    $qs = http_build_query($params);
    header('Location: ' . $base . ($qs ? '?' . $qs : ''));
    exit;
}

function t(string $txt): string
{
    $out = iconv('UTF-8', 'windows-1252//TRANSLIT', $txt);
    return $out !== false ? $out : $txt;
}

function clean(string $v): string
{
    return trim($v);
}

function aula_exists(mysqli $conn, string $nombre, int $excludeId = 0): bool
{
    if ($excludeId > 0) {
        $stmt = $conn->prepare("SELECT id FROM lk_aulas WHERE nombre = ? AND id <> ? LIMIT 1");
        if (!$stmt) return false;
        $stmt->bind_param('si', $nombre, $excludeId);
    } else {
        $stmt = $conn->prepare("SELECT id FROM lk_aulas WHERE nombre = ? LIMIT 1");
        if (!$stmt) return false;
        $stmt->bind_param('s', $nombre);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res->num_rows > 0;
    $stmt->close();
    return $exists;
}

function count_aulas(mysqli $conn, string $q = ''): int
{
    $sql = "SELECT COUNT(*) AS total FROM lk_aulas WHERE 1=1";
    $params = [];
    $types = '';

    if ($q !== '') {
        $sql .= " AND nombre LIKE ?";
        $params[] = '%' . $q . '%';
        $types .= 's';
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;

    if ($types !== '') {
        $bind = [$types];
        foreach ($params as $k => &$v) {
            $bind[] = &$v;
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $total = (int)($res->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    return $total;
}

function fetch_aulas(mysqli $conn, string $q = '', int $limit = 0, int $offset = 0): array
{
    $sql = "SELECT id, nombre FROM lk_aulas WHERE 1=1";
    $params = [];
    $types = '';

    if ($q !== '') {
        $sql .= " AND nombre LIKE ?";
        $params[] = '%' . $q . '%';
        $types .= 's';
    }

    $sql .= " ORDER BY nombre ASC";

    if ($limit > 0) {
        $sql .= " LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $limit;
        $types .= 'ii';
    }

    $stmt = $conn->prepare($sql);
    $rows = [];

    if ($stmt) {
        if ($types !== '') {
            $bind = [$types];
            foreach ($params as $k => &$v) {
                $bind[] = &$v;
            }
            call_user_func_array([$stmt, 'bind_param'], $bind);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $stmt->close();
    }

    return $rows;
}

class PDF extends FPDF
{
    public string $titulo = '';

    function Header()
    {
        $this->SetFillColor(0, 0, 0);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 12, t($this->titulo), 0, 1, 'C', true);
        $this->Ln(3);

        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(20, 8, t('ID'), 1, 0, 'C');
        $this->Cell(257, 8, t('Nombre del aula'), 1, 1, 'C');
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(90, 90, 90);
        $this->Cell(0, 10, t('Generado el ' . date('d/m/Y H:i:s')), 0, 0, 'C');
    }
}

function export_pdf(array $aulas, string $titulo): void
{
    $pdf = new PDF('L', 'mm', 'A4');
    $pdf->titulo = $titulo;
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 10);

    foreach ($aulas as $a) {
        $pdf->Cell(20, 8, t((string)$a['id']), 1, 0, 'C');
        $pdf->Cell(257, 8, t($a['nombre']), 1, 1, 'L');
    }

    if (ob_get_length()) {
        ob_end_clean();
    }

    $pdf->Output('D', 'aulas_' . date('Ymd_His') . '.pdf');
    exit;
}

function export_xls(array $aulas, string $titulo): void
{
    if (ob_get_length()) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="aulas_' . date('Ymd_His') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";
    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<table border="1">';
    echo '<tr><th colspan="2">' . htmlspecialchars($titulo) . '</th></tr>';
    echo '<tr><th>ID</th><th>Nombre del aula</th></tr>';

    foreach ($aulas as $a) {
        echo '<tr>';
        echo '<td>' . (int)$a['id'] . '</td>';
        echo '<td>' . htmlspecialchars($a['nombre']) . '</td>';
        echo '</tr>';
    }

    echo '</table></body></html>';
    exit;
}

csrf_check();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $nombre = clean($_POST['nombre'] ?? '');

        if ($nombre === '') {
            flash('El nombre del aula no puede estar vacío', 'danger');
            redirect_self([
                'q' => trim($_GET['q'] ?? ''),
                'pagina' => (int)($_GET['pagina'] ?? 1)
            ]);
        }

        if (aula_exists($conexion, $nombre)) {
            flash('Ya existe un aula con ese nombre', 'warning');
            redirect_self([
                'q' => trim($_GET['q'] ?? ''),
                'pagina' => (int)($_GET['pagina'] ?? 1)
            ]);
        }

        $stmt = $conexion->prepare("INSERT INTO lk_aulas (nombre) VALUES (?)");
        if ($stmt) {
            $stmt->bind_param('s', $nombre);
            if ($stmt->execute()) {
                flash('Aula registrada correctamente', 'success');
            } else {
                flash('No se pudo registrar el aula', 'danger');
            }
            $stmt->close();
        } else {
            flash('Error en la consulta SQL', 'danger');
        }

        redirect_self([
            'q' => trim($_GET['q'] ?? ''),
            'pagina' => (int)($_GET['pagina'] ?? 1)
        ]);
    }

    if ($accion === 'actualizar') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = clean($_POST['nombre'] ?? '');

        if ($id <= 0 || $nombre === '') {
            flash('Datos inválidos', 'danger');
            redirect_self([
                'q' => trim($_GET['q'] ?? ''),
                'pagina' => (int)($_GET['pagina'] ?? 1)
            ]);
        }

        if (aula_exists($conexion, $nombre, $id)) {
            flash('Ya existe otra aula con ese nombre', 'warning');
            redirect_self([
                'q' => trim($_GET['q'] ?? ''),
                'pagina' => (int)($_GET['pagina'] ?? 1)
            ]);
        }

        $stmt = $conexion->prepare("UPDATE lk_aulas SET nombre = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('si', $nombre, $id);
            if ($stmt->execute()) {
                flash('Aula actualizada correctamente', 'success');
            } else {
                flash('No se pudo actualizar el aula', 'danger');
            }
            $stmt->close();
        } else {
            flash('Error en la consulta SQL', 'danger');
        }

        redirect_self([
            'q' => trim($_GET['q'] ?? ''),
            'pagina' => (int)($_GET['pagina'] ?? 1)
        ]);
    }

    if ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            flash('Aula inválida', 'danger');
            redirect_self([
                'q' => trim($_GET['q'] ?? ''),
                'pagina' => (int)($_GET['pagina'] ?? 1)
            ]);
        }

        $stmt = $conexion->prepare("DELETE FROM lk_aulas WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                flash('Aula eliminada correctamente', 'success');
            } else {
                flash('No se pudo eliminar el aula', 'danger');
            }
            $stmt->close();
        } else {
            flash('Error en la consulta SQL', 'danger');
        }

        redirect_self([
            'q' => trim($_GET['q'] ?? ''),
            'pagina' => (int)($_GET['pagina'] ?? 1)
        ]);
    }
}

$q = clean($_GET['q'] ?? '');
$porPagina = 8;
$pagina = max(1, (int)($_GET['pagina'] ?? 1));

$total = count_aulas($conexion, $q);
$totalPaginas = max(1, (int)ceil($total / $porPagina));
$pagina = min($pagina, $totalPaginas);
$inicio = ($pagina - 1) * $porPagina;

$aulas = fetch_aulas($conexion, $q, $porPagina, $inicio);
$allFiltered = fetch_aulas($conexion, $q);

if (isset($_GET['export'])) {
    $export = $_GET['export'];
    $titulo = $q !== '' ? 'Aulas filtradas' : 'Listado de aulas';

    if ($export === 'pdf') {
        export_pdf($allFiltered, $titulo);
    }

    if ($export === 'xls') {
        export_xls($allFiltered, $titulo);
    }

    http_response_code(400);
    exit('Exportación inválida');
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$base = strtok($_SERVER['REQUEST_URI'], '?');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Gestión de Aulas</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style>
    body{
        min-height:100vh;
        background:#000;
        color:#fff;
    }
    .wrap{
        max-width:1200px;
        margin:0 auto;
        padding:22px 14px 40px;
    }
    .shell{
        background:#fff;
        color:#000;
        border-radius:26px;
        overflow:hidden;
        border:1px solid #151515;
        box-shadow:0 20px 60px rgba(255,255,255,.05);
    }
    .hero{
        background:#000;
        color:#fff;
        padding:20px 22px;
    }
    .body{
        padding:22px;
    }
    .cardx{
        background:#fff;
        border:1px solid #111;
        border-radius:20px;
        box-shadow:0 8px 24px rgba(0,0,0,.06);
    }
    .icon{
        width:46px;
        height:46px;
        border-radius:14px;
        display:flex;
        align-items:center;
        justify-content:center;
        background:#000;
        color:#fff;
    }
    .form-control,.form-select{
        border-radius:14px;
        border:1px solid #111;
    }
    .btn{
        border-radius:14px;
    }
    .btn-dark{
        background:#000;
        border-color:#000;
    }
    .btn-outline-dark:hover{
        background:#000;
        color:#fff;
    }
    .table thead th{
        background:#000;
        color:#fff;
        border-color:#222;
        white-space:nowrap;
    }
    .table td{
        vertical-align:middle;
    }
    .table-responsive{
        border-radius:18px;
        overflow:auto;
        border:1px solid #111;
    }
    .soft{
        color:#666;
    }
    .searchbar{
        background:#f7f7f7;
        border:1px solid #e9e9e9;
        border-radius:18px;
        padding:18px;
    }
    .modal-content{
        border-radius:22px;
        background:#fff;
        color:#000;
    }
    .modal-header,.modal-footer{
        border-color:#e8e8e8;
        background:#f8f9fa;
        color:#000;
    }
    .modal-title,.modal-body,.modal-body .form-label{
        color:#000;
    }
    .modal-body .form-control{
        background:#fff;
        color:#000;
        border:1px solid #111;
    }
    .modal-body .form-control::placeholder{
        color:#777;
    }
    .badge-soft{
        background:#111;
        color:#fff;
    }
    @media (max-width:576px){
        .hero{padding:18px;}
        .body{padding:16px;}
    }
</style>
</head>
<body>
<div class="wrap">
    <div class="shell">
        <div class="hero">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <a href="javascript:history.back()" class="btn btn-outline-light btn-sm">
                            <i class="bi bi-arrow-left"></i> Volver
                        </a>
                        <span class="badge badge-soft px-3 py-2">
                            <i class="bi bi-building me-1"></i> Aulas
                        </span>
                    </div>
                    <h1 class="h3 mb-1">Gestión de Aulas</h1>
                    <div class="text-white-50">CRUD con búsqueda, paginación y exportación PDF/Excel</div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#modalNuevo">
                        <i class="bi bi-plus-circle-fill me-1"></i> Nueva aula
                    </button>
                </div>
            </div>
        </div>

        <div class="body">
            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['tipo']) ?> border-0 rounded-4">
                    <?= htmlspecialchars($flash['mensaje']) ?>
                </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6">
                    <div class="cardx p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="soft small">Total aulas</div>
                                <div class="h3 mb-0"><?= (int)$total ?></div>
                            </div>
                            <div class="icon"><i class="bi bi-journal-richtext"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="cardx p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="soft small">Resultados visibles</div>
                                <div class="h3 mb-0"><?= count($aulas) ?></div>
                            </div>
                            <div class="icon"><i class="bi bi-funnel-fill"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="searchbar mb-4">
                <form class="row g-3 align-items-end" method="GET">
                    <div class="col-12 col-md-8">
                        <label class="form-label fw-semibold">Buscar aula</label>
                        <input type="text" name="q" class="form-control form-control-lg" placeholder="Nombre del aula" value="<?= htmlspecialchars($q) ?>">
                    </div>
                    <div class="col-12 col-md-2 d-grid">
                        <button class="btn btn-dark btn-lg">
                            <i class="bi bi-search me-1"></i> Buscar
                        </button>
                    </div>
                    <div class="col-12 col-md-2 d-grid">
                        <a href="<?= htmlspecialchars($base) ?>" class="btn btn-outline-dark btn-lg">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Limpiar
                        </a>
                    </div>
                </form>

                <div class="d-flex gap-2 flex-wrap mt-3">
                    <a href="<?= htmlspecialchars($base) ?>?export=pdf&q=<?= urlencode($q) ?>" class="btn btn-danger">
                        <i class="bi bi-file-earmark-pdf-fill me-1"></i> PDF
                    </a>
                    <a href="<?= htmlspecialchars($base) ?>?export=xls&q=<?= urlencode($q) ?>" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel-fill me-1"></i> Excel
                    </a>
                </div>
            </div>

            <div class="table-responsive shadow-sm">
                <table class="table table-striped table-hover align-middle mb-0 text-center">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th style="width:220px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($aulas): ?>
                            <?php foreach ($aulas as $a): ?>
                                <tr>
                                    <td><?= (int)$a['id'] ?></td>
                                    <td class="text-start"><?= htmlspecialchars($a['nombre']) ?></td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                                            <button
                                                class="btn btn-outline-dark btn-sm editar-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalEditar"
                                                data-id="<?= (int)$a['id'] ?>"
                                                data-nombre="<?= htmlspecialchars($a['nombre'], ENT_QUOTES) ?>"
                                            >
                                                <i class="bi bi-pencil-square me-1"></i>Editar
                                            </button>

                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                                <button class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar esta aula?')">
                                                    <i class="bi bi-trash3-fill me-1"></i>Eliminar
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="py-4">No hay aulas registradas</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPaginas > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center flex-wrap gap-1">
                        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                            <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(['q' => $q, 'pagina' => $i]) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNuevo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="accion" value="crear">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle-fill me-2"></i>Nueva aula</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Nombre del aula</label>
                <input type="text" name="nombre" class="form-control" placeholder="Ej: Aula 1" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-dark">
                    <i class="bi bi-check2-circle me-1"></i> Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="accion" value="actualizar">
            <input type="hidden" name="id" id="editId">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Editar aula</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Nombre del aula</label>
                <input type="text" name="nombre" id="editNombre" class="form-control" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-dark">
                    <i class="bi bi-save2 me-1"></i> Actualizar
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.editar-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('editId').value = btn.dataset.id;
        document.getElementById('editNombre').value = btn.dataset.nombre;
    });
});
</script>
</body>
</html>