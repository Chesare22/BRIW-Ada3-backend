<?php
include './ParseQuery.php';
include './db_connection.php';


$dummy_query = "SELECT id, product_code, product_name FROM products";
$rows = mysqli_fetch_all(mysqli_query($db_conection, $dummy_query));


$p_doublequote = $p_string('"');
$p_comma = $p_string(',');
$p_digit = $p_choice(array_map($p_string, array_map('strval', range(0, 9))));
$p_integer = $p_map('intval', $p_map('implode', $p_one_or_more($p_digit)));
$p_quoted_integer = $p_between($p_doublequote, $p_integer, $p_doublequote);
$p_optional_semicolon = $p_optional($p_string(';'));
$p_digit_then_semicolon = $p_and_then($p_digit, $p_optional_semicolon);
$p_digits_separated_by_comma = $p_trim($p_separated_by_1($p_digit, $p_comma));
echo '<pre>';
var_dump($rows);
var_dump($p_query('Potato Chips'));
var_dump($p_query('Potato AND Chips'));
var_dump($p_query('Potato AND NOT Chips'));
var_dump($p_query('CADENA(Potato Chips)'));
var_dump($p_query('PATRON(Pot)'));
var_dump($p_query('CAMPOS(suppliers.company)'));
var_dump($p_query('CAMPOS(suppliers.company, suppliers.job_title)'));
var_dump($p_query('Papas Potato AND NOT Chips AND CADENA (con chile) OR PATRON (sabri) CAMPOS (products.description)'));
echo '</pre>';

mysqli_close($db_conection);

?>