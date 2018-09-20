<?php
# TODO : 
# - translate
# - limits

namespace Epfl\SocialFeed;

require_once 'shortcake-config.php';
require_once 'twitter.php';

function epfl_social_feed_process_shortcode($atts) {
    // extract shortcode parameters
    $atts = shortcode_atts(array(
            'twitter_url'  => '',
            'instagram_url'  => '',
            'facebook_url'  => '',
            'height' => '788',
        ), $atts);

    if (has_action("epfl_social_feed_action")) {
        ob_start();

        try {
           do_action("epfl_social_feed_action", $atts);
           return ob_get_contents();
        } finally {
          ob_end_clean();
        }
    // otherwise the plugin does the rendering
    } else {
        return 'You must activate the epfl theme';
    }
}

add_action( 'register_shortcode_ui', __NAMESPACE__ . '\ShortCake\config' );

add_action( 'init', function() {
    // define the shortcode
   add_shortcode('epfl_social_feed', __NAMESPACE__ . '\epfl_social_feed_process_shortcode');
});
