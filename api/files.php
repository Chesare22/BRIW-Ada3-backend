<?php
include './pdo.php';

$filename = basename($_FILES["files"]["name"][0]);

$documentos = [];

$documentos[] = [
  'nombre_archivo' => $filename,
  'archivo' => file_get_contents($_FILES["files"]["tmp_name"][0])
];

$sql = "INSERT INTO Documentos(nombre_archivo, archivo, fecha) VALUES (:nombre_archivo, :archivo, now())";

try {
  foreach ($documentos as $documento) {
    $statement = $pdo->prepare($sql);
    $statement->execute($documento);
  }
  echo "The file ". htmlspecialchars($filename). " may have been uploaded.";

} catch (\Throwable $th) {
  echo $th;
}



?>
