<?php

/**
 * Plugin Name: EPFL snippets
 * Description: display snippets, an image with a title, subtitle, description and image.
 * @version: 1.1
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
    $url         = $atts['url'];
    $title       = sanitize_text_field($atts['title']);
    $subtitle    = sanitize_text_field($atts['subtitle']);
    $image       = sanitize_text_field($atts['image']);
    $big_image   = sanitize_text_field($atts['big_image']);
    $enable_zoom = sanitize_text_field($atts['enable_zoom']);

    $image_url = wp_get_attachment_url( $image );

    $has_url = trim($url)!="";

    $html  = '<div class="snippetsBox">';
    $html .= '  <div class="snippets-image">';

    if ( $has_url ) {
        $html .= '      <a href="' .  esc_attr($url) . '">';
    }

    $html .= '        <img src="' . esc_attr($image_url) . '"/>';

    if ( $has_url ) {
        $html .= '      </a>';
    }

    $html .= '</div>';
    $html .= '  <div class="snippets-content">';
    $html .= '    <div class="snippets-title"><h2>';

    if ( $has_url ) {
        $html .= '      <a href="' .  esc_attr($url) . '">';
    }

    $html .= $title;

    if ( $has_url ) {
        $html .= '      </a>';
    }

    $html .= '      </h2></div>';

    // note: we don't use esc_attr() here because the user is
    // allowed to put HTML
    $html .= '      <div class="snippets-subtitle"><p>' . $subtitle . '</p></div>';
    $html .= '      <div class="snippets-description"><p>' . $content . '</p></div>';
    $html .= '  </div>';
    $html .= '</div>';

    return $html;
}

// load .mo file for translation
function epfl_snippet_load_plugin_textdomain() {
    load_plugin_textdomain( 'epfl-snippet', FALSE, basename( plugin_dir_path( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'epfl_snippet_load_plugin_textdomain' );

add_action( 'init', function() {

    // define the shortcode
    add_shortcode( 'epfl_snippets', 'epfl_snippets_process_shortcode' );

});

add_action( 'register_shortcode_ui', ['ShortCakeSnippetConfig', 'config'] );
?>
