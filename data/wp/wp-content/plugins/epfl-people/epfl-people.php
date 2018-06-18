<?php

/**
 * Plugin Name: EPFL People shortcode
 * Plugin URI: https://github.com/epfl-idevelop/EPFL-WP-SC-People
 * Description: provides a shortcode to display results from People
 * Version: 1.1
 * Author: Emmanuel JAEP
 * Author URI: https://people.epfl.ch/emmanuel.jaep?lang=en
 * Contributors: LuluTchab, GregLeBarbar
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

function epfl_people_process_shortcode( $attributes, $content = null )
{
	
   $attributes = shortcode_atts( array(
        'unit' => ''
    ), $attributes );
    
   
   // Sanitize parameter
	$unit = sanitize_text_field( $attributes['unit'] );
	
	$url = "https://people.epfl.ch/cgi-bin/wsgetpeople?units=$unit&app=self&caller=104782";
	
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
   	return PeopleRender::epfl_people_build_html($items);
   }
}

// Load .mo file for translation
function epfl_people_load_plugin_textdomain()
{
    load_plugin_textdomain( 'epfl-people', FALSE, basename( plugin_dir_path( __FILE__ )) . '/languages/');
}

add_action( 'plugins_loaded', 'epfl_people_load_plugin_textdomain' );

add_action( 'init', function() {

    // define the shortcode
    add_shortcode('epfl_people', 'epfl_people_process_shortcode');

    // shortcake configuration
    if ( function_exists( 'shortcode_ui_register_for_shortcode' ) ) :
       ShortCakePeopleConfig::config();
    endif;

});

?>
