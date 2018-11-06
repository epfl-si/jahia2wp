<?php

/**
 * Plugin Name: Quote shortcode
 * Description: provides a shortcode to display Quote
 * @version: 1.0
 * @copyright: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

require_once 'shortcake-config.php';

/**
 * Main function of shortcode
 *
 * @param $atts: attributes of the shortcode
 * @param $content: the content of the shortcode. Always empty in our case.
 * @param $tag: the name of shortcode. epfl_card in our case.
 */
function epfl_quote_process_shortcode($atts = [], $content = '', $tag = '') {

    // shortcode parameters
    $atts = shortcode_atts(array(
            'quote' => '',
            'cite'  => '',
            'footer'  => '',
            'image' => '',
    ), $atts, $tag);

    // sanitize parameters
    $quote     = sanitize_text_field( $atts['quote'] );
    $cite      = sanitize_text_field( $atts['cite'] );
    $footer    = sanitize_text_field( $atts['footer'] );
    $image     = sanitize_text_field( $atts['image'] );
    $image_url = wp_get_attachment_url( $image );

    // if supported delegate the rendering to the theme
    if (has_action("epfl_quote_action")) {

        ob_start();

        try {

           do_action("epfl_quote_action", $quote, $cite, $footer, $image_url);

           return ob_get_contents();

        } finally {

            ob_end_clean();
        }

    // otherwise the plugin does the rendering
    } else {

        return 'You must activate the epfl theme';
    }
}

add_action( 'register_shortcode_ui', ['ShortCakeQuoteConfig', 'config'] );

add_action( 'init', function() {
    // define the shortcode
   add_shortcode('epfl_quote', 'epfl_quote_process_shortcode');
});