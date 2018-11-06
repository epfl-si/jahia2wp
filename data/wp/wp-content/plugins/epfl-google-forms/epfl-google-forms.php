<?php

/**
 * Plugin Name: EPFL Google Forms
 * Description: provides a shortcode to display Google Forms
 * @version: 0.1
 * @copyright: Copyright (c) 2018 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

require_once 'shortcake-config.php';

/*
    Extracts an attribute from givent HTML code.

    $attribute  -> Attribute name to extract
    $from_code  -> HTML code in which to look for attribute.
*/
function epfl_toggle_get_attribute($attribute, $from_code)
{
    if(preg_match('/'.$attribute.'="(.*?)"/', $from_code, $matches)!==1)
    {
        return null;
    }
    return $matches[1];
}

function epfl_google_forms_process_shortcode( $atts, $content = null ) {

    /*
    data contains thing like (encoded):
    <iframe src="https://docs.google.com/forms/d/e/1FAIpQLSeLZkqncWIvRbQvnn3K8yKUEn0Of8s-JTFZ3l94TWAIHnovJA/viewform?embedded=true" width="640" height="663" frameborder="0" marginheight="0" marginwidth="0">Chargement en cours...</iframe>
    */
    $atts = shortcode_atts( array(
            'data' => ''
            ), $atts );

    // sanitize parameters
    $data = urldecode($atts['data']);

    /* Extracting needed attributes */
    $src = epfl_toggle_get_attribute('src', $data);
    $width = epfl_toggle_get_attribute('width', $data);
    $height = epfl_toggle_get_attribute('height', $data);

    /* Checking if all attributes are present */
    if($src===null || $height===null || $width===null)
    {
        return '<span style="color:red">'.__("Error extracting parameters", "epfl-google-forms").'</span>';
    }

    /* Check that iframe has a Google Forms URL as source */
    if(strpos($src, 'https://docs.google.com/forms') > 0)
    {
        return '<span style="color:red">'.__("Incorrect URL found", "epfl-google-forms").'</span>';
    }

    if(!is_numeric($width) || !is_numeric($height))
    {
        return '<span style="color:red">'.__("Incorrect dimensions found", "epfl-google-forms").'</span>';
    }

    return '<iframe src="'.esc_url($src).'" width="'.esc_attr($width).'" height="'.esc_attr($height).'" frameborder="0" marginheight="0" marginwidth="0">'.
           __("Loading...", "epfl-google-forms"). '</iframe>';


}


// load .mo file for translation
function epfl_google_forms_load_plugin_textdomain() {
    load_plugin_textdomain( 'epfl-google-forms', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}

add_action( 'plugins_loaded', 'epfl_google_forms_load_plugin_textdomain' );

add_action( 'register_shortcode_ui', ['ShortCakeGoogleFormsConfig', 'config'] );

add_action( 'init', function() {

  // define the shortcode
  add_shortcode('epfl_google_forms', 'epfl_google_forms_process_shortcode');

});


?>
