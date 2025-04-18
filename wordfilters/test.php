<?php
function word_filter_callback($m) {
  static $vals = array(
    'smh' => 'baka',
    'SMH' => 'BAKA',
    'tbh' => 'desu',
    'TBH' => 'DESU',
    'fam' => 'senpai',
    'FAM' => 'SENPAI',
    'Fam' => 'Senpai',
    'fams' => 'senpaitachi',
    'FAMS' => 'SENPAITACHI',
    'FAMs' => 'SENPAITACHI',
    'Fams' => 'Senpaitachi'
  );
  
  if (!isset($vals[$m[2]])) {
    return $m[0];
  }
  
  return "{$m[1]}{$vals[$m[2]]}{$m[3]}";
}

function word_filter_callback_soy($m) {
  $is_uc = $m[2] === strtoupper($m[2]);
  
  $lc = strtolower($m[4]);
  
  if ($lc === 'uz') {
    return $m[0];
  }
  
  if ($lc === 'im' || $lc === 'lent') {
    $m[4] = '';
  }
  
  if (!isset($m[4][1])) {
    if ($is_uc) {
      $onions = 'ONIONS';
    }
    else {
      if ($m[2][0] === 's') {
        $onions = 'onions';
      }
      else {
        $onions = 'Onions';
      }
    }
    
    return "{$m[1]}{$onions}{$m[4]}";
  }
  
  if ($m[2][0] === 's') {
    $b = 'b';
  }
  else {
    $b = 'B';
  }
  
  $ac = mb_strlen($m[3]);
  
  if ($ac == 1) {
    $a = 'a';
  }
  else {
    if ($ac < 35) {
      $a = str_repeat('a', $ac);
    }
    else {
      $a = 'a';
    }
  }
  
  $based = "{$b}{$a}sed";
  
  if ($is_uc) {
    $based = strtoupper($based);
  }
  
  return $m[1] . $based . $m[4];
}

function april_leet_filter($text) {
  $pairs = [
    [
      'a' => '4',
      'A' => '4'
    ],
    [
      'e' => '3',
      'E' => '3'
    ],
    [
      'i' => '1',
      'I' => '1'
    ],
    [
      'o' => '0',
      'O' => '0'
    ],
    [
      's' => '5',
      'S' => '5'
    ],
    [
      'T' => '7',
      't' => '7'
    ]
  ];
  
  $roll1 = mt_rand(0, 5);
  $roll2 = mt_rand(0, 5);
  
  
  if ($roll1 === 5) {
    $text = preg_replace('/([^gl])[tT]/', '${1}7', $text);
  }
  else {
    $repl = $pairs[$roll1];
    $text = strtr($text, $repl);
  }
  
  if ($roll2 != $roll1) {
    if ($roll2 === 5) {
      $text = preg_replace('/([^gl])[tT]/', '${1}7', $text);
    }
    else {
      $repl = $pairs[$roll2];
      $text = strtr($text, $repl);
    }
  }
  
  return $text;
}

// $text contents of a field
// $type name of the field
function word_filter($text, $type) {
  if ($type !== 'com') {
    return $text;
  }
  
//  $text = str_replace('Cuck', 'Kek', $text);
//  $text = str_replace('cuck', 'kek', $text);
  $text = str_replace('CUCK', 'KEK', $text);
  
  $text = preg_replace_callback('/(\b)(sjw|sjws|smh|tbh|fams|fam)(\b)/i', 'word_filter_callback', $text);
  
  $text = preg_replace_callback('/(\b)(s([o0οоօჿ]+)[yуΥ])(\b|[[:alpha:]]{2,4})/iu', 'word_filter_callback_soy', $text);
  
  $text = april_leet_filter($text);
  
  return $text;
}
