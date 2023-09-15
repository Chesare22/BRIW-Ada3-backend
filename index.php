<?php
include './ParserLibrary.php';


$p_doublequote = $p_string('"');
$p_digit = $p_choice(array_map($p_string, array_map('strval', range(0, 9))));
$p_integer = $p_map('intval', $p_map('implode', $p_one_or_more($p_digit)));
$p_quoted_integer = $p_between($p_doublequote, $p_integer, $p_doublequote);
$p_optional_semicolon = $p_optional($p_string(';'));
$p_digit_then_semicolon = $p_and_then($p_digit, $p_optional_semicolon);
echo '<pre>';
var_dump($p_digit_then_semicolon('1;'));
var_dump($p_digit_then_semicolon('1'));
echo '</pre>';
?>