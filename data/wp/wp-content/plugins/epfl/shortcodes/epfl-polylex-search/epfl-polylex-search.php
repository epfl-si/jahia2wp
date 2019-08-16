<?php
/**
* Plugin Name: EPFL labs
* Description: Provide a way to search information about labs and their tags
* @version: 0.1
* @copyright: Copyright (c) 2019 Ecole Polytechnique Federale de Lausanne, Switzerland
*/

namespace Epfl\Polylex_Search_Plugin;

define("LEX_INFO_PROVIDER_URL", "https://wp-polylex.epfl.ch/api/v1/lexs");

function get_fixtures() {
    $string = file_get_contents(__DIR__  ."/fixture.json");
    $json_a=json_decode($string);
    return $json_a;
}

function process_shortcode($atts) {

    // if supported delegate the rendering to the theme
    if (!has_action("epfl_polylex_search_action"))
    {
        \Utils::render_user_msg('You must activate the epfl theme');
    }

    $atts = shortcode_atts( array(
        'category' => '',
        'subcategory' => '',
        'search' => ''
    ), $atts );

    // search can come from the url
    if (isset($_GET['search'])) {
        $atts['search'] = $_GET['search'];
    }

    // search can come from the url
    if (isset($_GET['category'])) {
        $atts['category'] = $_GET['category'];
    }

    // search can come from the url
    if (isset($_GET['subcategory'])) {
        $atts['subcategory'] = $_GET['subcategory'];
    }

    // sanitize what we get
    $category = sanitize_text_field($atts["category"]);
    $subcategory = sanitize_text_field($atts["subcategory"]);
    $search = sanitize_text_field($atts["search"]);

    # TODO:
    #$url = LEX_INFO_PROVIDER_URL;
    #$lexes = \Utils::get_items($url);
    $lexes = get_fixtures();

    ob_start();
    try {
       do_action("epfl_lexes_search_action", $lexes, $category, $subcategory, $search);
       return ob_get_contents();
    } finally {
        ob_end_clean();
    }
}

add_action( 'init', function() {
    // define the shortcode
   add_shortcode('epfl_polylex_search', __NAMESPACE__ . '\process_shortcode');
});