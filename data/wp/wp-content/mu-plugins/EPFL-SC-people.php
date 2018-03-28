<?php

/**
 * Plugin Name: EPFL People shortcode
 * Plugin URI: https://github.com/epfl-idevelop/EPFL-WP-SC-People
 * Description: provides a shortcode to display results from People
 * Version: 1.0
 * Author: Emmanuel JAEP
 * Author URI: https://people.epfl.ch/emmanuel.jaep?lang=en
 * License: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
 **/

function epfl_people_log( $message ) {
    if ( WP_DEBUG === true ) {
        if ( is_array( $message ) || is_object( $message ) ) {
            error_log( print_r( $message, true ) );
        } else {
            error_log( $message );
        }
    }
}

function epfl_people_urlExists( $url )
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
    curl_close( $handle );
}

function epfl_people_process_shortcode( $attributes, $content = null )
{
    $attributes = shortcode_atts( array(
        'url' => ''
    ), $attributes );

    // Sanitize parameter
    $url = sanitize_text_field( $attributes['url'] );

    // Check if the result is already in cache
    $result = wp_cache_get( $url, 'epfl_people' );
    if ( false === $result ){

        // Make sure the content is actually coming from the people pages and does exist
        if ( ( strcasecmp( parse_url( $url, PHP_URL_HOST ), 'people.epfl.ch' ) == 0 or strcasecmp( parse_url( $url, PHP_URL_HOST ), 'test-people.epfl.ch' ) == 0 ) && epfl_people_urlExists( $url ) ) {

            // Get the content of the page
            $page = file_get_contents( $url );

            // cache the result
            wp_cache_set( $url, $page, 'epfl_people' );

            // return the page
            return $page;
        } else {
            $error = new WP_Error( 'not found', 'The url passed is not part of people or is not found', $url );
            epfl_people_log( $error );
        }
    }
}

add_shortcode('epfl_people', 'epfl_people_process_shortcode');

?>