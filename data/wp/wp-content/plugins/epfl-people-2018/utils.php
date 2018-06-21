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
    
    if (is_array($response))
    {
      $header = $response['headers'];
      $data = $response['body'];
      
      return json_decode($data);     
    }
  }
}

?>