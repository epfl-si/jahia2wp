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
        'tags' => '',
    ), $atts );

    // sanitize what we get
    if ($atts['tags'] !== '') {
        $predefined_tags = explode( ';', sanitize_text_field($atts['tags']));
    } else {
        $predefined_tags = [];
    }

    # by default get all sites
    $url = LABS_INFO_PROVIDER_URL . 'sites?tagged=true';

    if (!empty($predefined_tags)) {
        $url = LABS_INFO_PROVIDER_URL . 'sites?';

        foreach($predefined_tags as $tag) {
            $url .= '&tags=' . $tag;
        }
    }

    $sites = \Utils::get_items($url);    

    ob_start();
    try {
       do_action("epfl_labs_search_action", $sites, $predefined_tags);
       return ob_get_contents();
    } finally {
        ob_end_clean();
    }
}

add_action( 'init', function() {
    // define the shortcode
   add_shortcode('epfl_labs_search', __NAMESPACE__ . '\process_shortcode');
});
