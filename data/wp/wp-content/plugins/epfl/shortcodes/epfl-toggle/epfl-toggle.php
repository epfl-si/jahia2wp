<?php

require_once 'shortcake-config.php';

function epfl_toggle_2018_process_shortcode($atts) {

  // if supported delegate the rendering to the theme
  if (has_action("epfl_toggle_action")) {

    ob_start();

    try {

       do_action("epfl_toggle_action", $atts);

       return ob_get_contents();

    } finally {

        ob_end_clean();
    }

  // otherwise the plugin does the rendering
  } else {

      return 'You must activate the epfl theme';
  }
}

add_action( 'register_shortcode_ui', ['ShortCakeToggleConfig', 'config'] );

add_action( 'init', function() {
    // define the shortcode
   add_shortcode('epfl_toggle_2018', 'epfl_toggle_2018_process_shortcode');
});
