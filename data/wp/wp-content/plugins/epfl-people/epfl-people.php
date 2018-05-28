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

function epfl_people_log( $message ) {
    if ( WP_DEBUG === true ) {
        if ( is_array( $message ) || is_object( $message ) ) {
            error_log( print_r( $message, true ) );
        } else {
            error_log( $message );
        }
    }
}

function epfl_people_url_exists( $url )
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
        if ( ( strcasecmp( parse_url( $url, PHP_URL_HOST ), 'people.epfl.ch' ) == 0 or strcasecmp( parse_url( $url, PHP_URL_HOST ), 'test-people.epfl.ch' ) == 0 ) && epfl_people_url_exists( $url ) ) {

            // Get the content of the page
            $response = wp_remote_get( $url );
            $page = wp_remote_retrieve_body( $response );

            // cache the result
            wp_cache_set( $url, $page, 'epfl_people' );

            // return the page
            return '<div class="peopleListBox">'.
                    $page.
                    '</div>';
        } else {
            $error = new WP_Error( 'not found', 'The url passed is not part of people or is not found', $url );
            epfl_people_log( $error );
        }
    } else {
        // Use cache
        return $result;
    }
}

// Load .mo file for translation
function epfl_people_load_plugin_textdomain() {
    load_plugin_textdomain( 'epfl-people', FALSE, basename( plugin_dir_path( __FILE__ )) . '/languages/');
}
add_action( 'plugins_loaded', 'epfl_people_load_plugin_textdomain' );

add_action( 'init', function() {

    add_shortcode('epfl_people', 'epfl_people_process_shortcode');

    if ( function_exists( 'shortcode_ui_register_for_shortcode' ) ) :

        if (get_locale() == 'fr_FR') {
            $documentation_url = "https://help-wordpress.epfl.ch/autres-types-de-contenus/people/";
        } else {
            $documentation_url = "https://help-wordpress.epfl.ch/en/other-types-of-content/list-of-people/";
        }
        $url_description = sprintf(
            esc_html__('How to get a people URL ? %sRead this documentation%s', 'epfl-people'),
            '<a target="_blank" href="' . $documentation_url . '">', '</a>'
        );

        shortcode_ui_register_for_shortcode(

            'epfl_people',

            array(
                'label' => __('Add People shortcode', 'epfl-people'),
                'listItemImage' => '<img src="' . plugins_url( 'people.svg', __FILE__ ) . '" >',
                'attrs'         => array(
                    array(
                        'label'         => '<h3>' . esc_html__('Enter people URL', 'epfl-people') . '</h3>',
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
