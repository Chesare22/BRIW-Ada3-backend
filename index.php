<?php
include './ParseQuery.php';


$p_doublequote = $p_string('"');
$p_comma = $p_string(',');
$p_digit = $p_choice(array_map($p_string, array_map('strval', range(0, 9))));
$p_integer = $p_map('intval', $p_map('implode', $p_one_or_more($p_digit)));
$p_quoted_integer = $p_between($p_doublequote, $p_integer, $p_doublequote);
$p_optional_semicolon = $p_optional($p_string(';'));
$p_digit_then_semicolon = $p_and_then($p_digit, $p_optional_semicolon);
$p_digits_separated_by_comma = $p_trim($p_separated_by_1($p_digit, $p_comma));
echo '<pre>';
var_dump($p_terms('Potato AND NOT Chips;'));
var_dump($p_terms('CADENA(Potato Chips);'));
var_dump($p_terms('ASereje;'));
var_dump($p_terms('ASereje;'));
echo '</pre>';
?>