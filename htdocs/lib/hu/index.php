<?php
session_start();
header('X-Content-Type-Options: nosniff');

function b64url_encode(string $bin): string {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}
function b64url_decode(string $b64): string {
    $remainder = strlen($b64) % 4;
    if ($remainder) {
        $b64 .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($b64, '-_', '+/')) ?: '';
}

$rpId = $_SERVER['HTTP_HOST'] ?? 'localhost';
$rpName = 'Demo Huella Bootstrap';

// --- API DEMO ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    $action = $_GET['action'];

    if ($action === 'challenge_register') {
        $challenge = random_bytes(32);
        $_SESSION['reg_challenge'] = b64url_encode($challenge);
        $_SESSION['reg_user'] = [
            'id' => b64url_encode(random_bytes(16)),
            'name' => 'demo@local',
            'displayName' => 'Usuario Demo',
        ];

        echo json_encode([
            'challenge' => $_SESSION['reg_challenge'],
            'rp' => ['name' => $rpName, 'id' => $rpId],
            'user' => [
                'id' => $_SESSION['reg_user']['id'],
                'name' => $_SESSION['reg_user']['name'],
                'displayName' => $_SESSION['reg_user']['displayName'],
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],
                ['type' => 'public-key', 'alg' => -257],
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'authenticatorSelection' => [
                'residentKey' => 'preferred',
                'userVerification' => 'required'
            ]
        ]);
        exit;
    }

    if ($action === 'save_register') {
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload) {
            echo json_encode(['ok' => false, 'message' => 'Payload inválido']);
            exit;
        }
        $_SESSION['credential'] = [
            'id' => $payload['id'] ?? '',
            'rawId' => $payload['rawId'] ?? '',
            'type' => $payload['type'] ?? 'public-key',
        ];
        $_SESSION['registered'] = true;
        echo json_encode(['ok' => true, 'message' => 'Huella/passkey registrada en modo demo']);
        exit;
    }

    if ($action === 'challenge_login') {
        if (empty($_SESSION['credential']['id'])) {
            echo json_encode(['ok' => false, 'message' => 'Primero registra una huella/passkey']);
            exit;
        }
        $challenge = random_bytes(32);
        $_SESSION['login_challenge'] = b64url_encode($challenge);
        echo json_encode([
            'challenge' => $_SESSION['login_challenge'],
            'timeout' => 60000,
            'rpId' => $rpId,
            'userVerification' => 'required',
            'allowCredentials' => [[
                'type' => 'public-key',
                'id' => $_SESSION['credential']['rawId'] ?? $_SESSION['credential']['id'],
                'transports' => ['internal']
            ]]
        ]);
        exit;
    }

    if ($action === 'save_login') {
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload) {
            echo json_encode(['ok' => false, 'message' => 'Payload inválido']);
            exit;
        }
        $_SESSION['logged_demo'] = true;
        echo json_encode(['ok' => true, 'message' => 'Huella/passkey aceptada en modo demo']);
        exit;
    }

    echo json_encode(['ok' => false, 'message' => 'Acción no válida']);
    exit;
}

$hasCredential = !empty($_SESSION['credential']['id']);
$loggedDemo = !empty($_SESSION['logged_demo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Demo Huella Bootstrap</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body{background:#0f0f0f;color:#fff;min-height:100vh}
    .glass{background:#000;border:1px solid #2e2e2e;border-radius:24px;box-shadow:0 12px 35px rgba(0,0,0,.45)}
    .title-icon{font-size:72px;line-height:1}
    .muted-small{color:#a9a9a9;font-size:.95rem}
    .btn-pill{border-radius:999px}
    .panel{background:#111;border:1px solid #303030;border-radius:18px}
    .codebox{background:#0a0a0a;border:1px solid #2f2f2f;border-radius:14px;padding:12px;overflow:auto;color:#d9d9d9;font-size:.9rem}
    .badge-soft{background:#1f1f1f;border:1px solid #343434;color:#fff}
  </style>
</head>
<body>
<nav class="navbar navbar-dark bg-black border-bottom border-secondary">
  <div class="container py-2">
    <span class="navbar-brand fw-bold"><i class="bi bi-fingerprint"></i> Demo Huella / Passkey</span>
    <span class="badge badge-soft rounded-pill px-3 py-2">Bootstrap 5</span>
  </div>
</nav>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-7 col-xl-6">
      <div class="card glass">
        <div class="card-body p-4 p-md-5">
          <div class="text-center mb-4">
            <div class="title-icon">🔐</div>
            <h1 class="fw-bold mt-2 mb-1">Acceso con huella</h1>
            <p class="muted-small mb-0">Prueba el lector biométrico del celular con WebAuthn/passkeys.</p>
          </div>

          <div class="alert alert-dark border-secondary text-light mb-4">
            <i class="bi bi-info-circle-fill"></i>
            Esta página es una demo para ver el prompt biométrico. En producción, la respuesta debe validarse en el servidor.
          </div>

          <?php if ($loggedDemo): ?>
            <div class="alert alert-success text-center">
              <i class="bi bi-check-circle-fill"></i> Sesión demo activa.
            </div>
          <?php endif; ?>

          <div class="panel p-3 mb-4">
            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
              <div>
                <div class="fw-semibold">Estado actual</div>
                <div class="text-secondary small"><?php echo $hasCredential ? 'Hay una credencial guardada en esta sesión.' : 'Todavía no hay una credencial registrada.'; ?></div>
              </div>
              <span class="badge <?php echo $hasCredential ? 'text-bg-success' : 'text-bg-secondary'; ?> rounded-pill px-3 py-2">
                <?php echo $hasCredential ? 'Registrado' : 'Pendiente'; ?>
              </span>
            </div>
          </div>

          <div class="d-grid gap-2 mb-4">
            <button class="btn btn-light btn-lg btn-pill fw-semibold" onclick="registrarHuella()">
              <i class="bi bi-person-plus-fill"></i> Registrar huella / passkey
            </button>
            <button class="btn btn-outline-light btn-lg btn-pill fw-semibold" onclick="entrarHuella()">
              <i class="bi bi-shield-lock-fill"></i> Entrar con huella / passkey
            </button>
          </div>

          <div id="msg" class="mb-4"></div>

          <div class="panel p-3">
            <div class="fw-semibold mb-2"><i class="bi bi-terminal"></i> Datos guardados en la sesión</div>
            <div class="codebox"><?php echo htmlspecialchars(json_encode([
                'credential' => $_SESSION['credential'] ?? null,
                'registered' => $_SESSION['registered'] ?? false,
                'logged_demo' => $_SESSION['logged_demo'] ?? false,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function base64urlToBuffer(base64url) {
  const pad = '='.repeat((4 - base64url.length % 4) % 4);
  const base64 = (base64url + pad).replace(/-/g, '+').replace(/_/g, '/');
  const raw = atob(base64);
  const out = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; ++i) out[i] = raw.charCodeAt(i);
  return out;
}
function bufferToBase64url(buf) {
  const bytes = new Uint8Array(buf);
  let bin = '';
  for (const b of bytes) bin += String.fromCharCode(b);
  return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
}
function setMsg(html, type='secondary') {
  document.getElementById('msg').innerHTML = `<div class="alert alert-${type} border-0 mb-0">${html}</div>`;
}

async function registrarHuella() {
  if (!window.PublicKeyCredential) {
    setMsg('Tu navegador no soporta WebAuthn/passkeys.', 'danger');
    return;
  }
  try {
    setMsg('Abriendo el registro biométrico...', 'dark');
    const res = await fetch('?action=challenge_register', {cache:'no-store'});
    const options = await res.json();

    options.challenge = base64urlToBuffer(options.challenge);
    options.user.id = base64urlToBuffer(options.user.id);

    const cred = await navigator.credentials.create({ publicKey: options });
    const payload = {
      id: cred.id,
      rawId: bufferToBase64url(cred.rawId),
      type: cred.type,
      response: {
        attestationObject: cred.response.attestationObject ? bufferToBase64url(cred.response.attestationObject) : null,
        clientDataJSON: cred.response.clientDataJSON ? bufferToBase64url(cred.response.clientDataJSON) : null
      }
    };

    const save = await fetch('?action=save_register', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const result = await save.json();
    localStorage.setItem('demo_credential_id', cred.id);
    setMsg('✅ ' + result.message, 'success');
    setTimeout(() => window.location.reload(), 900);
  } catch (e) {
    setMsg('❌ ' + e.message, 'danger');
  }
}

async function entrarHuella() {
  if (!window.PublicKeyCredential) {
    setMsg('Tu navegador no soporta WebAuthn/passkeys.', 'danger');
    return;
  }
  try {
    setMsg('Esperando huella / passkey...', 'dark');
    const res = await fetch('?action=challenge_login', {cache:'no-store'});
    const options = await res.json();
    if (!options.ok && options.message) {
      setMsg('❌ ' + options.message, 'danger');
      return;
    }

    options.challenge = base64urlToBuffer(options.challenge);
    options.allowCredentials = (options.allowCredentials || []).map(c => ({
      ...c,
      id: base64urlToBuffer(c.id)
    }));

    const assertion = await navigator.credentials.get({ publicKey: options });
    const payload = {
      id: assertion.id,
      rawId: bufferToBase64url(assertion.rawId),
      type: assertion.type,
      response: {
        authenticatorData: assertion.response.authenticatorData ? bufferToBase64url(assertion.response.authenticatorData) : null,
        clientDataJSON: assertion.response.clientDataJSON ? bufferToBase64url(assertion.response.clientDataJSON) : null,
        signature: assertion.response.signature ? bufferToBase64url(assertion.response.signature) : null,
        userHandle: assertion.response.userHandle ? bufferToBase64url(assertion.response.userHandle) : null
      }
    };

    const save = await fetch('?action=save_login', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const result = await save.json();
    setMsg('✅ ' + result.message, 'success');
    setTimeout(() => window.location.reload(), 900);
  } catch (e) {
    setMsg('❌ ' + e.message, 'danger');
  }
}
</script>
</body>
</html>
