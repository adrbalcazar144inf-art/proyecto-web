<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

/* =========================
   BASE DE CONOCIMIENTO
========================= */
$KB = [
    "ubicacion" => "La Escuela Industrial Superior Pedro Domingo Murillo está ubicada en Av. Chacaltaya #1001, Zona Achachicala, La Paz, Bolivia.",
    "direccion" => "Av. Chacaltaya #1001, Zona Achachicala, La Paz, Bolivia.",
    "telefono" => "Teléfonos: 2 2305533 / 2 2306553.",
    "correo" => "Correo: eispdm@industrialmurillo.edu.bo",
    "que_es" => "Es un Instituto Técnico Superior en La Paz, Bolivia, especializado en formación industrial y tecnológica.",
    "historia" => "Fundado aproximadamente en 1942 con más de 80 años de formación técnica.",
    "carreras" => "Mecánica Industrial, Automotriz, Electricidad, Electrónica, Informática Industrial, Química Industrial, Metalurgia, Textil.",
    "admision" => "Requiere inscripción, examen de ingreso y requisitos de bachillerato."
];

function normalize($text){
    $text = strtolower($text);

    $text = str_replace(
        ['á','à','ä','â','é','è','ë','ê','í','ì','ï','î','ó','ò','ö','ô','ú','ù','ü','û','ñ'],
        ['a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','u','u','u','u','n'],
        $text
    );

    $text = preg_replace('/[^a-z0-9\s]/','',$text);
    $text = preg_replace('/\s+/',' ',trim($text));

    return $text;
}

/* =========================
   CORRECCIÓN DE ERRORES
========================= */
function fix($q){

    $map = [
        "ubicasion"=>"ubicacion",
        "ubicacionn"=>"ubicacion",
        "direcion"=>"direccion",
        "telefonoos"=>"telefono",
        "carrerass"=>"carreras",
        "admsion"=>"admision",
        "historiaa"=>"historia",
        "institutoo"=>"instituto",
        "murilllo"=>"murillo"
    ];

    $q = normalize($q);
    $words = explode(" ",$q);

    foreach($words as &$w){
        if(isset($map[$w])) $w = $map[$w];
    }

    return implode(" ",$words);
}

/* =========================
   DETECCIÓN DE INTENCIÓN
========================= */
function intent($q){

    if(strpos($q,"ubicacion")!==false || strpos($q,"direccion")!==false) return "ubicacion";
    if(strpos($q,"telefono")!==false || strpos($q,"contacto")!==false) return "telefono";
    if(strpos($q,"carrera")!==false) return "carreras";
    if(strpos($q,"historia")!==false) return "historia";
    if(strpos($q,"admision")!==false) return "admision";
    if(strpos($q,"que es")!==false || strpos($q,"instituto")!==false) return "que_es";

    return null;
}

/* =========================
   BUSQUEDA LOCAL
========================= */
function local($q,$KB){
    $i = intent($q);
    if($i && isset($KB[$i])) return $KB[$i];
    return null;
}

/* =========================
   WEB FALLBACK
========================= */
function curl_get($url){
    if(!function_exists('curl_init')) return '';

    $ch = curl_init($url);
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_TIMEOUT=>10,
        CURLOPT_USERAGENT=>'Mozilla/5.0 Chatbot',
        CURLOPT_SSL_VERIFYPEER=>false
    ]);

    $res = curl_exec($ch);
    curl_close($ch);

    return $res ?: '';
}

function web($q){
    $url = "https://html.duckduckgo.com/html/?q=".urlencode($q);
    $html = curl_get($url);

    if(!$html) return null;

    preg_match('/<a[^>]*class="result__a"[^>]*>(.*?)<\/a>/si',$html,$m);

    if(!isset($m[1])) return null;

    return "Información relacionada: ".strip_tags($m[1]);
}

/* =========================
   MOTOR PRINCIPAL
========================= */
function answer($q,$KB){

    $q = fix($q);

    if(!$q) return "Escribe una pregunta.";

    // 1. LOCAL
    $r = local($q,$KB);
    if($r) return $r;

    // 2. WEB
    $w = web($q);
    if($w) return $w;

    // 3. FALLBACK
    return "No entendí bien. Intenta: ubicación, carreras, contacto, historia o admisión.";
}

/* =========================
   API
========================= */
if($_SERVER['REQUEST_METHOD']==='POST'){
    $q = $_POST['message'] ?? '';

    echo json_encode([
        "answer"=>answer($q,$KB)
    ],JSON_UNESCAPED_UNICODE);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chatbot EISPDM</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:#000;
    color:#fff;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
}

.box{
    width:95%;
    max-width:850px;
    height:85vh;
    background:#111;
    border:1px solid #333;
    display:flex;
    flex-direction:column;
}

.header{
    padding:15px;
    text-align:center;
    border-bottom:1px solid #333;
}

.chat{
    flex:1;
    overflow-y:auto;
    padding:15px;
}

.msg{margin:10px 0;}
.user{text-align:right;}

.bubble{
    display:inline-block;
    padding:10px 14px;
    border-radius:10px;
    max-width:75%;
}

.user .bubble{
    background:#fff;
    color:#000;
}

.bot .bubble{
    background:#222;
    color:#fff;
}

.input{
    display:flex;
    gap:10px;
    padding:10px;
    border-top:1px solid #333;
}

input{
    flex:1;
    background:#000;
    border:1px solid #444;
    color:#fff;
}
</style>
</head>

<body>

<div class="box">
    <div class="header">🤖 Chatbot Inteligente EISPDM</div>

    <div id="chat" class="chat"></div>

    <div class="input">
        <input id="msg" class="form-control" placeholder="Escribe tu pregunta...">
        <button class="btn btn-light" onclick="send()">Enviar</button>
    </div>
</div>

<script>
const chat=document.getElementById("chat");

function add(text,type){
    let d=document.createElement("div");
    d.className="msg "+type;
    d.innerHTML=`<div class="bubble">${text}</div>`;
    chat.appendChild(d);
    chat.scrollTop=chat.scrollHeight;
}

async function send(){
    let i=document.getElementById("msg");
    let t=i.value;
    if(!t)return;

    add(t,"user");
    i.value="";

    add("Pensando...","bot");

    let f=new FormData();
    f.append("message",t);

    let r=await fetch("",{method:"POST",body:f});
    let d=await r.json();

    chat.lastChild.remove();
    add(d.answer,"bot");
}

add("Hola 👋 pregunta sobre la Escuela Industrial Murillo","bot");
</script>

</body>
</html>