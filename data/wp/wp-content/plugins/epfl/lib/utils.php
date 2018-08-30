<?php

Class Utils
{
    public static function debug($var) {
        print "<pre>";
        var_dump($var);
        print "</pre>";
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

        $response = wp_remote_get($url);

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