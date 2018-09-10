<?php

/**
 * Plugin Name: EPFL People shortcode
 * Description: display results from people. This shortcode is intended to be used only with the new
 * web2018 theme, so it's not activated by default
 * Version: 1.0
 * License: Copyright (c) 2018 Ecole Polytechnique Federale de Lausanne, Switzerland
 **/

require_once 'shortcake-config.php';

/**
 * Process the shortcode
 */
function epfl_people_2018_process_shortcode( $attributes, $content = null )
{
  $attributes = shortcode_atts( array(
       'unit' => ''
    ), $attributes );
   
   // sanitize the parameters
  $unit = sanitize_text_field( $attributes['unit'] );
	
  // the web service we use to retrieve the data, can be "wsgetpeople" or "getProfiles"
  $ws = "getProfiles";
	
  switch($ws)
  {
    case "wsgetpeople":
      $url = "https://people.epfl.ch/cgi-bin/wsgetpeople?units=$unit&app=self&caller=104782";
      break;
    case "getProfiles":
      // TODO use production URL when it's ready
      $url = "https://test-people.epfl.ch/cgi-bin/getProfiles?unit=$unit&tmpl=JSON";
      break;
    default:
      throw new Exception("Unknown web service: $ws");	
  }
	
  // retrieve the data in JSON
  $items = Utils::get_items($url);
	
  // if supported delegate the rendering to the theme
  if (has_action("epfl_people_action"))
  {
    ob_start();
   	
    try
    {
      do_action("epfl_people_action", $items);
   		
      return ob_get_contents();
    }
    finally
    {
      ob_end_clean();
    }
  }   
  // otherwise the plugin does the rendering
  else 
  {
    return 'You must activate the epfl theme';
  }
}

// init action
add_action( 'init', function()
{
  // define the shortcode
  add_shortcode('epfl_people_2018', 'epfl_people_2018_process_shortcode');
});

add_action( 'register_shortcode_ui', ['ShortCakePeopleConfig', 'config'] );

?>
