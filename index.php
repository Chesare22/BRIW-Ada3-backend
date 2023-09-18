<?php
include './ParseQuery.php';
#include './db_connection.php';


#$dummy_query = "SELECT id, product_code, product_name FROM products";
#$rows = mysqli_fetch_all(mysqli_query($db_conection, $dummy_query));


echo '<pre>';
# var_dump($rows);
var_dump($p_query('Potato Chips'));
var_dump($p_query('Potato AND Chips'));
var_dump($p_query('Potato AND AND NOT Chips'));
var_dump($p_query('CADENA(Potato Chips)'));
var_dump($p_query('PATRON(Pot)'));
var_dump($p_query('CAMPOS(suppliers.company)'));
var_dump($p_query('CAMPOS(suppliers.company, suppliers.job_title)'));
var_dump($p_query('Papas Potato AND NOT Chips AND CADENA (con chile) OR PATRON (sabri) CAMPOS (products.description)'));
echo '</pre>';

#mysqli_close($db_conection);

?>