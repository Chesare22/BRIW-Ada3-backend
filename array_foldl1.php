<?php
$null_safe_callback = fn($callback) => fn($carry, $item) =>
  $carry === NULL ? $item : $callback($carry, $item);

$array_foldl1 = fn($callback) => fn($array) =>
  array_reduce($array, $null_safe_callback($callback));

?>