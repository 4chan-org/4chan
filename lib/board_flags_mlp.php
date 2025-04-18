<?php

// Flag code to name mapping
function get_board_flags_array() {
  static $board_flags = array(
    '4CC' => '4cc /mlp/',
    'ADA' => 'Adagio Dazzle',
    'AN' => 'Anon',
    'ANF' => 'Anonfilly',
    'APB' => 'Apple Bloom',
    'AJ' => 'Applejack',
    'AB' => 'Aria Blaze',
    'AU' => 'Autumn Blaze',
    'BB' => 'Bon Bon',
    'BM' => 'Big Mac',
    'BP' => 'Berry Punch',
    'BS' => 'Babs Seed',
    'CL' => 'Changeling',
    'CO' => 'Coco Pommel',
    'CG' => 'Cozy Glow',
    'CHE' => 'Cheerilee',
    'CB' => 'Cherry Berry',
    'DAY' => 'Daybreaker',
    'DD' => 'Daring Do',
    'DER' => 'Derpy Hooves',
    'DT' => 'Diamond Tiara',
    'DIS' => 'Discord',
    'EQA' => 'EqG Applejack',
    'EQF' => 'EqG Fluttershy',
    'EQP' => 'EqG Pinkie Pie',
    'EQR' => 'EqG Rainbow Dash',
    'EQT' => 'EqG Trixie',
    'EQI' => 'EqG Twilight Sparkle',
    'EQS' => 'EqG Sunset Shimmer',
    'ERA' => 'EqG Rarity',
    'FAU' => 'Fausticorn',
    'FLE' => 'Fleur de lis',
    'FL' => 'Fluttershy',
    'GI' => 'Gilda',
    'HT' => 'Hitch Trailblazer',
    'IZ' => 'Izzy Moonbow',
    'LI' => 'Limestone',
    'LT' => 'Lord Tirek',
    'LY' => 'Lyra Heartstrings',
    'MA' => 'Marble',
    'MAU' => 'Maud',
    'MIN' => 'Minuette',
    'NI' => 'Nightmare Moon',
    'NUR' => 'Nurse Redheart',
    'OCT' => 'Octavia',
    'PAR' => 'Parasprite',
    'PC' => 'Princess Cadance',
    'PCE' => 'Princess Celestia',
    'PI' => 'Pinkie Pie',
    'PLU' => 'Princess Luna',
    'PM' => 'Pinkamena',
    'PP' => 'Pipp Petals',
    'QC' => 'Queen Chrysalis',
    'RAR' => 'Rarity',
    'RD' => 'Rainbow Dash',
    'RLU' => 'Roseluck',
    'S1L' => 'S1 Luna',
    'SCO' => 'Scootaloo',
    'SHI' => 'Shining Armor',
    'SIL' => 'Silver Spoon',
    'SON' => 'Sonata Dusk',
    'SP' => 'Spike',
    'SPI' => 'Spitfire',
    'SS' => 'Sunny Starscout',
    'STA' => 'Star Dancer',
    'STL' => 'Starlight Glimmer',
    'SPT' => 'Sprout',
    'SUN' => 'Sunburst',
    'SUS' => 'Sunset Shimmer',
    'SWB' => 'Sweetie Belle',
    'TFA' => 'TFH Arizona',
    'TFO' => 'TFH Oleander',
    'TFP' => 'TFH Paprika',
    'TFS' => 'TFH Shanty',
    'TFT' => 'TFH Tianhuo',
    'TFV' => 'TFH Velvet',
    'TP' => 'TFH Pom',
    'TS' => 'Tempest Shadow',
    'TWI' => 'Twilight Sparkle',
    'TX' => 'Trixie',
    'VS' => 'Vinyl Scratch',
    'ZE' => 'Zecora',
    'ZS' => 'Zipp Storm'
  );
  
  return $board_flags;
}

// Flag names as they appear in the selection menu
function get_board_flags_selector() {
    return get_board_flags_array();
}

function board_flag_code_to_name($code) {
  $board_flags = get_board_flags_array();
  return isset($board_flags[$code]) ? $board_flags[$code] : 'None';
}
