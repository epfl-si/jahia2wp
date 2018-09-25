<?php

/**
 * Shortcode Name: EPFL toggle
 * Description: provides a shortcode to display toggle content
 * @version: 1.1
 * @copyright: Copyright (c) 2018 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

require_once 'shortcake-config.php';

function epfl_toggle_process_shortcode( $atts, $content = null ) {

  $atts = shortcode_atts( array(
    'title' => 'Title',
    'state' => 'open',
  ), $atts );

  // sanitize parameters
  $state  = sanitize_text_field( $atts['state'] );
  $title  = sanitize_text_field( $atts['title'] );

  // if supported delegate the rendering to the theme
  if (has_action("epfl_toggle_action")) {

    ob_start();

    try {
       do_action("epfl_toggle_action", $title, $state, $content);

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
  add_shortcode('epfl_toggle', 'epfl_toggle_process_shortcode');

});

add_action( 'register_shortcode_ui', ['ToggleShortCakeConfig', 'config'] );

?>
