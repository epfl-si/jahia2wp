<?php
/**
 * Filename: geoip.php
 * Count Per Day - GeoIP Addon
 */

if (!defined('ABSPATH'))
	exit;

if (!class_exists('GeoIp'))
	include_once($cpd_geoip_dir.'geoip.inc');

class CpdGeoIp
{

/**
 * gets country of ip adress
 * @param $ip IP
 * @return array e.g. ( 'de', image div , 'Germany' )
 */
static function getCountry( $ip )
{
	global $cpd_geoip_dir;
	
	// IPv4 > IPv6
	if( filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) )
		$ip = "::$ip";
	
	if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
	{
		// IPv6
		$gi = geoip_open($cpd_geoip_dir.'GeoIP.dat', GEOIP_STANDARD);
		$c = strtolower(geoip_country_code_by_addr_v6($gi, $ip));
		$cname = geoip_country_name_by_addr_v6($gi, $ip);
		geoip_close($gi);
	}
	
	if ( empty($c) )
	{
		$c = 'unknown';
		$cname = '';
	}
	$country = array( $c, '<div class="cpd-flag cpd-flag-'.$c.'" title="'.$cname.'"></div>', $cname );
	return $country;
}

/**
 * updates CountPerDay table
 */
static function updateDB()
{
	global $count_per_day, $cpd_geoip_dir, $wpdb;
	
	$limit = 20;
	$res = $count_per_day->mysqlQuery('rows', "SELECT ip, $count_per_day->ntoa(ip) realip FROM $wpdb->cpd_counter WHERE country LIKE '' GROUP BY ip LIMIT $limit", 'GeoIP updateDB '.__LINE__);
	$gi = geoip_open($cpd_geoip_dir.'GeoIP.dat', GEOIP_STANDARD);
	
	foreach ($res as $r)
	{
		$c = '';
		if ( filter_var($r->realip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) )
		{
			// IPv4
			$ip = explode('.', $r->realip);
			if ( $ip[0] == 10
				|| $ip[0] == 127
				|| ($ip[0] == 169 && $ip[1] == 254)
				|| ($ip[0] == 172 && $ip[1] >= 16 && $ip[1] <= 31)
				|| ($ip[0] == 192 && $ip[1] == 168) )
				// set local IPs to '-'
				$c = '-';
			else
				// get country
				$c = strtolower(geoip_country_code_by_addr_v6($gi, '::'.$r->realip));
		}
		else if ( filter_var($r->realip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) )
		{
			// IPv6
			if ( strpos($r->realip, '::1') === 0
				|| strpos($r->realip, 'fc00::') === 0
					)
				// set local IPs to '-'
				$c = '-';
			else
				// get country
				$c = strtolower(geoip_country_code_by_addr_v6($gi, $r->realip));
		}
		
		if ( !empty($c) )
			$count_per_day->mysqlQuery('', "UPDATE $wpdb->cpd_counter SET country = '$c' WHERE ip = '$r->ip'", 'GeoIP updateDB '.__LINE__);
	}

	geoip_close($gi);
	
	$rest = $count_per_day->mysqlQuery('var', "SELECT COUNT(*) FROM $wpdb->cpd_counter WHERE country like ''", 'GeoIP updateDB '.__LINE__);
	return (int) $rest;
}

/**
 * updates the GeoIP database file
 * works only if directory wp-content/count-per-day-geoip has correct permissions, set it in ftp client
 */
static function updateGeoIpFile()
{
	global $cpd_geoip_dir;
	
	// function checks
	if ( !ini_get('allow_url_fopen') )
		return '<div class="error"><p>'.__('Sorry, <code>allow_url_fopen</code> is disabled!', 'cpd').'</p></div>';
		
	if ( !function_exists('gzopen') )
		return '<div class="error"><p>'.__('Sorry, necessary functions (zlib) not installed or enabled in php.ini.', 'cpd').'</p></div>';
	
	$gzfile = 'http://geolite.maxmind.com/download/geoip/database/GeoIPv6.dat.gz';
	$file = $cpd_geoip_dir.'GeoIP.dat';

	// get remote file
	$h = gzopen($gzfile, 'rb');
	$content = gzread($h, 2000000);
	fclose($h);
	
	if ( strlen($content) < 1000000 )
		return '<div class="error"><p>'.__('Sorry, could not read the GeoIP database file.', 'cpd').'</p></div>';
	
	if ( is_writable($cpd_geoip_dir) )
	{
		// delete local file
		if (is_file($file))
			unlink($file);
			
		// file deleted?
		$del = (is_file($file)) ? 0 : 1;
	
		// write new locale file
		$h = fopen($file, 'wb');
		fwrite($h, $content);
		fclose($h);
		
		@chmod($file, 0755);
	}
	
	if ( is_file($file) && $del && filesize($file) > 1000000 )
		return '<div class="updated"><p>'.__('New GeoIP database installed.', 'cpd').'</p></div>';
	else
		return '<div class="error"><p>'.__('Sorry, an error occurred. Try again or check the access rights of directory "wp-content/count-per-day-geoip".', 'cpd').'</p></div>';
}


}

