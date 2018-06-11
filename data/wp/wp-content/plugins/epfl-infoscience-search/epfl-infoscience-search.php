<?php

/**
 * Plugin Name: EPFL Infoscience search shortcode
 * Plugin URI: https://github.com/epfl-idevelop/jahia2wp
 * Description: provides a shortcode to search and dispay results from Infoscience
 * Version: 0.1
 * Author: Julien Delasoie
 * Author URI: https://people.epfl.ch/julien.delasoie?lang=en
 * Contributors: 
 * License: Copyright (c) 2018 Ecole Polytechnique Federale de Lausanne, Switzerland
*/
declare(strict_types=1);

require_once 'utils.php';
require_once 'shortcake-config.php';

function epfl_infoscience_search_process_shortcode($provided_attributes = [], $content = null, $tag = '')
{
    // normalize attribute keys, lowercase
    $atts = array_change_key_case((array)$provided_attributes, CASE_LOWER);

    $infoscience_search_mangaged_attributes = array(
        # Content
        'pattern' => '',
        'field' => 'any',  # "any", "author", "title", "year", "unit", "collection", "journal", "summary", "keyword", "issn", "doi"
        'limit' => 25,  # 10,25,50,100,250,500,1000
        'order' => 'desc',  # "asc", "desc"
        # Advanced content
        'pattern2' => '',
        'field2' => 'any',
        'operator2' => 'and', # "and", "or", "and_not"
        'pattern3' => '',
        'field3' => '',
        'operator3' => 'and',        
        'collection' => 'Infoscience/Research',        
        # Presentation
        'format' => 'short',  # "short", "detailed", "full"
        'show_thumbnail' => true,
        'group_by' => '', # "", "year", "doctype"
        'group_by2' => '', # "", "year", "doctype"
    );

    # TODO: use array_diff_key and compare unmanage attributes
    $attributes = shortcode_atts($infoscience_search_mangaged_attributes, $atts, $tag);

    // Sanitize parameter
    $url = sanitize_text_field( $attributes['url'] );
    # hardcode the url for the demo
    $url =  "https://infoscience.epfl.ch/publication-exports/232/";

    // Check if the result is already in cache
    $result = wp_cache_get( $url, 'epfl_infoscience_search' );
    if ( false == $result ){
        if ( strcasecmp( parse_url( $url, PHP_URL_HOST ), 'infoscience.epfl.ch' ) == 0 && epfl_infoscience_url_exists( $url ) ) {

            $response = wp_remote_get( $url );
            $page = wp_remote_retrieve_body( $response );

            // cache the result
            wp_cache_set( $url, $page, 'epfl_infoscience_search' );

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

// Load .mo file for translation
function epfl_infoscience_search_load_plugin_textdomain() {
    load_plugin_textdomain( 'epfl-infoscience-search', FALSE, basename( plugin_dir_path( __FILE__ )) . '/languages/');
}

add_action( 'plugins_loaded', 'epfl_infoscience_search_load_plugin_textdomain' );

add_action( 'admin_enqueue_scripts', ['InfoscienceSearchShortCakeConfig', 'load_epfl_infoscience_search_wp_admin_style'], 99);

add_action( 'init', function() {

    add_shortcode( 'epfl_infoscience_search', 'epfl_infoscience_search_process_shortcode' );

    // shortcake configuration
    if ( function_exists( 'shortcode_ui_register_for_shortcode' ) ) :
        InfoscienceSearchShortCakeConfig::config();
    endif;
});
?>
