<?php

/**
 * Plugin Name: EPFL Infoscience shortcode
 * Plugin URI: https://github.com/jaepetto/EPFL-SC-Infoscience
 * Description: provides a shortcode to dispay results from Infoscience
 * Version: 1.1
 * Author: Emmanuel JAEP
 * Author URI: https://people.epfl.ch/emmanuel.jaep?lang=en
 * Contributors: LuluTchab, GregLeBarbar
 * License: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
*/

function epfl_infoscience_url_exists( $url )
{
    $handle = curl_init( $url );
    curl_setopt( $handle, CURLOPT_RETURNTRANSFER, TRUE );

    $response = curl_exec( $handle );
    $httpCode = curl_getinfo( $handle, CURLINFO_HTTP_CODE );

    if ( $httpCode >= 200 && $httpCode <= 400 ) {
        return true;
    } else {
        return false;
    }

    curl_close($handle);
}

function epfl_infoscience_process_shortcode( $attributes, $content = null )
{
    $attributes = shortcode_atts( array(
        'url' => ''
    ), $attributes);

    // Sanitize parameter
    $url = sanitize_text_field( $attributes['url'] );

    // Check if the result is already in cache
    $result = wp_cache_get( $url, 'epfl_infoscience' );
    if ( false === $result ){
        if ( strcasecmp( parse_url( $url, PHP_URL_HOST ), 'infoscience.epfl.ch' ) == 0 && epfl_infoscience_url_exists( $url ) ) {

            $response = wp_remote_get( $url );
            $page = wp_remote_retrieve_body( $response );

            // wrap the page
            $page = '<div class="infoscienceBox">'.
                        $page.
                    '</div>';

            // cache the result
            wp_cache_set( $url, $page, 'epfl_infoscience' );

            // return the page
            return $page;
        } else {
            $error = new WP_Error( 'not found', 'The url passed is not part of Infoscience or is not found', $url );

        }
    } else {
        // Use cache
        return $result;
    }
}

// Load .mo file for translation
function epfl_infoscience_load_plugin_textdomain() {
    load_plugin_textdomain( 'epfl-infoscience', FALSE, basename( plugin_dir_path( __FILE__ )) . '/languages/');
}
add_action( 'plugins_loaded', 'epfl_infoscience_load_plugin_textdomain' );

add_action( 'init', function() {

    add_shortcode( 'epfl_infoscience', 'epfl_infoscience_process_shortcode' );

    if ( function_exists( 'shortcode_ui_register_for_shortcode' ) ) :

        $documentation_url = "https://support.epfl.ch/kb_view.do?sysparm_article=KB0014227";

        $url_description = sprintf(
            esc_html__('How to get an infoscience URL to insert publications? %sRead this documentation%s', 'epfl-infoscience'),
            '<a target="_blank" href="' . $documentation_url . '">', '</a>'
        );

        shortcode_ui_register_for_shortcode(

            'epfl_infoscience',

            array(
                'label' => __('Add Infoscience shortcode', 'epfl-infoscience'),
                'listItemImage' => '<img src="' . plugins_url( 'img/infoscience.svg', __FILE__ ) . '" >',
                'attrs'         => array(
                    array(
                        'label'         => '<h3>' . esc_html__('Enter infoscience URL', 'epfl-infoscience') . '</h3>',
                        'attr'          => 'url',
                        'type'          => 'text',
                        'description'   => $url_description,
                    ),
                ),

                'post_type'     => array( 'post', 'page' ),
            )
        );
    endif;
});
?>
