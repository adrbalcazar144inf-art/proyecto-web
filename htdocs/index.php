<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title>Acceso</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
}

body{
min-height:100vh;
font-family:Arial;
overflow:hidden;
background:
linear-gradient(135deg,#0f172a,#111827,#000);
display:flex;
align-items:center;
justify-content:center;
position:relative;
color:#fff;
}

/* FONDO */

body::before{
content:"";
position:absolute;
width:200%;
height:200%;
background-image:
linear-gradient(rgba(255,255,255,.03) 1px,transparent 1px),
linear-gradient(90deg,rgba(255,255,255,.03) 1px,transparent 1px);
background-size:45px 45px;
animation:grid 15s linear infinite;
}

.circle1,.circle2{
position:absolute;
border-radius:50%;
filter:blur(120px);
}

.circle1{
width:350px;
height:350px;
background:#00ffff33;
top:-100px;
left:-100px;
animation:float1 8s infinite ease-in-out;
}

.circle2{
width:400px;
height:400px;
background:#ff00ff22;
bottom:-120px;
right:-120px;
animation:float2 10s infinite ease-in-out;
}

/* CONTENIDO */

.container-box{
width:95%;
max-width:1100px;
display:grid;
grid-template-columns:1fr 1fr;
gap:30px;
position:relative;
z-index:10;
}

/* PANEL IZQUIERDO */

.left{
padding:50px;
border-radius:35px;
background:rgba(255,255,255,.05);
backdrop-filter:blur(14px);
border:1px solid rgba(255,255,255,.08);
box-shadow:0 15px 50px rgba(0,0,0,.6);
animation:card 5s infinite ease-in-out;
}

.logo{
font-size:90px;
margin-bottom:20px;
display:inline-block;
background:linear-gradient(45deg,#00ffff,#00ff88);
-webkit-background-clip:text;
-webkit-text-fill-color:transparent;
animation:pulse 3s infinite;
}

.left h1{
font-size:55px;
font-weight:900;
margin-bottom:15px;
}

.left p{
color:#aaa;
line-height:1.7;
margin-bottom:30px;
}

.features{
display:flex;
flex-direction:column;
gap:15px;
}

.feature{
padding:18px;
border-radius:20px;
background:rgba(255,255,255,.04);
border:1px solid rgba(255,255,255,.05);
transition:.3s;
}

.feature:hover{
transform:translateX(10px);
background:rgba(255,255,255,.08);
}

.feature i{
margin-right:10px;
color:#00ffff;
}

/* PANEL DERECHO */

.right{
padding:45px;
border-radius:35px;
background:rgba(0,0,0,.45);
backdrop-filter:blur(15px);
border:1px solid rgba(255,255,255,.08);
box-shadow:0 15px 50px rgba(0,0,0,.6);
animation:card2 6s infinite ease-in-out;
}

.right h2{
font-size:38px;
font-weight:900;
margin-bottom:10px;
}

.right p{
color:#aaa;
margin-bottom:25px;
}

.btn-main{
display:block;
padding:16px;
border-radius:18px;
text-decoration:none;
font-weight:bold;
margin-top:15px;
transition:.3s;
text-align:center;
}

.login{
background:linear-gradient(45deg,#00ffff,#00ff88);
color:#000;
}

.register{
border:2px solid #00ffff;
color:#fff;
}

.btn-main:hover{
transform:scale(1.03);
}

/* BOTONES SOPORTE */

.actions{
display:grid;
grid-template-columns:1fr 1fr;
gap:15px;
margin-top:30px;
}

.action-btn{
padding:18px;
border:none;
border-radius:20px;
font-weight:bold;
transition:.3s;
background:#111827;
color:#fff;
border:1px solid rgba(255,255,255,.08);
}

.action-btn:hover{
transform:translateY(-5px);
background:#00ffff;
color:#000;
}

/* MODAL */

.modal-content{
background:#0f172a;
border-radius:25px;
border:1px solid rgba(255,255,255,.08);
color:#fff;
}

.modal-header,
.modal-footer{
border:none;
}

.modal-body a{
display:block;
padding:15px;
border-radius:15px;
text-align:center;
text-decoration:none;
font-weight:bold;
background:linear-gradient(45deg,#00ffff,#00ff88);
color:#000;
margin-top:20px;
}

/* ANIMACIONES */

@keyframes grid{
0%{
transform:translate(0,0);
}
100%{
transform:translate(-45px,-45px);
}
}

@keyframes float1{
0%,100%{
transform:translateY(0);
}
50%{
transform:translateY(30px);
}
}

@keyframes float2{
0%,100%{
transform:translateY(0);
}
50%{
transform:translateY(-30px);
}
}

@keyframes pulse{
0%,100%{
transform:scale(1);
}
50%{
transform:scale(1.08);
}
}

@keyframes card{
0%,100%{
transform:translateY(0);
}
50%{
transform:translateY(-10px);
}
}

@keyframes card2{
0%,100%{
transform:translateY(0);
}
50%{
transform:translateY(10px);
}
}

/* RESPONSIVE */

@media(max-width:900px){

.container-box{
grid-template-columns:1fr;
}

.left h1{
font-size:42px;
}

}

</style>
</head>

<body>

<div class="circle1"></div>
<div class="circle2"></div>

<div class="container-box">

<!-- IZQUIERDA -->

<div class="left">

<div class="logo">
<i class="bi bi-shield-lock-fill"></i>
</div>

<h1>PGP MASTER</h1>

<p>
Sistema moderno de autenticación, soporte y gestión segura con integración PHPMailer y protección avanzada.
</p>

<div class="features">

<div class="feature">
<i class="bi bi-envelope-fill"></i>
Soporte técnico en tiempo real
</div>

<div class="feature">
<i class="bi bi-shield-check"></i>
Sistema protegido y cifrado
</div>

<div class="feature">
<i class="bi bi-whatsapp"></i>
Contacto rápido mediante WhatsApp
</div>

</div>

</div>

<!-- DERECHA -->

<div class="right">

<h2>Bienvenido</h2>

<p>
Accede o crea una cuenta para ingresar al sistema.
</p>

<a href="panels/login.php" class="btn-main login">
<i class="bi bi-box-arrow-in-right"></i>
Login
</a>

<a href="panels/registro.php" class="btn-main register">
<i class="bi bi-person-plus-fill"></i>
Crear cuenta
</a>

<div class="actions">

<button class="action-btn" data-bs-toggle="modal" data-bs-target="#supportModal">
<i class="bi bi-envelope-fill"></i>
Soporte
</button>

<button class="action-btn" data-bs-toggle="modal" data-bs-target="#whatsappModal">
<i class="bi bi-whatsapp"></i>
Contactos
</button>

</div>

</div>

</div>

<!-- MODAL SOPORTE -->

<div class="modal fade" id="supportModal">

<div class="modal-dialog modal-dialog-centered">

<div class="modal-content">

<div class="modal-header">

<h5 class="modal-title">
<i class="bi bi-envelope-fill"></i>
Soporte PGP MASTER
</h5>

<button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>

</div>

<div class="modal-body">

<p>
Enviar un reclamo, consulta o reporte técnico al soporte oficial.
</p>

<a href="mailto:adrbalcazar144.inf@industrialmurillo.edu.bo?subject=RECLAMO%20PGP%20MASTER&body=Hola%20PGP%20MASTER,%20quisiera%20consultar%20o%20reclamar%20algo%20sobre%20su%20sistema.">
Enviar reclamo
</a>

</div>

</div>
</div>
</div>

<!-- MODAL WHATSAPP -->

<div class="modal fade" id="whatsappModal">

<div class="modal-dialog modal-dialog-centered">

<div class="modal-content">

<div class="modal-header">

<h5 class="modal-title">
<i class="bi bi-whatsapp"></i>
WhatsApp
</h5>

<button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>

</div>

<div class="modal-body">

<p>
Abrir conversación directa con soporte técnico.
</p>

<a href="https://wa.me/59174279761?text=Hola%20PGP%20MASTER,%20quisiera%20consultar%20o%20reclamar%20algo%20sobre%20su%20sistema." target="_blank">
Abrir WhatsApp
</a>

</div>

</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>