<?php
include './Result.php';
include './Maybe.php';


$p_string = fn($string_to_match) => function($input) use ($string_to_match) {
  $type_of_input = gettype($input);
  if ($type_of_input !== 'string') {
    return [Result::Err, "Expecting a string. Got a '$type_of_input' instead", $input];
  }

  $string_to_match_length = strlen($string_to_match);
  $input_start = substr($input, 0,  $string_to_match_length);
  if ($input_start !== $string_to_match) {
    return [Result::Err, "Expecting '$string_to_match'. Got '$input_start' instead", $input];
  }
  
  $input_rest = substr($input, $string_to_match_length);
  return [Result::Ok, $input_start, $input_rest];
};


$p_and_then = fn($parser1, $parser2) => function($input) use ($parser1, $parser2) {
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


$p_or_else = fn($parser1, $parser2) => function($input) use ($parser1, $parser2) {
  $output1 = $parser1($input);
  [$result1] = $output1;
  if($result1 === Result::Ok) {
    return $output1;
  }

  return $parser2($input);
};

$null_safe_callback = fn($callback) => fn($carry, $item) =>
  $carry === NULL ? $item : $callback($carry, $item);

$p_choice = fn($parsers) =>
  array_reduce($parsers, $null_safe_callback($p_or_else));


$p_map = fn($mapper, $parser) => function($input) use ($mapper, $parser) {
  $output = $parser($input);
  [$result, $value, $remaining] = $output;
  if($result === Result::Err) {
    return $output;
  }

  $mapped_value = $mapper($value);
  return [Result::Ok, $mapped_value, $remaining];
};


$p_left = function($parser_left, $parser_right) use ($p_and_then, $p_map) {
  $p_left_then_right = $p_and_then($parser_left, $parser_right);
  $keep_left = fn($results) => $results[0];
  
  return $p_map($keep_left, $p_left_then_right);
};


$p_right = function($parser_left, $parser_right) use ($p_and_then, $p_map) {
  $p_left_then_right = $p_and_then($parser_left, $parser_right);
  $keep_right = fn($results) => $results[1];
  
  return $p_map($keep_right, $p_left_then_right);
};


$p_between = fn($parser_left, $parser_middle, $parser_right) =>
  $p_left(
    $p_right($parser_left, $parser_middle),
    $parser_right
  );


$p_zero_or_more = function($parser) use (&$p_zero_or_more) {
  return function($input) use (&$p_zero_or_more, $parser) {
    [$first_result, $first_value, $input_after_first_parse] = $parser($input);
    if ($first_result === Result::Err) {
      return [Result::Ok, [], $input];
    }

    [, $subsequent_values, $remaining_input] = $p_zero_or_more($parser)($input_after_first_parse);
    $values = array_merge([$first_value], $subsequent_values);
    return [Result::Ok, $values, $remaining_input];
  };
};


$p_one_or_more = fn($parser) => function($input) use ($parser, $p_zero_or_more) {
  $first_output = $parser($input);
  [$first_result, $first_value, $input_after_first_parse] = $first_output;
  if($first_result === Result::Err) {
    return $first_output;
  }

  [, $subsequent_values, $remaining_input] = $p_zero_or_more($parser)($input_after_first_parse);
    $values = array_merge([$first_value], $subsequent_values);
    return [Result::Ok, $values, $remaining_input];
};


$p_return = fn($value) => fn($input) =>
  [Result::Ok, $value, $input];


$p_optional = fn($parser) =>
  $p_or_else(
    $p_map($maybe_return, $parser),
    $p_return([Maybe::Nothing])
  );

?>