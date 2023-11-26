<?php
include './db_connection.php';

header("Access-Control-Allow-Origin: *");

[$filename, $content, $size, $type] =
  mysqli_fetch_array(
    mysqli_execute_query(
      $db_conection,
      "SELECT nombre_archivo, archivo, size, mime_type FROM `Documentos` WHERE id_documento = ?",
      [$_GET["id"]]
    )
  );


header("Content-length: $size");
header("Content-type: $type");
header("Content-Disposition: attachment; filename=$filename");
echo $content;
?>
