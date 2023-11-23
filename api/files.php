<?php
include './pdo.php';
include './ParserLibrary.php';

// Copied from https://stackoverflow.com/a/67111853/13194448
function array_every(array $arr, callable $predicate) {
    foreach ($arr as $e) {
        if (!call_user_func($predicate, $e)) {
             return false;
        }
    }

    return true;
}

// Copied from https://gist.github.com/davidrjonas/8f820ab0c75534b45189eba1d1fbeb23
function array_flatmap(callable $fn, $array) {
    return array_merge(...array_map($fn, $array));
}

// Copied from https://stackoverflow.com/a/35882000/13194448
function array_find($xs, $f) {
  foreach ($xs as $x) {
    if (call_user_func($f, $x) === true)
      return $x;
  }
  return null;
}

$filenames = array_map("basename", $_FILES["files"]["name"]);

$is_txt = fn($filename) => str_ends_with($filename, '.txt');

if (!array_every($filenames, $is_txt)) {
  echo "Not all files are txt";
  exit();
}

$file_contents =
  array_map("file_get_contents", $_FILES["files"]["tmp_name"]);

$sanitize = fn($content) =>
  strtolower(
    iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE',
      preg_replace('/\p{P}/', '',
        $content
      )
    )
  );

$sanitized_file_contents =
  array_map($sanitize, $file_contents);


$p_token =
  $p_concatenate(
    $p_one_or_more(
      $p_satisfy_char('ctype_alnum')
    )
  );

$p_tokens = 
  $p_trim(
    $p_separated_by(
      $p_token,
      $p_one_or_more_whitespaces
    )
  );


$tokens_in_files = 
  array_map($p_tokens, $sanitized_file_contents);


for ($i=0; $i < count($tokens_in_files); $i++) {
  $tokens_in_file = $tokens_in_files[$i];

  if ($tokens_in_file[0] === Result::Err) {
    echo $tokens_in_file[1];
    http_response_code(400);
    exit();
  }

  if (count($tokens_in_file[1]) === 0) {
    $filename = $filenames[$i];
    echo "The file '$filename' is empty";
    http_response_code(400);
    exit();
  }
}


$total_tokens =
  array_unique(
    array_flatmap(
      fn($tokens_in_file) => $tokens_in_file[1],
      $tokens_in_files
    )
  );

foreach($total_tokens as $token) {
  try {
    $pdo
      ->prepare("INSERT INTO `Vocabulario` (token) VALUES ('$token')")
      ->execute();
  } catch (\Throwable $th) {}
}

$vocabulary =
  array_map(
    fn($token) =>
      $pdo
        ->query("SELECT * FROM `Vocabulario` WHERE token = '$token'")
        ->fetch(),
    $total_tokens
  );

$get_token_id = fn($token) =>
  array_find(
    $vocabulary,
    fn($vocabulary_item) => $vocabulary_item['token'] === $token
  )['id_token'];


$documentos = [];

for ($i=0; $i < count($_FILES["files"]["name"]); $i++) { 
  $documentos[] = [
    'nombre_archivo' => basename($_FILES["files"]["name"][$i]),
    'archivo' => file_get_contents($_FILES["files"]["tmp_name"][$i])
  ];
}

$document_ids = [];
$sql = "INSERT INTO Documentos(nombre_archivo, archivo, fecha) VALUES (:nombre_archivo, :archivo, now())";

try {
  foreach ($documentos as $documento) {
    $statement = $pdo->prepare($sql);
    $statement->execute($documento);
    $document_ids[] = $pdo->lastInsertId();
  }

} catch (\Throwable $th) {
  echo $th;
}


for ($i=0; $i < count($tokens_in_files); $i++) { 
  $tokens_in_file = $tokens_in_files[$i][1];
  $document_id = $document_ids[$i];

  foreach (array_count_values($tokens_in_file) as $token => $frequency) {
    $token_id = $get_token_id($token);
    
    $pdo
      ->prepare("INSERT INTO `Posting` (id_token, id_documento, frecuencia) VALUES ($token_id, $document_id, $frequency)")
      ->execute();
  }
}


?>
