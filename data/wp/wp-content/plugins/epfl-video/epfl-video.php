<?php

/**
 * Plugin Name: EPFL Video
 * Description: provides a shortcode to display video from YouTube and SwitchTube
 * @version: 1.1
 * @copyright: Copyright (c) 2018 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

require_once 'shortcake-config.php';

function epfl_video_process_shortcode( $atts, $content = null ) {

  $atts = shortcode_atts( array(
    'url' => '',
    'width' => '600',
    'height' => '400'
  ), $atts );

  // sanitize parameters
  $url  = $atts['url'];
  $width  = sanitize_text_field( $atts['width'] );
  $height  = sanitize_text_field( $atts['height'] );

  // If YouTube video
  if(preg_match('/(youtube\.com|youtu\.be)/', $url)===1 && preg_match('/\/embed\//', $url)===0)
  {
    /* Extracting video ID from URL which is like :
    https://www.youtube.com/watch?v=M4Ufs7-FpvU
    https://youtu.be/M4Ufs7-FpvU
    */
    $video_id = str_replace('watch?v=', '', substr($url, strrpos($url, '/')+1 ));

    $url = "https://www.youtube.com/embed/".$video_id;
  }
  // if Switch video
  else if(preg_match('/tube\.switch\.ch/', $url)===1 && preg_match('/\/embed\//', $url)===0)
  {
    /* Extracting video ID from URL which is like :
    https://tube.switch.ch/videos/2527ae24
    */

    $video_id = substr($url, strrpos($url, '/')+1 );

    $url = "https://tube.switch.ch/embed/".$video_id;
  }

  return '<div class="container>'.
         '<div class="epfl-video epfl-video-responsive embed-responsive embed-responsive-16by9">'.
         '<iframe src="'.$url.'" width="'.$width.'" height="'.$height.'" webkitallowfullscreen mozallowfullscreen allowfullscreen allow="autoplay; encrypted-media" frameborder="0" class="embed-responsive-item"></iframe>'.
         '</div>'.
         '</div>';

}


// load .mo file for translation
function epfl_video_load_plugin_textdomain() {
    load_plugin_textdomain( 'epfl-video', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}

add_action( 'plugins_loaded', 'epfl_video_load_plugin_textdomain' );

add_action( 'register_shortcode_ui', ['ShortCakeVideoConfig', 'config'] );

add_action( 'init', function() {

  // define the shortcode
  add_shortcode('epfl_video', 'epfl_video_process_shortcode');

});

add_action( 'wp_enqueue_scripts', function() {

  // enqueue style
   wp_enqueue_style( 'epfl_video_style', plugin_dir_url(__FILE__).'css/style.css' );

});

?>
