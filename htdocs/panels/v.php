<?php
require_once '../TOOLS/conexion.php';

$conn = conectarse();

$usuario = null;
$error = '';
$warning = '';
$modelsOk = true;

$requiredModels = [
    'models1/tiny_face_detector_model-weights_manifest.json',
    'models1/tiny_face_detector_model-shard1',
    'models1/face_landmark_68_model-weights_manifest.json',
    'models1/face_landmark_68_model-shard1',
    'models1/face_recognition_model-weights_manifest.json',
    'models1/face_recognition_model-shard1',
    'models1/face_recognition_model-shard2',
];

$missingModels = [];

foreach ($requiredModels as $file) {
    if (!is_file(__DIR__ . '/' . $file)) {
        $missingModels[] = $file;
        $modelsOk = false;
    }
}

if (!$modelsOk) {
    $warning = "Faltan archivos en <b>models1</b>:<br>" . implode("<br>", array_map('htmlspecialchars', $missingModels));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ci = trim($_POST['ci'] ?? '');

    if ($ci === '') {
        $error = 'Ingrese un CI válido.';
    } else {
        $stmt = $conn->prepare("
            SELECT id, nombre, nro_ci, foto
            FROM usuarios
            WHERE nro_ci = ?
            LIMIT 1
        ");

        if ($stmt) {
            $stmt->bind_param("s", $ci);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res && $res->num_rows > 0) {
                $usuario = $res->fetch_assoc();
            } else {
                $error = 'Usuario no encontrado.';
            }

            $stmt->close();
        } else {
            $error = 'Error al preparar la consulta.';
        }
    }
}

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($scriptDir === '.' || $scriptDir === '/') {
    $scriptDir = '';
}
$modelUrl = $scriptDir . '/models1';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verificación Facial</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- PRECARGA DE MODELOS -->

<link rel="preload"
      href="models1/tiny_face_detector_model-weights_manifest.json"
      as="fetch"
      crossorigin="anonymous">

<link rel="preload"
      href="models1/face_landmark_68_model-weights_manifest.json"
      as="fetch"
      crossorigin="anonymous">

<link rel="preload"
      href="models1/face_recognition_model-weights_manifest.json"
      as="fetch"
      crossorigin="anonymous">

<!-- FACE API -->

<script defer src="https://cdn.jsdelivr.net/npm/face-api.js"></script>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
/* Puedes dejar aquí tu CSS actual completo */
:root{
    --bg1:#0b1020;
    --bg2:#111827;
    --card:#ffffff;
    --soft:#f5f7fb;
    --line:#e5e7eb;
    --text:#111827;
    --muted:#6b7280;
}
*{box-sizing:border-box}
body{
    margin:0;
    min-height:100vh;
    background:
        radial-gradient(circle at top left, rgba(37,99,235,.20), transparent 30%),
        radial-gradient(circle at top right, rgba(22,163,74,.14), transparent 24%),
        linear-gradient(180deg, var(--bg1), var(--bg2));
    color:#fff;
}
.page-wrap{min-height:100vh;padding:24px 0 36px}
.shell{
    max-width:1180px;
    background:rgba(255,255,255,.96);
    color:var(--text);
    border:1px solid rgba(255,255,255,.18);
    border-radius:28px;
    box-shadow:0 24px 60px rgba(0,0,0,.35);
    padding:22px;
    backdrop-filter: blur(8px);
}
.panel{
    background:var(--card);
    border:1px solid var(--line);
    border-radius:24px;
    padding:18px;
    height:100%;
    box-shadow:0 10px 24px rgba(15,23,42,.06);
}
.section-title{font-weight:800;margin:0 0 4px 0}
.section-sub{color:var(--muted);margin:0 0 14px 0;font-size:.95rem}
.form-control-lg{border-radius:16px;padding:.9rem 1rem;border:1px solid #d1d5db}
.btn{border-radius:16px;padding:.9rem 1rem;font-weight:700}
.media-box{
    background:#0b1220;
    border-radius:20px;
    overflow:hidden;
    border:1px solid #dbe1ea;
    aspect-ratio: 4 / 3;
    display:flex;
    align-items:center;
    justify-content:center;
    position:relative;
}
video,img{width:100%;height:100%;object-fit:cover;display:block}
.camera-visual{
    display:flex;align-items:center;justify-content:center;width:100%;height:100%;
    color:#fff;font-weight:700;font-size:.95rem;text-align:center;padding:16px;
    background:radial-gradient(circle at center, rgba(37,99,235,.25), transparent 45%), #0b1220;
}
.result{
    margin-top:14px;
    padding:14px 16px;
    border-radius:16px;
    font-weight:800;
    text-align:center;
    border:1px solid transparent;
}
.result.ok{background:#ecfdf5;color:#166534;border-color:#bbf7d0}
.result.fail{background:#fef2f2;color:#991b1b;border-color:#fecaca}
.result.loading{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}
.status-line{font-size:.95rem;color:var(--muted);margin:10px 0 12px}
.badge-soft{background:#eef2ff;color:#3730a3;border:1px solid #c7d2fe}
.badge-ok{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
.badge-warn{background:#fef3c7;color:#92400e;border:1px solid #fde68a}
.badge-bad{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
@media (max-width: 991.98px){
    .shell{border-radius:20px;padding:16px}
}
</style>
</head>

<body>
<div class="page-wrap">
    <div class="container shell">

        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h1 class="h4 fw-bold mb-1">🔐 Verificación Facial</h1>
                <div class="text-muted">Buscar usuario por CI, encender cámara y comparar rostros.</div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12">
                <div class="panel">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <h5 class="section-title">Estado general</h5>
                            <p class="section-sub mb-0">Revisa si los modelos están disponibles y si la cámara puede iniciarse.</p>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <span class="badge rounded-pill px-3 py-2 <?= $modelsOk ? 'badge-ok' : 'badge-bad' ?>" id="modelBadge">
                                <?= $modelsOk ? 'Modelos listos' : 'Modelos faltantes' ?>
                            </span>
                            <span class="badge rounded-pill px-3 py-2 badge-soft" id="cameraBadge">
                                Cámara apagada
                            </span>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-lg-6">
                            <div class="small fw-bold mb-2">Verificación del servidor</div>
                            <ul class="list-group" id="serverModelList">
                                <li class="list-group-item">Leyendo archivos...</li>
                            </ul>
                        </div>
                        <div class="col-12 col-lg-6">
                            <div class="small fw-bold mb-2">Verificación del navegador</div>
                            <div class="small text-muted mb-2">Aquí se mostrará si el navegador pudo descargar los modelos.</div>
                             <div class="mt-3">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <small class="fw-bold">Carga de modelos</small>
        <small id="modelProgressText">0%</small>
    </div>

    <div class="progress" style="height:22px; border-radius:14px;">
        <div
            id="modelProgressBar"
            class="progress-bar progress-bar-striped progress-bar-animated"
            role="progressbar"
            style="width:0%">
            0%
        </div>
    </div>

    <div class="small text-muted mt-2" id="modelCurrentFile">
        Esperando inicio...
    </div>
</div>
                        </div>
                    </div>

                    <?php if ($warning): ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <strong>Atención:</strong><br>
                            <?= $warning ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-12">
                <div class="panel">
                    <h5 class="section-title">Buscar usuario</h5>
                    <p class="section-sub">Escribe el CI y abre la ficha del usuario para empezar la verificación.</p>

                    <form method="POST" class="row g-2">
                        <div class="col-12 col-md-9">
                            <input
                                type="text"
                                name="ci"
                                class="form-control form-control-lg"
                                placeholder="Ingrese CI"
                                value="<?= htmlspecialchars($_POST['ci'] ?? '') ?>"
                                required>
                        </div>
                        <div class="col-12 col-md-3">
                            <button class="btn btn-primary btn-lg w-100">
                                Buscar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="col-12">
                    <div class="alert alert-danger mb-0">
                        <?= htmlspecialchars($error) ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($usuario): ?>
                <div class="col-12 col-lg-5">
                    <div class="panel">
                        <h5 class="section-title">Rostro registrado</h5>
                        <p class="section-sub">Imagen guardada del usuario encontrado.</p>

                        <div class="media-box mb-3">
                            <img
                                id="storedFace"
                                src="<?= htmlspecialchars($usuario['foto']) ?>"
                                alt="Rostro guardado">
                        </div>

                        <div class="face-info">
                            <strong><?= htmlspecialchars($usuario['nombre']) ?></strong>
                            <div>CI: <?= htmlspecialchars($usuario['nro_ci']) ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-7">
                    <div class="panel">
                        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                            <div>
                                <h5 class="section-title mb-1">Cámara en vivo</h5>
                                <p class="section-sub mb-0">Pulsa el botón para encender o apagar la cámara.</p>
                            </div>
                            <span class="badge rounded-pill px-3 py-2 badge-soft" id="modelStep">
                                Esperando modelos
                            </span>
                        </div>

                        <div class="status-line" id="cameraStatus">Preparando sistema...</div>

                        <div class="media-box mb-3">
                            <video id="video" autoplay muted playsinline></video>
                            <div id="videoPlaceholder" class="camera-visual">
                                Cámara apagada<br>
                                <span class="opacity-75">Pulsa “Prender cámara”</span>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex">
                            <button id="btnCamera" class="btn btn-dark flex-fill">
                                Prender cámara
                            </button>
                            <button id="btnVerify" class="btn btn-success flex-fill" disabled>
                                Verificar rostro
                            </button>
                        </div>

                        <div id="result"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
window.MODEL_URL = <?= json_encode($modelUrl) ?>;
window.MODEL_CHECK_URL = <?= json_encode($scriptDir . '/check_models.php') ?>;
</script>
<script defer src="https://cdn.jsdelivr.net/npm/face-api.js"></script>
<script defer src="assets/js/face-verificacion.js"></script>
</body>
</html>