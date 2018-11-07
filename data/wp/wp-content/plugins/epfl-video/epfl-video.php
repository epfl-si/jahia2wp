<?php

/**
 * Plugin Name: EPFL Video
 * Description: provides a shortcode to display video from YouTube and SwitchTube
 * @version: 1.2
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
    'url' => '',
  ), $atts );

  // sanitize parameters
  $url  = $atts['url'];

  /* To handle video URL redirection*/
  if(($url = epfl_video_get_final_video_url($url)) === false)
  {
    return epfl_video_get_error(__("EPFL-Video: Error getting final URL", 'epfl-video'));
  }


  /* If YouTube video - Allowed formats:
    - https://www.youtube.com/watch?v=Tit6bvRIDtI
    - https://www.youtube.com/watch?v=Tit6bvRIDtI&t=281s
    - https://youtu.be/M4Ufs7-FpvU
    - https://www.youtube.com/watch?v=M4Ufs7-FpvU&feature=youtu.be
  */
  if(preg_match('/(youtube\.com|youtu\.be)/', $url)===1 && preg_match('/\/embed\//', $url)===0)
  {
    /* Extracting video ID from URL which is like one of the example before (we also extract rest of query string) */
    $video_id = str_replace('watch?v=', '', substr($url, strrpos($url, '/')+1 ));
    $url = "https://www.youtube.com/embed/".$video_id;
  }

  /* if Vimeo video - Allowed formats:
    - https://vimeo.com/escapev/espace
    - https://vimeo.com/escapev/espace#t=10s
    - https://vimeo.com/174044440
    - https://vimeo.com/174044440#t=10s
  */
  else if(preg_match('/vimeo\.com\/[0-9]+/', $url)===1 && preg_match('/\/embed\//', $url)===0)
  {
    /* Extracting video ID (and rest of string) from URL which is like :
    https://vimeo.com/174044440
    https://vimeo.com/174044440#t=10s
    */
    $video_id = substr($url, strrpos($url, '/')+1 );

    $url = "https://player.vimeo.com/video/".$video_id;
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

  return '<div class="container">'.
         '<div class="epfl-video epfl-video-responsive embed-responsive embed-responsive-16by9">'.
         '<iframe src="'.$url.'" webkitallowfullscreen mozallowfullscreen allowfullscreen allow="autoplay; encrypted-media" frameborder="0" class="embed-responsive-item"></iframe>'.
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
