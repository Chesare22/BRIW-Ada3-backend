<?php
enum Result {
  case Ok;
  case Err;
};


$parse_word = fn($word_to_match) => function($input) use ($word_to_match) {
  $type_of_input = gettype($input);
  if ($type_of_input !== 'string') {
    return [Result::Err, "Expecting a string. Got a '$type_of_input' instead", $input];
  }

  $word_to_match_length = strlen($word_to_match);
  $input_start = substr($input, 0,  $word_to_match_length);
  if ($input_start !== $word_to_match) {
    return [Result::Err, "Expecting '$word_to_match'. Got '$input_start' instead", $input];
  }
  
  $input_rest = substr($input, $word_to_match_length);
  return [Result::Ok, $input_start, $input_rest];
};


$parse_and_then = fn($parser1, $parser2) => function($input) use ($parser1, $parser2) {
  $output1 = $parser1($input);
  [$result1, $value1, $remaining1] = $output1;
  if($result1 === Result::Err) {
    return $output1;
  }

  $output2 = $parser2($remaining1);
  [$result2, $value2, $remaining2] = $output2;
  if($result2 === Result::Err) {
    return $output2;
  }

  $combined_value = [$value1, $value2];
  return [Result::Ok, $combined_value, $remaining2];
};


$parse_or_else = fn($parser1, $parser2) => function($input) use ($parser1, $parser2) {
  $output1 = $parser1($input);
  [$result1] = $output1;
  if($result1 === Result::Ok) {
    return $output1;
  }

  return $parser2($input);
};

$null_safe_callback = fn($callback) => fn($carry, $item) =>
  $carry === NULL ? $item : $callback($carry, $item);

$parse_choice = fn($parsers) =>
  array_reduce($parsers, $null_safe_callback($parse_or_else));


$parse_a = $parse_word('a');
$parse_b = $parse_word('b');
$parse_c = $parse_word('c');
$parse_b_or_c = $parse_choice([$parse_b, $parse_c]);
$parse_a_and_then_b_or_c = $parse_and_then($parse_a, $parse_b_or_c);
$parse_a_or_b = $parse_or_else($parse_a, $parse_b);
echo '<pre>';
print_r($parse_a_and_then_b_or_c('abz'));
print_r($parse_a_and_then_b_or_c('acz'));
print_r($parse_a_and_then_b_or_c('qbz'));
print_r($parse_a_and_then_b_or_c('aqz'));
echo '</pre>';
?>