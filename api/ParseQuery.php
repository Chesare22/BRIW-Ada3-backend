<?php
include './ParserLibrary.php';
include './db_connection.php';

enum PQuery {
  case Keyword;
  case Chain;
  case Pattern;
  case Not;
  case And;
  case Or;
};


$p_alphanumeric =
  $p_satisfy_char('ctype_alpha');


$p_keyword =
  $p_map(
    fn($value) => [PQuery::Keyword, $value],
    $p_concatenate($p_one_or_more($p_alphanumeric))
  );


$p_not_closing_parenthesis =
  $p_satisfy_char(fn($char) => $char !== ')');


$p_opener = fn($str) =>
  $p_and_then(
    $p_trim($p_string($str)),
    $p_string('(')
  );


$p_chain =
  $p_map(
    fn($value) => [PQuery::Chain, $value],
    $p_between(
      $p_opener('CADENA'),
      $p_concatenate($p_one_or_more($p_not_closing_parenthesis)),
      $p_string(')')
    )
  );


$p_pattern =
  $p_map(
    fn($value) => [PQuery::Pattern, $value],
    $p_between(
      $p_opener('PATRON'),
      $p_concatenate($p_one_or_more($p_not_closing_parenthesis)),
      $p_string(')')
    )
  );


$p_term =
  $p_choice([
    $p_chain,
    $p_pattern,
    $p_keyword
  ]);


$p_not_term =
  $p_map(
    fn($value) => [PQuery::Not, $value],
    $p_right(
      $p_and_then(
        $p_string('NOT'),
        $p_one_or_more_whitespaces
      ),
      $p_term
    )
  );


$p_terms =
  $p_or_else(
    $p_not_term,
    $p_term
  );


$p_operator =
  $p_or_else(
    $p_map(
      fn($value) => [PQuery::And],
      $p_string('AND')
    ),
    $p_map(
      fn($value) => [PQuery::Or],
      $p_string('OR')
    ),
  );


$p_tokens =
  $p_separated_by(
    $p_or_else(
      $p_operator,
      $p_terms
    ),
    $p_one_or_more_whitespaces
  );


$is_operator = fn($expression_token) =>
  $expression_token[0] === PQuery::And || $expression_token[0] === PQuery::Or;


# An expression token is a term or an operator
$insert_missing_or_operators = function($expression_tokens) use (&$insert_missing_or_operators, $is_operator) {
  $number_of_tokens = count($expression_tokens);
  if ($number_of_tokens === 0) {
    return [];
  }

  if ($is_operator($expression_tokens[0])) {
    throw new Exception("Invalid operator placement", 1);
  }

  if ($number_of_tokens === 1) {
    return $expression_tokens;
  }

  $rest_of_tokens = $expression_tokens;
  array_splice($rest_of_tokens, 0, 2);
  if ($is_operator($expression_tokens[1])) {
    return [
      $expression_tokens[0],
      $expression_tokens[1],
      ...$insert_missing_or_operators($rest_of_tokens)
    ];
  } else {
    return [
      $expression_tokens[0],
      [PQuery::Or],
      ...$insert_missing_or_operators([$expression_tokens[1], ...$rest_of_tokens])
    ];
  }
};


$p_query =
  $p_map(
    $insert_missing_or_operators,
    $p_tokens
  );


$separated_by_or_operators_and_around_parenthesis = fn($values) =>
  '(' . implode(' OR ', $values) . ')';


$sanitize = fn($str) =>
  strtolower(
    mysqli_real_escape_string(
      $db_conection,
      $str
    )
  );


$token_to_sql_condition = function($column_names) use (&$token_to_sql_condition, $sanitize, $separated_by_or_operators_and_around_parenthesis) {
  return fn($token) =>
    match ($token[0]) {
      PQuery::And =>
        'AND',

      PQuery::Or =>
        'OR',

      PQuery::Not =>
        '(NOT ' . $token_to_sql_condition($column_names)($token[1]) . ')',

      PQuery::Keyword =>
        $separated_by_or_operators_and_around_parenthesis(
          array_map(
            fn($column_name) => "$column_name LIKE CONCAT('%','" . $sanitize($token[1]) . "', '%')",
            $column_names
          )
        ),

      PQuery::Chain =>
        $separated_by_or_operators_and_around_parenthesis(
          array_map(
            fn($column_name) => "$column_name = '" . $sanitize($token[1]) . "'",
            $column_names
          )
        ),

      PQuery::Pattern =>
        $separated_by_or_operators_and_around_parenthesis(
          array_map(
            fn($column_name) => "$column_name LIKE CONCAT('%', '" . $sanitize($token[1]) . "', '%')",
            $column_names
          )
        ),
    };
};


$query_to_sql_query = function($expression_tokens) use ($token_to_sql_condition) {
  $condition = implode(' ', array_map($token_to_sql_condition(["Vocabulario.token"]), $expression_tokens));
  return <<<EOD
  SELECT Documentos.id_documento, Documentos.nombre_archivo, Vocabulario.token, Posting.frecuencia
    FROM (SELECT * from Vocabulario WHERE $condition) AS Vocabulario
  INNER JOIN Posting
    ON Posting.id_token = Vocabulario.id_token
  INNER JOIN Documentos
    ON Posting.id_documento = Documentos.id_documento
  EOD;
};


$get_column_names = function($table_name) use ($db_conection) {
  $table_description = mysqli_fetch_all(mysqli_query($db_conection, "DESCRIBE $table_name"), MYSQLI_ASSOC);
  return array_map(fn($description) => $description["Field"], $table_description);
};

$make_sql_request = function($table_name, $query) use ($db_conection, $get_column_names) {  
  $column_names = $get_column_names($table_name);
  $sql_result = mysqli_fetch_all(mysqli_query($db_conection, $query));

  return [
    "table_name" => $table_name,
    "column_names" => $column_names,
    "query" => $query,
    "rows" => $sql_result,
  ];
};

$p_sql_query =
  $p_map(
    $query_to_sql_query,
    $p_query
  );


$p_sql_results = 
  $p_map(
    fn($sql_query) => $make_sql_request("Vocabulario", $sql_query),
    $p_sql_query
  );

?>