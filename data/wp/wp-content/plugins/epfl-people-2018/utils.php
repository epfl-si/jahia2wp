<?php

/**
 * Utils
 */
Class PeopleUtils
{
  /**
   * Call API
   * @param url  : the url
   * @return decoded JSON data
   */
  public static function get_items(string $url)
  {
    $response = wp_remote_get($url);
    
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