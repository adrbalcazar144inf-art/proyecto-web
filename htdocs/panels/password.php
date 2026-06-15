<?php
ob_start();
session_start();

require_once '../TOOLS/conexion.php';
require_once __DIR__ . '/../lib/fpdf/fpdf.php';

$conn = conectarse();
$conn->set_charset('utf8mb4');

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'superusuario') {
    http_response_code(403);
    exit('Acceso denegado');
}

date_default_timezone_set('America/La_Paz');

function t(string $texto): string
{
    $out = iconv('UTF-8', 'windows-1252//TRANSLIT', $texto);
    return $out !== false ? $out : $texto;
}

function claveSegura(int $len = 12): string
{
    $a = 'abcdefghijklmnopqrstuvwxyz';
    $b = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $c = '0123456789';
    $d = '!@#$%&*?-_';
    $all = $a . $b . $c . $d;

    $p = [
        $a[random_int(0, strlen($a) - 1)],
        $b[random_int(0, strlen($b) - 1)],
        $c[random_int(0, strlen($c) - 1)],
        $d[random_int(0, strlen($d) - 1)],
    ];

    for ($i = 4; $i < $len; $i++) {
        $p[] = $all[random_int(0, strlen($all) - 1)];
    }

    for ($i = count($p) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$p[$i], $p[$j]] = [$p[$j], $p[$i]];
    }

    return implode('', $p);
}

function nombreCompleto(array $u): string
{
    return trim(($u['nombre'] ?? '') . ' ' . ($u['paterno'] ?? '') . ' ' . ($u['materno'] ?? ''));
}

function buscarEstudiantes(mysqli $conn, string $q = ''): array
{
    $sql = "SELECT id, nro_ci, nombre, paterno, materno, email, telefono, must_change, rol
            FROM usuarios
            WHERE rol = 'estudiante'";
    $params = [];
    $types = '';

    if ($q !== '') {
        $sql .= " AND (
            nro_ci LIKE ?
            OR nombre LIKE ?
            OR paterno LIKE ?
            OR materno LIKE ?
            OR CONCAT(nombre, ' ', paterno, ' ', materno) LIKE ?
        )";
        $like = '%' . $q . '%';
        $params = [$like, $like, $like, $like, $like];
        $types = 'sssss';
    }

    $sql .= " ORDER BY paterno ASC, materno ASC, nombre ASC";

    $stmt = $conn->prepare($sql);
    $rows = [];

    if ($stmt) {
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
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

function buscarUsuarioPorId(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare("SELECT id, nro_ci, nombre, paterno, materno, email, telefono, must_change, rol
                            FROM usuarios
                            WHERE id = ? AND rol = 'estudiante'
                            LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc() ?: null;
    $stmt->close();

    return $row;
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
        $this->Ln(2);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(90, 90, 90);
        $this->Cell(0, 10, t('Generado el ' . date('d/m/Y H:i:s')), 0, 0, 'C');
    }
}

function pdfUltimaClave(array $data): void
{
    $pdf = new PDF();
    $pdf->titulo = 'Contraseña temporal';
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 15);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(0, 0, 0);

    $pdf->Cell(0, 8, t('Datos del estudiante'), 0, 1);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(55, 8, t('CI:'), 0, 0);
    $pdf->Cell(0, 8, t($data['ci']), 0, 1);
    $pdf->Cell(55, 8, t('Nombre completo:'), 0, 0);
    $pdf->Cell(0, 8, t($data['nombre_completo']), 0, 1);
    $pdf->Cell(55, 8, t('Rol:'), 0, 0);
    $pdf->Cell(0, 8, t($data['rol']), 0, 1);
    $pdf->Cell(55, 8, t('Fecha:'), 0, 0);
    $pdf->Cell(0, 8, t($data['fecha']), 0, 1);

    $pdf->Ln(6);
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(0, 8, t('Clave generada'), 0, 1);
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 12, t($data['password_plana']), 1, 1, 'C');

    $pdf->Ln(5);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 6, t('La clave queda guardada en la base solo como hash Argon2id. Esta vista muestra la contraseña temporal únicamente para entrega o impresión inmediata.'));

    if (ob_get_length()) {
        ob_end_clean();
    }
    $pdf->Output('D', 'clave_temporal.pdf');
    exit;
}

function pdfUsuario(array $u): void
{
    $pdf = new PDF();
    $pdf->titulo = 'Ficha del estudiante';
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 15);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(0, 0, 0);

    $pdf->Cell(0, 8, t('Datos del estudiante'), 0, 1);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(55, 8, t('CI:'), 0, 0);
    $pdf->Cell(0, 8, t($u['nro_ci']), 0, 1);
    $pdf->Cell(55, 8, t('Nombre completo:'), 0, 0);
    $pdf->Cell(0, 8, t(nombreCompleto($u)), 0, 1);
    $pdf->Cell(55, 8, t('Email:'), 0, 0);
    $pdf->Cell(0, 8, t($u['email'] ?? ''), 0, 1);
    $pdf->Cell(55, 8, t('Teléfono:'), 0, 0);
    $pdf->Cell(0, 8, t($u['telefono'] ?? ''), 0, 1);
    $pdf->Cell(55, 8, t('Rol:'), 0, 0);
    $pdf->Cell(0, 8, t($u['rol']), 0, 1);
    $pdf->Cell(55, 8, t('Estado clave:'), 0, 0);
    $pdf->Cell(0, 8, ((int)$u['must_change'] === 1) ? t('Debe cambiar') : t('Normal'), 0, 1);

    if (ob_get_length()) {
        ob_end_clean();
    }
    $pdf->Output('D', 'estudiante_' . preg_replace('/\s+/', '_', strtolower(nombreCompleto($u))) . '.pdf');
    exit;
}

function pdfLista(array $usuarios, string $titulo): void
{
    $pdf = new PDF();
    $pdf->titulo = $titulo;

    foreach ($usuarios as $u) {
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, t('Datos del estudiante'), 0, 1);

        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(55, 8, t('CI:'), 0, 0);
        $pdf->Cell(0, 8, t($u['nro_ci']), 0, 1);
        $pdf->Cell(55, 8, t('Nombre completo:'), 0, 0);
        $pdf->Cell(0, 8, t(nombreCompleto($u)), 0, 1);
        $pdf->Cell(55, 8, t('Email:'), 0, 0);
        $pdf->Cell(0, 8, t($u['email'] ?? ''), 0, 1);
        $pdf->Cell(55, 8, t('Teléfono:'), 0, 0);
        $pdf->Cell(0, 8, t($u['telefono'] ?? ''), 0, 1);
        $pdf->Cell(55, 8, t('Rol:'), 0, 0);
        $pdf->Cell(0, 8, t($u['rol']), 0, 1);
        $pdf->Cell(55, 8, t('Estado clave:'), 0, 0);
        $pdf->Cell(0, 8, ((int)$u['must_change'] === 1) ? t('Debe cambiar') : t('Normal'), 0, 1);
    }

    if (ob_get_length()) {
        ob_end_clean();
    }
    $pdf->Output('D', 'estudiantes_filtrados.pdf');
    exit;
}

if (isset($_GET['pdf'])) {
    $pdf = $_GET['pdf'];

    if ($pdf === 'clave' && isset($_SESSION['ultima_clave'])) {
        pdfUltimaClave($_SESSION['ultima_clave']);
    }

    if ($pdf === 'uno' && isset($_GET['id'])) {
        $u = buscarUsuarioPorId($conn, (int)$_GET['id']);
        if ($u) {
            pdfUsuario($u);
        }
        http_response_code(404);
        exit('Usuario no encontrado');
    }

    if ($pdf === 'todo') {
        $qpdf = trim($_GET['q'] ?? '');
        $usuariosPdf = buscarEstudiantes($conn, $qpdf);
        pdfLista($usuariosPdf, $qpdf !== '' ? 'Estudiantes filtrados' : 'Todos los estudiantes');
    }

    http_response_code(400);
    exit('Solicitud inválida');
}

$mensaje = '';
$tipo = 'dark';

if (isset($_SESSION['flash'])) {
    $mensaje = $_SESSION['flash']['mensaje'] ?? '';
    $tipo = $_SESSION['flash']['tipo'] ?? 'dark';
    unset($_SESSION['flash']);
}

$ultimaGenerada = $_SESSION['ultima_clave'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reset_clave') {
    $id = (int)($_POST['usuario_id'] ?? 0);

    if ($id <= 0) {
        $_SESSION['flash'] = ['mensaje' => 'Usuario inválido', 'tipo' => 'danger'];
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

    $u = buscarUsuarioPorId($conn, $id);

    if (!$u) {
        $_SESSION['flash'] = ['mensaje' => 'No se encontró un estudiante válido', 'tipo' => 'warning'];
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

    $clave = claveSegura(12);
    $hash = password_hash($clave, PASSWORD_ARGON2ID);

    $upd = $conn->prepare("UPDATE usuarios SET contrasena = ?, must_change = 1 WHERE id = ? AND rol = 'estudiante'");
    if ($upd) {
        $upd->bind_param('si', $hash, $id);
        if ($upd->execute()) {
            $_SESSION['ultima_clave'] = [
                'ci' => $u['nro_ci'],
                'nombre_completo' => nombreCompleto($u),
                'rol' => $u['rol'],
                'password_plana' => $clave,
                'fecha' => date('d/m/Y H:i:s')
            ];
            $_SESSION['flash'] = ['mensaje' => 'Contraseña actualizada correctamente', 'tipo' => 'success'];
        } else {
            $_SESSION['flash'] = ['mensaje' => 'No se pudo actualizar la contraseña', 'tipo' => 'danger'];
        }
        $upd->close();
    } else {
        $_SESSION['flash'] = ['mensaje' => 'Error en la consulta SQL', 'tipo' => 'danger'];
    }

    $redir = strtok($_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $redir);
    exit;
}

$q = trim($_GET['q'] ?? '');
$usuarios = buscarEstudiantes($conn, $q);
$base = strtok($_SERVER['REQUEST_URI'], '?');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel Superusuario</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style>
    body{min-height:100vh;background:#000;color:#fff}
    .wrap{max-width:1280px;margin:0 auto;padding:28px 16px 40px}
    .cardx{background:#fff;color:#000;border:1px solid #111;border-radius:24px;overflow:hidden;box-shadow:0 18px 50px rgba(255,255,255,.06)}
    .headx{background:#000;color:#fff;padding:24px}
    .bodyx{padding:24px}
    .form-control,.form-select{border-radius:14px;border:1px solid #000}
    .btn{border-radius:14px}
    .table thead th{background:#000;color:#fff;border-color:#222}
    .soft{color:#555}
    .password-box{font-size:1.1rem;letter-spacing:.08em;word-break:break-all}
    .badge-soft{background:#111;color:#fff}
    .divider{height:1px;background:#e9e9e9;margin:18px 0}
</style>
</head>
<body>
<div class="wrap">
    <div class="cardx">
        <div class="headx">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                       <a href="javascript:history.back()" class="btn btn-light mb-3">
        <i class="bi bi-arrow-left-circle-fill me-2"></i>
        Retroceder
    </a>
                    <h1 class="h3 mb-1">Panel de superusuario</h1>
                    <div class="text-white-50">Gestión de estudiantes, contraseñas temporales y PDF</div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge badge-soft px-3 py-2"><i class="bi bi-shield-lock me-1"></i>Solo superusuario</span>
                </div>
            </div>
        </div>

        <div class="bodyx">
            <?php if ($mensaje): ?>
                <div class="alert alert-<?= htmlspecialchars($tipo) ?> border-0 rounded-4">
                    <?= htmlspecialchars($mensaje) ?>
                </div>
            <?php endif; ?>

            <?php if ($ultimaGenerada): ?>
                <div class="alert alert-dark border-0 rounded-4">
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
                        <div>
                            <div class="fw-bold mb-1"><?= htmlspecialchars($ultimaGenerada['nombre_completo']) ?></div>
                            <div class="soft">CI: <?= htmlspecialchars($ultimaGenerada['ci']) ?></div>
                            <div class="password-box mt-2"><?= htmlspecialchars($ultimaGenerada['password_plana']) ?></div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="<?= htmlspecialchars($base) ?>?pdf=clave" class="btn btn-danger">
                                <i class="bi bi-file-earmark-pdf me-1"></i> PDF clave
                            </a>
                            <button class="btn btn-dark" onclick="window.print()">
                                <i class="bi bi-printer me-1"></i> Imprimir página
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <form class="row g-3 mb-4" method="GET">
                <div class="col-12 col-md-9">
                    <label class="form-label fw-semibold">Buscar por CI o nombre completo</label>
                    <input type="text" name="q" class="form-control form-control-lg" placeholder="Ej: 1000001 o Juan Perez" value="<?= htmlspecialchars($q) ?>">
                </div>
                <div class="col-12 col-md-3 d-grid">
                    <label class="form-label invisible">Buscar</label>
                    <button class="btn btn-dark btn-lg">
                        <i class="bi bi-search me-1"></i> Buscar
                    </button>
                </div>
            </form>

            <div class="d-flex gap-2 flex-wrap mb-4">
                <a href="<?= htmlspecialchars($base) ?>?pdf=todo&q=<?= urlencode($q) ?>" class="btn btn-outline-dark">
                    <i class="bi bi-people-fill me-1"></i> PDF filtrado
                </a>
                <a href="<?= htmlspecialchars($base) ?>?pdf=todo" class="btn btn-secondary">
                    <i class="bi bi-list-check me-1"></i> PDF todos
                </a>
            </div>

            <div class="table-responsive rounded-4 border border-dark">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>CI</th>
                            <th>Nombre completo</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($usuarios): ?>
                            <?php foreach ($usuarios as $u): ?>
                                <tr>
                                    <td><?= htmlspecialchars($u['nro_ci']) ?></td>
                                    <td><?= htmlspecialchars(nombreCompleto($u)) ?></td>
                                    <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($u['telefono'] ?? '') ?></td>
                                    <td>
                                        <?php if ((int)$u['must_change'] === 1): ?>
                                            <span class="badge text-bg-warning">Debe cambiar clave</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-secondary">Normal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex gap-2 justify-content-end flex-wrap">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="accion" value="reset_clave">
                                                <input type="hidden" name="usuario_id" value="<?= (int)$u['id'] ?>">
                                                <button class="btn btn-warning btn-sm">
                                                    <i class="bi bi-key-fill me-1"></i> Generar clave
                                                </button>
                                            </form>
                                            <a href="<?= htmlspecialchars($base) ?>?pdf=uno&id=<?= (int)$u['id'] ?>" class="btn btn-info btn-sm text-white">
                                                <i class="bi bi-file-earmark-pdf me-1"></i> PDF usuario
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">No hay estudiantes para mostrar</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="divider"></div>

            <div class="soft">
                La contraseña siempre se guarda con hash Argon2id y solo se muestra en texto plano al momento de generarla para entrega inmediata.
            </div>
        </div>
    </div>
</div>
</body>
</html>