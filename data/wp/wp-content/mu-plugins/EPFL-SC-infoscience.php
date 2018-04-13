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

function epfl_infoscience_log( $message )
{
    if ( WP_DEBUG === true ) {
        if ( is_array( $message ) || is_object( $message ) ) {
            error_log( print_r( $message, true ) );
        } else {
            error_log( $message );
        }
    }
}

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

            // cache the result
            wp_cache_set( $url, $page, 'epfl_infoscience' );

            // return the page
            return '<div class="infoscienceBox">'.
                    $page.
                    '</div>';
        } else {
            $error = new WP_Error( 'not found', 'The url passed is not part of Infoscience or is not found', $url );
            epfl_infoscience_log( $error );
        }
    } else {
        // Use cache
        return $result;
    }
}

add_shortcode( 'epfl_infoscience', 'epfl_infoscience_process_shortcode' );
?>