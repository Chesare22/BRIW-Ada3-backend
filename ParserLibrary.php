<?php
include './Result.php';
include './Maybe.php';
include './array_foldl1.php';


$p_string = fn($string_to_match) => function($input) use ($string_to_match) {
  $type_of_input = gettype($input);
  if ($type_of_input !== 'string') {
    return [Result::Err, "Expecting a string. Got a '$type_of_input' instead", $input];
  }

  $length_of_string_to_match = strlen($string_to_match);
  $input_start = substr($input, 0,  $length_of_string_to_match);
  if ($input_start !== $string_to_match) {
    return [Result::Err, "Expecting '$string_to_match'. Got '$input_start' instead", $input];
  }
  
  $input_rest = substr($input, $length_of_string_to_match);
  return [Result::Ok, $input_start, $input_rest];
};


$p_satisfy_char = fn($predicate) => function($input) use ($predicate) {
  $type_of_input = gettype($input);
  if ($type_of_input !== 'string') {
    return [Result::Err, "Expecting a string. Got a '$type_of_input' instead", $input];
  }

  $input_first_char = substr($input, 0, 1);
  if (!$predicate($input_first_char)) {
    return [Result::Err, "Unexpected '$input_first_char'", $input];
  }

  $input_rest = substr($input, 1);
  return [Result::Ok, $input_first_char, $input_rest];
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


$p_choice =
  $array_foldl1($p_or_else);


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


$p_around = fn($parser_left, $parser_middle, $parser_right) =>
  $p_and_then(
    $p_left($parser_left, $parser_middle),
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


$p_exception = fn($stopper, $parser) => function($input) use ($stopper, $parser) {
  [$stopper_result] = $stopper($input);
  if ($stopper_result === Result::Ok) {
    return [Result::Err, 'Invalid input', $input];
  }

  return $parser($input);
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


$p_separated_by_1 = function($parser, $separator) use ($p_right, $p_and_then, $p_map, $p_zero_or_more) {
  $separator_then_parser = $p_right($separator, $parser);
  return $p_map(
    fn($values) => array_merge([$values[0]], $values[1]),
    $p_and_then($parser, $p_zero_or_more($separator_then_parser))
  );
};


$p_separated_by = fn($parser, $separator) =>
  $p_or_else(
    $p_separated_by_1($parser, $separator),
    $p_return([])
  );


$p_whitespace =
  $p_satisfy_char('ctype_space'); 


$p_zero_or_more_whitespaces =
  $p_zero_or_more($p_whitespace);


$p_one_or_more_whitespaces =
  $p_one_or_more($p_whitespace);


$p_trim = fn($parser) =>
  $p_between(
    $p_zero_or_more_whitespaces,
    $parser,
    $p_zero_or_more_whitespaces
  );


$p_concatenate = fn($parser) =>
  $p_map('implode', $parser);
?>