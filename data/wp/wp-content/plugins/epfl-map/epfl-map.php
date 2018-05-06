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

// Load .mo file for translation
function epfl_map_load_plugin_textdomain() {
    load_plugin_textdomain( 'epfl-map', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'epfl_map_load_plugin_textdomain' );

add_action( 'init', function() {
    add_shortcode( 'epfl_map', 'epfl_map_process_shortcode' );

    if ( function_exists( 'shortcode_ui_register_for_shortcode' ) ) :

        $lang_options = array(
            array('value' => 'en', 'label' => esc_html__('English', 'epfl-map')),
            array('value' => 'fr', 'label' => esc_html__('French', 'epfl-map')),
        );

        shortcode_ui_register_for_shortcode(

            'epfl_map',

            array(
                'label' => __('Add Map shortcode', 'epfl-map'),
                'listItemImage' => '',
                'attrs'         => array(
                    array(
                        'label'         => '<h3>' . esc_html__('Width', 'epfl-map') . '</h3>',
                        'attr'          => 'width',
                        'type'          => 'text',
                        'description'   => esc_html__('Width of the map', 'epfl-map'),
                        'value'         => '600',
                    ),
                    array(
                        'label'         => '<h3>' . esc_html__('Height', 'epfl-map') . '</h3>',
                        'attr'          => 'height',
                        'type'          => 'text',
                        'description'   => esc_html__('Height of the map', 'epfl-map'),
                    ),
                    array(
                        'label'         => '<h3>' . esc_html__('Query', 'epfl-map') . '</h3>',
                        'attr'          => 'query',
                        'type'          => 'text',
                        'description'   => esc_html__('For example a room', 'epfl-map'),
                    ),
                    array(
                        'label'         => '<h3>' . esc_html__('Language', 'epfl-map') . '</h3>',
                        'attr'          => 'lang',
                        'type'          => 'select',
                        'options'       => $lang_options,
                        'description'   => esc_html__('The language used to render map result', 'epfl-map'),
                    ),
                ),

                'post_type'     => array( 'post', 'page' ),
            )
        );

    endif;
});

?>