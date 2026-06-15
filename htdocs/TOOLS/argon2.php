<?php

require_once 'conexion.php';

$conn = conectarse();

// Contraseña simple
$password = "12";

// Generar hash Argon2ID
$hash = password_hash($password, PASSWORD_ARGON2ID);

// Actualizar estudiantes
$sql1 = "UPDATE usuarios 
SET contrasena='$hash'
WHERE nro_ci='1001'";

// Actualizar docente
$sql2 = "UPDATE usuarios 
SET contrasena='$hash'
WHERE nro_ci='1002'";

// Actualizar superusuario
$sql3 = "UPDATE usuarios 
SET contrasena='$hash'
WHERE nro_ci='1003'";

$conn->query($sql1);
$conn->query($sql2);
$conn->query($sql3);

echo "<h2>Contraseñas actualizadas correctamente</h2>";

echo "<hr>";
echo "<strong>HASH GENERADO:</strong><br>";
echo $hash;

$conn->close();

?>