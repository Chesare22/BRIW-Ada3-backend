<?php
include './pdo.php';

// Copied from https://stackoverflow.com/a/67111853/13194448
function array_every(array $arr, callable $predicate) {
    foreach ($arr as $e) {
        if (!call_user_func($predicate, $e)) {
             return false;
        }
    }

    return true;
}

$filenames = array_map("basename", $_FILES["files"]["name"]);

$is_txt = fn($filename) => str_ends_with($filename, '.txt');

if (!array_every($filenames, $is_txt)) {
  echo "Not all files are txt";
  exit();
}

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
