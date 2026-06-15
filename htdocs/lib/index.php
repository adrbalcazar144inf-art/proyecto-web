<?php
session_start();

const STORAGE_FILE = __DIR__ . '/storage/passkeys.json';

function load_storage(): array {
    if (!file_exists(STORAGE_FILE)) return [];
    $raw = file_get_contents(STORAGE_FILE);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function save_storage(array $data): void {
    if (!is_dir(dirname(STORAGE_FILE))) {
        mkdir(dirname(STORAGE_FILE), 0775, true);
    }
    file_put_contents(STORAGE_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function b64url_encode(string $bin): string {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function b64url_decode(string $txt): string {
    $remainder = strlen($txt) % 4;
    if ($remainder) {
        $txt .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($txt, '-_', '+/')) ?: '';
}

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function safe_string(string $s): string {
    return htmlspecialchars(trim($s), ENT_QUOTES, 'UTF-8');
}

$storage = load_storage();

if (!isset($_SESSION['challenge_register'])) {
    $_SESSION['challenge_register'] = b64url_encode(random_bytes(32));
}
if (!isset($_SESSION['challenge_login'])) {
    $_SESSION['challenge_login'] = b64url_encode(random_bytes(32));
}

$action = $_GET['action'] ?? '';

if ($action === 'options-register') {
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['correo'] ?? '');

    if ($nombre === '' || $correo === '') {
        json_response(['ok' => false, 'message' => 'Completa nombre y correo.'], 422);
    }

    $_SESSION['reg_nombre'] = $nombre;
    $_SESSION['reg_correo'] = $correo;
    $_SESSION['challenge_register'] = b64url_encode(random_bytes(32));

    $options = [
        'challenge' => $_SESSION['challenge_register'],
        'rp' => [
            'name' => 'Sistema Académico',
            'id' => $_SERVER['HTTP_HOST'],
        ],
        'user' => [
            'id' => b64url_encode(random_bytes(16)),
            'name' => $correo,
            'displayName' => $nombre,
        ],
        'pubKeyCredParams' => [
            ['type' => 'public-key', 'alg' => -7],
            ['type' => 'public-key', 'alg' => -257],
        ],
        'timeout' => 600000,
        'attestation' => 'none',
        'authenticatorSelection' => [
            'authenticatorAttachment' => 'platform',
            'residentKey' => 'preferred',
            'userVerification' => 'required',
        ],
        'excludeCredentials' => [],
        'extensions' => new stdClass(),
    ];

    json_response(['ok' => true, 'options' => $options]);
}

if ($action === 'register') {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        json_response(['ok' => false, 'message' => 'JSON inválido.'], 400);
    }

    $name = $_SESSION['reg_nombre'] ?? 'Usuario';
    $email = $_SESSION['reg_correo'] ?? '';

    $stored = [
        'id' => $payload['id'] ?? '',
        'rawId' => $payload['rawId'] ?? '',
        'type' => $payload['type'] ?? 'public-key',
        'name' => $name,
        'email' => $email,
        'createdAt' => date('c'),
        'response' => $payload['response'] ?? [],
    ];

    if ($stored['id'] === '' || $stored['rawId'] === '') {
        json_response(['ok' => false, 'message' => 'No llegó la credencial completa.'], 400);
    }

    $storage[] = $stored;
    save_storage($storage);

    unset($_SESSION['challenge_register']);
    json_response(['ok' => true, 'message' => 'Credencial guardada en storage/passkeys.json']);
}

if ($action === 'options-login') {
    if (empty($storage)) {
        json_response(['ok' => false, 'message' => 'Todavía no hay credenciales registradas.'], 422);
    }

    $_SESSION['challenge_login'] = b64url_encode(random_bytes(32));

    $allow = [];
    foreach ($storage as $cred) {
        if (!empty($cred['rawId'])) {
            $allow[] = [
                'type' => 'public-key',
                'id' => $cred['rawId'],
                'transports' => ['internal', 'hybrid', 'usb', 'nfc', 'ble']
            ];
        }
    }

    json_response([
        'ok' => true,
        'options' => [
            'challenge' => $_SESSION['challenge_login'],
            'timeout' => 600000,
            'rpId' => $_SERVER['HTTP_HOST'],
            'userVerification' => 'required',
            'allowCredentials' => $allow,
        ]
    ]);
}

if ($action === 'login') {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        json_response(['ok' => false, 'message' => 'JSON inválido.'], 400);
    }

    $credId = $payload['id'] ?? '';
    $found = null;
    foreach ($storage as $cred) {
        if (($cred['id'] ?? '') === $credId) {
            $found = $cred;
            break;
        }
    }

    if (!$found) {
        json_response(['ok' => false, 'message' => 'No existe esa credencial en tu sistema.'], 401);
    }

    $_SESSION['autenticado'] = true;
    $_SESSION['usuario'] = $found['name'] ?? 'Usuario';
    json_response(['ok' => true, 'message' => 'Huella/passkey aceptada. Acceso permitido.']);
}

$last = array_slice($storage, -5);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Passkey / Huella - Demo Bootstrap</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body{background:#0b0b0b;color:#fff}
        .panel{background:#000;border:1px solid #2f2f2f;border-radius:24px;box-shadow:0 20px 60px rgba(0,0,0,.45)}
        .muted{color:#a8a8a8}
        .soft{background:#111;border:1px solid #2b2b2b;color:#fff}
        .soft:focus{background:#111;color:#fff;border-color:#fff;box-shadow:none}
        .btn-pill{border-radius:999px}
        .big-icon{font-size:72px;text-shadow:0 0 18px rgba(255,255,255,.18)}
        code{color:#d7e7ff}
        .box{border:1px solid #272727;border-radius:18px;background:#0f0f0f}
    </style>
</head>
<body>
<nav class="navbar navbar-dark border-bottom border-secondary-subtle" style="background:#000">
    <div class="container py-1">
        <span class="navbar-brand fw-bold"><i class="bi bi-fingerprint"></i> Passkey / Huella</span>
        <span class="text-secondary small">Demo Bootstrap</span>
    </div>
</nav>

<div class="container py-5">
    <div class="row justify-content-center g-4">
        <div class="col-lg-8">
            <div class="panel p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="big-icon">🔐</div>
                    <h1 class="fw-bold mb-2">Registrar y entrar con huella</h1>
                    <p class="muted mb-0">El celular pedirá biometría y tu sistema guardará la credencial, no la huella.</p>
                </div>

                <div id="msg"></div>

                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="box p-4 h-100">
                            <h4 class="fw-semibold mb-3"><i class="bi bi-person-plus-fill"></i> Registrar passkey</h4>
                            <div class="mb-3">
                                <label class="form-label muted">Nombre</label>
                                <input id="nombre" class="form-control soft" placeholder="Ej. Milenka Roque">
                            </div>
                            <div class="mb-3">
                                <label class="form-label muted">Correo</label>
                                <input id="correo" type="email" class="form-control soft" placeholder="correo@ejemplo.com">
                            </div>
                            <button class="btn btn-light btn-pill w-100 fw-semibold" onclick="registrar()">
                                <i class="bi bi-phone"></i> Crear huella / passkey
                            </button>
                            <small class="d-block muted mt-3">
                                En el móvil aparecerá el lector biométrico o Face ID. La clave privada queda en el dispositivo.
                            </small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="box p-4 h-100">
                            <h4 class="fw-semibold mb-3"><i class="bi bi-box-arrow-in-right"></i> Entrar con passkey</h4>
                            <p class="muted">Usa una credencial ya guardada en <code>storage/passkeys.json</code>.</p>
                            <button class="btn btn-outline-light btn-pill w-100 fw-semibold" onclick="entrar()">
                                <i class="bi bi-fingerprint"></i> Usar huella
                            </button>
                            <div class="mt-3 small muted">
                                Si no hay credenciales, primero registra una.
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="border-secondary my-4">

                <div class="row g-3">
                    <div class="col-md-7">
                        <div class="box p-3">
                            <div class="fw-semibold mb-2">Credenciales guardadas</div>
                            <?php if (empty($last)): ?>
                                <div class="muted">Aún no hay registros.</div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach (array_reverse($last) as $row): ?>
                                        <div class="list-group-item bg-transparent text-white border-secondary-subtle px-0">
                                            <div class="d-flex justify-content-between gap-3">
                                                <div>
                                                    <div class="fw-semibold"><?= safe_string($row['name'] ?? 'Usuario') ?></div>
                                                    <div class="small muted"><?= safe_string($row['email'] ?? '') ?></div>
                                                </div>
                                                <div class="small text-secondary"><?= safe_string(substr((string)($row['createdAt'] ?? ''), 0, 19)) ?></div>
                                            </div>
                                            <div class="small muted text-break">ID: <?= safe_string($row['id'] ?? '') ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="box p-3">
                            <div class="fw-semibold mb-2">Qué guarda este demo</div>
                            <div class="small muted">
                                Guarda el identificador de la credencial y la respuesta del navegador en un archivo JSON.
                                Eso sirve para pruebas visuales y de flujo, pero la validación criptográfica completa debe hacerse con tu librería en producción.
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function b64urlToBuf(v){
    const pad = '='.repeat((4 - v.length % 4) % 4);
    const base64 = (v + pad).replace(/-/g,'+').replace(/_/g,'/');
    const str = atob(base64);
    const buf = new Uint8Array(str.length);
    for (let i=0;i<str.length;i++) buf[i] = str.charCodeAt(i);
    return buf;
}
function bufToB64url(buf){
    let binary = '';
    const bytes = new Uint8Array(buf);
    for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}
function show(type, text){
    document.getElementById('msg').innerHTML = `
        <div class="alert alert-${type} border-0 shadow-sm">${text}</div>
    `;
}
async function registrar(){
    const nombre = document.getElementById('nombre').value.trim();
    const correo = document.getElementById('correo').value.trim();
    if(!nombre || !correo){ show('warning','Completa nombre y correo.'); return; }

    try{
        show('secondary','Preparando registro...');
        const form = new FormData();
        form.append('nombre', nombre);
        form.append('correo', correo);

        const res = await fetch('?action=options-register', { method:'POST', body: form });
        const data = await res.json();
        if(!data.ok) throw new Error(data.message || 'No se pudo crear opciones');

        const pk = data.options;
        pk.challenge = b64urlToBuf(pk.challenge);
        pk.user.id = b64urlToBuf(pk.user.id);
        pk.excludeCredentials = (pk.excludeCredentials || []).map(c => ({...c, id: b64urlToBuf(c.id)}));

        const cred = await navigator.credentials.create({ publicKey: pk });
        if(!cred) throw new Error('El navegador canceló el registro.');

        const payload = {
            id: cred.id,
            rawId: bufToB64url(cred.rawId),
            type: cred.type,
            response: {
                clientDataJSON: bufToB64url(cred.response.clientDataJSON),
                attestationObject: bufToB64url(cred.response.attestationObject)
            }
        };

        const save = await fetch('?action=register', {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify(payload)
        });
        const saved = await save.json();
        if(!saved.ok) throw new Error(saved.message || 'No se pudo guardar');

        show('success', '✅ Credencial registrada y guardada en el archivo JSON.');
        setTimeout(() => location.reload(), 900);
    }catch(e){
        show('danger', '❌ ' + e.message);
    }
}
async function entrar(){
    try{
        show('secondary','Esperando biometría...');
        const res = await fetch('?action=options-login', { method:'POST' });
        const data = await res.json();
        if(!data.ok) throw new Error(data.message || 'No hay credenciales');

        const pk = data.options;
        pk.challenge = b64urlToBuf(pk.challenge);
        pk.allowCredentials = (pk.allowCredentials || []).map(c => ({...c, id: b64urlToBuf(c.id)}));

        const assertion = await navigator.credentials.get({ publicKey: pk });
        if(!assertion) throw new Error('Login cancelado');

        const payload = {
            id: assertion.id,
            rawId: bufToB64url(assertion.rawId),
            type: assertion.type,
            response: {
                clientDataJSON: bufToB64url(assertion.response.clientDataJSON),
                authenticatorData: bufToB64url(assertion.response.authenticatorData),
                signature: bufToB64url(assertion.response.signature),
                userHandle: assertion.response.userHandle ? bufToB64url(assertion.response.userHandle) : null
            }
        };

        const verify = await fetch('?action=login', {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify(payload)
        });
        const result = await verify.json();
        if(!result.ok) throw new Error(result.message || 'No pasó la validación');

        show('success', '✅ ' + result.message);
    }catch(e){
        show('danger', '❌ ' + e.message);
    }
}
</script>
</body>
</html>
