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
 * @param $text: the label for the image (text for the link also)
 * @return string html of div containing the image and the text, both pointing to the URL
 */
function epfl_buttons_box_build_html( string $type, string $url, string $image_url, string $text ): string
{
    $html  = '<div class="' . esc_attr($type) . 'ButtonsBox"><a href="'. esc_attr($url) . '">';
    $html .= '<img src="' . $image_url . '" alt="' . esc_attr($text) . '"/>';
    $html .= $text . '</a></div>';
    return $html;
}
/**
 * Check the parameters
 *
 * Return True if all parameters are populated
 *
 * @param $type: Box size : "small" or "big"
 * @param $url: the url pointed by the shortcode
 * @param $image_url: the id of the media (image) to show
 * @param $text: the label for the image (text for the link also)
 * @return True if all parameters are populated
 */
function epfl_buttons_box_check_parameters( string $type, string $url, string $image_url, string $text ): bool
{
    return $image_url !== '' && $url !== "" && $text !== "" && ($type == "small" || $type == "big");
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
        'text'      => '',
    ), $attributes);

    // sanitize parameters
    $type  = sanitize_text_field($atts['type']);
    $image = sanitize_text_field($atts['image']);
    $url   = sanitize_text_field($atts['url']);
    $text  = sanitize_text_field($atts['text']);

    $image_url = wp_get_attachment_url( $image );

    // check parameters
    if ( false == epfl_buttons_box_check_parameters($type, $url, $image_url, $text) ) {
        return "";
    }
    return epfl_buttons_box_build_html( $type, $url, $image_url, $text );
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
