<?php
include './pdo.php';

$documentos = [];

for ($i=0; $i < count($_FILES["files"]["name"]); $i++) { 
  $documentos[] = [
    'nombre_archivo' => basename($_FILES["files"]["name"][$i]),
    'archivo' => file_get_contents($_FILES["files"]["tmp_name"][$i])
  ];
}


$sql = "INSERT INTO Documentos(nombre_archivo, archivo, fecha) VALUES (:nombre_archivo, :archivo, now())";

try {
  foreach ($documentos as $documento) {
    $statement = $pdo->prepare($sql);
    $statement->execute($documento);
  }
  echo "The files have been uploaded.";

} catch (\Throwable $th) {
  echo $th;
}



?>
