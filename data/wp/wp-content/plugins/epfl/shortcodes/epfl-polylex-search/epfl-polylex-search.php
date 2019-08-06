<?php
/**
* Plugin Name: EPFL labs
* Description: Provide a way to search information about labs and their tags
* @version: 0.1
* @copyright: Copyright (c) 2019 Ecole Polytechnique Federale de Lausanne, Switzerland
*/

namespace Epfl\Polylex_Search_Plugin;

define("LEX_INFO_PROVIDER_URL", "https://wp-veritas.epfl.ch/api/v1/");

function process_shortcode($atts) {

    // if supported delegate the rendering to the theme
    if (!has_action("epfl_polylex_search_action"))
    {
        \Utils::render_user_msg('You must activate the epfl theme');
    }

    $atts = shortcode_atts( array(
        'category' => '',
        'subcategory' => ''
    ), $atts );

    // sanitize what we get
    $category = sanitize_text_field($atts["category"]);
    $subcategory = sanitize_text_field($atts["subcategory"]);

    # by default get all sites with at least a tag
    $url = LEX_INFO_PROVIDER_URL . 'sites?tagged=true';
    $lexes = \Utils::get_items($url);

    ob_start();
    try {
       do_action("epfl_lex_search_action", $lexes, $category, $subcategory);
       return ob_get_contents();
    } finally {
        ob_end_clean();
    }
}

add_action( 'init', function() {
    // define the shortcode
   add_shortcode('epfl_polylex_search', __NAMESPACE__ . '\process_shortcode');
});
