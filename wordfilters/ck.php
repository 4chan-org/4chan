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
  
  return $text;
}
