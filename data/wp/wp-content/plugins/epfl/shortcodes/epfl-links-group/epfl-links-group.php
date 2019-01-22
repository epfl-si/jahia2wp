<?php

require_once 'shortcake-config.php';

function epfl_links_group_process_shortcode($atts) {

    // if supported delegate the rendering to the theme
    if (!has_action("epfl_links_group_action"))
    {
        Utils::render_user_msg('You must activate the epfl theme');
    }

    ob_start();

    try {

       do_action("epfl_links_group_action", $atts);

       return ob_get_contents();

    } finally {

        ob_end_clean();
    }


}

add_action( 'register_shortcode_ui', ['ShortCakeLinksGroupConfig', 'config'] );

add_action( 'init', function() {
    // define the shortcode
   add_shortcode('epfl_links_group', 'epfl_links_group_process_shortcode');
});
