<?php

function return_archive_link( $board, $resno, $admin = false, $url_only = false, $thread_id = 0 )
{
  switch( $board ) {
    case 'a':
    case 'aco':
    case 'an':
    case 'c':
    case 'co':
    case 'd':
    case 'fit':
    case 'g':
    case 'his':
    case 'int':
    case 'k':
    case 'm':
    case 'mu':
    case 'mlp':
    case 'qa':
    case 'r9k':
    case 'tg':
    case 'trash':
    case 'vr':
    case 'wsg':
      $url = "https://desuarchive.org/$board/post/$resno";
      break;
    
    case 'h':
    case 'hc':
    case 'hm':
    case 'i':
    case 'lgbt':
    case 'r':
    case 's':
    case 'soc':
    case 't':
    case 'u':
      $url = "http://archiveofsins.com/$board/post/$resno";
      break;
    
    case 'asp':
    case 'cm':
    case 'y':
    case 'b':
    case 'ck':
    case 'gd':
    case 'gif':
    case 'po':
    case 'xs':
      $url = "http://archived.moe/$board/post/$resno";
      break;
    
    case 'bant':
    case 'news':
    case 'out':
    case 'p':
    case 'pw':
    case 'e':
    case 'n':
    case 'qst':
    case 'toy':
    case 'vip':
    case 'vt':
    case 'vp':
    case 'w':
    case 'wg':
    case 'wsr':
      $url = "https://archive.palanq.win/$board/post/$resno";
      break;
    
    case 'v':
    case 'vrpg':
    case 'vmg':
    case 'vm':
    case 'vg':
    case 'vst':
      $url = "https://arch.b4k.dev/$board/post/$resno";
      break;
    
    case 'adv':
    case 'f':
    case 'hr':
    case 'o':
    case 'pol':
    case 's4s':
    case 'sp':
    case 'trv':
    case 'tv':
    case 'x':
      $url = "https://archive.4plebs.org/$board/post/$resno";
      break;
    
    case '3':
    case 'biz':
    case 'cgl':
    case 'diy':
    case 'fa':
    case 'ic':
    case 'sci':
    case 'jp':
    case 'lit':
      $url = "https://warosu.org/$board/?task=post&ghost=&post=$resno";
      break;
    /*
      if ($thread_id) {
        $url = "https://yuki.la/$board/$thread_id";
        
        if ($resno) {
          $url .= '#' . $resno;
        }
        
        break;
      }
    */
    // Return a link to the deletion log
    default:
      if ($url_only) {
        return false;
      }
      
      if (!$admin) {
        return '/' . $board . '/' . $resno;
      }
      /*
      $u = $_COOKIE['4chan_auser'];
      
      if (has_level('manager')) {
        $allow = mysql_global_call("SELECT admin,id FROM `" . SQLLOGDEL . "` WHERE postno=%d AND board='%s'", $resno, $board);
        if (!mysql_num_rows($allow)) {
          return 'Could not find admin/post.';
        }
        
        $res   = mysql_fetch_assoc( $allow );
        $admin = $res['admin'];
        $id    = $res['id'];
        
        $allow = mysql_global_call("SELECT allow FROM `" . SQLLOGMOD . "` WHERE username = '%s'", $admin);
        $res   = mysql_fetch_assoc($allow);
        $file  = (strpos( $res['allow'], 'janitor') !== false) ? 'log_janitors' : 'admin/log_moderators';
      }
      else {
        $allow = mysql_global_call("SELECT allow FROM `" . SQLLOGMOD . "` WHERE username='%s' AND allow LIKE '%s'", $admin, '%janitor%');
        
        if (!mysql_num_rows($allow)) {
          return 'None Available.';
        }
        
        $allow = mysql_global_call("SELECT admin,id FROM `" . SQLLOGDEL . "` WHERE postno=%d AND board='%s'", $resno, $board);
        
        if (!mysql_num_rows($allow)) {
          return 'Could not find post.';
        }
        
        $res  = mysql_fetch_assoc($allow);
        $id   = $res['id'];
        $file = 'log_janitors';
      }
      */
      return '<a href="https://team.4chan.org/stafflog#board=' . $board . ',post=' . $resno . '" rel="noreferrer" target="_blank">/' . $board . '/' . $resno . '</a>';
  }
  
  if ($url_only) {
    return $url;
  }
  
  $url = rawurlencode($url);
  
  return '<a href="https://www.4chan.org/derefer?url=' .
    $url . '" target="_blank">/' . $board . '/' . $resno . '</a>';
}
