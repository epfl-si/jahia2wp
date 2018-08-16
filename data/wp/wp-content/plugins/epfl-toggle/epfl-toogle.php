<?php

/**
 * Plugin Name: EPFL toggle
 * Description: provides a shortcode to display toggle content
 * @version: 1.1
 * @copyright: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
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

  $html = '<section class="collapsible ' . esc_attr( $state ) . '">';
  $html .= '<div class="collapsible-header"><h3 class="title collapse-link">' . $title . '</h3></div>';
  $html .= '<div class="content collapsible-content clearfix">';
  $html .= do_shortcode( $content );
  $html .= '</div></section>';

  return $html;
}

// Load .mo file for translation
function epfl_toggle_load_plugin_textdomain() {
    load_plugin_textdomain( 'epfl-toggle', FALSE, basename( plugin_dir_path( __FILE__ )) . '/languages/');
}
add_action( 'plugins_loaded', 'epfl_toggle_load_plugin_textdomain' );

add_action( 'init', function() {

  // define the shortcode
  add_shortcode('epfl_toggle', 'epfl_toggle_process_shortcode');

});

add_action( 'register_shortcode_ui', ['ToggleShortCakeConfig', 'config'] );
?>
