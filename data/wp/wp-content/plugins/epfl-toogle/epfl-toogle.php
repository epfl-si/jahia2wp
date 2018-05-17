<?php

/**
 * Plugin Name: EPFL toogle
 * Description: provides a shortcode to display toogle content
 * @version: 1.0
 * @copyright: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

function toggle( $atts, $content = null ) {
  $a = shortcode_atts( array(
        'title' => 'Title',
        'state' => 'open',
    ), $atts );
  $html = '<section class="collapsible ' . esc_attr( $a['state'] ) . '"><div class="collapsible-header"><h3 class="title collapse-link">' . esc_attr( $a['title'] ) . '</h3></div><div class="content collapsible-content clearfix">';
  $html .= do_shortcode($content);
  $html .= '</div></section>';
  return $html;
}

add_shortcode('toggle-box', 'toggle');

?>