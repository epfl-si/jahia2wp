<?php

/**
 * Plugin Name: EPFL Map shortcode
 * Description: provides a shortcode to display a map from map.epfl.ch
 * @version: 1.1
 * @copyright: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

 use function EPFL\Language\get_current_or_default_language;

/**
 * Check the parameters
 *
 * Return True if all parameters are populated
 *
 * @param $query: query example: the office, the person
 * @param $lang: language
 * @return True if all parameters are populated
 */
function epfl_map_check_parameters( string $query, string $lang): bool
{
    return $query !== "" && $lang !== "";
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
    // if supported delegate the rendering to the theme
    if (!has_action("epfl_map_action"))
    {
        Utils::render_user_msg('You must activate the epfl theme');
    }

    // get parameters
    $atts = shortcode_atts(array(
        'query'  => '',
        'lang'   => '',
    ), $attributes);

    // sanitize parameters
    $query  = sanitize_text_field($atts['query']);
    $lang   = sanitize_text_field($atts['lang']);

    if (empty($lang)) {
        # use the current page language
        $lang = get_current_or_default_language();
    }

    // check parameters
    if ( false == epfl_map_check_parameters($query, $lang) ) {
        return Utils::render_user_msg("Map shortcode: Please check required parameters");
    }

    ob_start();

    try {

       do_action("epfl_map_action", $query, $lang);

       return ob_get_contents();

    } finally {

        ob_end_clean();
    }


}

add_action( 'init', function() {
    add_shortcode( 'epfl_map', 'epfl_map_process_shortcode' );
});

add_action( 'register_shortcode_ui',  function() {
    $lang_options = array(
        array('value' => 'en', 'label' => esc_html__('English', 'epfl')),
        array('value' => 'fr', 'label' => esc_html__('French', 'epfl')),
    );

    shortcode_ui_register_for_shortcode(

        'epfl_map',

        array(
            'label' => __('EPFL Map', 'epfl'),
            'listItemImage' => '<img src="' . plugins_url( 'img/map.svg', __FILE__ ) . '" >',
            'attrs'         => array(
                array(
                    'label'         => '<h3>' . esc_html__('What information do you want to display?', 'epfl') . '</h3>',
                    'attr'          => 'query',
                    'type'          => 'text',
                    'value'         => 'INN011',
                    'description'   => esc_html__('A room for example', 'epfl'),
                ),
            ),

            'post_type'     => array( 'post', 'page' ),
        )
    );
});

?>
