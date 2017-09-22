<?php
if (!defined('ABSPATH'))
	exit;

$what = (empty($_GET['map'])) ? 'reads' : strip_tags($_GET['map']);
if ( !$cpd_geoip || !in_array($what, array('visitors','reads','online')) )
	die();

$cpd_dir = $count_per_day->dir; 
$data = array('-' => 0);
$what = (empty($_GET['map'])) ? 'reads' : strip_tags($_GET['map']);

if ( $what == 'online' )
{
	require_once(WP_PLUGIN_DIR.'/count-per-day/geoip.php');
	$oc = get_option('count_per_day_online', array());
	$gi = geoip_open($cpd_geoip_dir.'GeoIP.dat', GEOIP_STANDARD);
	$vo = array();
	foreach ($oc as $ip => $x)
	{
		$country = '-';
		if( filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) )
			// IPv4 -> IPv6
			$ip = '::'.$ip;
		
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
			// IPv6
			$country = strtoupper(geoip_country_code_by_addr_v6($gi, $ip));
		$data[$country] = (isset($data[$country])) ? $data[$country] + 1 : 1;
	}
}
else
{
	$temp = $count_per_day->addCollectionToCountries( ($what == 'visitors') );
	foreach ($temp as $country => $value)
		if ($country != '-')
			$data[strtoupper($country)] = $value;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>CountPerDay</title>
<link rel="stylesheet" type="text/css" href="<?php echo $cpd_dir ?>/counter.css" />
<script src="<?php echo $cpd_dir ?>/map/ammap.js" type="text/javascript"></script>
<script src="<?php echo $cpd_dir ?>/map/worldLow.js" type="text/javascript"></script>
<script type="text/javascript">
AmCharts.ready(function() {
var map = new AmCharts.AmMap();
map.pathToImages = "<?php echo $cpd_dir ?>/map/images/";
map.addTitle("Your Visitors all over the World", 14);
map.mouseWheelZoomEnabled = true;
var c = "#38E";
map.dataProvider = {
	mapVar: AmCharts.maps.worldLow,
	getAreasFromMap:true,
	areas: [
	{id:"AE",value:0,color:c},{id:"AF",value:0,color:c},{id:"AL",value:0,color:c},{id:"AM",value:0,color:c},{id:"AO",value:0,color:c},{id:"AR",value:0,color:c},{id:"AT",value:0,color:c},{id:"AU",value:0,color:c},{id:"AZ",value:0,color:c},{id:"BA",value:0,color:c},{id:"BD",value:0,color:c},{id:"BE",value:0,color:c},{id:"BF",value:0,color:c},{id:"BG",value:0,color:c},{id:"BI",value:0,color:c},{id:"BJ",value:0,color:c},{id:"BN",value:0,color:c},{id:"BO",value:0,color:c},{id:"BR",value:0,color:c},{id:"BS",value:0,color:c},{id:"BT",value:0,color:c},{id:"BW",value:0,color:c},{id:"BY",value:0,color:c},{id:"BZ",value:0,color:c},{id:"CA",value:0,color:c},{id:"CD",value:0,color:c},{id:"CF",value:0,color:c},{id:"CG",value:0,color:c},{id:"CH",value:0,color:c},{id:"CI",value:0,color:c},{id:"CL",value:0,color:c},{id:"CM",value:0,color:c},{id:"CN",value:0,color:c},{id:"CO",value:0,color:c},{id:"CR",value:0,color:c},{id:"CU",value:0,color:c},{id:"CY",value:0,color:c},{id:"CZ",value:0,color:c},{id:"DE",value:0,color:c},{id:"DJ",value:0,color:c},{id:"DK",value:0,color:c},{id:"DO",value:0,color:c},{id:"DZ",value:0,color:c},{id:"EC",value:0,color:c},{id:"EE",value:0,color:c},{id:"EG",value:0,color:c},{id:"EH",value:0,color:c},{id:"ER",value:0,color:c},{id:"ES",value:0,color:c},{id:"ET",value:0,color:c},{id:"FK",value:0,color:c},{id:"FI",value:0,color:c},{id:"FJ",value:0,color:c},{id:"FR",value:0,color:c},{id:"GA",value:0,color:c},{id:"GB",value:0,color:c},{id:"GE",value:0,color:c},{id:"GF",value:0,color:c},{id:"GH",value:0,color:c},{id:"GL",value:0,color:c},{id:"GM",value:0,color:c},{id:"GN",value:0,color:c},{id:"GQ",value:0,color:c},{id:"GR",value:0,color:c},{id:"GT",value:0,color:c},{id:"GW",value:0,color:c},{id:"GY",value:0,color:c},{id:"HN",value:0,color:c},{id:"HR",value:0,color:c},{id:"HT",value:0,color:c},{id:"HU",value:0,color:c},{id:"ID",value:0,color:c},{id:"IE",value:0,color:c},{id:"IL",value:0,color:c},{id:"IN",value:0,color:c},{id:"IQ",value:0,color:c},{id:"IR",value:0,color:c},{id:"IS",value:0,color:c},{id:"IT",value:0,color:c},{id:"JM",value:0,color:c},{id:"JO",value:0,color:c},{id:"JP",value:0,color:c},{id:"KE",value:0,color:c},{id:"KG",value:0,color:c},{id:"KH",value:0,color:c},{id:"KP",value:0,color:c},{id:"KR",value:0,color:c},{id:"XK",value:0,color:c},{id:"KW",value:0,color:c},{id:"KZ",value:0,color:c},{id:"LA",value:0,color:c},{id:"LB",value:0,color:c},{id:"LK",value:0,color:c},{id:"LR",value:0,color:c},{id:"LS",value:0,color:c},{id:"LT",value:0,color:c},{id:"LU",value:0,color:c},{id:"LV",value:0,color:c},{id:"LY",value:0,color:c},{id:"MA",value:0,color:c},{id:"MD",value:0,color:c},{id:"ME",value:0,color:c},{id:"MG",value:0,color:c},{id:"MK",value:0,color:c},{id:"ML",value:0,color:c},{id:"MM",value:0,color:c},{id:"MN",value:0,color:c},{id:"MR",value:0,color:c},{id:"MW",value:0,color:c},{id:"MX",value:0,color:c},{id:"MY",value:0,color:c},{id:"MZ",value:0,color:c},{id:"NA",value:0,color:c},{id:"NC",value:0,color:c},{id:"NE",value:0,color:c},{id:"NG",value:0,color:c},{id:"NI",value:0,color:c},{id:"NL",value:0,color:c},{id:"NO",value:0,color:c},{id:"NP",value:0,color:c},{id:"NZ",value:0,color:c},{id:"OM",value:0,color:c},{id:"PA",value:0,color:c},{id:"PE",value:0,color:c},{id:"PG",value:0,color:c},{id:"PH",value:0,color:c},{id:"PL",value:0,color:c},{id:"PK",value:0,color:c},{id:"PR",value:0,color:c},{id:"PS",value:0,color:c},{id:"PT",value:0,color:c},{id:"PY",value:0,color:c},{id:"QA",value:0,color:c},{id:"RO",value:0,color:c},{id:"RS",value:0,color:c},{id:"RU",value:0,color:c},{id:"RW",value:0,color:c},{id:"SA",value:0,color:c},{id:"SB",value:0,color:c},{id:"SD",value:0,color:c},{id:"SE",value:0,color:c},{id:"SI",value:0,color:c},{id:"SJ",value:0,color:c},{id:"SK",value:0,color:c},{id:"SL",value:0,color:c},{id:"SN",value:0,color:c},{id:"SO",value:0,color:c},{id:"SR",value:0,color:c},{id:"SS",value:0,color:c},{id:"SV",value:0,color:c},{id:"SY",value:0,color:c},{id:"SZ",value:0,color:c},{id:"TD",value:0,color:c},{id:"TF",value:0,color:c},{id:"TG",value:0,color:c},{id:"TH",value:0,color:c},{id:"TJ",value:0,color:c},{id:"TL",value:0,color:c},{id:"TM",value:0,color:c},{id:"TN",value:0,color:c},{id:"TR",value:0,color:c},{id:"TT",value:0,color:c},{id:"TW",value:0,color:c},{id:"TZ",value:0,color:c},{id:"UA",value:0,color:c},{id:"UG",value:0,color:c},{id:"US",value:0,color:c},{id:"UY",value:0,color:c},{id:"UZ",value:0,color:c},{id:"VE",value:0,color:c},{id:"VN",value:0,color:c},{id:"VU",value:0,color:c},{id:"YE",value:0,color:c},{id:"ZA",value:0,color:c},{id:"ZM",value:0,color:c},{id:"ZW",value:0,color:c},
   	<?php
   	$r = __('Reads','cpd');
   	foreach ( $data as $k => $v )
   		echo "{id:'$k',value:$v,balloonText:'[[title]]<br><b>[[value]]</b> $r<br>[[percent]]%'},"
	?>
	]};
map.areasSettings = {
    color: "#FFFFFF",
    outlineColor: "#CCCCCC",
    outlineThickness: 0.2,
    rollOverColor: "#FFFF00"
   	};
var legend = new AmCharts.ValueLegend();
legend.minValue = <?php echo min($data) ?>;
legend.left = 10;
legend.bottom = 25;
legend.width = 150;
legend.borderThickness = 0;
legend.showAsGradient = true;
map.valueLegend = legend;

map.write("mapdiv");               
});
</script>
</head>
<body style="overflow:hidden; padding:0; margin:0; background:#49F;">
<div id="mapdiv" style="width:100%;height:430px;"></div>
</body>
</html>