<?php
session_start();
 
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../TOOLS/conexion.php';

$conn = conectarse();
$user_id = (int)$_SESSION['user_id'];

$error = '';
$success = '';
$ci = '';
$nombre = '';
$telefono_actual = '';

$stmtUser = $conn->prepare("SELECT nro_ci, nombre, telefono FROM usuarios WHERE id = ? LIMIT 1");
if ($stmtUser) {
    $stmtUser->bind_param("i", $user_id);
    $stmtUser->execute();
    $resUser = $stmtUser->get_result();
    $userData = $resUser->fetch_assoc();

    if ($userData) {
        $ci = $userData['nro_ci'] ?? '';
        $nombre = $userData['nombre'] ?? '';
        $telefono_actual = $userData['telefono'] ?? '';
    }

    $stmtUser->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $foto_data = trim($_POST['foto_data'] ?? '');
    $nueva_contra = $_POST['nueva_contra'] ?? '';
    $confirmar_contra = $_POST['confirmar_contra'] ?? '';
    $telefono = trim($_POST['telefono'] ?? '');
    $face_detected = trim($_POST['face_detected'] ?? '0');

    if ($face_detected !== '1') {
        $error = 'Debe detectar un rostro antes de guardar.';
    } elseif (!preg_match('/^data:image\/png;base64,/', $foto_data)) {
        $error = 'Debe capturar una foto válida en formato PNG.';
    } elseif (empty($nueva_contra) || empty($confirmar_contra)) {
        $error = 'Complete ambos campos de contraseña.';
    } elseif ($nueva_contra !== $confirmar_contra) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (strlen($nueva_contra) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif (empty($telefono)) {
        $error = 'Complete el campo de teléfono.';
    }

    if (empty($error)) {
        $data = explode(',', $foto_data, 2);

        if (count($data) !== 2 || empty($data[1])) {
            $error = 'Formato de foto inválido.';
        } else {
            $foto_decoded = base64_decode($data[1], true);

            if ($foto_decoded === false) {
                $error = 'La foto no es una imagen válida.';
            }
        }
    }

    if (empty($error)) {
        $upload_dir = __DIR__ . '/uploads';

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $filename_db = 'uploads/user_' . $user_id . '_' . time() . '.png';
        $filename_fs = __DIR__ . '/' . $filename_db;

        if (file_put_contents($filename_fs, $foto_decoded) === false) {
            $error = 'No se pudo guardar la foto en el servidor.';
        } else {
            $hash = password_hash($nueva_contra, PASSWORD_DEFAULT);

            $sql = "UPDATE usuarios 
                    SET foto = ?, contrasena = ?, telefono = ?, must_change = 0
                    WHERE id = ?";

            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                $error = 'Error en la preparación de la consulta.';
            } else {
                $stmt->bind_param("sssi", $filename_db, $hash, $telefono, $user_id);

                if ($stmt->execute()) {
                    $success = 'Cuenta configurada correctamente.';
                    $telefono_actual = $telefono;
                } else {
                    $error = 'Error al guardar los datos.';
                }

                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Biometría Facial Avanzada</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root{
    --bg:#0a0a0a;
    --panel:#ffffff;
    --panel2:#f4f4f4;
    --text:#111111;
    --muted:#666;
    --cyan:#0dcaf0;
    --green:#198754;
    --blue:#0d6efd;
    --yellow:#ffc107;
    --red:#dc3545;
}

body{
    background:var(--bg);
    color:#fff;
    overflow-x:hidden;
}

.page-shell{
    min-height:100vh;
}

.card-bio{
    background:var(--panel);
    color:var(--text);
    border:1px solid #d9d9d9;
    border-radius:22px;
    box-shadow:0 10px 35px rgba(0,0,0,.25);
}

.title-glow{
    color:#000;
    font-weight:800;
    letter-spacing:.2px;
}

.subtle{
    color:var(--muted);
    font-size:.92rem;
}

.video-wrap{
    position:relative;
    width:100%;
    aspect-ratio:16/10;
    background:#000;
    border:2px solid #e5e5e5;
    border-radius:20px;
    overflow:hidden;
}

#video,
#overlay{
    position:absolute;
    inset:0;
    width:100%;
    height:100%;
    object-fit:cover;
}

#overlay{
    pointer-events:none;
}

.mesh-panel{
    position:relative;
    width:100%;
    aspect-ratio:3/4;
    background:#000;
    border-radius:20px;
    border:2px solid #e5e5e5;
    overflow:hidden;
}

#meshCanvas{
    width:100%;
    height:100%;
    display:block;
}

.metric{
    background:var(--panel2);
    border:1px solid #dfdfdf;
    border-radius:16px;
    padding:14px 15px;
    margin-bottom:14px;
}

.metric h6{
    margin:0 0 6px 0;
    color:#444;
    font-size:.9rem;
}

.metric .value{
    font-size:1.35rem;
    font-weight:800;
    color:#000;
    line-height:1.1;
}

.status-dot{
    width:12px;
    height:12px;
    border-radius:50%;
    display:inline-block;
    background:#28a745;
    box-shadow:0 0 10px rgba(40,167,69,.6);
    margin-right:8px;
    vertical-align:middle;
}

.btn-color{
    border:none;
    border-radius:999px;
    font-weight:700;
    padding:.8rem 1rem;
}

.btn-capture{
    background:var(--cyan);
    color:#000;
}

.btn-capture:hover{
    background:#59d9f5;
    color:#000;
}

.btn-save{
    background:var(--green);
    color:#fff;
}

.btn-save:hover{
    background:#157347;
    color:#fff;
}

.btn-skip{
    background:var(--blue);
    color:#fff;
}

.btn-skip:hover{
    background:#0b5ed7;
    color:#fff;
}

.form-control,
.input-group-text{
    border-radius:14px;
}

.form-control{
    background:#fff;
    color:#111;
    border:1px solid #d9d9d9;
}

.form-control:focus{
    border-color:var(--cyan);
    box-shadow:0 0 0 .2rem rgba(13,202,240,.15);
}

.form-control[readonly]{
    background:#efefef;
    cursor:not-allowed;
}

.input-group-text{
    background:#f7f7f7;
    border:1px solid #d9d9d9;
    color:#111;
}

#capturePreview{
    width:100%;
    display:none;
    margin-top:14px;
    border-radius:16px;
    border:2px solid #d9d9d9;
}

.face-tag{
    position:absolute;
    top:14px;
    left:14px;
    background:rgba(255,255,255,.92);
    color:#000;
    border-radius:999px;
    padding:.45rem .8rem;
    font-size:.85rem;
    font-weight:700;
    z-index:3;
}

.hint-box{
    background:#fafafa;
    border:1px dashed #d5d5d5;
    border-radius:16px;
    padding:12px 14px;
    color:#333;
    font-size:.92rem;
}

.progress{
    height:10px;
    border-radius:999px;
}

.progress-bar{
    border-radius:999px;
}

.canvas-card{
    background:#fff;
}

@media (max-width: 991.98px){
    .mesh-panel{
        aspect-ratio:16/11;
        min-height:320px;
    }
}
</style>
</head>

<body>
<div class="container-fluid page-shell py-3 py-md-4">
    <div class="row g-4 align-items-start">

        <div class="col-12 col-lg-3 order-2 order-lg-1">
            <div class="card card-bio p-3 p-md-4 h-100">
                <h3 class="title-glow mb-2">
                    <i class="bi bi-person-badge"></i> Configurar cuenta
                </h3>
                <div class="subtle mb-3">
                    Complete sus datos, capture su rostro y guarde la cuenta.
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger rounded-4"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success rounded-4"><?= htmlspecialchars($success) ?></div>
                    <a href="seleccion_enlace.php" class="btn btn-primary btn-color w-100 mb-3">
                        <i class="bi bi-arrow-right-circle"></i> Continuar
                    </a>
                <?php endif; ?>

                <form method="POST" id="frm" autocomplete="off">
                    <input type="hidden" name="foto_data" id="foto_data">
                    <input type="hidden" name="face_detected" id="face_detected" value="0">

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">CI</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($ci) ?>" readonly>
                        <div class="form-text">Dato fijo, no editable.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Nombre</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($nombre) ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Teléfono</label>
                        <input
                            type="text"
                            name="telefono"
                            id="telefono"
                            class="form-control"
                            placeholder="Ingrese su teléfono"
                            value="<?= htmlspecialchars($telefono_actual) ?>"
                            required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Nueva contraseña</label>
                        <input
                            type="password"
                            name="nueva_contra"
                            id="nueva_contra"
                            class="form-control"
                            placeholder="Nueva contraseña"
                            required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold text-dark">Confirmar contraseña</label>
                        <input
                            type="password"
                            name="confirmar_contra"
                            id="confirmar_contra"
                            class="form-control"
                            placeholder="Confirmar contraseña"
                            required>
                    </div>

                    <div class="mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-muted">Fuerza de contraseña</small>
                            <small id="strengthText" class="fw-semibold text-dark">Muy débil</small>
                        </div>
                        <div class="progress">
                            <div id="strengthBar" class="progress-bar bg-danger" style="width: 10%"></div>
                        </div>
                        <div id="passwordMatchMessage" class="small mt-2"></div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="button" class="btn btn-color btn-capture" id="captureBtn" disabled>
                            <i class="bi bi-camera-fill"></i> Esperando rostro...
                        </button>

                        <button type="submit" class="btn btn-color btn-save">
                            <i class="bi bi-save"></i> Guardar
                        </button>

                        <a href="seleccion_enlace.php" class="btn btn-color btn-skip">
                            <i class="bi bi-skip-forward-fill"></i> Omitir configuración
                        </a>
                    </div>

                    <img id="capturePreview" alt="Vista previa del rostro capturado">
                </form>
            </div>
        </div>

        <div class="col-12 col-lg-6 order-1 order-lg-2">
            <div class="card card-bio p-3 p-md-4 mb-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h4 class="title-glow mb-1">
                            <i class="bi bi-camera-video-fill"></i> Escaneo facial
                        </h4>
                        <div class="subtle">Vista en tiempo real con detección y malla facial.</div>
                    </div>
                    <span class="badge text-bg-dark rounded-pill px-3 py-2">
                        <span class="status-dot"></span> Activo
                    </span>
                </div>

                <div class="video-wrap">
                    <div class="face-tag" id="faceTag">Sin rostro detectado</div>
                    <video id="video" autoplay muted playsinline></video>
                    <canvas id="overlay"></canvas>
                </div>
            </div>

            <div class="card card-bio p-3 p-md-4">
                <h4 class="title-glow mb-3">
                    <i class="bi bi-graph-up-arrow"></i> Análisis biométrico
                </h4>
                <canvas id="chart1" height="140"></canvas>
            </div>
        </div>

        <div class="col-12 col-lg-3 order-3">
            <div class="card card-bio p-3 p-md-4 h-100">
                <h4 class="title-glow mb-3">
                    <i class="bi bi-diagram-3-fill"></i> Malla facial
                </h4>

                <div class="metric">
                    <h6>Rostros detectados</h6>
                    <div class="value" id="faceCount">0</div>
                </div>

                <div class="metric">
                    <h6>Precisión</h6>
                    <div class="value" id="precision">98%</div>
                </div>

                <div class="metric">
                    <h6>Ángulo facial</h6>
                    <div class="value" id="angle">0°</div>
                </div>

                <div class="metric">
                    <h6>Simetría</h6>
                    <div class="value" id="symmetry">97%</div>
                </div>

                <div class="hint-box mb-3">
                    El botón de captura se activa solo cuando hay un rostro visible.
                </div>

                <div class="mesh-panel">
                    <canvas id="meshCanvas"></canvas>
                </div>
            </div>
        </div>

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script type="module">
import { FaceLandmarker, FilesetResolver } from "https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision/vision_bundle.mjs";
 
const video = document.getElementById('video');
const overlay = document.getElementById('overlay');
const ctx = overlay.getContext('2d');

const meshCanvas = document.getElementById('meshCanvas');
const meshCtx = meshCanvas.getContext('2d');

const faceCount = document.getElementById('faceCount');
const precision = document.getElementById('precision');
const angle = document.getElementById('angle');
const symmetry = document.getElementById('symmetry');
const faceTag = document.getElementById('faceTag');
const faceDetectedInput = document.getElementById('face_detected');
const captureBtn = document.getElementById('captureBtn');
const capturePreview = document.getElementById('capturePreview');
const fotoData = document.getElementById('foto_data');

const nuevaContra = document.getElementById('nueva_contra');
const confirmarContra = document.getElementById('confirmar_contra');
const strengthBar = document.getElementById('strengthBar');
const strengthText = document.getElementById('strengthText');
const passwordMatchMessage = document.getElementById('passwordMatchMessage');

let faceLandmarker = null;
let latestHasFace = false;
let lastVideoTime = -1;
let running = false;
let lastChartUpdate = 0;
function updateStrength(value) {
    let score = 0;
    if (value.length >= 6) score++;
    if (/[A-Z]/.test(value)) score++;
    if (/\d/.test(value)) score++;
    if (/[^A-Za-z0-9]/.test(value)) score++;

    const widths = ['10%', '35%', '60%', '85%', '100%'];
    const colors = ['bg-danger', 'bg-warning', 'bg-info', 'bg-success', 'bg-success'];
    const labels = ['Muy débil', 'Débil', 'Media', 'Fuerte', 'Muy fuerte'];

    const idx = Math.max(0, Math.min(score, 4));
    strengthBar.style.width = widths[idx];
    strengthBar.className = `progress-bar ${colors[idx]}`;
    strengthText.textContent = labels[idx];
}

function updateMatchMessage() {
    const v1 = nuevaContra.value;
    const v2 = confirmarContra.value;

    if (!v1 && !v2) {
        passwordMatchMessage.textContent = '';
        return;
    }

    if (v1 === v2) {
        passwordMatchMessage.textContent = 'Las contraseñas coinciden.';
        passwordMatchMessage.className = 'small mt-2 text-success fw-semibold';
    } else {
        passwordMatchMessage.textContent = 'Las contraseñas no coinciden.';
        passwordMatchMessage.className = 'small mt-2 text-danger fw-semibold';
    }
}

nuevaContra.addEventListener('input', () => {
    updateStrength(nuevaContra.value);
    updateMatchMessage();
});

confirmarContra.addEventListener('input', updateMatchMessage);

function resizeCanvases() {
    overlay.width = video.videoWidth || 1280;
    overlay.height = video.videoHeight || 720;

    const rect = meshCanvas.getBoundingClientRect();
    const pxWidth = Math.max(320, Math.floor(rect.width || 320));
    const pxHeight = Math.max(320, Math.floor(rect.height || 420));

    meshCanvas.width = pxWidth;
    meshCanvas.height = pxHeight;
}

window.addEventListener('resize', () => {
    if (video.videoWidth && video.videoHeight) {
        resizeCanvases();
    }
});

async function createFaceLandmarker() {
    const vision = await FilesetResolver.forVisionTasks(
        "https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@latest/wasm"
    );

    faceLandmarker = await FaceLandmarker.createFromOptions(vision, {
        baseOptions: {
            modelAssetPath: "https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task",
            delegate: "GPU"
        },
        runningMode: "VIDEO",
        numFaces: 1,
        outputFaceBlendshapes: false,
        outputFacialTransformationMatrixes: false
    });
}

async function startCamera() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: "user",
                width: { ideal: 1280 },
                height: { ideal: 720 }
            },
            audio: false
        });

        video.srcObject = stream;

        await video.play();

        resizeCanvases();
        await createFaceLandmarker();

        running = true;
        requestAnimationFrame(renderLoop);
    } catch (err) {
        alert("No se pudo acceder a la cámara.");
        console.error(err);
    }
}

function getBoundingBox(landmarks) {
    const xs = landmarks.map(p => p.x * overlay.width);
    const ys = landmarks.map(p => p.y * overlay.height);

    const minX = Math.max(0, Math.min(...xs));
    const minY = Math.max(0, Math.min(...ys));
    const maxX = Math.min(overlay.width, Math.max(...xs));
    const maxY = Math.min(overlay.height, Math.max(...ys));

    return {
        x: minX,
        y: minY,
        w: maxX - minX,
        h: maxY - minY
    };
}

function drawBoundingBox(box) {
    ctx.strokeStyle = "#000";
    ctx.lineWidth = 3;
    ctx.strokeRect(box.x, box.y, box.w, box.h);

    ctx.strokeStyle = "#0dcaf0";
    ctx.lineWidth = 2;
    ctx.strokeRect(box.x + 3, box.y + 3, box.w - 6, box.h - 6);
}

function drawFaceMesh(landmarks) {
    const pts = landmarks.map(p => ({
        x: p.x * overlay.width,
        y: p.y * overlay.height
    }));

    ctx.save();
    ctx.strokeStyle = "rgba(0, 0, 0, 0.35)";
    ctx.lineWidth = 1;

    for (let i = 0; i < pts.length - 1; i++) {
        if (i % 3 === 0) {
            ctx.beginPath();
            ctx.moveTo(pts[i].x, pts[i].y);
            ctx.lineTo(pts[i + 1].x, pts[i + 1].y);
            ctx.stroke();
        }

        if (i + 8 < pts.length && i % 5 === 0) {
            ctx.beginPath();
            ctx.moveTo(pts[i].x, pts[i].y);
            ctx.lineTo(pts[i + 8].x, pts[i + 8].y);
            ctx.stroke();
        }
    }

    for (let i = 0; i < pts.length; i += 2) {
        ctx.beginPath();
        ctx.fillStyle = "#0dcaf0";
        ctx.arc(pts[i].x, pts[i].y, 1.8, 0, Math.PI * 2);
        ctx.fill();
    }

    ctx.restore();
}

function drawMiniMesh(landmarks) {
    meshCtx.clearRect(0, 0, meshCanvas.width, meshCanvas.height);
    meshCtx.fillStyle = "#000";
    meshCtx.fillRect(0, 0, meshCanvas.width, meshCanvas.height);

    const pts = landmarks.map(p => ({
        x: p.x * meshCanvas.width,
        y: p.y * meshCanvas.height
    }));

    meshCtx.lineWidth = 1;

    for (let i = 0; i < pts.length - 1; i++) {
        if (i % 3 === 0) {
            meshCtx.strokeStyle = "rgba(255,255,255,.25)";
            meshCtx.beginPath();
            meshCtx.moveTo(pts[i].x, pts[i].y);
            meshCtx.lineTo(pts[i + 1].x, pts[i + 1].y);
            meshCtx.stroke();
        }

        if (i + 8 < pts.length && i % 5 === 0) {
            meshCtx.strokeStyle = "rgba(13,202,240,.35)";
            meshCtx.beginPath();
            meshCtx.moveTo(pts[i].x, pts[i].y);
            meshCtx.lineTo(pts[i + 8].x, pts[i + 8].y);
            meshCtx.stroke();
        }
    }

    for (let i = 0; i < pts.length; i += 2) {
        meshCtx.beginPath();
        meshCtx.fillStyle = "#fff";
        meshCtx.arc(pts[i].x, pts[i].y, 1.5, 0, Math.PI * 2);
        meshCtx.fill();
    }
}

function updateMetrics(landmarks) {
    const leftEye = landmarks[33];
    const rightEye = landmarks[263];

    if (leftEye && rightEye) {
        const dx = (rightEye.x - leftEye.x);
        const dy = (rightEye.y - leftEye.y);
        const tilt = Math.atan2(dy, dx) * 180 / Math.PI;
        angle.textContent = `${Math.round(tilt)}°`;

        const symmetryValue = Math.max(90, 100 - Math.min(10, Math.abs(tilt)));
        symmetry.textContent = `${Math.round(symmetryValue)}%`;
        // ===== ACTUALIZAR GRÁFICO EN TIEMPO REAL =====
        biometricChart.data.datasets[0].data = [
            98,
            Math.round(symmetryValue),
            Math.abs(Math.round(tilt))
        ];

         const now = Date.now();

if (now - lastChartUpdate > 120) {
    biometricChart.update('none');
    lastChartUpdate = now;
}
    } else {
        angle.textContent = '0°';
        symmetry.textContent = '97%';
    }

    precision.textContent = '98%';
}

function renderLoop() {
    if (!running || !faceLandmarker) return;

    if (video.currentTime !== lastVideoTime) {
        lastVideoTime = video.currentTime;

        const result = faceLandmarker.detectForVideo(video, performance.now());
        const faces = result.faceLandmarks || [];
        const count = faces.length;

        faceCount.textContent = count;

        latestHasFace = count > 0;
        faceDetectedInput.value = latestHasFace ? '1' : '0';
        captureBtn.disabled = !latestHasFace;
        captureBtn.innerHTML = latestHasFace
            ? '<i class="bi bi-camera-fill"></i> Capturar rostro'
            : '<i class="bi bi-camera-fill"></i> Esperando rostro...';

        ctx.clearRect(0, 0, overlay.width, overlay.height);
        ctx.drawImage(video, 0, 0, overlay.width, overlay.height);

        if (latestHasFace) {
            const landmarks = faces[0];
            const box = getBoundingBox(landmarks);

            faceTag.textContent = 'Rostro detectado';
            drawBoundingBox(box);
            drawFaceMesh(landmarks);
            drawMiniMesh(landmarks);
            updateMetrics(landmarks);
        } else {
            faceTag.textContent = 'Sin rostro detectado';
            meshCtx.clearRect(0, 0, meshCanvas.width, meshCanvas.height);
            meshCtx.fillStyle = "#000";
            meshCtx.fillRect(0, 0, meshCanvas.width, meshCanvas.height);
            precision.textContent = '98%';
            angle.textContent = '0°';
            symmetry.textContent = '97%';
        }
    }

    requestAnimationFrame(renderLoop);
}
captureBtn.addEventListener('click', () => {

    if (!latestHasFace) {
        alert('Debe detectar un rostro antes de capturar.');
        return;
    }

    const result = faceLandmarker.detectForVideo(video, performance.now());

    if (!result.faceLandmarks || result.faceLandmarks.length === 0) {
        alert('No se detectó rostro.');
        return;
    }

    const landmarks = result.faceLandmarks[0];

    const box = getBoundingBox(landmarks);

    const padding = 40;

    const sx = Math.max(0, box.x - padding);
    const sy = Math.max(0, box.y - padding);

    const sw = Math.min(video.videoWidth - sx, box.w + padding * 2);
    const sh = Math.min(video.videoHeight - sy, box.h + padding * 2);

    const capture = document.createElement('canvas');

    capture.width = sw;
    capture.height = sh;

    const cctx = capture.getContext('2d');

    cctx.drawImage(
        video,
        sx,
        sy,
        sw,
        sh,
        0,
        0,
        sw,
        sh
    );

    const img = capture.toDataURL('image/png');

    fotoData.value = img;

    capturePreview.src = img;
    capturePreview.style.display = 'block';

    alert('Rostro capturado correctamente.');
});

document.getElementById('frm').addEventListener('submit', (e) => {
    const pass = nuevaContra.value.trim();
    const confirm = confirmarContra.value.trim();
    const telefono = document.getElementById('telefono').value.trim();

    if (!latestHasFace || faceDetectedInput.value !== '1') {
        e.preventDefault();
        alert('Debe detectar un rostro antes de guardar.');
        return;
    }

    if (!fotoData.value) {
        e.preventDefault();
        alert('Debe capturar el rostro antes de guardar.');
        return;
    }

    if (!pass || !confirm) {
        e.preventDefault();
        alert('Complete la contraseña y su confirmación.');
        return;
    }

    if (pass !== confirm) {
        e.preventDefault();
        alert('Las contraseñas no coinciden.');
        return;
    }

    if (!telefono) {
        e.preventDefault();
        alert('Complete el teléfono.');
        return;
    }
});

function boot() {
    updateStrength('');
    updateMatchMessage();
    startCamera();
}

boot();
// ====== GRÁFICO BIOMÉTRICO ======
const chartCanvas = document.getElementById('chart1');

const biometricChart = new Chart(chartCanvas, {
    type: 'line',
    data: {
        labels: ['Precisión', 'Simetría', 'Ángulo'],
        datasets: [{
            label: 'Datos biométricos',
            data: [98, 97, 0],
            borderColor: '#0dcaf0',
            backgroundColor: 'rgba(13,202,240,0.2)',
            borderWidth: 3,
            tension: 0.4,
            fill: true,
            pointRadius: 5,
            pointBackgroundColor: '#0dcaf0'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                labels: {
                    color: '#000'
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    color: '#000'
                }
            },
            x: {
                ticks: {
                    color: '#000'
                }
            }
        }
    }
});
</script>

</body>
</html>
