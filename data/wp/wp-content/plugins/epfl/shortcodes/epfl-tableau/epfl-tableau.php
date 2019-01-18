<?php
/**
* Plugin Name: EPFL tableau
* Description: Provide a way to show charts from tableau.epfl.ch
* @version: 1.0
* @copyright: Copyright (c) 2018 Ecole Polytechnique Federale de Lausanne, Switzerland
*/

namespace Epfl\Tableau;

require_once 'shortcake-config.php';

function process_shortcode($atts) {

    // if supported delegate the rendering to the theme
    if (!has_action("epfl_tableau_action"))
    {
        Utils::render_user_msg('You must activate the epfl theme');
    }

    $atts = shortcode_atts( array(
        'url' => '',
        'height'   => '',
        'width' => '',
        'embed_code'   => '',
    ), $atts );

    # or get the already set url, width and height
    if (!empty($atts['embed_code'])) {
        # from a copy-paste of a embed view, parse this information :
        # the view url, the width and the height
        $embed_code = urldecode(wp_kses_post($atts['embed_code']));

        // first step, check if we have a copy paste in a editor that encode quote
        if (strpos($embed_code, "width=") !== false) {
            $matches = [];
            preg_match("#width='([0-9]+)'#", $embed_code, $matches);
            $width = $matches[1];

            preg_match("#height='([0-9]+)'#", $embed_code, $matches);
            $height = $matches[1];

            preg_match("#param name='name' value='(.*?)'\s/>#", $embed_code, $matches);
            $url = $matches[1];
        }
    }

    # set or overload url, width and height if set in the shortcode
    if (!empty($atts['url'])) {
        $url = $atts['url'];
    }

    if (!empty($atts['width'])) {
        $width = $atts['width'];
    }

    if (!empty($atts['height'])) {
        $height = $atts['height'];
    }

    // sanitize what we get
    $url = sanitize_text_field($url);
    $width = sanitize_text_field($width);
    $height = sanitize_text_field($height);

    ob_start();
    try {
       do_action("epfl_tableau_action", $url, $width, $height);
       return ob_get_contents();
    } finally {
        ob_end_clean();
    }

}

add_action( 'register_shortcode_ui', __NAMESPACE__ . '\ShortCake\config');

add_action( 'init', function() {
    // define the shortcode
   add_shortcode('epfl_tableau', __NAMESPACE__ . '\process_shortcode');
});
