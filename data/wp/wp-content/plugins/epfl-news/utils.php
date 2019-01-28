<?php

Class NewsUtils
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

        $start = microtime(true);
        $response = wp_remote_get( $url );
        $end = microtime(true);

        // If there is some mechanism to log webservice call, we do it
        if(has_action('epfl_log_webservice_call'))
        {
            do_action('epfl_log_webservice_call', $url, $end-$start);
        }

        if (is_array($response)) {
                $header = $response['headers']; // array of http header lines
                $data = $response['body']; // use the content
                if ( $header["content-type"] === "application/json" ) {
                        return json_decode($data);
                }
        }
    }
}

?>