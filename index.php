<?php
include './ParseQuery.php';

header("Content-type: application/json");

$input_query = $_GET["q"];
$query_output = $p_sql_results($input_query);
if ($query_output[0] === Result::Err) {
  echo json_encode([
    "error_message" => $query_output[1],
    "failed_input" => $query_output[2]
  ]);
  mysqli_close($db_conection);
  http_response_code(400);
  exit();
}

[,$sql_results, $no_parsed_input] = $query_output;

echo json_encode([
  "input" => $input_query,
  "no_parsed_input" => $no_parsed_input,
  "results" => $sql_results
]);

mysqli_close($db_conection);
http_response_code(200);

?>