<?php
require __DIR__ . '/../lib/twilio-php-main/src/Twilio/autoload.php';
use Twilio\Rest\Client;

/* Configuración Twilio */
$cfg = [
  'sid'   => 'TU_SID_AQUI',
  'token' => 'TU_TOKEN_AQUI',
  'from'  => '+12566745076', // Número Twilio
  'to'    => '+59174279761'  // Destino
];

$resultado = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $mensaje = trim($_POST['message'] ?? '');
  if ($mensaje !== '') {
    try {
      $twilio = new Client($cfg['sid'], $cfg['token']);
      $sms = $twilio->messages->create($cfg['to'], [
        'from' => $cfg['from'],
        'body' => $mensaje
      ]);
      $resultado = "<div class='alert alert-success'>✅ Mensaje enviado. SID: {$sms->sid}</div>";
    } catch (Exception $e) {
      $resultado = "<div class='alert alert-danger'>❌ Error: {$e->getMessage()}</div>";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Enviar SMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body{background:#000;color:#0ff;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:'Segoe UI',sans-serif;margin:0}
    .card{background:#111;border-radius:1rem;padding:2rem;width:100%;max-width:420px;box-shadow:0 0 25px rgba(0,255,255,.4)}
    h3{color:#0ff;text-align:center;margin-bottom:1.5rem;text-shadow:0 0 10px #0ff}
    .form-control{background:0 0;border:2px solid #0ff;color:#0ff}
    .form-control::placeholder{color:#5ff}
    .form-control:focus{background:0 0;color:#aff;border-color:#0ff;box-shadow:0 0 12px #0ff}
    .btn-primary{background:0 0;border:2px solid #0ff;color:#0ff;width:100%;padding:.6rem;font-weight:700;transition:.3s}
    .btn-primary:hover{background:#0ff;color:#000;box-shadow:0 0 20px #0ff}
    .alert{border-radius:.6rem;font-size:.95rem;background:#111;border:1px solid #0ff;color:#0ff;box-shadow:0 0 15px rgba(0,255,255,.3)}
    label{color:#0ff}
  </style>
</head>
<body>
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-6">
        <div class="card">
          <div class="card-body">
            <h3><i class="bi bi-chat-dots"></i> Enviar SMS</h3>
            <?= $resultado ?>
            <form method="post" autocomplete="off">
              <div class="mb-3">
                <label for="message" class="form-label">Mensaje</label>
                <textarea class="form-control" id="message" name="message" rows="4" required placeholder="Escribe tu mensaje aquí...  ponga sus datos ci o correo para solucionar el problema que posea"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
              </div>
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-send-fill"></i> Enviar
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
