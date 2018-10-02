<?php
/**
* Plugin Name: EPFL share
* Description: Provide share buttons for EPFL websites
* @version: 1.0
* @copyright: Copyright (c) 2018 Ecole Polytechnique Federale de Lausanne, Switzerland
*/

function epfl_share_process_shortcode( $atts, $content = null ) {
  // if supported delegate the rendering to the theme
  if (has_action("epfl_share_action")) {
    ob_start();

    try {
       do_action("epfl_share_action", $title, $state, $content);
       return ob_get_contents();
    } finally {
        ob_end_clean();
    }
  // otherwise the plugin does the rendering
  } else {
      return 'You must activate the epfl theme';
  }
}

add_action( 'init', function() {
  // define the shortcode
  add_shortcode('epfl_share_2018', 'epfl_share_process_shortcode');
});
?>
