<?php

require_once 'shortcake-config.php';

/*
    Extracts an attribute from givent HTML code.

    $attribute  -> Attribute name to extract
    $from_code  -> HTML code in which to look for attribute.
*/
function epfl_google_forms_get_attribute($attribute, $from_code)
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
    $src = epfl_google_forms_get_attribute('src', $data);
    $width = epfl_google_forms_get_attribute('width', $data);
    $height = epfl_google_forms_get_attribute('height', $data);

    /* Checking if all attributes are present */
    if($src===null || $height===null || $width===null)
    {
        return Utils::render_user_msg(__("Error extracting parameters", "epfl"));
    }

    /* Check that iframe has a Google Forms URL as source */
    if(strpos($src, 'https://docs.google.com/forms') > 0)
    {
        return Utils::render_user_msg(__("Incorrect URL found", "epfl"));
    }

    if(!is_numeric($width) || !is_numeric($height))
    {
        return Utils::render_user_msg(__("Incorrect dimensions found", "epfl"));
    }

    // if supported delegate the rendering to the theme
    if (has_action("epfl_google_forms_action")) {

        ob_start();

        try {

           do_action("epfl_google_forms_action", $src, $width, $height, __("Loading...", "epfl"));

           return ob_get_contents();

        } finally {

            ob_end_clean();
        }

    // otherwise the plugin does the rendering
    } else {

        return 'You must activate the epfl theme';
    }

}

add_action( 'register_shortcode_ui', ['ShortCakeGoogleFormsConfig', 'config'] );

add_action( 'init', function() {

  // define the shortcode
  add_shortcode('epfl_google_forms', 'epfl_google_forms_process_shortcode');

});


?>
