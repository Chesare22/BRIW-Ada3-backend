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
    $p_exception(
      $p_opener('CAMPOS'),
      $p_or_else(
        $p_operator,
        $p_terms
      )
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


$p_expressions =
  $p_map(
    $insert_missing_or_operators,
    $p_tokens
  );


$p_table_name =
  $p_concatenate(
    $p_one_or_more(
      $p_or_else(
        $p_alphanumeric,
        $p_string('_')
      )
    )
  );

$p_column_name =
  $p_table_name;

$p_field =
  $p_around(
    $p_table_name,
    $p_string('.'),
    $p_column_name
  );

$p_field_separator =
  $p_and_then(
    $p_string(','),
    $p_zero_or_more_whitespaces
  );

$p_fields =
  $p_between(
    $p_opener('CAMPOS'),
    $p_separated_by_1(
      $p_field,
      $p_field_separator
    ),
    $p_string(')')
  );


$fields_by_table = function($tables_so_far, $field) {
  [$table_name, $field_name] = $field;

  if (array_key_exists($table_name, $tables_so_far)) {
    $new_table = $tables_so_far[$table_name];
    array_push($new_table, $field_name);
    $new_tables = $tables_so_far;
    $new_tables[$table_name] = $new_table;
    return $new_tables;
  }

  $new_tables = $tables_so_far;
  $new_tables[$table_name] = [$field_name];
  return $new_tables;
};


$default_tables = [
  "products" => [
    "name",
    "quantity_per_unit",
    "category"
  ]
];


$p_tables = 
  $p_map(
    fn($fields) =>
      match ($fields[0]) {
        Maybe::Nothing => $default_tables,
        Maybe::Some => array_reduce($fields[1], $fields_by_table, []),
      },
    $p_optional($p_fields)
  );

$p_query = 
  $p_and_then(
    $p_expressions,
    $p_tables
  );


$separated_by_or_operators_and_around_parenthesis = fn($values) =>
  '(' . implode(' OR ', $values) . ')';


$sanitize = fn($str) =>
  mysqli_real_escape_string($db_conection, $str);


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
            fn($column_name) => "LOWER($column_name) = LOWER('" . $sanitize($token[1]) . "')",
            $column_names
          )
        ),

      PQuery::Chain =>
        $separated_by_or_operators_and_around_parenthesis(
          array_map(
            fn($column_name) => "LOWER($column_name) = LOWER('" . $sanitize($token[1]) . "')",
            $column_names
          )
        ),

      PQuery::Pattern =>
        $separated_by_or_operators_and_around_parenthesis(
          array_map(
            fn($column_name) => "LOWER($column_name) LIKE CONCAT('%', LOWER('" . $sanitize($token[1]) . "'), '%')",
            $column_names
          )
        ),
    };
};

$table_to_sql_query = fn($expression_tokens) => function($table_name, $column_names) use ($expression_tokens, $token_to_sql_condition) {
  $where_statement =
    count($expression_tokens) === 0
    ? ''
    : "WHERE " . implode(' ', array_map($token_to_sql_condition($column_names), $expression_tokens));

  return [$table_name, "SELECT * FROM $table_name $where_statement;"];
};

function array_map_assoc(callable $f, array $a) {
    return array_column(array_map($f, array_keys($a), $a), 1, 0);
}

$query_to_sql_query = function($query_result) use ($table_to_sql_query) {
  [$expression_tokens, $tables] = $query_result;
  return array_map_assoc(
    $table_to_sql_query($expression_tokens),
    $tables
  );
};


$p_sql_query =
  $p_map(
    $query_to_sql_query,
    $p_query
  );

?>