 <?php

 ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
function conectarse() {
    $servidor = "sjeueie04.com";  
    $usuario = "ezyro_jsueueu4741";              
    $clave = "18f0308";               
    $base_datos = "ezjeudu7741_bd2";       

    
 
    
    $conexion = new mysqli($servidor, $usuario, $clave, $base_datos);

    if ($conexion->connect_error) {
        die(json_encode(["error" => "Error de conexión: " . $conexion->connect_error]));
    }

    $conexion->set_charset("utf8");
    return $conexion;
}
?>
