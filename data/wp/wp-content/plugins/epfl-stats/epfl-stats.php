<?PHP
/**
 * Plugin Name: EPFL-Stats
 * Description: Provide a filter to allow others plugins to log duration of their external call to webservices
 * @version: 1.0
 * @copyright: Copyright (c) 2019 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

require_once 'lib/prometheus.php';

use Prometheus\CollectorRegistry;

/*
    Save a webservice call duration including source page and timestamp on which call occurs

    @param $url         -> Webservice URL call
    @param $duration    -> webservice call duration (microsec)
*/
function epfl_stats_perf($url, $duration)
{

    global $wp;

    $url_details = parse_url($url);

    /* Building target host name with scheme */
    $target_host  = $url_details['scheme']."://".$url_details['host'];
    if(array_key_exists('port', $url_details) && $url_details['port'] != "") $target_host .= ":".$url_details['port'];

    $query = (array_key_exists('query', $url_details))?$url_details['query']:"";

    $adapter = new Prometheus\Storage\APC();

    $registry = new CollectorRegistry($adapter);

    $gauge = $registry->registerGauge('wp',
                                      'epfl_shortcode_duration_second',
                                      'How long a web service request takes',
                                       ['src', 'target_host', 'target_path', 'target_query', 'timestamp']);
    /* Timestamp is given in millisec (C2C prerequisite) */
    $gauge->set($duration, [home_url( $wp->request ),
                            $target_host,
                            $url_details['path'],
                            $query,
                            floor(microtime(true)*1000)]);

    error_log('logging URL $url\n');

}

// We register a new action so others plugins can use it to log webservice call duration
add_action('epfl_log_webservice_call', 'epfl_stats_perf', 10, 2);