<?php

/**
 * Plugin Name: EPFL twitter
 * Description: display a embedded twitter timelines for a specific account.
 * @version: 1.0
 * @copyright: Copyright (c) 2018 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

declare( strict_types = 1 );

/**
 * Execute the shortcode
 *
 * @attributes: array of all input parameters
 * @content: the content of the shortcode. In our case the content is empty
 * @return html of shortcode
 */
function epfl_twitter_process_shortcode($attributes): string
{
    // get parameters
    $atts = shortcode_atts(array(
            'url'         => '',
            'height'       => '',
            'limit'        => '',
        ), $attributes);

    // sanitize parameters
    $url = sanitize_text_field($atts['url']);
    $height = sanitize_text_field($atts['height']);
    $limit = sanitize_text_field($atts['limit']);

    $has_url = trim($url)!="";

    if (!$has_url) {
        return '';
    }

    $html = '<div class="twitterBox">';
    $html .= '  <a class="twitter-timeline" href="' . $url . '"';

    if ($height && is_numeric($height) && $height != 0) {
        $html .= ' data-height=' . $height;
    } else {
        $html .= ' data-height="327"';
    }

    if ($limit && is_numeric($limit) && $limit != 0) {
        $html .= ' data-tweet-limit=' . $limit;
    }
    $html .= '></a> <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>';
    $html .= '</div>';

    return $html;
}

add_action( 'init', function() {
    // define the shortcode
    add_shortcode( 'epfl_twitter', 'epfl_twitter_process_shortcode' );
});

?>
