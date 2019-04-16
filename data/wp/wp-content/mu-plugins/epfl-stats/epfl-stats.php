<?PHP

require_once 'lib/prometheus.php';

use Prometheus\CollectorRegistry;

/*
    Save a webservice call duration including source page and timestamp on which call occurs

    @param $url         -> Webservice URL call
    @param $duration    -> webservice call duration (seconds with microseconds)
*/
function epfl_stats_webservice_call_duration($url, $duration)
{
    /* If we are in CLI mode, it's useless to update in APC because it's the APC for mgmt container and not httpd
    container */
    if(php_sapi_name()=='cli') return;

    global $wp;

    $url_details = parse_url($url);

    /* Building target host name with scheme */
    $target_host  = $url_details['scheme']."://".$url_details['host'];
    if(array_key_exists('port', $url_details) && $url_details['port'] != "") $target_host .= ":".$url_details['port'];

    /* Extracting infos */
    $src = home_url( $wp->request );
    $query = (array_key_exists('query', $url_details))?$url_details['query']:"";

    $adapter = new Prometheus\Storage\APC();

    $registry = new CollectorRegistry($adapter);



    /* To count time we spend waiting for web services (will disappear in a near future) */
    $counter = $registry->registerCounter('wp',
                                          'epfl_shortcode_duration_milliseconds',
                                          'How long we spend waiting for Web services overall, in milliseconds',
                                          ['src', 'target_host', 'target_path', 'target_query']);

    $counter->incBy(floor($duration*1000), [$src,
                                            $target_host,
                                            $url_details['path'],
                                            $query]);

    /* To count number of calls to web services */
    $counter = $registry->registerCounter('wp',
                                          'epfl_shortcode_ws_call_total',
                                          'Number of Web service call',
                                          ['src', 'target_host', 'target_path', 'target_query']);

    $counter->inc([$src,
                  $target_host,
                  $url_details['path'],
                  $query]);

    /* JSON logging */

    /* Generating date/time in correct format: yyyy-MM-dd'T'HH:mm:ss.SSSZZ (ex: 2019-03-27T12:46:14.078Z ) */
    $time_generated = date("Y-m-d\TH:i:s.v\Z");
    $log_array = array("@timegenerated" => $time_generated,
                       "priority"       => "INFO",
                       "verb"           => "GET",
                       "code"           => "200",
                       "src"            => $src,
                       "targethost"     => $target_host,
                       "targetpath"     => $url_details['path'],
                       "targetquery"    => $query,
                       "responsetime"   => floor($duration*1000));
    $log_json = json_encode($log_array);

}
// We register a new action so others plugins can use it to log webservice call duration
add_action('epfl_stats_webservice_call_duration', 'epfl_stats_webservice_call_duration', 10, 2);


/*
    Save count of nb medias, size usage and quota size

    @param $used_bytes  -> Nb bytes used by medias on disk
    @param $quota_bytes -> Quota size in bytes
    @param $nb_files    -> Number of medias
*/
function epfl_stats_media_size_and_count($used_bytes, $quota_bytes, $nb_files)
{
    /* If we are in CLI mode, it's useless to update in APC because it's the APC for mgmt container and not httpd
    container */
    if(php_sapi_name()=='cli') return;

    global $wp;

    $adapter = new Prometheus\Storage\APC();

    $registry = new CollectorRegistry($adapter);

    /* Size information */
    $size_gauge = $registry->registerGauge('wp',
                                           'epfl_media_size_bytes',
                                           'Used (and max) space for medias',
                                           ['site', 'type']);

    $size_gauge->set($used_bytes, [home_url( $wp->request ), "used"]);
    $size_gauge->set($quota_bytes, [home_url( $wp->request ), "quota"]);


    /* Media count */
    $count_gauge = $registry->registerGauge('wp',
                                           'epfl_media_nb_files',
                                           'Media count on website',
                                           ['site', 'type']);

    $count_gauge->set($nb_files, [home_url( $wp->request ), "used"]);
}
// We register a new action so others plugins can use it to log webservice call duration
add_action('epfl_stats_media_size_and_count', 'epfl_stats_media_size_and_count', 10, 3);