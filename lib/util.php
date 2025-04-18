<?

// Linker for boards since we need to know the subdomain
final class L {
  static $nws = array('aco'=>true,'b'=>true,'bant'=>true,'d'=>true,'e'=>true,'f'=>true,'gif'=>true,'h'=>true,'hc'=>true,'hm'=>true,'hr'=>true,'i'=>true,'ic'=>true,'pol'=>true,'r'=>true,'r9k'=>true,'s'=>true,'s4s'=>true,'soc'=>true,'t'=>true,'trash'=>true,'u'=>true,'wg'=>true,'y'=>true);
  
  private static $blue = '4chan.org'; // Domain for worksafe boards
  private static $red = '4chan.org'; // Domain for nws boards
  
  static public function d($board) {
    return isset(self::$nws[$board]) ? self::$red : self::$blue;
  }
}

// FIXME ipv6
function cidrtest ($longip, $CIDR) {
	list ($net, $mask) = explode("/", $CIDR);
	$mask = (int)$mask;
	
	if (!$mask) return false;

	$ip_net = ip2long ($net);
	
	if (!$ip_net) return false;
	
	$ip_mask = ~((1 << (32 - $mask)) - 1);

	$ip_ip = $longip;

	$ip_ip_net = $ip_ip & $ip_mask;

	return ($ip_ip_net == $ip_net);
}

function internal_error_log($cat, $err, $trace=true) {
	$err = sprintf("[%s error] %s", $cat, $err);

	error_log($err);
	if ($trace) {
	ob_start();
	debug_print_backtrace();
	error_log(ob_get_contents());
	ob_end_clean();
	}
}

function quick_log_to( $f, $s, $do_bt=false )
{
	if ($do_bt) {
	ob_start();
	debug_print_backtrace();
	$bt = ob_get_contents();
	ob_end_clean();
	}
	
	$out = ($do_bt ? $bt : date("r")." ".$s)."\n\n";

	$h = fopen( $f, "a" );
	flock( $h, LOCK_EX );
	fwrite( $h, $out);
	fclose( $h );
}

function post_filter_get($base)
{
	$path = "/www/global/yotsuba/filters/";

	$strs = @file("$path$base.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	$res  = @file("$path$base-re.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

	if (!is_array($strs)) $strs = array();
	if (!is_array($res)) $res = array();

	return array($strs, $res);
}

function find_ipxff_in($longip,$longxff,$ips)
{
	$badip = false;

	foreach($ips as $cidr)
	{
		if ($cidr[0] == '#' || !$cidr) continue;

		if(cidrtest($longip, $cidr) || cidrtest($longxff, $cidr))
		{
			$badip = true;
			break;
		}
	}

	return $badip;
}

function utf8_wordwrap( $string, $width = 75, $break = "\n", $cut = false )
{
	if( $cut ) {
		// Cut lines that are too long by hand, even if they aren't official break opportunities
		$search  = '/(.{' . $width . '})/uS';
		$replace = '$1$2' . $break;
	}

	return preg_replace( $search, $replace, $string );
}

function xhprof_save()
{
	$xhprof_data = xhprof_disable();

	include_once XHPROF_ROOT . "/xhprof_lib/utils/xhprof_lib.php";
	include_once XHPROF_ROOT . "/xhprof_lib/utils/xhprof_runs.php";

	$xhprof_runs = new XHProfRuns_Default();

	$name = basename($_SERVER["SELF_PATH"]);
	$name = preg_replace("/\..*$/","",$name);
	
	$run_id = $xhprof_runs->save_run($xhprof_data, $name);
}

function record_post_info($fn, $ex="")
{
	$rh = fopen($fn, "a");
	flock($rh, LOCK_EX);
	fwrite($rh, print_r($_SERVER, TRUE));
	fwrite($rh, print_r($_REQUEST, TRUE));
	fwrite($rh, print_r($HTTP_RAW_POST_DATA, TRUE));
	fwrite($rh, print_r($_FILES, TRUE));
	if ($ex) fwrite($rh, $ex);
	fwrite($rh, "---\n");
	fclose($rh);
}

function sec2hms($sec, $padTime = false, $showSeconds = false)
{
    $hms = "";
    $hours = intval(intval($sec) / 3600);
	$minutes = intval(intval($sec) / 60);

	if ($hours) {
    $hms .= $padTime
          ? str_pad($hours, 2, "0", STR_PAD_LEFT). " hour"
          : $hours. " hour";
    if( $hours != 1 ) $hms .= 's';

	$hms .= ' ';
	}

	if ($minutes) {
    $minutes = intval(($sec / 60) % 60);

    $hms .= $padTime
			? str_pad($minutes, 2, "0", STR_PAD_LEFT). " minute"
			: $minutes. " minute";
    if( $minutes != 1 ) $hms .= 's';
	}

	$seconds = intval($sec % 60);

	if ($showSeconds && $seconds) {
		$hms .= ' ';
    	$hms .= $padTime
				? str_pad($seconds, 2, "0", STR_PAD_LEFT). " second"
				: $seconds. " second";
		if ($seconds != 1) $hms .= "s";
	}

    return $hms;
}

function flush_output_buffers()
{
	ob_flush();
	flush();
}

function country_table()
{
	/** COUNTRY CODE */
	static $countryLookupArray = array(
		'AD' => 'Andorra',
		'AE' => 'United Arab Emirates',
		'AF' => 'Afghanistan',
		'AG' => 'Antigua and Barbuda',
		'AI' => 'Anguilla',
		'AL' => 'Albania',
		'AM' => 'Armenia',
		'AN' => 'Netherlands Antilles',
		'AO' => 'Angola',
		'AQ' => 'Antarctica',
		'AR' => 'Argentina',
		'AS' => 'American Samoa',
		'AT' => 'Austria',
		'AU' => 'Australia',
		'AW' => 'Aruba',
		'AX' => 'Aland',
		'AZ' => 'Azerbaijan',
		'BA' => 'Bosnia and Herzegovina',
		'BB' => 'Barbados',
		'BD' => 'Bangladesh',
		'BE' => 'Belgium',
		'BF' => 'Burkina Faso',
		'BG' => 'Bulgaria',
		'BH' => 'Bahrain',
		'BI' => 'Burundi',
		'BJ' => 'Benin',
		'BL' => 'Saint Barthélemy',
		'BM' => 'Bermuda',
		'BN' => 'Brunei',
		'BO' => 'Bolivia',
		'BQ' => 'Bonaire, Sint Eustatius and Saba',
		'BR' => 'Brazil',
		'BS' => 'Bahamas',
		'BT' => 'Bhutan',
		'BV' => 'Bouvet Island',
		'BW' => 'Botswana',
		'BY' => 'Belarus',
		'BZ' => 'Belize',
		'CA' => 'Canada',
		'CC' => 'Cocos (Keeling) Islands',
		'CD' => 'The Democratic Republic of the Congo',
		'CF' => 'Central African Republic',
		'CG' => 'Congo',
		'CH' => 'Switzerland',
		'CI' => 'Côte d\'Ivoire',
		'CK' => 'Cook Islands',
		'CL' => 'Chile',
		'CM' => 'Cameroon',
		'CN' => 'China',
		'CO' => 'Colombia',
		'CR' => 'Costa Rica',
		'CU' => 'Cuba',
		'CV' => 'Cape Verde',
		'CW' => 'Curaçao',
		'CX' => 'Christmas Island',
		'CY' => 'Cyprus',
		'CZ' => 'Czech Republic',
		'DE' => 'Germany',
		'DJ' => 'Djibouti',
		'DK' => 'Denmark',
		'DM' => 'Dominica',
		'DO' => 'Dominican Republic',
		'DZ' => 'Algeria',
		'EC' => 'Ecuador',
		'EE' => 'Estonia',
		'EG' => 'Egypt',
		'EH' => 'Western Sahara',
		'ER' => 'Eritrea',
		'ES' => 'Spain',
		'ET' => 'Ethiopia',
		'EU' => 'Europe',
		'FI' => 'Finland',
		'FJ' => 'Fiji Islands',
		'FK' => 'Falkland Islands',
		'FM' => 'Federated States of Micronesia',
		'FO' => 'Faroe Islands',
		'FR' => 'France',
		'GA' => 'Gabon',
		'GB' => 'United Kingdom',
		'GD' => 'Grenada',
		'GE' => 'Georgia',
		'GF' => 'French Guiana',
		'GG' => 'Guernsey',
		'GH' => 'Ghana',
		'GI' => 'Gibraltar',
		'GL' => 'Greenland',
		'GM' => 'Gambia',
		'GN' => 'Guinea',
		'GP' => 'Guadeloupe',
		'GQ' => 'Equatorial Guinea',
		'GR' => 'Greece',
		'GS' => 'South Georgia and the South Sandwich Islands',
		'GT' => 'Guatemala',
		'GU' => 'Guam',
		'GW' => 'Guinea-Bissau',
		'GY' => 'Guyana',
		'HK' => 'Hong Kong',
		'HM' => 'Heard Island and McDonald Islands',
		'HN' => 'Honduras',
		'HR' => 'Croatia',
		'HT' => 'Haiti',
		'HU' => 'Hungary',
		'ID' => 'Indonesia',
		'IE' => 'Ireland',
		'IL' => 'Israel',
		'IM' => 'Isle of Man',
		'IN' => 'India',
		'IO' => 'British Indian Ocean Territory',
		'IQ' => 'Iraq',
		'IR' => 'Iran',
		'IS' => 'Iceland',
		'IT' => 'Italy',
		'JE' => 'Jersey',
		'JM' => 'Jamaica',
		'JO' => 'Jordan',
		'JP' => 'Japan',
		'KE' => 'Kenya',
		'KG' => 'Kyrgyzstan',
		'KH' => 'Cambodia',
		'KI' => 'Kiribati',
		'KM' => 'Comoros',
		'KN' => 'Saint Kitts and Nevis',
		'KP' => 'North Korea',
		'KR' => 'South Korea',
		'KW' => 'Kuwait',
		'KY' => 'Cayman Islands',
		'KZ' => 'Kazakhstan',
		'LA' => 'Laos',
		'LB' => 'Lebanon',
		'LC' => 'Saint Lucia',
		'LI' => 'Liechtenstein',
		'LK' => 'Sri Lanka',
		'LR' => 'Liberia',
		'LS' => 'Lesotho',
		'LT' => 'Lithuania',
		'LU' => 'Luxembourg',
		'LV' => 'Latvia',
		'LY' => 'Libya',
		'MA' => 'Morocco',
		'MC' => 'Monaco',
		'MD' => 'Moldova',
		'ME' => 'Montenegro',
		'MF' => 'Saint Martin',
		'MG' => 'Madagascar',
		'MH' => 'Marshall Islands',
		'MK' => 'Macedonia',
		'ML' => 'Mali',
		'MM' => 'Myanmar',
		'MN' => 'Mongolia',
		'MO' => 'Macao',
		'MP' => 'Northern Mariana Islands',
		'MQ' => 'Martinique',
		'MR' => 'Mauritania',
		'MS' => 'Montserrat',
		'MT' => 'Malta',
		'MU' => 'Mauritius',
		'MV' => 'Maldives',
		'MW' => 'Malawi',
		'MX' => 'Mexico',
		'MY' => 'Malaysia',
		'MZ' => 'Mozambique',
		'NA' => 'Namibia',
		'NC' => 'New Caledonia',
		'NE' => 'Niger',
		'NF' => 'Norfolk Island',
		'NG' => 'Nigeria',
		'NI' => 'Nicaragua',
		'NL' => 'Netherlands',
		'NO' => 'Norway',
		'NP' => 'Nepal',
		'NR' => 'Nauru',
		'NU' => 'Niue',
		'NZ' => 'New Zealand',
		'OM' => 'Oman',
		'PA' => 'Panama',
		'PE' => 'Peru',
		'PF' => 'French Polynesia',
		'PG' => 'Papua New Guinea',
		'PH' => 'Philippines',
		'PK' => 'Pakistan',
		'PL' => 'Poland',
		'PM' => 'Saint Pierre and Miquelon',
		'PN' => 'Pitcairn',
		'PR' => 'Puerto Rico',
		'PS' => 'Palestine',
		'PT' => 'Portugal',
		'PW' => 'Palau',
		'PY' => 'Paraguay',
		'QA' => 'Qatar',
		'RE' => 'Réunion',
		'RO' => 'Romania',
		'RS' => 'Serbia',
		'RU' => 'Russian Federation',
		'RW' => 'Rwanda',
		'SA' => 'Saudi Arabia',
		'SB' => 'Solomon Islands',
		'SC' => 'Seychelles',
		'SD' => 'Sudan',
		'SE' => 'Sweden',
		'SG' => 'Singapore',
		'SH' => 'Saint Helena, Ascension, and Tristan da Cunha',
		'SI' => 'Slovenia',
		'SJ' => 'Svalbard and Jan Mayen',
		'SK' => 'Slovakia',
		'SL' => 'Sierra Leone',
		'SM' => 'San Marino',
		'SN' => 'Senegal',
		'SO' => 'Somalia',
		'SR' => 'Suriname',
		'SS' => 'South Sudan',
		'ST' => 'Sao Tome and Principe',
		'SV' => 'El Salvador',
		'SX' => 'Sint Maarten',
		'SY' => 'Syria',
		'SZ' => 'Swaziland',
		'TC' => 'Turks and Caicos Islands',
		'TD' => 'Chad',
		'TF' => 'French Southern Territories',
		'TG' => 'Togo',
		'TH' => 'Thailand',
		'TJ' => 'Tajikistan',
		'TK' => 'Tokelau',
		'TM' => 'Turkmenistan',
		'TN' => 'Tunisia',
		'TO' => 'Tonga',
		'TP' => 'East Timor',
		'TR' => 'Turkey',
		'TT' => 'Trinidad and Tobago',
		'TV' => 'Tuvalu',
		'TW' => 'Taiwan',
		'TZ' => 'Tanzania',
		'UA' => 'Ukraine',
		'UG' => 'Uganda',
		'UM' => 'United States Minor Outlying Islands',
		'US' => 'United States',
		'UY' => 'Uruguay',
		'UZ' => 'Uzbekistan',
		'VA' => 'Holy See (Vatican City State)',
		'VC' => 'Saint Vincent and the Grenadines',
		'VE' => 'Venezuela',
		'VG' => 'British Virgin Islands',
		'VI' => 'U.S. Virgin Islands',
		'VN' => 'Vietnam',
		'VU' => 'Vanuatu',
		'WF' => 'Wallis and Futuna',
		'WS' => 'Samoa',
		'XE' => 'England',
		'XK' => 'Kosovo',
		'XS' => 'Scotland',
		'XW' => 'Wales',
		'YE' => 'Yemen',
		'YT' => 'Mayotte',
		'YU' => 'Yugoslavia',
		'ZA' => 'South Africa',
		'ZM' => 'Zambia',
		'ZW' => 'Zimbabwe',
		'XX' => 'Unknown');
	
	return $countryLookupArray;
}

function country_code_to_name( $code )
{
	$countryLookupArray = country_table();

	return isset( $countryLookupArray[$code] ) ? $countryLookupArray[$code] : $countryLookupArray['XX'];
}

function troll_countries() {
  static $trollCountries = array(
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
    'NZ' => 'Nazi',
    'PC' => 'Hippie',
    'PR' => 'Pirate',
    'RE' => 'Republican',
    'TM' => 'DEUS VULT',
    'TR' => 'Tree Hugger',
    'UN' => 'United Nations',
    'WP' => 'White Supremacist'
  );
  
  return $trollCountries;
}

// For flag selection dropdown menu
function troll_countries_selector() {
  static $trollCountries = array(
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
    'NZ' => 'Nazi',
    'PC' => 'Hippie',
    'PR' => 'Pirate',
    'RE' => 'Republican',
    'TM' => 'Templar',
    'TR' => 'Tree Hugger',
    'UN' => 'United Nations',
    'WP' => 'White Supremacist'
  );
  
  return $trollCountries;
}

function country_code_to_name_troll( $code )
{
	$trollCountries = troll_countries();

	return isset( $trollCountries[$code] ) ? $trollCountries[$code] : $trollCountries['IL'];
}

