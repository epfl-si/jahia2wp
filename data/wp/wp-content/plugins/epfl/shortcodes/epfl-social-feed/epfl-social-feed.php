<?php
namespace Epfl\SocialFeed;

require_once 'shortcake-config.php';

define('Epfl\SocialFeed\DEFAULT_HEIGHT', 450);
define('Epfl\SocialFeed\DEFAULT_WIDTH', 374);

function epfl_social_feed_process_shortcode($atts) {

    // if supported delegate the rendering to the theme
    if (!has_action("epfl_social_feed_action"))
    {
        Utils::render_user_msg('You must activate the epfl theme');
    }


    // extract shortcode parameters
    $atts = shortcode_atts(array(
            'twitter_url'  => '',
            'twitter_limit' => 0,
            'instagram_url'  => '',
            'facebook_url'  => '',
            'height' => DEFAULT_HEIGHT,
            'width' => DEFAULT_WIDTH,
        ), $atts);

    $atts['height'] = empty($atts['height']) ? DEFAULT_HEIGHT : $atts['height'];
    $atts['width'] = empty($atts['width']) ? DEFAULT_WIDTH : $atts['width'];
    $atts['twitter_limit'] = intval($atts['twitter_limit']) == 0 ? '' : $atts['twitter_limit'];

    ob_start();

    try {
       do_action("epfl_social_feed_action", $atts);
       return ob_get_contents();
    } finally {
      ob_end_clean();
    }

}

add_action( 'register_shortcode_ui', __NAMESPACE__ . '\ShortCake\config' );

add_action( 'init', function() {
    // define the shortcode
   add_shortcode('epfl_social_feed', __NAMESPACE__ . '\epfl_social_feed_process_shortcode');
});
