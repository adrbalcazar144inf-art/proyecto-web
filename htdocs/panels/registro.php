<?php
session_start();

require_once '../TOOLS/conexion.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../lib/PHPMailer/src/Exception.php';
require '../lib/PHPMailer/src/PHPMailer.php';
require '../lib/PHPMailer/src/SMTP.php';

$conn = conectarse();

if (!$conn) {
    die("Error de conexión");
}

$msg = '';
$ok = false;
$credencialesTxt = '';

function generarPassword($longitud = 10)
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    return substr(str_shuffle($chars), 0, $longitud);
}

function limpiarCorreo($correo)
{
    $correo = trim(strtolower($correo));

    if (strpos($correo, '@') === false) {
        $correo .= '@gmail.com';
    }

    return $correo;
}

function enviarCorreo($correo, $nombre, $password, $rol)
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'the9999ban@gmail.com';
        $mail->Password = 'nuid isak kksd rqro';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom('the9999ban@gmail.com', 'Sistema Académico');
        $mail->addAddress($correo, $nombre);
        $mail->isHTML(true);
        $mail->Subject = 'Registro exitoso';

        $nombreEsc = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
        $correoEsc = htmlspecialchars($correo, ENT_QUOTES, 'UTF-8');
        $passwordEsc = htmlspecialchars($password, ENT_QUOTES, 'UTF-8');
        $rolEsc = htmlspecialchars($rol, ENT_QUOTES, 'UTF-8');

        $mail->Body = "
        <div style='background:#f4f6f9;padding:50px;font-family:Arial,sans-serif;'>
            <div style='max-width:650px;margin:auto;background:#fff;border-radius:18px;overflow:hidden;'>
                <div style='background:#212529;padding:35px;text-align:center;'>
                    <h1 style='color:white;margin:0;'>Sistema Académico</h1>
                    <p style='color:#ced4da;margin-top:10px;'>Registro completado correctamente</p>
                </div>

                <div style='padding:40px;color:#212529;'>
                    <h2>Hola {$nombreEsc}</h2>

                    <p style='line-height:1.8;color:#555;'>
                        Tu cuenta fue creada exitosamente. Ya puedes iniciar sesión.
                    </p>

                    <div style='background:#f8f9fa;border:1px solid #dee2e6;border-radius:14px;padding:25px;margin-top:25px;'>
                        <p><strong>Correo:</strong> {$correoEsc}</p>
                        <p><strong>Contraseña:</strong> {$passwordEsc}</p>
                        <p><strong>Rol:</strong> {$rolEsc}</p>
                    </div>

                    <div style='margin-top:25px;padding:18px;background:#fff3cd;border:1px solid #ffe69c;border-radius:12px;color:#664d03;'>
                        <strong>Recomendación:</strong> cambia tu contraseña al iniciar sesión por primera vez por seguridad.
                    </div>
                </div>

                <div style='background:#212529;text-align:center;padding:20px;color:#adb5bd;font-size:13px;'>
                    © " . date('Y') . " Sistema Académico
                </div>
            </div>
        </div>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $ci = trim($_POST['ci'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $paterno = trim($_POST['paterno'] ?? '');
    $materno = trim($_POST['materno'] ?? '');
    $correo = limpiarCorreo($_POST['correo'] ?? '');
    $rol = trim($_POST['rol'] ?? '');

    if (
        $ci === '' ||
        $nombre === '' ||
        $paterno === '' ||
        $materno === '' ||
        $correo === '' ||
        $rol === ''
    ) {
        $msg = 'Complete todos los campos';
    } else {

        $verificar = $conn->prepare("
            SELECT id
            FROM usuarios
            WHERE nro_ci = ?
               OR email = ?
        ");

        $verificar->bind_param("ss", $ci, $correo);
        $verificar->execute();
        $resultado = $verificar->get_result();

        if ($resultado->num_rows > 0) {
            $msg = 'El usuario ya existe';
        } else {
            $passwordPlano = generarPassword();
            $hash = password_hash($passwordPlano, PASSWORD_DEFAULT);
            $foto = 'default.png';
            $must = 1;

            $insertar = $conn->prepare("
                INSERT INTO usuarios
                (
                    nro_ci,
                    nombre,
                    paterno,
                    materno,
                    email,
                    contrasena,
                    foto,
                    must_change,
                    rol
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $insertar->bind_param(
                "sssssssis",
                $ci,
                $nombre,
                $paterno,
                $materno,
                $correo,
                $hash,
                $foto,
                $must,
                $rol
            );

            if ($insertar->execute()) {

                // Enviar correo
                enviarCorreo(
                    $correo,
                    $nombre,
                    $passwordPlano,
                    ucfirst($rol)
                );

                // Texto para descarga automática
                $credencialesTxt =
                    "SISTEMA ACADÉMICO\n" .
                    "=================================\n" .
                    "Registro completado correctamente\n\n" .
                    "Datos de acceso:\n" .
                    "Correo: {$correo}\n" .
                    "Contraseña: {$passwordPlano}\n" .
                    "Rol: " . ucfirst($rol) . "\n\n" .
                    "Recomendación:\n" .
                    "Cambie su contraseña al iniciar sesión por primera vez.\n";

                $ok = true;
                $msg = 'Registro completado correctamente';
            } else {
                $msg = 'Error al registrar usuario';
            }

            $insertar->close();
        }

        $verificar->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registro</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body{
            background:#111;
        }

        .card-custom{
            background:#000;
            border:1px solid #444;
            border-radius:25px;
            box-shadow:0 0 25px rgba(255,255,255,.08);
        }

        .icon-3d{
            font-size:75px;
            color:#fff;
            text-shadow:
                0 2px 0 #999,
                0 4px 0 #777,
                0 8px 15px rgba(255,255,255,.25);
        }

        .input-custom{
            background:#111 !important;
            color:#fff !important;
            border:1px solid #555 !important;
        }

        .input-custom::placeholder{
            color:#bdbdbd !important;
            opacity:1;
        }

        .input-custom:focus{
            background:#111 !important;
            color:#fff !important;
            border-color:#fff !important;
            box-shadow:none !important;
        }

        .input-custom:-webkit-autofill,
        .input-custom:-webkit-autofill:hover,
        .input-custom:-webkit-autofill:focus{
            -webkit-text-fill-color:#fff;
            -webkit-box-shadow:0 0 0px 1000px #111 inset;
            transition:background-color 5000s ease-in-out 0s;
        }

        .input-custom option{
            background:#111;
            color:#fff;
        }

        .btn-custom{
            background:linear-gradient(145deg,#fff,#d8d8d8);
            border:none;
            color:#000;
            border-radius:50px;
            transition:.3s;
            box-shadow:0 8px 20px rgba(255,255,255,.15);
        }

        .btn-custom:hover{
            transform:scale(1.03);
        }
    </style>
</head>

<body class="text-white d-flex flex-column min-vh-100">

<nav class="navbar navbar-dark bg-black border-bottom border-secondary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">
            <i class="bi bi-mortarboard-fill"></i>
            Registro
        </a>

        <a href="javascript:history.back()" class="btn btn-outline-light rounded-pill">
            <i class="bi bi-arrow-left"></i>
            Volver
        </a>
    </div>
</nav>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card card-custom">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="bi bi-person-vcard-fill icon-3d"></i>
                        <h2 class="fw-bold mt-3">Registro</h2>
                        <p class="text-secondary">Crea tu cuenta para ingresar</p>
                    </div>

                    <?php if ($msg): ?>
                    <div class="alert <?= $ok ? 'alert-success' : 'alert-dark' ?> border-secondary rounded-4">
                        <i class="bi <?= $ok ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' ?>"></i>
                        <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" autocomplete="off">
                        <div class="input-group mb-3">
                            <span class="input-group-text bg-dark text-white border-secondary">
                                <i class="bi bi-credit-card-2-front-fill"></i>
                            </span>
                            <input
                                type="text"
                                name="ci"
                                class="form-control input-custom"
                                placeholder="Carnet de identidad"
                                required
                            >
                        </div>

                        <div class="input-group mb-3">
                            <span class="input-group-text bg-dark text-white border-secondary">
                                <i class="bi bi-person-fill"></i>
                            </span>
                            <input
                                type="text"
                                name="nombre"
                                class="form-control input-custom"
                                placeholder="Nombre"
                                required
                            >
                        </div>

                        <div class="input-group mb-3">
                            <span class="input-group-text bg-dark text-white border-secondary">
                                <i class="bi bi-person-badge-fill"></i>
                            </span>
                            <input
                                type="text"
                                name="paterno"
                                class="form-control input-custom"
                                placeholder="Apellido paterno"
                                required
                            >
                        </div>

                        <div class="input-group mb-3">
                            <span class="input-group-text bg-dark text-white border-secondary">
                                <i class="bi bi-person-badge"></i>
                            </span>
                            <input
                                type="text"
                                name="materno"
                                class="form-control input-custom"
                                placeholder="Apellido materno"
                                required
                            >
                        </div>

                        <div class="input-group mb-3">
                            <span class="input-group-text bg-dark text-white border-secondary">
                                <i class="bi bi-envelope-fill"></i>
                            </span>
                            <input
                                type="text"
                                name="correo"
                                class="form-control input-custom"
                                placeholder="Correo electrónico"
                                required
                            >
                        </div>

                        <div class="input-group mb-4">
                            <span class="input-group-text bg-dark text-white border-secondary">
                                <i class="bi bi-person-workspace"></i>
                            </span>
                            <select
                                name="rol"
                                class="form-select input-custom"
                                required
                            >
                                <option value="" selected disabled>
                                    Seleccione un rol
                                </option>
                                <option value="estudiante">
                                    Estudiante
                                </option>
                                <option value="docente">
                                    Docente
                                </option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-custom w-100 py-2 fw-semibold">
                            <i class="bi bi-person-plus-fill"></i>
                            Crear cuenta
                        </button>
                    </form>

                    <?php if ($ok): ?>
                    <a href="login.php" class="btn btn-outline-light w-100 rounded-pill mt-3">
                        <i class="bi bi-box-arrow-in-right"></i>
                        Ir al login
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="bg-black border-top border-secondary py-3 mt-5">
    <div class="container text-center text-secondary">
        <i class="bi bi-shield-lock-fill"></i>
         © <?= date('Y') ?>
    </div>
</footer>

<?php if ($ok && !empty($credencialesTxt)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const contenido = <?= json_encode($credencialesTxt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const blob = new Blob([contenido], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);

    const a = document.createElement('a');
    a.href = url;
    a.download = 'credenciales_acceso.txt';
    document.body.appendChild(a);
    a.click();
    a.remove();

    setTimeout(() => URL.revokeObjectURL(url), 1000);
});
</script>
<?php endif; ?>

</body>
</html>