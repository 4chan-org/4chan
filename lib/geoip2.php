<?php

//require_once('MaxMind-DB-Reader/autoload.php');

final class GeoIP2 {
  private static
    $db_file_country    = '/usr/local/share/GeoIP2/GeoLite2-City.mmdb',
    $db_file_asn        = '/usr/local/share/GeoIP2/GeoLite2-ASN.mmdb'
  ;
  
  private static
    $mmdb_country       = null,
    $mmdb_asn           = null
  ;
  
  private function __construct() {}
  
  private static function load_db($file) {
    try {
      return new MaxMind\Db\Reader($file);
    } catch (Exception $e) {
      return false;
    }
  }
  
  // geoip_record_by_name
  public static function get_country($ip) {
    if (!$ip) {
      return null;
    }
    
    if (self::$mmdb_country === null) {
      self::$mmdb_country = self::load_db(self::$db_file_country);
    }
    
    if (!self::$mmdb_country) {
      return null;
    }
    
    try {
      $entry = self::$mmdb_country->get($ip);
    } catch (Exception $e) {
      return null;
    }
    
    $data = array();
    
    // Continent
    if (isset($entry['continent']['code'])) {
      $data['continent_code'] = $entry['continent']['code'];
    }
    
    // Country
    if (isset($entry['country']['iso_code'])) {
      $data['country_code'] = $entry['country']['iso_code'];
      $data['country_name'] = $entry['country']['names']['en'];
      
      // State for US
      if ($data['country_code'] === 'US' && isset($entry['subdivisions'][0]['iso_code'])) {
        $data['state_code'] = $entry['subdivisions'][0]['iso_code'];
        $data['state_name'] = $entry['subdivisions'][0]['names']['en'];
      }
      // FIXME: subdivisions for UK during sport events
      else if ($data['country_code'] === 'GB' && isset($entry['subdivisions'][0]['iso_code'])) {
        $data['sub_code'] = $entry['subdivisions'][0]['iso_code'];
      }
    }
    
    if (isset($entry['city']['names']['en'])) {
      $data['city_name'] = $entry['city']['names']['en'];
    }
    
    if (empty($data)) {
      return null;
    }
    
    return $data;
  }
  
  public static function get_asn($ip) {
    if (!$ip) {
      return null;
    }
    
    if (self::$mmdb_asn === null) {
      self::$mmdb_asn = self::load_db(self::$db_file_asn);
    }
    
    if (!self::$mmdb_asn) {
      return null;
    }
    
    try {
      $entry = self::$mmdb_asn->get($ip);
    } catch (Exception $e) {
      return null;
    }
    
    $data = array();
    
    if (isset($entry['autonomous_system_number'])) {
      $data['asn'] = $entry['autonomous_system_number'];
    }
    
    if (isset($entry['autonomous_system_organization'])) {
      $data['aso'] = $entry['autonomous_system_organization'];
    }
    
    if (empty($data)) {
      return null;
    }
    
    return $data;
  }
}
