<?php
enum Maybe {
  case Some;
  case Nothing;
};

$maybe_return = fn($value) =>
  [Maybe::Some, $value];

?>