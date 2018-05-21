<?php

/**
 * Plugin Name: EPFL snippets
 * Description: display snippets, an image with a title, subtitle, description and image.
 * @version: 1.0
 * @copyright: Copyright (c) 2018 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

declare( strict_types = 1 );

require_once 'shortcake-config.php';


/**
 * Execute the shortcode
 *
 * @attributes: array of all input parameters
 * @content: the content of the shortcode. In our case the content is empty
 * @return html of shortcode
 */
function epfl_snippets_process_shortcode( $attributes, string $content = null ): string
{
    // get parameters
    $atts = shortcode_atts(array(
            'url'          => '',
            'title'        => '',
            'subtitle'     => '',
            'image'        => '',
            'big_image'    => '',
            'enable_zoom'  => '',
        ), $attributes);

    // sanitize parameters
    $url         = sanitize_text_field($atts['url']);
    $title       = sanitize_text_field($atts['title']);
    $subtitle    = sanitize_text_field($atts['subtitle']);
    $image       = sanitize_text_field($atts['image']);
    $big_image   = sanitize_text_field($atts['big_image']);
    $enable_zoom = sanitize_text_field($atts['enable_zoom']);

    $html  = '<div class="snippetsBox">';

    $has_url = filter_var($url, FILTER_VALIDATE_URL);

    if ( $has_url ) {
      $html .= '<a href="' . $url . '">';
    }

    // note: we don't use esc_attr() here because the user is
    // allowed to put HTML, same for subtitle and description
    $html .= '<div class="snippets-title">' . $title . '</div>';

    if ( $has_url ) {
      $html .= '</a>';
    }

    $html .= '<div class="snippets-subtitle">' . $subtitle . '</div>';
    $html .= '<div class="snippets-description">' . $content . '</div>';

    if ( $has_url ) {
      $html .= '<a href="' . $url . '">';
    }

    $html .= '<div class="snippets-image"><img src="' . esc_attr($image) . '"/></div>';

    if ( $has_url ) {
      $html .= '</a>';
    }

    $html .= '</div>';

    return $html;

}




// load .mo file for translation
function epfl_snippet_load_plugin_textdomain() {
    load_plugin_textdomain( 'epfl-snippet', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'epfl_snippet_load_plugin_textdomain' );

add_action( 'init', function() {

    // define the shortcode
    add_shortcode( 'epfl_snippets', 'epfl_snippets_process_shortcode' );

    // shortcake configuration
    if ( function_exists( 'shortcode_ui_register_for_shortcode' ) ) :
        ShortCakeSnippetConfig::config();
    endif;
} );

?>
