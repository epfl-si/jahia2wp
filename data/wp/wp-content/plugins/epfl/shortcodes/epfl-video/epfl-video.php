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
    'url'    => '',
    'width'  => '600',
    'height' => '400'
  ), $atts );

  // sanitize parameters
  $url    = esc_url($atts['url']);
  $width  = sanitize_text_field( $atts['width'] );
  $height = sanitize_text_field( $atts['height'] );

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

  // if supported delegate the rendering to the theme
  if (has_action("epfl_video_action")) {

    ob_start();

    try {

       do_action("epfl_video_action", $url, $width, $height);

       return ob_get_contents();

    } finally {

        ob_end_clean();
    }

  // otherwise the plugin does the rendering
  } else {

      return 'You must activate the epfl theme';
  }
}

add_action( 'register_shortcode_ui', ['ShortCakeVideoConfig', 'config'] );

add_action( 'init', function() {

  // define the shortcode
  add_shortcode('epfl_video', 'epfl_video_process_shortcode');

});

?>
