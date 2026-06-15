<?php
session_start();
require_once '../TOOLS/conexion.php';
if (isset($_GET['captcha'])) {
    ob_clean();
    $caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $codigo = '';
    for ($i = 0; $i < 6; $i++) {
        $codigo .= $caracteres[random_int(0, strlen($caracteres) - 1)];
    }
    $_SESSION['captcha_code'] = $codigo;
    $ancho = 240; $alto = 65;
    $img = imagecreatetruecolor($ancho, $alto);

    $color_fondo = imagecolorallocate($img, 242, 246, 244); 
    $color_texto = imagecolorallocate($img, 15, 65, 55); 
    $color_ruido = imagecolorallocate($img, 130, 165, 150); 

    imagefill($img, 0, 0, $color_fondo);
    for ($i = 0; $i < 150; $i++) imagesetpixel($img, random_int(0, $ancho-1), random_int(0, $alto-1), $color_ruido);
    for ($i = 0; $i < 6; $i++) imageline($img, random_int(0, $ancho), random_int(0, $alto), random_int(0, $ancho), random_int(0, $alto), $color_ruido);
    $x = 25;
    for ($i = 0; $i < strlen($codigo); $i++) {
        $y = random_int(18, 28);
        imagechar($img, 5, $x, $y, $codigo[$i], $color_texto); // Fuente interna tamaño 5 (el máximo nativo)
        $x += 32; 
    }
    header('Content-Type: image/png');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    imagepng($img); imagedestroy($img); exit;
}

if (isset($_SESSION['rol'])) { header("Location: seleccion_enlace.php"); exit; }
$errorLogin = ''; $errorCaptcha = ''; $ident = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ident   = trim($_POST['identificador'] ?? '');
    $pass    = trim($_POST['contrasena'] ?? '');
    $rol     = trim($_POST['rol'] ?? '');
    $captcha = trim($_POST['captcha'] ?? '');

    if ($ident === '' || $pass === '' || $rol === '') {
        $errorLogin = 'Por favor, rellene todos los campos.';
    } elseif (!isset($_SESSION['captcha_code']) || strcasecmp($captcha, $_SESSION['captcha_code']) !== 0) {
        $errorCaptcha = 'Captcha incorrecto';
    } else {
        $conn = conectarse();
        if (!$conn) die('Error de conexión');

        $stmt = $conn->prepare("SELECT id,nombre,email,contrasena,must_change,rol,foto FROM usuarios WHERE nro_ci=? OR email=? LIMIT 1");
        $stmt->bind_param("ss", $ident, $ident);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || !password_verify($pass, $user['contrasena']) || $rol !== $user['rol']) {
            $errorLogin = 'Credenciales incorrectas o rol no asignado';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nombre']  = $user['nombre'];
            $_SESSION['rol']     = $user['rol'];
            unset($_SESSION['captcha_code']);

            $foto = trim((string)$user['foto']);
            if ((int)$user['must_change'] === 1 || $foto === '' || strtolower($foto) === 'default.png') {
                header("Location: configurar.php");
            } else {
                header("Location: seleccion_enlace.php");
            }
            exit;
        }
        $stmt->close(); $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body{ background:#111; }
        .login-card{ background:#000; border:1px solid #444; border-radius:25px; box-shadow:0 0 25px rgba(255,255,255,.08); }
        .icon-3d{ font-size:75px; color:#fff; text-shadow: 0 2px 0 #999, 0 4px 0 #777, 0 8px 15px rgba(255,255,255,.25); }
        .input-custom{ background:#111; color:#fff; border:1px solid #555; }
        .input-custom:focus{ background:#111; color:#fff; border-color:#fff; box-shadow:none; }
        .role-btn{ border-radius:50px; transition:.3s; }
        .role-btn:hover{ transform:scale(1.03); }
        .btn-login{ background:linear-gradient(145deg,#fff,#d8d8d8); border:none; color:#000; border-radius:50px; transition:.3s; box-shadow:0 8px 20px rgba(255,255,255,.15); }
        .btn-login:hover{ transform:scale(1.03); }
        .captcha-box{ border:1px solid #444; border-radius:15px; background:#161616; padding:18px; }
        .captcha-img { display:block; width: auto ; height: 85px; border-radius:8px; margin:0 auto 12px auto; border:1px solid #ccc; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
        .captcha-label { display: flex; align-items: center; justify-content: center; gap: 6px; font-weight: 600; color: #a0a0a0; margin-bottom: 8px; font-size: 14px; }
        .label-requerido { color: #ef4444; }
    </style>
</head>
<body class="text-white d-flex flex-column min-vh-100">

<nav class="navbar navbar-dark bg-black border-bottom border-secondary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#"><i class="bi bi-shield-lock-fill"></i> Login</a>
        <div class="d-flex gap-2">
            <a href="registro.php" class="btn btn-outline-light rounded-pill"><i class="bi bi-person-plus-fill"></i> Registro</a>
            <a href="javascript:history.back()" class="btn btn-outline-secondary rounded-pill"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
    </div>
</nav>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card login-card">
                <div class="card-body p-5">

                    <div class="text-center mb-4">
                        <i class="bi bi-person-circle icon-3d"></i>
                        <h2 class="fw-bold mt-3">Iniciar Sesión</h2>
                        <p class="text-secondary">Ingrese sus credenciales</p>
                    </div>

                    <?php if($errorLogin): ?>
                    <div class="alert alert-dark border-secondary text-danger py-2 text-center">
                        <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($errorLogin, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="input-group mb-3">
                            <span class="input-group-text bg-dark text-white border-secondary"><i class="bi bi-envelope-fill"></i></span>
                            <input type="text" name="identificador" class="form-control input-custom" placeholder="CI o correo" value="<?= htmlspecialchars($ident, ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>

                        <div class="input-group mb-3">
                            <span class="input-group-text bg-dark text-white border-secondary"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" name="contrasena" class="form-control input-custom" placeholder="Contraseña" required>
                        </div>

                        <div class="mb-4">
                            <label class="text-secondary mb-2">Seleccione su rol</label>
                            <div class="d-grid gap-2">
                                <input type="radio" class="btn-check" name="rol" id="r1" value="estudiante">
                                <label class="btn btn-outline-light role-btn" for="r1"><i class="bi bi-mortarboard-fill"></i> Estudiante</label>

                                <input type="radio" class="btn-check" name="rol" id="r2" value="docente">
                                <label class="btn btn-outline-light role-btn" for="r2"><i class="bi bi-person-video3"></i> Docente</label>

                                <input type="radio" class="btn-check" name="rol" id="r3" value="superusuario">
                                <label class="btn btn-outline-light role-btn" for="r3"><i class="bi bi-shield-fill-check"></i> Superusuario</label>
                            </div>
                        </div>

                        <div class="mb-4 captcha-box text-center">
                            <img src="?captcha=1&rand=<?= time() ?>" id="captcha-img" class="captcha-img" alt="Código Captcha">

                            <div class="d-flex justify-content-center mb-3">
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="refrescarCaptcha()">
                                    <i class="bi bi-arrow-clockwise"></i> Generar otro captcha
                                </button>
                            </div>

                            <div class="captcha-label">
                                <i class="bi bi-shield-check text-info"></i> Captcha <span class="label-requerido">*</span>
                            </div>

                            <input type="text" name="captcha" class="form-control input-custom text-center" placeholder="Introduzca los caracteres tal como se muestran" autocomplete="off" required>
                        </div>

                        <?php if($errorCaptcha): ?>
                        <div class="alert alert-dark border-secondary text-danger py-2 text-center">
                            <i class="bi bi-shield-exclamation"></i> <?= htmlspecialchars($errorCaptcha, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-login w-100 fw-semibold py-2">
                            <i class="bi bi-box-arrow-in-right"></i> Ingresar
                        </button>
                     
                    </form>

                    <div class="text-center mt-4">
                        <a href="registro.php" class="text-decoration-none text-secondary"><i class="bi bi-person-plus-fill"></i> Crear cuenta</a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
<script>
function refrescarCaptcha() {
    document.getElementById('captcha-img').src = '?captcha=1&rand=' + Math.random();
}
</script>
</body>
</html>