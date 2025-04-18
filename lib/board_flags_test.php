<?php

// Flag code to name mapping
function get_board_flags_array() {
  static $board_flags = array(
    'FL1' => 'Flag 1',
    'FL2' => 'Flag 2'
  );
  
  return $board_flags;
}

// Flag names as they appear in the selection menu
function get_board_flags_selector() {
  static $board_flags = array(
    'FL1' => 'Flag 1',
    'FL2' => 'Flag 2'
  );
  
  return $board_flags;
}

function board_flag_code_to_name($code) {
  $board_flags = get_board_flags_array();
  return isset($board_flags[$code]) ? $board_flags[$code] : 'None';
}
