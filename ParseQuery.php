<?php
include './ParserLibrary.php';

enum PQuery {
  case Keyword;
  case Chain;
  case Pattern;
  case Fields;
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


$p_chain =
  $p_map(
    fn($value) => [PQuery::Chain, $value],
    $p_between(
      $p_string('CADENA('),
      $p_concatenate($p_one_or_more($p_not_closing_parenthesis)),
      $p_string(')')
    )
  );


$p_pattern =
  $p_map(
    fn($value) => [PQuery::Pattern, $value],
    $p_between(
      $p_string('PATRON('),
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
  $p_separated_by_1(
    $p_or_else(
      $p_not_term,
      $p_term
    ),
    $p_one_or_more_whitespaces
  );
?>