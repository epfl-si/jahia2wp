<?php
namespace Epfl\Contact;

require_once 'shortcake-config.php';

function epfl_contact_process_shortcode($atts) {

    // if supported delegate the rendering to the theme
    if (!has_action("epfl_contact_action"))
    {
        Utils::render_user_msg('You must activate the epfl theme');
    }

    // sanitize parameters
    foreach($atts as $key => $value) {
        if (strpos($key, 'information') !== false ||
        strpos($key, 'timetable') !== false) {
            $atts[$key] = wp_kses_post($value);
        }
        elseif ($key == 'introduction')
        {
            $atts[$key] = sanitize_textarea_field($value);
        } else {
            $atts[$key] = sanitize_text_field($value);
        }
    }

    ob_start();

    try {
       do_action("epfl_contact_action", $atts);
       return ob_get_contents();
    } finally {
      ob_end_clean();
    }

}

add_action( 'register_shortcode_ui', __NAMESPACE__ . '\ShortCake\config');

add_action( 'init', function() {
    // define the shortcode
   add_shortcode('epfl_contact', __NAMESPACE__ . '\epfl_contact_process_shortcode');
});
