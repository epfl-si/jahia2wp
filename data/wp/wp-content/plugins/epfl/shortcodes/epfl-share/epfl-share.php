<?php
/**
* Plugin Name: EPFL share
* Description: Provide share buttons for EPFL websites
* @version: 1.0
* @copyright: Copyright (c) 2018 Ecole Polytechnique Federale de Lausanne, Switzerland
*/

namespace Epfl\SocialFeed;

/*
Get http/https protocol at the website
*/
function get_http_protocol()
{
  if( isset( $_SERVER['HTTPS'] ) && ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] != 'off' ) {
    return "https://";
  }
  else {
    return "http://";
  }
}

/*
Sanitize post title
*/
function sanitize_post_title( $post_title ) {
  $post_title = html_entity_decode( $post_title, ENT_QUOTES, 'UTF-8' );
  $post_title = rawurlencode( $post_title );
  $post_title = str_replace( '#', '%23', $post_title );
  $post_title = esc_html( $post_title );

  return $post_title;
}

function get_target_url(){
  global $post;

  if (is_front_page()){
    $target_url = esc_url( home_url() );
  }
  elseif (!is_singular() && $type == 'vertical') {
    $target_url = html_entity_decode( esc_url( the_champ_get_http() . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"] ) );
  }
  elseif ( isset( $_SERVER['QUERY_STRING'] ) && $_SERVER['QUERY_STRING'] ) {
    $target_url = html_entity_decode( esc_url( $this->get_http_protocol() . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"] ) );
  }
  elseif ( get_permalink( $post -> ID ) ) {
    $target_url = get_permalink( $post -> ID );
  }
  else {
    $target_url = html_entity_decode( esc_url( $this->get_http_protocol() . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"] ) );
  }

  return $target_url;
} 

function epfl_share_process_shortcode( $atts, $content = null ) {

  // if supported delegate the rendering to the theme
  if (has_action("epfl_share_action")) {
    global $post;

    $post_title = sanitize_post_title($post->post_title);
    $target_url = get_target_url();
    $target_url_encoded = urlencode($target_url);

    ob_start();

    try {
       do_action("epfl_share_action", $post_title, $target_url, $target_url_encoded);
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
  add_shortcode('epfl_share_2018', __NAMESPACE__ . '\epfl_share_process_shortcode');
});

?>
