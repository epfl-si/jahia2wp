<?php
/**
 * Plugin Name: EPFL Restauration
 * Description: provides a shortcode to display cafeterias and restaurant menus offers
 * Version: 0.1
 * Author: Lucien Chaboudez
 * Contributors:
 * License: Copyright (c) 2019 Ecole Polytechnique Federale de Lausanne, Switzerland
 **/


function epfl_restauration_process_shortcode( $atts, $content = null ) {

    global $wp;

    $atts = shortcode_atts( array(
        'params' => '',
    ), $atts );

    /* We remove ? at the beginning if any*/
    $params = preg_replace('/^\?/', '', $atts['params']);


    /* Prod */
    $url = 'https://menus.epfl.ch/cgi-bin/getMenus?'. $params;
    /* uncomment following line to access test environment */
    //$url = 'https://test-menus.epfl.ch/cgi-bin/getMenus?'. $params;


    /* Adding JavaScript */
    wp_enqueue_script( 'epfl_restauration_script', plugin_dir_url(__FILE__).'js/script.js' );
ob_start();

    /* While rendering the iframe, we have to add current URL in 'name' attribute. This then will be used by JavaScript
     code in iframe content to know where to send a message to tell iframe's height. This information will be used by
     JavaScript code in 'js/script.js' to resize iframe */
?>
<iframe src="<?PHP echo $url; ?>" name="<?PHP echo home_url( $wp->request ); ?>" frameborder="0" height="500" width="100%" scrolling="no" id="epfl-restauration">
</iframe>

<?php

return ob_get_clean();
}

add_action( 'init', function() {
  // define the shortcode
  add_shortcode('epfl_restauration', 'epfl_restauration_process_shortcode');
});
