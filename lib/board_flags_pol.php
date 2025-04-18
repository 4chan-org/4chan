<?php

// Flag code to name mapping
function get_board_flags_array() {
  static $board_flags = array(
    'AC' => 'Anarcho-Capitalist',
    'AN' => 'Anarchist',
    'BL' => 'Black Lives Matter',
    'CF' => 'Confederate',
    'CM' => 'Commie',
    'CT' => 'Catalonia',
    'DM' => 'Democrat',
    'EU' => 'European',
    'FC' => 'Fascist',
    'GN' => 'Gadsden',
    'GY' => 'LGBT',
    'JH' => 'Jihadi',
    'KN' => 'Kekistani',
    'MF' => 'Muslim',
    'NB' => 'National Bolshevik',
    'NT' => 'NATO',
    'NZ' => 'Nazi',
    'PC' => 'Hippie',
    'PR' => 'Pirate',
    'RE' => 'Republican',
    'TM' => 'DEUS VULT',
    'MZ' => 'Task Force Z',
    'TR' => 'Tree Hugger',
    'UN' => 'United Nations',
    'WP' => 'White Supremacist'
  );
  
  return $board_flags;
}

// Flag names as they appear in the selection menu
function get_board_flags_selector() {
  static $board_flags = array(
    'AC' => 'Anarcho-Capitalist',
    'AN' => 'Anarchist',
    'BL' => 'Black Nationalist',
    'CF' => 'Confederate',
    'CM' => 'Communist',
    'CT' => 'Catalonia',
    'DM' => 'Democrat',
    'EU' => 'European',
    'FC' => 'Fascist',
    'GN' => 'Gadsden',
    'GY' => 'Gay',
    'JH' => 'Jihadi',
    'KN' => 'Kekistani',
    'MF' => 'Muslim',
    'NB' => 'National Bolshevik',
    'NT' => 'NATO',
    'NZ' => 'Nazi',
    'PC' => 'Hippie',
    'PR' => 'Pirate',
    'RE' => 'Republican',
    'MZ' => 'Task Force Z',
    'TM' => 'Templar',
    'TR' => 'Tree Hugger',
    'UN' => 'United Nations',
    'WP' => 'White Supremacist'
  );
  
  return $board_flags;
}

function board_flag_code_to_name($code) {
  $board_flags = get_board_flags_array();
  return isset($board_flags[$code]) ? $board_flags[$code] : 'None';
}
