<?php

Class ScienceQAUtils
{
  /**
   * Call API
   * @param url  : the url
   * @return decoded JSON data
   */
  public static function get_items(string $url)
  {
    $start = microtime(true);
    $response = wp_remote_get($url);
    $end = microtime(true);

    // Logging webservice call
    do_action('epfl_stats_webservice_call_duration', $url, $end-$start);

    
    if (is_wp_error($response))
    {
      return $response->get_error_message();
    }
    else
    { 
      return json_decode($response['body']);     
    }
  }
}
?>
