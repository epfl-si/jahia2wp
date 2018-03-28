<?php

/**
 * Plugin Name: EPFL Map shortcode
 * Description: provides a shortcode to display a map from map.epfl.ch
 * @version: 1.0
 * @copyright: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

declare( strict_types = 1 );

/**
 * Helper to debug the code
 * @param $var: variable to display
 */
function epfl_map_debug( $var ) {
    print "<pre>";
    var_dump( $var );
    print "</pre>";
}

/**
 * Build html
 *
 * @param $width: width of the map iframe
 * @param $height: height of the map iframe
 * @param $query: query example: the office, the person
 * @param $lang: language
 * @return string html of map iframe
 */
function epfl_map_build_html( string $width, string $height, string $query, string $lang ): string
{
    $html  = '<iframe frameborder="0" ';
    $html .= 'width="' . esc_attr($width) . '" ';
    $html .= 'height="' . esc_attr($height) . '" ';
    $html .= 'scrolling="no" src="https://plan.epfl.ch/iframe/?q=' . esc_attr($query);
    $html .= '&amp;lang=' . esc_attr($lang) . '&amp;map_zoom=10"></iframe>';
    return $html;
}

/**
 * Check the parameters
 *
 * Return True if all parameters are populated
 *
 * @param $width: width of the map iframe
 * @param $height: height of the map iframe
 * @param $query: query example: the office, the person
 * @param $lang: language
 * @return True if all parameters are populated
 */
function epfl_map_check_parameters( string $width, string $height, string $query, string $lang): bool
{
    return $width !== "" && $height !== "" && $query !== "" && $lang !== "";
}

/**
 * Execute the shortcode
 *
 * @attributes: array of all input parameters
 * @content: the content of the shortcode. In our case the content is empty
 * @return html of shortcode
 */
function epfl_map_process_shortcode( $attributes, string $content = null ): string
{
    // get parameters
    $atts = shortcode_atts(array(
        'width'  => '',
        'height' => '',
        'query'  => '',
        'lang'   => '',
    ), $attributes);

    // sanitize parameters
    $width  = sanitize_text_field($atts['width']);
    $height = sanitize_text_field($atts['height']);
    $query  = sanitize_text_field($atts['query']);
    $lang   = sanitize_text_field($atts['lang']);

    // check parameters
    if ( epfl_map_check_parameters($width, $height, $query, $lang) == FALSE ) {
        return "";
    }
    return epfl_map_build_html( $width, $height, $query, $lang );
}

add_shortcode( 'epfl_map', 'epfl_map_process_shortcode' );

?>