<?php
ob_start();
session_start();

require_once '../TOOLS/conexion.php';
require_once __DIR__ . '/../lib/fpdf/fpdf.php';

$conn = conectarse();
$conn->set_charset('utf8mb4');
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

function nombre_completo(array $u): string
{
    return trim(($u['nombre'] ?? '') . ' ' . ($u['paterno'] ?? '') . ' ' . ($u['materno'] ?? ''));
}

function pdfText(string $txt): string
{
    $out = iconv('UTF-8', 'windows-1252//TRANSLIT', $txt);
    return $out !== false ? $out : $txt;
}

function generar_clave(int $len = 12): string
{
    $minus = 'abcdefghijklmnopqrstuvwxyz';
    $mayus = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $nums  = '0123456789';
    $spec  = '!@#$%&*?-_';
    $all   = $minus . $mayus . $nums . $spec;

    $pass = [
        $minus[random_int(0, strlen($minus) - 1)],
        $mayus[random_int(0, strlen($mayus) - 1)],
        $nums[random_int(0, strlen($nums) - 1)],
        $spec[random_int(0, strlen($spec) - 1)],
    ];

    for ($i = 4; $i < $len; $i++) {
        $pass[] = $all[random_int(0, strlen($all) - 1)];
    }

    for ($i = count($pass) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$pass[$i], $pass[$j]] = [$pass[$j], $pass[$i]];
    }

    return implode('', $pass);
}

function allowed_role(string $rol): string
{
    $roles = ['estudiante', 'docente', 'superusuario'];
    return in_array($rol, $roles, true) ? $rol : '';
}

function bind_params(mysqli_stmt $stmt, string $types, array &$params): bool
{
    $bind = [$types];
    foreach ($params as $k => &$v) {
        $bind[] = &$v;
    }
    return call_user_func_array([$stmt, 'bind_param'], $bind);
}

function ci_exists(mysqli $conn, string $ci, int $excludeId = 0): bool
{
    if ($excludeId > 0) {
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE nro_ci = ? AND id <> ? LIMIT 1");
        if (!$stmt) return false;
        $stmt->bind_param('si', $ci, $excludeId);
    } else {
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE nro_ci = ? LIMIT 1");
        if (!$stmt) return false;
        $stmt->bind_param('s', $ci);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res->num_rows > 0;
    $stmt->close();
    return $exists;
}

function get_user_by_id(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
    if (!$stmt) return null;

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function fetch_users(mysqli $conn, string $q = '', string $rolFiltro = '', int $limit = 0, int $offset = 0): array
{
    $sql = "SELECT id, nro_ci, nombre, paterno, materno, email, telefono, rol, must_change FROM usuarios WHERE 1=1";
    $params = [];
    $types = '';

    if ($q !== '') {
        $sql .= " AND (
            nro_ci LIKE ? OR
            nombre LIKE ? OR
            paterno LIKE ? OR
            materno LIKE ? OR
            CONCAT(nombre, ' ', paterno, ' ', materno) LIKE ? OR
            email LIKE ?
        )";
        $like = '%' . $q . '%';
        for ($i = 0; $i < 6; $i++) {
            $params[] = $like;
        }
        $types .= str_repeat('s', 6);
    }

    if ($rolFiltro !== '') {
        $sql .= " AND rol = ?";
        $params[] = $rolFiltro;
        $types .= 's';
    }

    $sql .= " ORDER BY id DESC";

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
            bind_params($stmt, $types, $params);
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

function count_users(mysqli $conn, string $q = '', string $rolFiltro = ''): int
{
    $sql = "SELECT COUNT(*) AS total FROM usuarios WHERE 1=1";
    $params = [];
    $types = '';

    if ($q !== '') {
        $sql .= " AND (
            nro_ci LIKE ? OR
            nombre LIKE ? OR
            paterno LIKE ? OR
            materno LIKE ? OR
            CONCAT(nombre, ' ', paterno, ' ', materno) LIKE ? OR
            email LIKE ?
        )";
        $like = '%' . $q . '%';
        for ($i = 0; $i < 6; $i++) {
            $params[] = $like;
        }
        $types .= str_repeat('s', 6);
    }

    if ($rolFiltro !== '') {
        $sql .= " AND rol = ?";
        $params[] = $rolFiltro;
        $types .= 's';
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;

    if ($types !== '') {
        bind_params($stmt, $types, $params);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $total = (int)($res->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    return $total;
}

class PDF extends FPDF
{
    public string $titulo = '';

    function Header()
    {
        $this->SetFillColor(0, 0, 0);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 12, pdfText($this->titulo), 0, 1, 'C', true);
        $this->Ln(2);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(90, 90, 90);
        $this->Cell(0, 10, pdfText('Generado el ' . date('d/m/Y H:i:s')), 0, 0, 'C');
    }
}

function export_pdf(array $usuarios, string $titulo): void
{
    $pdf = new PDF();
    $pdf->titulo = $titulo;

    foreach ($usuarios as $u) {
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, pdfText('Datos del usuario'), 0, 1);

        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(55, 8, pdfText('ID:'), 0, 0);
        $pdf->Cell(0, 8, pdfText((string)$u['id']), 0, 1);

        $pdf->Cell(55, 8, pdfText('CI:'), 0, 0);
        $pdf->Cell(0, 8, pdfText($u['nro_ci']), 0, 1);

        $pdf->Cell(55, 8, pdfText('Nombre completo:'), 0, 0);
        $pdf->Cell(0, 8, pdfText(nombre_completo($u)), 0, 1);

        $pdf->Cell(55, 8, pdfText('Email:'), 0, 0);
        $pdf->Cell(0, 8, pdfText($u['email'] ?? ''), 0, 1);

        $pdf->Cell(55, 8, pdfText('Teléfono:'), 0, 0);
        $pdf->Cell(0, 8, pdfText($u['telefono'] ?? ''), 0, 1);

        $pdf->Cell(55, 8, pdfText('Rol:'), 0, 0);
        $pdf->Cell(0, 8, pdfText($u['rol']), 0, 1);

        $pdf->Cell(55, 8, pdfText('Clave:'), 0, 0);
        $pdf->Cell(0, 8, ((int)$u['must_change'] === 1) ? pdfText('Temporal') : pdfText('Normal'), 0, 1);
    }

    if (ob_get_length()) {
        ob_end_clean();
    }

    $pdf->Output('D', 'usuarios_' . date('Ymd_His') . '.pdf');
    exit;
}

function export_xls(array $usuarios, string $titulo): void
{
    if (ob_get_length()) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="usuarios_' . date('Ymd_His') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<table border="1">';
    echo '<tr><th colspan="7">' . htmlspecialchars($titulo) . '</th></tr>';
    echo '<tr>
            <th>ID</th>
            <th>CI</th>
            <th>Nombre completo</th>
            <th>Email</th>
            <th>Teléfono</th>
            <th>Rol</th>
            <th>Clave</th>
          </tr>';

    foreach ($usuarios as $u) {
        echo '<tr>';
        echo '<td>' . (int)$u['id'] . '</td>';
        echo '<td>' . htmlspecialchars($u['nro_ci']) . '</td>';
        echo '<td>' . htmlspecialchars(nombre_completo($u)) . '</td>';
        echo '<td>' . htmlspecialchars($u['email'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($u['telefono'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($u['rol']) . '</td>';
        echo '<td>' . (((int)$u['must_change'] === 1) ? 'Temporal' : 'Normal') . '</td>';
        echo '</tr>';
    }

    echo '</table>';
    exit;
}

csrf_check();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $nro_ci   = trim($_POST['nro_ci'] ?? '');
        $nombre   = trim($_POST['nombre'] ?? '');
        $paterno  = trim($_POST['paterno'] ?? '');
        $materno  = trim($_POST['materno'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $rol      = allowed_role(trim($_POST['rol'] ?? ''));
        $password = trim($_POST['contrasena'] ?? '');
        $gen      = isset($_POST['generar_clave']);

        if ($nro_ci === '' || $nombre === '' || $paterno === '' || $materno === '' || $rol === '') {
            flash('Completa los campos obligatorios', 'danger');
            redirect_self();
        }

        if (ci_exists($conn, $nro_ci)) {
            flash('Ya existe un usuario con ese CI', 'warning');
            redirect_self();
        }

        if ($gen || $password === '') {
            $password = generar_clave(12);
            $mustChange = 1;
        } else {
            $mustChange = 0;
        }

        $hash = password_hash($password, PASSWORD_ARGON2ID);

        $stmt = $conn->prepare("INSERT INTO usuarios (nro_ci, nombre, paterno, materno, email, telefono, contrasena, rol, must_change) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('ssssssssi', $nro_ci, $nombre, $paterno, $materno, $email, $telefono, $hash, $rol, $mustChange);
            if ($stmt->execute()) {
                $_SESSION['ultima_clave'] = [
                    'ci' => $nro_ci,
                    'nombre_completo' => trim($nombre . ' ' . $paterno . ' ' . $materno),
                    'rol' => $rol,
                    'password_plana' => $password,
                    'fecha' => date('d/m/Y H:i:s')
                ];
                flash('Usuario registrado correctamente', 'success');
            } else {
                flash('No se pudo registrar el usuario', 'danger');
            }
            $stmt->close();
        } else {
            flash('Error en la consulta SQL', 'danger');
        }

        redirect_self([
            'q' => trim($_GET['q'] ?? ''),
            'rol' => trim($_GET['rol'] ?? ''),
            'pagina' => (int)($_GET['pagina'] ?? 1)
        ]);
    }

    if ($accion === 'actualizar') {
        $id       = (int)($_POST['id'] ?? 0);
        $nro_ci   = trim($_POST['nro_ci'] ?? '');
        $nombre   = trim($_POST['nombre'] ?? '');
        $paterno  = trim($_POST['paterno'] ?? '');
        $materno  = trim($_POST['materno'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $rol      = allowed_role(trim($_POST['rol'] ?? ''));
        $password = trim($_POST['contrasena'] ?? '');
        $gen      = isset($_POST['generar_clave']);

        if ($id <= 0 || $nro_ci === '' || $nombre === '' || $paterno === '' || $materno === '' || $rol === '') {
            flash('Completa los campos obligatorios', 'danger');
            redirect_self();
        }

        if (ci_exists($conn, $nro_ci, $id)) {
            flash('Ese CI ya pertenece a otro usuario', 'warning');
            redirect_self();
        }

        $setPassword = ($gen || $password !== '');
        $mustChange = 0;

        if ($setPassword) {
            if ($gen || $password === '') {
                $password = generar_clave(12);
                $mustChange = 1;
            }
            $hash = password_hash($password, PASSWORD_ARGON2ID);

            $stmt = $conn->prepare("UPDATE usuarios SET nro_ci = ?, nombre = ?, paterno = ?, materno = ?, email = ?, telefono = ?, rol = ?, contrasena = ?, must_change = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('ssssssssii', $nro_ci, $nombre, $paterno, $materno, $email, $telefono, $rol, $hash, $mustChange, $id);
            }
        } else {
            $stmt = $conn->prepare("UPDATE usuarios SET nro_ci = ?, nombre = ?, paterno = ?, materno = ?, email = ?, telefono = ?, rol = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('sssssssi', $nro_ci, $nombre, $paterno, $materno, $email, $telefono, $rol, $id);
            }
        }

        if ($stmt) {
            if ($stmt->execute()) {
                if ($setPassword) {
                    $_SESSION['ultima_clave'] = [
                        'ci' => $nro_ci,
                        'nombre_completo' => trim($nombre . ' ' . $paterno . ' ' . $materno),
                        'rol' => $rol,
                        'password_plana' => $password,
                        'fecha' => date('d/m/Y H:i:s')
                    ];
                }
                flash('Usuario actualizado correctamente', 'success');
            } else {
                flash('No se pudo actualizar el usuario', 'danger');
            }
            $stmt->close();
        } else {
            flash('Error en la consulta SQL', 'danger');
        }

        redirect_self([
            'q' => trim($_GET['q'] ?? ''),
            'rol' => trim($_GET['rol'] ?? ''),
            'pagina' => (int)($_GET['pagina'] ?? 1)
        ]);
    }

    if ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            flash('Usuario inválido', 'danger');
            redirect_self();
        }

        if (isset($_SESSION['id']) && (int)$_SESSION['id'] === $id) {
            flash('No puedes eliminar tu propio usuario activo', 'warning');
            redirect_self();
        }

        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                flash('Usuario eliminado correctamente', 'success');
            } else {
                flash('No se pudo eliminar el usuario', 'danger');
            }
            $stmt->close();
        } else {
            flash('Error en la consulta SQL', 'danger');
        }

        redirect_self([
            'q' => trim($_GET['q'] ?? ''),
            'rol' => trim($_GET['rol'] ?? ''),
            'pagina' => (int)($_GET['pagina'] ?? 1)
        ]);
    }

    if ($accion === 'reset_clave') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            flash('Usuario inválido', 'danger');
            redirect_self();
        }

        $u = get_user_by_id($conn, $id);
        if (!$u) {
            flash('No se encontró el usuario', 'warning');
            redirect_self();
        }

        $nueva = generar_clave(12);
        $hash = password_hash($nueva, PASSWORD_ARGON2ID);

        $stmt = $conn->prepare("UPDATE usuarios SET contrasena = ?, must_change = 1 WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('si', $hash, $id);
            if ($stmt->execute()) {
                $_SESSION['ultima_clave'] = [
                    'ci' => $u['nro_ci'],
                    'nombre_completo' => nombre_completo($u),
                    'rol' => $u['rol'],
                    'password_plana' => $nueva,
                    'fecha' => date('d/m/Y H:i:s')
                ];
                flash('Contraseña temporal generada correctamente', 'success');
            } else {
                flash('No se pudo resetear la contraseña', 'danger');
            }
            $stmt->close();
        } else {
            flash('Error en la consulta SQL', 'danger');
        }

        redirect_self([
            'q' => trim($_GET['q'] ?? ''),
            'rol' => trim($_GET['rol'] ?? ''),
            'pagina' => (int)($_GET['pagina'] ?? 1)
        ]);
    }
}

$q = trim($_GET['q'] ?? '');
$rolFiltro = allowed_role(trim($_GET['rol'] ?? ''));
$porPagina = 10;
$pagina = max(1, (int)($_GET['pagina'] ?? 1));

$totalFilas = count_users($conn, $q, $rolFiltro);
$totalPaginas = max(1, (int)ceil($totalFilas / $porPagina));
$pagina = min($pagina, $totalPaginas);
$inicio = ($pagina - 1) * $porPagina;

$usuarios = fetch_users($conn, $q, $rolFiltro, $porPagina, $inicio);
$allFiltered = fetch_users($conn, $q, $rolFiltro, 0, 0);

if (isset($_GET['export'])) {
    $tipoExport = $_GET['export'];

    if ($tipoExport === 'pdf') {
        export_pdf($allFiltered, $rolFiltro !== '' ? 'Usuarios filtrados por rol' : 'Listado de usuarios');
    }

    if ($tipoExport === 'xls') {
        export_xls($allFiltered, $rolFiltro !== '' ? 'Usuarios filtrados por rol' : 'Listado de usuarios');
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
<title>CRUD Usuarios - Superusuario</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style>
    body{min-height:100vh;background:#000;color:#fff}
    .wrap{max-width:1400px;margin:0 auto;padding:22px 14px 40px}
    .shell{background:#fff;color:#000;border-radius:26px;overflow:hidden;box-shadow:0 20px 60px rgba(255,255,255,.06);border:1px solid #151515}
    .hero{background:#000;color:#fff;padding:20px 22px}
    .hero .btn{border-radius:999px}
    .body{padding:22px}
    .card-stat{background:#fff;border:1px solid #111;border-radius:20px;box-shadow:0 8px 24px rgba(0,0,0,.06)}
    .card-stat .icon{width:46px;height:46px;border-radius:14px;display:flex;align-items:center;justify-content:center;background:#000;color:#fff}
    .form-control,.form-select{border-radius:14px;border:1px solid #111}
    .btn{border-radius:14px}
    .btn-dark{background:#000;border-color:#000}
    .btn-outline-dark:hover{background:#000;color:#fff}
    .table thead th{background:#000;color:#fff;border-color:#222;white-space:nowrap}
    .table td{vertical-align:middle}
    .badge-soft{background:#111;color:#fff}
    .soft{color:#555}
    .table-responsive{border-radius:18px;overflow:auto;border:1px solid #111}
    .modal-content{border-radius:22px;background:#fff;color:#000}
    .modal-header,.modal-footer{border-color:#e8e8e8;background:#f8f9fa;color:#000}
    .modal-title{color:#000}
    .modal-body{color:#000}
    .modal-body .form-label{color:#000;font-weight:600}
    .modal-body .form-control,.modal-body .form-select{background:#fff;color:#000;border:1px solid #111}
    .modal-body .form-control::placeholder{color:#777}
    .searchbar{background:#f7f7f7;border:1px solid #e9e9e9;border-radius:18px;padding:18px}
    .password-box{font-size:1rem;letter-spacing:.08em;word-break:break-all}
    .action-btns .btn{min-width:42px}
    @media (max-width: 576px){
        .hero{padding:18px}
        .body{padding:16px}
        .action-btns{flex-wrap:wrap}
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
                        <span class="badge badge-soft px-3 py-2"><i class="bi bi-shield-lock me-1"></i>Superusuario</span>
                    </div>
                    <h1 class="h3 mb-1">Panel CRUD de usuarios</h1>
                    <div class="text-white-50">Gestión rápida, segura y responsive para PC, tablet y celular</div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#modalNuevo">
                        <i class="bi bi-person-plus-fill me-1"></i> Nuevo usuario
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

            <?php if (isset($_SESSION['ultima_clave'])): $k = $_SESSION['ultima_clave']; ?>
                <div class="alert alert-dark border-0 rounded-4">
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
                        <div>
                            <div class="fw-bold"><?= htmlspecialchars($k['nombre_completo']) ?></div>
                            <div class="soft">CI: <?= htmlspecialchars($k['ci']) ?> · Rol: <?= htmlspecialchars($k['rol']) ?></div>
                            <div class="password-box mt-2"><?= htmlspecialchars($k['password_plana']) ?></div>
                        </div>
                        <button class="btn btn-outline-dark" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($k['password_plana']) ?>')">
                            <i class="bi bi-clipboard-check me-1"></i> Copiar clave
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card-stat p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="soft small">Total</div>
                                <div class="h3 mb-0"><?= (int)$totalFilas ?></div>
                            </div>
                            <div class="icon"><i class="bi bi-people-fill"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card-stat p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="soft small">Estudiantes</div>
                                <div class="h3 mb-0"><?= (int)$conn->query("SELECT COUNT(*) c FROM usuarios WHERE rol='estudiante'")->fetch_assoc()['c'] ?></div>
                            </div>
                            <div class="icon"><i class="bi bi-mortarboard-fill"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card-stat p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="soft small">Docentes</div>
                                <div class="h3 mb-0"><?= (int)$conn->query("SELECT COUNT(*) c FROM usuarios WHERE rol='docente'")->fetch_assoc()['c'] ?></div>
                            </div>
                            <div class="icon"><i class="bi bi-person-badge-fill"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card-stat p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="soft small">Superusuarios</div>
                                <div class="h3 mb-0"><?= (int)$conn->query("SELECT COUNT(*) c FROM usuarios WHERE rol='superusuario'")->fetch_assoc()['c'] ?></div>
                            </div>
                            <div class="icon"><i class="bi bi-shield-fill-check"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="searchbar mb-4">
                <form class="row g-3 align-items-end" method="GET">
                    <div class="col-12 col-md-6 col-lg-5">
                        <label class="form-label fw-semibold">Buscar</label>
                        <input type="text" name="q" class="form-control form-control-lg" placeholder="CI, nombre, apellido o email" value="<?= htmlspecialchars($q) ?>">
                    </div>
                    <div class="col-12 col-md-4 col-lg-3">
                        <label class="form-label fw-semibold">Filtrar por rol</label>
                        <select name="rol" class="form-select form-select-lg">
                            <option value="">Todos</option>
                            <option value="estudiante" <?= $rolFiltro === 'estudiante' ? 'selected' : '' ?>>Estudiante</option>
                            <option value="docente" <?= $rolFiltro === 'docente' ? 'selected' : '' ?>>Docente</option>
                            <option value="superusuario" <?= $rolFiltro === 'superusuario' ? 'selected' : '' ?>>Superusuario</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2 col-lg-2 d-grid">
                        <button class="btn btn-dark btn-lg">
                            <i class="bi bi-search me-1"></i> Buscar
                        </button>
                    </div>
                    <div class="col-12 col-md-12 col-lg-2 d-grid">
                        <a href="<?= htmlspecialchars($base) ?>" class="btn btn-outline-dark btn-lg">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Limpiar
                        </a>
                    </div>
                </form>

                <div class="d-flex gap-2 flex-wrap mt-3">
                    <a href="<?= htmlspecialchars($base) ?>?export=pdf&q=<?= urlencode($q) ?>&rol=<?= urlencode($rolFiltro) ?>" class="btn btn-danger">
                        <i class="bi bi-file-earmark-pdf-fill me-1"></i> PDF
                    </a>
                    <a href="<?= htmlspecialchars($base) ?>?export=xls&q=<?= urlencode($q) ?>&rol=<?= urlencode($rolFiltro) ?>" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel-fill me-1"></i> Excel
                    </a>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div class="soft">
                    Mostrando <?= count($usuarios) ?> de <?= (int)$totalFilas ?> registros
                </div>
            </div>

            <div class="table-responsive shadow-sm">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>CI</th>
                            <th>Nombre completo</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Rol</th>
                            <th>Clave</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($usuarios): ?>
                            <?php foreach ($usuarios as $u): ?>
                                <tr>
                                    <td><?= (int)$u['id'] ?></td>
                                    <td><?= htmlspecialchars($u['nro_ci']) ?></td>
                                    <td><?= htmlspecialchars(nombre_completo($u)) ?></td>
                                    <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($u['telefono'] ?? '') ?></td>
                                    <td><span class="badge text-bg-dark"><?= htmlspecialchars($u['rol']) ?></span></td>
                                    <td>
                                        <?php if ((int)$u['must_change'] === 1): ?>
                                            <span class="badge text-bg-warning">Temporal</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-secondary">Normal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2 action-btns flex-wrap">
                                            <button
                                                class="btn btn-outline-dark btn-sm editar-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalEditar"
                                                data-id="<?= (int)$u['id'] ?>"
                                                data-ci="<?= htmlspecialchars($u['nro_ci'], ENT_QUOTES) ?>"
                                                data-nombre="<?= htmlspecialchars($u['nombre'], ENT_QUOTES) ?>"
                                                data-paterno="<?= htmlspecialchars($u['paterno'], ENT_QUOTES) ?>"
                                                data-materno="<?= htmlspecialchars($u['materno'], ENT_QUOTES) ?>"
                                                data-email="<?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES) ?>"
                                                data-telefono="<?= htmlspecialchars($u['telefono'] ?? '', ENT_QUOTES) ?>"
                                                data-rol="<?= htmlspecialchars($u['rol'], ENT_QUOTES) ?>"
                                            >
                                                <i class="bi bi-pencil-square"></i>
                                            </button>

                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="accion" value="reset_clave">
                                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                                <button class="btn btn-warning btn-sm" onclick="return confirm('¿Generar nueva contraseña temporal?')">
                                                    <i class="bi bi-key-fill"></i>
                                                </button>
                                            </form>

                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                                <button class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar este usuario?')">
                                                    <i class="bi bi-trash3-fill"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">No hay registros para mostrar</td>
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
                                <a class="page-link" href="?<?= http_build_query(['q' => $q, 'rol' => $rolFiltro, 'pagina' => $i]) ?>">
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
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="accion" value="crear">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>Nuevo usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label">CI *</label>
                        <input type="text" name="nro_ci" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Apellido paterno *</label>
                        <input type="text" name="paterno" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Apellido materno *</label>
                        <input type="text" name="materno" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" class="form-control">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Rol *</label>
                        <select name="rol" class="form-select" required>
                            <option value="">Seleccione</option>
                            <option value="estudiante">Estudiante</option>
                            <option value="docente">Docente</option>
                            <option value="superusuario">Superusuario</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Contraseña</label>
                        <input type="text" name="contrasena" class="form-control" placeholder="Opcional">
                    </div>
                    <div class="col-12 col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="generar_clave" id="genNuevo" checked>
                            <label class="form-check-label" for="genNuevo">Generar clave temporal</label>
                        </div>
                    </div>
                </div>
                <div class="mt-3 soft">
                    Si activas la opción, el sistema crea una contraseña fuerte de 12 caracteres y obliga a cambiarla.
                </div>
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
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="accion" value="actualizar">
            <input type="hidden" name="id" id="editId">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Editar usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label">CI *</label>
                        <input type="text" name="nro_ci" id="editCi" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="nombre" id="editNombre" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Apellido paterno *</label>
                        <input type="text" name="paterno" id="editPaterno" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Apellido materno *</label>
                        <input type="text" name="materno" id="editMaterno" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="editEmail" class="form-control">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" id="editTelefono" class="form-control">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Rol *</label>
                        <select name="rol" id="editRol" class="form-select" required>
                            <option value="estudiante">Estudiante</option>
                            <option value="docente">Docente</option>
                            <option value="superusuario">Superusuario</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Nueva contraseña</label>
                        <input type="text" name="contrasena" class="form-control" placeholder="Dejar vacío para no cambiar">
                    </div>
                    <div class="col-12 col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="generar_clave" id="genEditar">
                            <label class="form-check-label" for="genEditar">Generar clave temporal</label>
                        </div>
                    </div>
                </div>
                <div class="mt-3 soft">
                    La contraseña solo cambia si escribes una nueva o activas la generación temporal.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-dark">
                    <i class="bi bi-save2 me-1"></i> Guardar cambios
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
        document.getElementById('editCi').value = btn.dataset.ci;
        document.getElementById('editNombre').value = btn.dataset.nombre;
        document.getElementById('editPaterno').value = btn.dataset.paterno;
        document.getElementById('editMaterno').value = btn.dataset.materno;
        document.getElementById('editEmail').value = btn.dataset.email;
        document.getElementById('editTelefono').value = btn.dataset.telefono;
        document.getElementById('editRol').value = btn.dataset.rol;
    });
});
</script>
</body>
</html>