<?php



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
     * @param url            : the fetchable url
     * @param cache_time_sec : Nb of sec during which we have to cache information in transient
     * @return decoded JSON data
     */
    public static function get_items(string $url, $cache_time_sec=300) {


        /* Caching mechanism is only used when :
         - No user is logged.
         - A user is logged in and he is in admin panel
         - cache time is greater than 0
         */
        if((!is_user_logged_in() || (is_user_logged_in() && is_admin())) && $cache_time_sec > 0)
        {
            /* Generating unique transient ID. We cannot directly use URL (and replace some characters) because we are
            limited to 172 characters for transient identifiers (https://codex.wordpress.org/Transients_API) */
            $transient_id = 'epfl_'.md5($url);

            /* If we have an URL call result in DB, */
            if ( false !== ( $data = get_transient($transient_id) ) )
            {
                 /* We return result */
                 return json_decode($data);
            }
        }

        $start = microtime(true);
        $response = wp_remote_get($url);
        $end = microtime(true);

        // Logging call
        do_action('epfl_stats_webservice_call_duration', $url, $end-$start);


        if (is_array($response)) {
            $header = $response['headers']; // array of http header lines
            $data = $response['body']; // use the content

            // Check is 'application/json' is in the content type
            // Example of content type: 'application/json;charset=utf-8'
            if (strpos($header["content-type"], 'application/json') === False) {
                error_log("Webservice " . $url . " doesn't seem to be returning JSON");
                return False;
            } else {

                /* If we have to store result in a transient,
                (this time, we don't check if user is logged in or not so futur calls from unlogged users will
                use cache directly)*/
                if($cache_time_sec > 0)
                {
                    set_transient($transient_id, $data, $cache_time_sec);
                }

                return json_decode($data);
            }
        }
    }

}

?>