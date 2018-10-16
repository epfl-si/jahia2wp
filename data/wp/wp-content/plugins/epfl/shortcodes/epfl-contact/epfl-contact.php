<?php
namespace Epfl\Contact;

require_once 'shortcake-config.php';

function epfl_contact_process_shortcode($atts) {
    // sanitize parameters
    foreach($atts as $key => $value) {
        if ($key == 'introduction' || strpos($key, 'information') !== false)
        {
            $atts[$key] = sanitize_textarea_field($value);
        } else {
            $atts[$key] = sanitize_text_field($value);
        }
    }

    if (has_action("epfl_contact_action")) {
        ob_start();

        try {
           do_action("epfl_contact_action", $atts);
           return ob_get_contents();
        } finally {
          ob_end_clean();
        }
    // otherwise the plugin does the rendering
    } else {
        return 'You must activate the epfl theme';
    }
}

add_action( 'register_shortcode_ui', __NAMESPACE__ . '\ShortCake\config');

add_action( 'init', function() {
    // define the shortcode
   add_shortcode('epfl_contact', __NAMESPACE__ . '\epfl_contact_process_shortcode');
});
