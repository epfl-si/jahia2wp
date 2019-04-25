<?php

Class EventUtils
{
    public static function debug($var) {
        print "<pre>";
        var_dump($var);
        print "</pre>";
    }

    /**
     * Call API
     * @param url  : the fetchable url
     * @param args : array('timeout' => 10), see https://codex.wordpress.org/Function_Reference/wp_remote_get
     * @return decoded JSON data
     */
    public static function get_items(string $url) {


        /* Generating unique transient ID. We cannot directly use URL (and replace some characters) because we are
        limited to 172 characters for transient identifiers (https://codex.wordpress.org/Transients_API) */
        $transient_id = 'epfl_'.md5($url);


        /* Caching mechanism is only used when :
         - No user is logged in
         - A user is logged in AND he is in admin panel
         */
        if((!is_user_logged_in() || (is_user_logged_in() && is_admin())))
        {

            /* If we have an URL call result in DB, */
            if ( false !== ( $data = get_transient($transient_id) ) )
            {
                /* We tell result has been recovered from transient cache  */
                do_action('epfl_stats_webservice_call_duration', $url, 0, true);
                /* We return result */
                return json_decode($data);
            }
        }

        $start = microtime(true);
        $response = wp_remote_get( $url );
        $end = microtime(true);

        // If there is some mechanism to log webservice call, we do it
        do_action('epfl_stats_webservice_call_duration', $url, $end-$start);

        if (is_array($response)) {
            $header = $response['headers']; // array of http header lines
            $data = $response['body']; // use the content
            if ( $header["content-type"] === "application/json" ) {

                set_transient($transient_id, $data, 300);
                return json_decode($data);
            }
        }
    }
}

?>