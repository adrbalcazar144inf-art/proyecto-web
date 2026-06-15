<?php
$token = $_GET['token'] ?? '';
$data = json_decode(file_get_contents('data.json'), true);
$carpeta = 'uploads/';

if (!isset($data[$token])) {
  http_response_code(404);
  exit(json_encode(['error' => 'Token inválido.']));
}

$archivo = $data[$token]['archivo'];
if (strtolower(pathinfo($archivo, PATHINFO_EXTENSION)) !== 'zip') {
  http_response_code(400);
  exit(json_encode(['error' => 'No es un archivo ZIP.']));
}

$ruta = $carpeta . $archivo;
if (!file_exists($ruta)) {
  http_response_code(404);
  exit(json_encode(['error' => 'Archivo no encontrado.']));
}

$zip = new ZipArchive();
if ($zip->open($ruta) !== TRUE) {
  http_response_code(500);
  exit(json_encode(['error' => 'No se pudo abrir ZIP.']));
}

$contenido = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
  $contenido[] = $zip->getNameIndex($i);
}
$zip->close();

header('Content-Type: application/json');
echo json_encode($contenido);
