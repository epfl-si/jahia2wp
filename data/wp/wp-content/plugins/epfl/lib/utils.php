<?php

use Prometheus\CollectorRegistry;


Class Utils
{
    public static function debug($var) {
        print "<pre>";
        var_dump($var);
        print "</pre>";
    }

    public static function normalize ($string) {
        $table = array(
            'Š'=>'S', 'š'=>'s', 'Ð'=>'Dj', 'd'=>'dj', 'Ž'=>'Z', 'ž'=>'z', 'C'=>'C', 'c'=>'c', 'C'=>'C', 'c'=>'c',
            'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
            'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss',
            'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e',
            'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o',
            'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b',
            'ÿ'=>'y', 'R'=>'R', 'r'=>'r',
        );

        return strtr($string, $table);
    }

    /**
     * Return a user message
     */
    public static function render_user_msg($msg) {
        $html = '<div class="alert alert-warning alert-dismissible fade show" role="alert">';
        $html .= '<strong> Warning </strong>' . $msg;
        $html .= '<button type="button" class="close" data-dismiss="alert" aria-label="Close">';
        $html .= '  <span aria-hidden="true">&times;</span>';
        $html .= '</button>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Call API
     * @param url  : the fetchable url
     * @param args : array('timeout' => 10), see https://codex.wordpress.org/Function_Reference/wp_remote_get
     * @return decoded JSON data
     */
    public static function get_items(string $url) {

        $start = microtime(true);
        $response = wp_remote_get($url);
        $end = microtime(true);

        Utils::perf($url, $end-$start);

        if (is_array($response)) {
            $header = $response['headers']; // array of http header lines
            $data = $response['body']; // use the content

            // Check is 'application/json' is in the content type
            // Example of content type: 'application/json;charset=utf-8'
            if (strpos($header["content-type"], 'application/json') === False) {
                error_log("Webservice " . $url . " doesn't seem to be returning JSON");
                return False;
            } else {
                return json_decode($data);
            }
        }
    }

    /*
        Save a webservice call duration including source page and timestamp on which call occurs

        @param $url         -> Webservice URL call
        @param $duration    -> webservice call duration (microsec)
    */
    public static function perf($url, $duration)
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

    }
}

?>