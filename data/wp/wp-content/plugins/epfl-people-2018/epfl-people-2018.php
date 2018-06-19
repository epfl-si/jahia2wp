<?php

/**
 * Plugin Name: EPFL People shortcode
 * Plugin URI: https://github.com/epfl-idevelop/jahia2wp
 * Description: provides a shortcode to display results from People
 * Version: 1.1
 * License: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
 **/

require_once 'render.php';
require_once 'shortcake-config.php';

function epfl_people_log( $message ) {
    if ( WP_DEBUG === true ) {
        if ( is_array( $message ) || is_object( $message ) ) {
            error_log( print_r( $message, true ) );
        } else {
            error_log( $message );
        }
    }
}

function epfl_people_2018_process_shortcode( $attributes, $content = null )
{
   $attributes = shortcode_atts( array(
        'unit' => ''
    ), $attributes );
   
   // Sanitize parameter
	$unit = sanitize_text_field( $attributes['unit'] );
	
	// the web service we use to retrieve the data, can be "wsgetpeople" or "getProfiles"
	$ws = "wsgetpeople";
	
	switch($ws)
	{
		case "wsgetpeople":
			$url = "https://people.epfl.ch/cgi-bin/wsgetpeople?units=$unit&app=self&caller=104782";
			break;
		case "getProfiles":
			$url = "https://people.epfl.ch/cgi-bin/getProfiles?unit=$unit&tmpl=JSON";
			break;
		default:
		   throw new Exception("Unknown web service: $ws");	
	}
	
	$items = PeopleUtils::get_items($url);
	
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
   	return PeopleRender::build_html($items, $ws);
   }
}

// Load .mo file for translation
function epfl_people_2018_load_plugin_textdomain()
{
    load_plugin_textdomain( 'epfl-people-2018', FALSE, basename( plugin_dir_path( __FILE__ )) . '/languages/');
}

add_action( 'plugins_loaded', 'epfl_people_2018_load_plugin_textdomain' );

add_action( 'init', function() {

    // define the shortcode
    add_shortcode('epfl_people_2018', 'epfl_people_2018_process_shortcode');

    // shortcake configuration
    if ( function_exists( 'shortcode_ui_register_for_shortcode' ) ) :
       ShortCakePeople2018Config::config();
    endif;

});

?>
