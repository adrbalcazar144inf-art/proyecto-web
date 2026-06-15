<?php

$DB_HOST="sql204.ezyro.com";
$DB_USER="ezyro_41317741";
$DB_PASS="18f0308";
$DB_NAME="ezyro_41317741_bd2";

$BACKUP_DIR="backups/";
$AUTO_BACKUP=true;
$INTERVAL_TYPE="hours";
$INTERVAL_VALUE=6;
$RETENTION_DAYS=365;

$conn=new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);

if($conn->connect_error){die("Error conexion BD");}

if(!is_dir($BACKUP_DIR)){mkdir($BACKUP_DIR,0777,true);}

if(file_exists("auto_config.json")){
$c=json_decode(file_get_contents("auto_config.json"),true);
$INTERVAL_TYPE=$c['type'];
$INTERVAL_VALUE=$c['value'];
}

function intervaloSegundos($tipo,$valor){

switch($tipo){

case "minutes": return $valor*60;
case "hours": return $valor*3600;
case "days": return $valor*86400;
case "years": return $valor*31536000;
default: return 3600;

}

}

function crearBackup($conn,$dir){

$tables=[];
$r=$conn->query("SHOW TABLES");

while($row=$r->fetch_row()){
$tables[]=$row[0];
}

$sql="";

foreach($tables as $table){

$res=$conn->query("SELECT * FROM `$table`");
$num=$res->field_count;

$sql.="DROP TABLE IF EXISTS `$table`;\n";

$row2=$conn->query("SHOW CREATE TABLE `$table`")->fetch_row();
$sql.=$row2[1].";\n\n";

while($row=$res->fetch_row()){

$sql.="INSERT INTO `$table` VALUES(";

for($i=0;$i<$num;$i++){

$row[$i]=addslashes($row[$i]);
$sql.='"'.$row[$i].'"';

if($i<($num-1)){ $sql.=","; }

}

$sql.=");\n";

}

$sql.="\n";

}

$file=$dir."backup_".date("Ymd_His").".sql.gz";

$gz=gzopen($file,"w9");
gzwrite($gz,$sql);
gzclose($gz);

return $file;

}

function limpiarBackups($dir,$days){

$files=glob($dir."*.gz");

foreach($files as $f){

if(time()-filemtime($f) > ($days*86400)){
unlink($f);
}

}

}

function autoBackup($conn,$dir,$tipo,$valor){

$control="backup_time.txt";
$intervalo=intervaloSegundos($tipo,$valor);
$ultimo=0;

if(file_exists($control)){
$ultimo=file_get_contents($control);
}

if(time()-$ultimo >= $intervalo){

crearBackup($conn,$dir);
file_put_contents($control,time());

}

}

if($AUTO_BACKUP){
autoBackup($conn,$BACKUP_DIR,$INTERVAL_TYPE,$INTERVAL_VALUE);
}

limpiarBackups($BACKUP_DIR,$RETENTION_DAYS);

$msg="";

if(isset($_POST['set_auto'])){

$INTERVAL_VALUE=intval($_POST['interval_val']);
$INTERVAL_TYPE=$_POST['interval_type'];

file_put_contents("auto_config.json",json_encode([
"type"=>$INTERVAL_TYPE,
"value"=>$INTERVAL_VALUE
]));

$msg="Backup automático actualizado";

}

if(isset($_POST['backup'])){
$file=crearBackup($conn,$BACKUP_DIR);
$msg="Backup creado: ".basename($file);
}

if(isset($_POST['restore'])){

$f=$_FILES['file']['tmp_name'];
$sql="";

if(substr($_FILES['file']['name'],-3)=="gz"){

$gz=gzopen($f,"r");

while(!gzeof($gz)){
$sql.=gzread($gz,4096);
}

gzclose($gz);

}else{
$sql=file_get_contents($f);
}

$q=explode(";",$sql);

foreach($q as $query){

$query=trim($query);

if($query){
$conn->query($query);
}

}

$msg="Backup restaurado";

}

if(isset($_POST['sql'])){

$q=$_POST['query'];

if($conn->query($q)){
$msg="Consulta ejecutada";
}else{
$msg="Error: ".$conn->error;
}

}

if(isset($_POST['dropdb'])){

$r=$conn->query("SHOW TABLES");

while($row=$r->fetch_row()){
$conn->query("DROP TABLE `$row[0]`");
}

$msg="Base de datos eliminada";

}

if(isset($_GET['opt'])){
$t=$conn->real_escape_string($_GET['opt']);
$conn->query("OPTIMIZE TABLE `$t`");
$msg="Tabla optimizada";
}

if(isset($_GET['repair'])){
$t=$conn->real_escape_string($_GET['repair']);
$conn->query("REPAIR TABLE `$t`");
$msg="Tabla reparada";
}

if(isset($_GET['truncate'])){
$t=$conn->real_escape_string($_GET['truncate']);
$conn->query("TRUNCATE TABLE `$t`");
$msg="Tabla vaciada";
}

if(isset($_GET['drop'])){
$t=$conn->real_escape_string($_GET['drop']);
$conn->query("DROP TABLE `$t`");
$msg="Tabla eliminada";
}

$tables=0;
$rows=0;
$size=0;

$res=$conn->query("SHOW TABLE STATUS");

while($row=$res->fetch_assoc()){

$tables++;
$rows+=$row['Rows'];
$size+=($row['Data_length']+$row['Index_length']);

}

$size=round($size/1024/1024,2);

?>

<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">
<title>Administrador BD</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{background:#0f172a;color:white;font-family:Segoe UI}
.card{background:#020617;border:none;border-radius:12px;padding:20px}
table{background:#020617}

</style>

</head>

<body class="container py-4">

<h2>Administrador Base de Datos</h2>

<?php if($msg!=""){ ?>
<div class="alert alert-info"><?php echo $msg ?></div>
<?php } ?>

<div class="row g-4 mb-4">

<div class="col-md-3"><div class="card">Tablas<h3><?php echo $tables ?></h3></div></div>
<div class="col-md-3"><div class="card">Registros<h3><?php echo $rows ?></h3></div></div>
<div class="col-md-3"><div class="card">Tamaño<h3><?php echo $size ?> MB</h3></div></div>
<div class="col-md-3"><div class="card">MySQL<h3><?php echo $conn->server_info ?></h3></div></div>

</div>

<h4>Backup Automático</h4>

<form method="POST" class="row g-2">

<div class="col-md-3">
<input type="number" name="interval_val" class="form-control" placeholder="Valor">
</div>

<div class="col-md-3">
<select name="interval_type" class="form-control">
<option value="minutes">Minutos</option>
<option value="hours">Horas</option>
<option value="days">Días</option>
<option value="years">Años</option>
</select>
</div>

<div class="col-md-3">
<button name="set_auto" class="btn btn-primary">Guardar</button>
</div>

</form>

<hr>

<h4>Crear Backup</h4>

<form method="POST">
<button name="backup" class="btn btn-success">Crear Backup</button>
</form>

<hr>

<h4>Restaurar Backup</h4>

<form method="POST" enctype="multipart/form-data">

<input type="file" name="file" class="form-control">

<button name="restore" class="btn btn-warning mt-2">Restaurar</button>

</form>

<hr>

<h4>Ejecutar SQL</h4>

<form method="POST">

<textarea name="query" class="form-control" rows="4"></textarea>

<button name="sql" class="btn btn-primary mt-2">Ejecutar</button>

</form>

<hr>

<h4 class="text-danger">Eliminar toda la Base de Datos</h4>

<form method="POST">

<button name="dropdb" class="btn btn-danger"
onclick="return confirm('¿Eliminar TODA la base de datos?')">

Eliminar Base de Datos

</button>

</form>

<hr>

<h4>Tablas</h4>

<table class="table table-dark table-striped">

<tr>
<th>Tabla</th>
<th>Acciones</th>
</tr>

<?php

$r=$conn->query("SHOW TABLES");

while($t=$r->fetch_row()){

echo "<tr>";
echo "<td>".$t[0]."</td>";

echo "<td>

<a class='btn btn-sm btn-info' href='?opt=".$t[0]."'>Optimizar</a>

<a class='btn btn-sm btn-secondary' href='?repair=".$t[0]."'>Reparar</a>

<a class='btn btn-sm btn-warning' href='?truncate=".$t[0]."'>Vaciar</a>

<a class='btn btn-sm btn-danger' href='?drop=".$t[0]."'>Eliminar</a>

</td>";

echo "</tr>";

}

?>

</table>

<hr>

<h4>Backups</h4>

<div class="table-responsive">

<table class="table table-dark table-striped align-middle">

<thead>
<tr>
<th>Archivo</th>
<th>Fecha</th>
<th>Hora</th>
<th>Descargar</th>
</tr>
</thead>

<tbody>

<?php

$files=glob($BACKUP_DIR."*.gz");

foreach($files as $f){

$name=basename($f);

$fecha=date("Y-m-d",filemtime($f));
$hora=date("H:i:s",filemtime($f));

echo "<tr>";

echo "<td>$name</td>";
echo "<td>$fecha</td>";
echo "<td>$hora</td>";

echo "<td>
<a class='btn btn-success btn-sm' href='$f'>
Descargar
</a>
</td>";

echo "</tr>";

}

?>

</tbody>

</table>

</div>

<?php

$files=glob($BACKUP_DIR."*.gz");

foreach($files as $f){

$name=basename($f);

echo "<tr>";
echo "<td>$name</td>";
echo "<td><a class='btn btn-success btn-sm' href='$f'>Descargar</a></td>";
echo "</tr>";

}

?>

</table>

</body>
</html>