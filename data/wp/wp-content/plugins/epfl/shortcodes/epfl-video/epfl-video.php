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
    'url'    => '',
  ), $atts );

  // sanitize parameters
  $url    = esc_url($atts['url']);

  /* To handle video URL redirection

    NOTE: if we have an URL like 'https://youtu.be/M4Ufs7-FpvU', it will be transformed to
    https://youtube.com/watch?v=M4Ufs7-FpvU&feature=youtu.be
    So, '&feature=youtu.be' will be added at the end and we will have to handle it.
  */
  if(($url = epfl_video_get_final_video_url($url)) === false)
  {
    return epfl_video_get_error(__("EPFL-Video: Error getting final URL", 'epfl-video'));
  }

  /* If YouTube video - Allowed formats:
    - https://www.youtube.com/watch?v=Tit6bvRIDtI
    - https://www.youtube.com/watch?v=Tit6bvRIDtI&t=281s
    - https://www.youtube.com/watch?v=M4Ufs7-FpvU&feature=youtu.be
  */
  if(preg_match('/(youtube\.com|youtu\.be)/', $url)===1 && preg_match('/\/embed\//', $url)===0)
  {
    /* Extracting query string */
    $query = parse_url($url, PHP_URL_QUERY);

    parse_str($query, $query_args);

    $video_id = $query_args['v'];
    /* Removing video ID from query string */
    unset($query_args['v']);

    /* If we have a time at which to start video */
    if(array_key_exists('t', $query_args))
    {
        /* When video is embed, the parameters is called 'start' and not 't', so we remove the incorrect one
        and add the new one. */
        $query_args['start'] = $query_args['t'];
        unset($query_args['t']);
    }

    /* We remove existing query string from URL */
    $url = str_replace('?'.$query, '', $url);

    /* Updating query (without video_id if it was present) to reuse it later */
    $query = http_build_query($query_args);

    $url = "https://www.youtube.com/embed/".$video_id;
    if($query != "")
    {
        $url .= '?'.$query;
    }
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
