<?PHP

namespace EPFL\Stats;

require_once 'lib/prometheus.php';

use Prometheus\CollectorRegistry;

class StatsController
{
    public static function hook ()
    {
        /* If we are in CLI mode, nobody cares about our stats: */
        if (static::is_cli()) return;
        add_action('epfl_stats_webservice_call_duration',
                   array(get_called_class(), 'prometheus_webservice_perf_counters'),
                   10, 2);
        add_action('epfl_stats_media_size_and_count',
                   array(get_called_class(), 'prometheus_media_gauges'),
                   10, 3);
    }

    protected static function is_cli ()
    {
        return php_sapi_name() == 'cli';
    }

    /**
     * Save a webservice call duration including source page and timestamp on which call occurs
     *
     * @param $url         Webservice URL call
     * @param $duration    webservice call duration (seconds with microseconds)
     */
    static function prometheus_webservice_perf_counters ($url, $duration)
    {

        global $wp;

        $url_details = parse_url($url);

        /* Building target host name with scheme */
        $target_host  = $url_details['scheme']."://".$url_details['host'];
        if(array_key_exists('port', $url_details) && $url_details['port'] != "") $target_host .= ":".$url_details['port'];

        $query = (array_key_exists('query', $url_details))?$url_details['query']:"";

        $adapter = new Prometheus\Storage\APC();

        $registry = new CollectorRegistry($adapter);

        /* To count time we spend waiting for web services (will disappear in a near future) */
        $counter = $registry->registerCounter('wp',
                                              'epfl_shortcode_duration_milliseconds',
                                              'How long we spend waiting for Web services overall, in milliseconds',
                                              ['src', 'target_host', 'target_path', 'target_query']);

        $counter->incBy(floor($duration*1000), [home_url( $wp->request ),
                                                $target_host,
                                                $url_details['path'],
                                                $query]);

        /* To count number of calls to web services */
        $counter = $registry->registerCounter('wp',
                                              'epfl_shortcode_ws_call_total',
                                              'Number of Web service call',
                                              ['src', 'target_host', 'target_path', 'target_query']);

        $counter->inc([home_url( $wp->request ),
                       $target_host,
                       $url_details['path'],
                       $query]);

    }

    /**
     *   Save count of nb medias, size usage and quota size
     *
     *    @param $used_bytes  Nb bytes used by medias on disk
     *    @param $quota_bytes Quota size in bytes
     *   @param $nb_files     Number of medias
     */
    static function prometheus_media_gauges ($used_bytes, $quota_bytes, $nb_files)
    {
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
}

StatsController::hook();
