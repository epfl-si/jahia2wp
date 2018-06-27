<?php
/**
 * Plugin Name: Small and Big Buttons Box 
 * Description: provides a shortcode to display an equivalent of the smallButtonsBox and the bigButtonsBox in Jahia.
 * @version: 1.0
 * @copyright: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

declare( strict_types = 1 );

require_once 'shortcake-config.php';

/**
 * Helper to debug the code
 * @param $var: variable to display
 */
function epfl_buttons_box_debug( $var ) {
    print "<pre>";
    var_dump( $var );
    print "</pre>";
}

/**
 * Build html
 *
 * @param $type: Box size : "small" or "big"
 * @param $url: the url pointed by the shortcode
 * @param $image_url: the id of the media (image) to show
 * @param $alt_text: the label for the image
 * @param $text: link text
 * @param $title: Text to display under image
 * @param $key: Key to identify small button class
 * @return string html of div containing the image and the text, both pointing to the URL
 */
function epfl_buttons_box_build_html( string $type, string $url, string $image_url, string $alt_text, string $text, string $key ): string
{
    $html  = '<div class="' . esc_attr($type) . 'ButtonsBox"><a href="'. esc_attr($url) . '" title="' . esc_attr($alt_text) .'">';
    if($type == 'big')
    {
        $html .= '<img src="' . $image_url . '" />';
    }
    else
    {
        $html .= '<img class="' . esc_attr($key) . '" />';
    }
    $html .= $text . '</a></div>';
    return $html;
}

/**
 * Execute the shortcode
 *
 * @attributes: array of all input parameters
 * @content: the content of the shortcode. In our case the content is empty
 * @return html of shortcode
 */
function epfl_buttons_process_shortcode( $attributes, string $content = null ): string
{
    // get parameters
    $atts = shortcode_atts( array(
        'type'      => 'big',
        'image'     => '',
        'url'       => '',
        'alt_text'  => '',
        'text'      => '',
        'key'       => '',
    ), $attributes);

    // sanitize parameters
    $type       = sanitize_text_field($atts['type']);
    $image      = sanitize_text_field($atts['image']); // only for big buttons
    $url        = sanitize_text_field($atts['url']);
    $alt_text   = sanitize_text_field($atts['alt_text']);
    $text       = sanitize_text_field($atts['text']);
    $key        = sanitize_text_field($atts['key']); // only for small buttons

    if($type == 'big')
    {
        $image_url = wp_get_attachment_url( $image );
        if (false == $image_url) {
            $image_url = "BAD MEDIA ID";
        }
    }

    return epfl_buttons_box_build_html( $type, $url, $image_url, $alt_text, $text, $key );
}

// load .mo file for translation
function epfl_buttons_load_plugin_textdomain() {
    load_plugin_textdomain( 'epfl-buttons', FALSE, basename( plugin_dir_path( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'epfl_buttons_load_plugin_textdomain' );
add_action( 'init', function() {
    // define the shortcode
    add_shortcode( 'epfl_buttons', 'epfl_buttons_process_shortcode' );
    // shortcake configuration
    if ( function_exists( 'shortcode_ui_register_for_shortcode' ) ) :
        ShortCakeButtonsConfig::config();
    endif;
} );

?>