<?php
/**
* Plugin Name: EPFL labs
* Description: Provide a way to search information about labs and their tags
* @version: 0.1
* @copyright: Copyright (c) 2019 Ecole Polytechnique Federale de Lausanne, Switzerland
*/

namespace Epfl\Labs_Search_Plugin;

define("LABS_INFO_PROVIDER_URL", "https://wp-veritas.epfl.ch/api/v1/");

function process_shortcode($atts) {

    // if supported delegate the rendering to the theme
    if (!has_action("epfl_labs_search_action"))
    {
        \Utils::render_user_msg('You must activate the epfl theme');
    }

    $atts = shortcode_atts( array(
        'faculty' => '',
        'institute'   => '',
        'field_of_research'   => '',
    ), $atts );

    // sanitize what we get
    $faculty = sanitize_text_field($atts['faculty']);
    $institute = sanitize_text_field($atts['institute']);
    $clusters = sanitize_text_field($atts['field_of_research']);


    ob_start();
    try {
       do_action("epfl_labs_search_action");
       return ob_get_contents();
    } finally {
        ob_end_clean();
    }
}

add_action( 'init', function() {
    // define the shortcode
   add_shortcode('epfl_labs_search', __NAMESPACE__ . '\process_shortcode');
});

/**
 * Do the actual search, when the user submitted is query
 */
function process_search($text) {
    $url = LABS_INFO_PROVIDER_URL . 'sites?text=' . $text;
    error_log($url);
    $sites = \Utils::get_items($url);
    return $sites;
}

add_filter('epfl_labs_search_action_callback', __NAMESPACE__ . '\process_search', 10, 1);
