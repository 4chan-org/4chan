<?php

function perk_options_process_comment($options_str, &$comment) {
  if (strlen($options_str > 50)) {
    return false;
  }
  
  $perks = explode(' ', $options_str);
  
  if (empty($perks)) {
    return false;
  }
  
  if (count($perks) > 3) {
    $perks = array_slice($perks, 0, 3);
  }
  
  $all_perks = array(
    'smiley','happy','neutral','sad','ok','check','cross',
    'whiteflag','blackflag','like','rabbit','nolove','anger','perfect',
    'onsen','heart','joy','pigface','dogface','catface','monkeyface',
    'frogface','tigerface','lionface','bearface','chicken','eagle','cowface',
    'babychick','rainbow','star','fire','zap','water','maple','animal','food',
    'apple','banana','tomato','hamburger','potato','carrot','popcorn','pizza',
    'dango','beer','football','superball','vidya','thoughts','confetti',
    'lightbulb','book','creditcard','music','rpg'
  );
  
  $html_perks = array();
  
  foreach ($perks as $perk) {
    if ($perk === 'randomperk') {
      $perk = $all_perks[array_rand($all_perks)];
    }
    
    switch ($perk) {
      case 'smiley':
        $html_perks[] = "<ins title=\"$perk\">&#x1F642;</ins>";
        break;
      case 'happy':
        $html_perks[] = "<ins title=\"$perk\">&#x1F603;</ins>";
        break;
      case 'neutral':
        $html_perks[] = "<ins title=\"$perk\">&#x1F610;</ins>";
        break;
      case 'sad':
        $html_perks[] = "<ins title=\"$perk\">&#x1F641;</ins>";
        break;
      case 'ok':
        $html_perks[] = "<ins title=\"$perk\">&#x1F44C;</ins>";
        break;
      case 'check':
        $html_perks[] = "<ins title=\"$perk\">&#x2714;&#xFE0F;</ins>";
        break;
      case 'cross':
        $html_perks[] = "<ins title=\"$perk\">&#x274C;</ins>";
        break;
      case 'whiteflag':
        $html_perks[] = "<ins title=\"$perk\">&#x1F3F3;&#xFE0F;</ins>";
        break;
      case 'blackflag':
        $html_perks[] = "<ins title=\"$perk\">&#x1F3F4;</ins>";
        break;
      case 'like':
        $html_perks[] = "<ins title=\"$perk\">&#x2764;&#xFE0F;</ins>";
        break;
      case 'rabbit':
        $html_perks[] = "<ins title=\"$perk\">&#x1F430;</ins>";
        break;
      case 'nolove':
        $html_perks[] = "<ins title=\"$perk\">&#x1F494;</ins>";
        break;
      case 'anger':
        $html_perks[] = "<ins title=\"$perk\">&#x1F4A2;</ins>";
        break;
      case 'perfect':
        $html_perks[] = "<ins title=\"$perk\">&#x1F4AF;</ins>";
        break;
      case 'onsen':
        $html_perks[] = "<ins title=\"$perk\">&#x2668;&#xFE0F;</ins>";
        break;
      case 'heart':
        $ary = array('&#x2764;&#xFE0F;','&#x1F499;','&#x1F49C;','&#x1F49B;','&#x1F5A4;','&#x1F49A;');
        $html_perks[] = "<ins title=\"$perk\">" . $ary[array_rand($ary)] . '</ins>';
        break;
      case 'joy':
        $html_perks[] = "<ins title=\"$perk\">&#x1F602;</ins>";
        break;
      case 'pigface':
        $html_perks[] = "<ins title=\"$perk\">&#x1F437;</ins>";
        break;
      case 'dogface':
        $html_perks[] = "<ins title=\"$perk\">&#x1F436;</ins>";
        break;
      case 'catface':
        $html_perks[] = "<ins title=\"$perk\">&#x1F431;</ins>";
        break;
      case 'monkeyface':
        $html_perks[] = "<ins title=\"$perk\">&#x1F435;</ins>";
        break;
      case 'frogface':
        $html_perks[] = "<ins title=\"$perk\">&#x1F438;</ins>";
        break;
      case 'tigerface':
        $html_perks[] = "<ins title=\"$perk\">&#x1F42F;</ins>";
        break;
      case 'lionface':
        $html_perks[] = "<ins title=\"$perk\">&#x1F981;</ins>";
        break;
      case 'bearface':
        $html_perks[] = "<ins title=\"$perk\">&#x1F43B;</ins>";
        break;
      case 'chicken':
        $html_perks[] = "<ins title=\"$perk\">&#x1F414;</ins>";
        break;
      case 'eagle':
        $html_perks[] = "<ins title=\"$perk\">&#x1F985;</ins>";
        break;
      case 'cowface':
        $html_perks[] = "<ins title=\"$perk\">&#x1F42E;</ins>";
        break;
      case 'babychick':
        $html_perks[] = "<ins title=\"$perk\">&#x1F424;</ins>";
        break;
      case 'rainbow':
        $html_perks[] = "<ins title=\"$perk\">&#x1F308;</ins>";
        break;
      case 'star':
        $html_perks[] = "<ins title=\"$perk\">&#x2B50;</ins>";
        break;
      case 'fire':
        $html_perks[] = "<ins title=\"$perk\">&#x1F525;</ins>";
        break;
      case 'zap':
        $html_perks[] = "<ins title=\"$perk\">&#x26A1;</ins>";
        break;
      case 'water':
        $html_perks[] = "<ins title=\"$perk\">&#x1F4A7;</ins>";
        break;
      case 'maple':
        $html_perks[] = "<ins title=\"$perk\">&#x1F341;</ins>";
        break;
      
      case 'animal':
        $ary = array('&#x1F42D;','&#x1F439;','&#x1F430;','&#x1F436;','&#x1F43A;','&#x1F98A;','&#x1F435;','&#x1F438;','&#x1F648;','&#x1F649;','&#x1F64A;','&#x1F42F;','&#x1F981;','&#x1F993;','&#x1F992;','&#x1F434;','&#x1F42E;','&#x1F437;','&#x1F43B;','&#x1F43C;','&#x1F432;','&#x1F984;','&#x1F431;','&#x1F638;','&#x1F639;','&#x1F63A;','&#x1F63B;','&#x1F63C;','&#x1F63D;','&#x1F63E;','&#x1F63F;','&#x1F640;','&#x1F405;','&#x1F406;','&#x1F418;','&#x1F98F;','&#x1F402;','&#x1F403;','&#x1F404;','&#x1F40E;','&#x1F98C;','&#x1F410;','&#x1F40F;','&#x1F411;','&#x1F416;','&#x1F417;','&#x1F42A;','&#x1F42B;','&#x1F98D;','&#x1F409;','&#x1F996;','&#x1F995;','&#x1F408;','&#x1F400;','&#x1F401;','&#x1F407;','&#x1F412;','&#x1F415;','&#x1F429;','&#x1F428;','&#x1F43F;','&#x1F994;','&#x1F987;','&#x1F40D;','&#x1F985;','&#x1F989;','&#x1F986;','&#x1F413;','&#x1F414;','&#x1F983;','&#x1F54A;','&#x1F423;','&#x1F424;','&#x1F425;','&#x1F426;','&#x1F427;','&#x1F40B;','&#x1F433;','&#x1F42C;','&#x1F988;','&#x1F41F;','&#x1F420;','&#x1F421;','&#x1F419;','&#x1F991;','&#x1F990;','&#x1F980;','&#x1F41A;','&#x1F40C;','&#x1F422;','&#x1F98E;','&#x1F40A;','&#x1F3C7;','&#x1F3A0;','&#x2658;','&#x265E;','&#x1F43D;','&#x1F43E;','&#x1F463;','&#x1F400;','&#x1F403;','&#x1F405;','&#x1F407;','&#x1F409;','&#x1F40D;','&#x1F40E;','&#x1F410;','&#x1F412;','&#x1F413;','&#x1F415;','&#x1F416;');
        $html_perks[] = "<ins title=\"$perk\">" . $ary[array_rand($ary)] . '</ins>';
        break;
      
      case 'food':
        $ary = array('&#x1F9C0;','&#x1F95A;','&#x1F373;','&#x1F95E;','&#x1F360;','&#x1F35E;','&#x1F950;','&#x1F956;','&#x1F968;','&#x1F354;','&#x1F355;','&#x1F35D;','&#x1F35F;','&#x1F364;','&#x1F32D;','&#x1F32E;','&#x1F32F;','&#x1F35B;','&#x1F959;','&#x1F958;','&#x1F957;','&#x1F96A;','&#x1F96B;','&#x1F953;','&#x1F356;','&#x1F357;','&#x1F969;','&#x1F962;','&#x1F961;','&#x1F95F;','&#x1F35A;','&#x1F35C;','&#x1F372;','&#x1F960;','&#x1F358;','&#x1F359;','&#x1F363;','&#x1F365;','&#x1F371;','&#x1F361;','&#x1F362;','&#x1F347;','&#x1F348;','&#x1F349;','&#x1F34A;','&#x1F34B;','&#x1F34C;','&#x1F34D;','&#x1F34E;','&#x1F34F;','&#x1F350;','&#x1F351;','&#x1F352;','&#x1F353;','&#x1F95D;','&#x1F965;','&#x1F966;','&#x1F344;','&#x1F345;','&#x1F346;','&#x1F336;','&#x1F951;','&#x1F955;','&#x1F952;','&#x1F954;','&#x1F95C;','&#x1F370;','&#x1F382;','&#x1F967;','&#x1F368;','&#x1F366;','&#x1F369;','&#x1F36A;','&#x1F37F;','&#x1F36E;','&#x1F36F;','&#x1F367;','&#x1F36B;','&#x1F36C;','&#x1F36D;','&#x1F37A;','&#x1F37B;','&#x1F377;','&#x1F378;','&#x1F379;','&#x1F376;','&#x1F942;','&#x1F943;','&#x1F37E;','&#x2615;','&#x1F375;','&#x1F95B;','&#x1F37C;','&#x1F964;','&#x1F374;','&#x1F37D;','&#x1F963;','&#x1F944;');
        $html_perks[] = "<ins title=\"$perk\">" . $ary[array_rand($ary)] . '</ins>';
        break;
      
      case 'apple':
        $ary = array('&#x1F34F;','&#x1F34E;');
        $html_perks[] = "<ins title=\"$perk\">" . $ary[array_rand($ary)] . '</ins>';
        break;
      case 'banana':
        $html_perks[] = "<ins title=\"$perk\">&#x1F34C;</ins>";
        break;
      case 'tomato':
        $html_perks[] = "<ins title=\"$perk\">&#x1F345;</ins>";
        break;
      case 'hamburger':
        $html_perks[] = "<ins title=\"$perk\">&#x1F354;</ins>";
        break;
      case 'potato':
        $html_perks[] = "<ins title=\"$perk\">&#x1F954;</ins>";
        break;
      case 'carrot':
        $html_perks[] = "<ins title=\"$perk\">&#x1F955;</ins>";
        break;
      case 'popcorn':
        $html_perks[] = "<ins title=\"$perk\">&#x1F37F;</ins>";
        break;
      case 'pizza':
        $html_perks[] = "<ins title=\"$perk\">&#x1F355;</ins>";
        break;
      case 'dango':
        $html_perks[] = "<ins title=\"$perk\">&#x1F361;</ins>";
        break;
      case 'beer':
        $html_perks[] = "<ins title=\"$perk\">&#x1F37A;</ins>";
        break;
      case 'football':
        $html_perks[] = "<ins title=\"$perk\">&#x26BD;</ins>";
        break;
      case 'superball':
        $html_perks[] = "<ins title=\"$perk\">&#x1F3C8;</ins>";
        break;
      case 'vidya':
        $html_perks[] = "<ins title=\"$perk\">&#x1F3AE;</ins>";
        break;
      case 'thoughts':
        $html_perks[] = "<ins title=\"$perk\">&#x1F4AD;</ins>";
        break;
      case 'confetti':
        $html_perks[] = "<ins title=\"$perk\">&#x1F389;</ins>";
        break;
      case 'lightbulb':
        $html_perks[] = "<ins title=\"$perk\">&#x1F4A1;</ins>";
        break;
      case 'book':
        $ary = array('&#x1F4D5;','&#x1F4D9;','&#x1F4D8;','&#x1F4D7;');
        $html_perks[] = "<ins title=\"$perk\">" . $ary[array_rand($ary)] . '</ins>';
        break;
      case 'creditcard':
        $html_perks[] = "<ins title=\"$perk\">&#x1F4B3;</ins>";
        break;
      case 'music':
        $html_perks[] = "<ins title=\"$perk\">&#x1F3B5;</ins>";
        break;
      case 'rpg':
        $ary = array('&#x270A;','&#x270B;','&#x270C;');
        $html_perks[] = "<ins title=\"$perk\">" . $ary[array_rand($ary)] . '</ins>';
        break;
      
      default:
        break;
    }

  }
  
  if (!empty($html_perks)) {
    $comment = $comment . '<div class="perk-cnt">' . implode(' ', $html_perks) . '</div>';
    return true;
  }
  
  return false;
}
