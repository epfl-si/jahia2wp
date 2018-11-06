<?php

/**
 * Plugin Name: EPFL Video
 * Description: provides a shortcode to display video from YouTube and SwitchTube
 * @version: 1.1
 * @copyright: Copyright (c) 2018 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

require_once 'shortcake-config.php';

function epfl_video_get_final_video_url($url)
{
    $ch = curl_init();
    // the url to request
    curl_setopt( $ch, CURLOPT_URL, $url );
    // (don't) verify host ssl cert
    curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
    // (don't) verify peer ssl cert
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
    // To disable page display when executing curl_exec
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);

    if ( ($response = curl_exec( $ch ))=== false )	{
        // if we get an error, use that
        error_log("EPFL-video: ".curl_error( $ch ));
        $res = false;
    }
    else // no error
    {

        $redirect_url = curl_getinfo($ch, CURLINFO_REDIRECT_URL);

        // If there's no redirection
        $res = empty(trim($redirect_url))?$url:$redirect_url;
    }
    // close the resource
    curl_close( $ch );

    return $res;
}

function epfl_video_get_error($error)
{
    return '<p><font color="red">'.$error.'</font></p>';
}

function epfl_video_process_shortcode( $atts, $content = null ) {

  $atts = shortcode_atts( array(
    'url'    => '',
  ), $atts );

  // sanitize parameters
  $url    = esc_url($atts['url']);

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

       do_action("epfl_video_action", $url);

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
