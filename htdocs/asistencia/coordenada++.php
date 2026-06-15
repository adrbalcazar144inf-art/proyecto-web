<?php
function distanceMeters($lat1,$lng1,$lat2,$lng2){
    $r=6371000;
    $dLat=deg2rad($lat2-$lat1);
    $dLng=deg2rad($lng2-$lng1);
    $a=sin($dLat/2)**2+cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLng/2)**2;
    return $r*(2*atan2(sqrt($a),sqrt(1-$a)));
}
 
if($_SERVER['REQUEST_METHOD']=='POST'){
    header('Content-Type: application/json');

    $schoolLat=-16.475498;
    $schoolLng=-68.1515059;

    // RADIO AQUI ↓↓↓
    $radiusMeters=100;

    $lat=floatval($_POST['lat']??0);
    $lng=floatval($_POST['lng']??0);

    $dist=distanceMeters($schoolLat,$schoolLng,$lat,$lng);
    $inside=$dist<=$radiusMeters;
    $proximity=max(0,min(100,100-(($dist/$radiusMeters)*100)));

    echo json_encode([
        'distance'=>round($dist,2),
        'inside'=>$inside,
        'proximity'=>round($proximity,2),
        'message'=>$inside?'Dentro del radio':'Fuera del radio'
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ubicación GPS</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
#map{height:500px}
</style>
</head>

<body class="bg-dark">

<div class="container-fluid py-3">

<div class="card bg-primary text-white border-0 shadow-lg rounded-4 mb-4">
<div class="card-body">

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

<div>
<h2 class="fw-bold mb-1">
<i class="bi bi-geo-alt-fill"></i>
 GPS EN VIVO
</h2>
<div>Mapa satelital y validación en tiempo real</div>
</div>

<div class="d-flex gap-2 flex-wrap">
<button onclick="miUbicacion()" class="btn btn-warning fw-bold">
<i class="bi bi-crosshair"></i>
 Ver dónde estoy
</button>

<span id="estado" class="badge text-bg-dark fs-6 p-3">
Esperando ubicación
</span>
</div>

</div>

</div>
</div>

<div class="row g-4">

<div class="col-lg-8">

<div class="card border-0 shadow-lg rounded-4 overflow-hidden">

<div class="card-header bg-black text-white fw-bold">
<i class="bi bi-map-fill"></i>
 MAPA SATELITAL
</div>

<div class="card-body p-0">
<div id="map"></div>
</div>

</div>

</div>

<div class="col-lg-4">

<div class="row g-3">

<div class="col-6">
<div class="card text-bg-info border-0 rounded-4 shadow h-100">
<div class="card-body text-center">
<div class="small">DISTANCIA</div>
<div class="fs-3 fw-bold" id="distance">0</div>
<div>metros</div>
</div>
</div>
</div>

<div class="col-6">
<div class="card text-bg-success border-0 rounded-4 shadow h-100">
<div class="card-body text-center">
<div class="small">VELOCIDAD</div>
<div class="fs-3 fw-bold" id="speed">0</div>
<div>m/s</div>
</div>
</div>
</div>

<div class="col-6">
<div class="card text-bg-warning border-0 rounded-4 shadow h-100">
<div class="card-body text-center">
<div class="small text-dark">ESTADO</div>
<div class="fs-4 fw-bold text-dark" id="inside">---</div>
</div>
</div>
</div>

<div class="col-6">
<div class="card text-bg-danger border-0 rounded-4 shadow h-100">
<div class="card-body text-center">
<div class="small">PROXIMIDAD</div>
<div class="fs-3 fw-bold" id="proximity">0%</div>
</div>
</div>
</div>

</div>

<div class="card border-0 shadow-lg rounded-4 mt-4">
<div class="card-header bg-dark text-white fw-bold">
<i class="bi bi-graph-up"></i>
 PROXIMIDAD
</div>

<div class="card-body">
<div style="height:250px">
<canvas id="chart"></canvas>
</div>

<div class="progress mt-3" style="height:18px">
<div id="bar" class="progress-bar bg-success" style="width:0%"></div>
</div>

<div id="msg" class="alert alert-secondary mt-3 mb-0">
Esperando datos...
</div>

</div>
</div>

</div>

</div>

</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
const schoolLat=-16.475498;
const schoolLng=-68.1515059;
const radius=100;

const el=id=>document.getElementById(id);

const map=L.map('map').setView([schoolLat,schoolLng],19);

L.tileLayer(
'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
{
maxZoom:20,
attribution:'Esri'
}
).addTo(map);

L.circle([schoolLat,schoolLng],{
radius:radius,
color:'#0dcaf0',
fillColor:'#0dcaf0',
fillOpacity:.2
}).addTo(map);

L.marker([schoolLat,schoolLng])
.addTo(map)
.bindPopup('Escuela');

let userMarker,linea;

const chart=new Chart(el('chart'),{
type:'doughnut',
data:{
labels:['Proximidad','Restante'],
datasets:[{
data:[0,100],
backgroundColor:['#198754','#dee2e6']
}]
},
options:{
responsive:true,
maintainAspectRatio:false,
cutout:'70%'
}
});

function miUbicacion(){
    if(userMarker){
        map.setView(userMarker.getLatLng(),20);
        userMarker.openPopup();
    }
}

function success(pos){

    const lat=pos.coords.latitude;
    const lng=pos.coords.longitude;
    const speed=pos.coords.speed||0;

    el('speed').innerHTML=speed.toFixed(2);

    if(!userMarker){

        userMarker=L.marker([lat,lng]).addTo(map)
        .bindPopup('Estás aquí');

    }else{

        userMarker.setLatLng([lat,lng]);

    }

    if(!linea){

        linea=L.polyline([
            [schoolLat,schoolLng],
            [lat,lng]
        ],{
            color:'yellow',
            weight:4
        }).addTo(map);

    }else{

        linea.setLatLngs([
            [schoolLat,schoolLng],
            [lat,lng]
        ]);

    }

    fetch('',{
        method:'POST',
        headers:{
            'Content-Type':'application/x-www-form-urlencoded'
        },
        body:`lat=${lat}&lng=${lng}`
    })

    .then(r=>r.json())

    .then(d=>{

        el('distance').innerHTML=d.distance;
        el('inside').innerHTML=d.inside?'DENTRO':'FUERA';
        el('proximity').innerHTML=d.proximity+'%';

        el('bar').style.width=d.proximity+'%';

        el('msg').className=d.inside
        ?'alert alert-success mt-3 mb-0'
        :'alert alert-danger mt-3 mb-0';

        el('msg').innerHTML=d.message;

        el('estado').className=d.inside
        ?'badge text-bg-success fs-6 p-3'
        :'badge text-bg-danger fs-6 p-3';

        el('estado').innerHTML=d.inside
        ?'Ubicación válida'
        :'Fuera del radio';

        chart.data.datasets[0].data=[
            d.proximity,
            100-d.proximity
        ];

        chart.update();

    });

}

function error(){

    el('msg').className='alert alert-danger mt-3 mb-0';
    el('msg').innerHTML='Activa el GPS del celular';

    el('estado').className='badge text-bg-danger fs-6 p-3';
    el('estado').innerHTML='Sin ubicación';

}

navigator.geolocation.watchPosition(
success,
error,
{
enableHighAccuracy:true,
maximumAge:0,
timeout:15000
}
);
</script>

</body>
</html>