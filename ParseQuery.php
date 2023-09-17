<?php
include './ParserLibrary.php';

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


$p_expressions =
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


$p_query =
  $p_and_then(
    $p_expressions,
    $p_optional($p_fields)
  );
?>