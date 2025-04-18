<?php

class Phash {
  const IMG_SIZE = 32;
  
  // Precalculates DCT constants matrix and its transpose
  private static function dct_matrices() {
    $N = self::IMG_SIZE;
    
    static $ret = null;
    
    if ($ret !== null) {
      return $ret;
    }
    
    $dct = [];
    
    $c0 = sqrt(1.0 / $N);
    $c1 = sqrt(2.0 / $N);
    
    $hpn = M_PI / 2 / $N;
    
    for ($y = 0; $y < $N; ++$y) {
      $row = [];
      
      if ($y === 0) {
        $c = $c0;
      }
      else {
        $c = $c1;
      }
      
      for ($x = 0; $x < $N; ++$x) {
        $row[] = $c * cos($hpn * $y * (2 * $x + 1));
      }
      
      $dct[] = $row;
    }
    
    $dct_t = array_map(null, ...$dct);
    
    $ret = [ $dct, $dct_t ];
    
    return $ret;
  }
  
  private static function mat_mul($mat1, $mat2) {
    $N = self::IMG_SIZE;
    
    $ret = [];
    
    for ($i = 0; $i < $N; $i++) {
      $ret[$i] = [];
      
      for ($j = 0; $j < $N; $j++) {
        $ret[$i][$j] = 0;
        
        for ($k = 0; $k < $N; $k++) {
          $ret[$i][$j] += $mat1[$i][$k] * $mat2[$k][$j];
        }
      }
    }
    
    return $ret;
  }
  
  private static function median($array) {
    if (!$array) {
      return 0;
    }
    
    sort($array);
    
    $count = count($array);
    
    $mid = (int)($count / 2);
    
    if ($count & 1) {
      return $array[$mid];
    }
    else {
      return ($array[$mid - 1] + $array[$mid]) / 2;
    }
  }
  
  public static function hash_distance($hash1, $hash2) {
    $counts = [0,1,1,2,1,2,2,3,1,2,2,3,2,3,3,4];
    
    $res = 0;
    
    for ($i = 0; $i < 16; $i++) {
      if ($hash1[$i] != $hash2[$i]) {
        $res += $counts[hexdec($hash1[$i]) ^ hexdec($hash2[$i])];
      }
    }
    
    return $res;
  }
  
  // $input_image is a GD image instance
  public static function hash($input_image, $width, $height) {
    if (!$input_image || $width < 1 || $height < 1) {
      return false;
    }
    
    $N = self::IMG_SIZE;
    
    $img = imagecreatetruecolor($N, $N);
    
    if (!$img) {
      return false;
    }
    
    $_ret = imagecopyresampled($img, $input_image, 0, 0, 0, 0, $N, $N, $width, $height);
    
    if (!$_ret) {
      return false;
    }
    
    imagefilter($img, IMG_FILTER_GRAYSCALE);
    
    $colors = [];
    
    for ($y = 0; $y < $N; $y++){
      $row = [];
      
      for ($x = 0; $x < $N; $x++){
        $row[] = imagecolorat($img, $x, $y) & 0xFF;
      }
      
      $colors[] = $row;
    }
    
    imagedestroy($img);
    
    list($dct, $dct_t) = self::dct_matrices();
    
    $dct_img = self::mat_mul(self::mat_mul($dct, $colors), $dct_t);
    
    $subsec = [];
    
    for ($y = 1; $y < 9; ++$y) {
      for ($x = 1; $x < 9; ++$x) {
        $subsec[] = $dct_img[$y][$x];
      }
    }
    
    $median = self::median($subsec);
    
    $hash = 0;
    
    for ($i = 0; $i < 64; $i++, $hash <<= 1) {
      if ($subsec[$i] > $median) {
        $hash |= 0x01;
      }
    }
    
    return sprintf("%016x", $hash);
  }
  
  public static function hash_file($image_path, &$err = null) {
    if (!$image_path) {
      return false;
    }
    
    $size = getimagesize($image_path);
    
    if (!$size) {
      $err = "Couldn't get file information";
      return false;
    }
    
    $type = $size[2];
    
    if ($type === IMAGETYPE_PNG) {
      $img = imagecreatefrompng($image_path);
    }
    else if ($type === IMAGETYPE_JPEG) {
      $img = imagecreatefromjpeg($image_path);
    }
    else if ($type === IMAGETYPE_GIF) {
      $img = imagecreatefromgif($image_path);
    }
    else {
      $err = "Invalid image";
      return false;
    }
    
    $width = $size[0];
    $height = $size[1];
    
    $hash = self::hash($img, $width, $height);
    
    if ($hash === false) {
      $err = "Couldn't process the image";
      return false;
    }
    
    return $hash;
  }
}
