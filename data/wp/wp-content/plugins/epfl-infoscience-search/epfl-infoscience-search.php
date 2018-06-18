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

 set_include_path(get_include_path() . PATH_SEPARATOR . dirname( __FILE__) . '/lib');

require_once 'utils.php';
require_once 'shortcake-config.php';
require_once 'render.php';
require_once 'marc_converter.php';

define("INFOSCIENCE_SEARCH_URL", "https://infoscience.epfl.ch/search?");
define("SHORTCAKE_INFOSCIENCE_PARAMETERS_MAP", array(
    'pattern' => 'p1',
    'field' => 'f1',
    'limit' => 'rg',
    'order' => 'so',
    'collection' => 'c',
    'pattern2' => 'p2',
    'field2' => 'f2',
    'operator2' => 'op1',
    'pattern3' => 'p3',
    'field3' => 'f3',
    'operator3' => 'op2',
));

 /**
  * From any attributes, set them as url parameters for Infoscience
  *
  * @param array $attrs attributes that need to be sent to Infoscience
  *
  * @return string $url the url build
  */
function epfl_infoscience_search_generate_url_from_attrs($attrs) {
    $url = INFOSCIENCE_SEARCH_URL;

    $parameters = array(
        'as' => '1',  # advanced search 
        'ln' => 'en',  #TODO: dynamic langage
        'of' => 'xm'  # template format
    );

    $parameters = $attrs + $parameters;

    $parameters = InfoscienceSearchUtils::convert_keys($parameters, SHORTCAKE_INFOSCIENCE_PARAMETERS_MAP);

    # sort before build, for the caching system
    ksort($parameters);

    return INFOSCIENCE_SEARCH_URL . http_build_query($parameters);
}


function epfl_infoscience_search_process_shortcode($provided_attributes = [], $content = null, $tag = '')
{
    // normalize attribute keys, lowercase
    $atts = array_change_key_case((array)$provided_attributes, CASE_LOWER);

    $infoscience_search_managed_attributes = array(
        # Content
        'pattern' => '',
        'field' => 'any',  # "any", "author", "title", "year", "unit", "collection", "journal", "summary", "keyword", "issn", "doi"
        'limit' => 1000,  # 10,25,50,100,250,500,1000
        'order' => 'desc',  # "asc", "desc"
        # Advanced content
        'collection' => '',
        'pattern2' => '',
        'field2' => 'any',  # see field
        'operator2' => 'and',  # "and", "or", "and_not"
        'pattern3' => '',
        'field3' => '',  # see field
        'operator3' => 'and',  # "and", "or", "and_not"
        # Presentation
        'format' => 'short',  # "short", "detailed", "full"
        'show_thumbnail' => "false",  # "true", "false"
        'group_by' => '', # "", "year", "doctype"
        'group_by2' => '', # "", "year", "doctype"
    );

    # TODO: use array_diff_key and compare unmanaged attributes
    $attributes = shortcode_atts($infoscience_search_managed_attributes, $atts, $tag);

    $unmanaged_attributes = array_diff_key($atts, $attributes);

    # Sanitize parameters
    foreach ($unmanaged_attributes as $key => $value) {
        $unmanaged_attributes[$key] = sanitize_text_field($value);
    }

    $attributes['pattern'] = sanitize_text_field( $attributes['pattern'] );
    $attributes['pattern2'] = sanitize_text_field( $attributes['pattern2'] );
    $attributes['pattern3'] = sanitize_text_field( $attributes['pattern3'] );
    $attributes['collection'] = sanitize_text_field( $attributes['collection'] );

    $attributes['show_thumbnail'] = $attributes['show_thumnail'] === 'true'? true: false;
    # if limit is empty, set it to max
    if($attributes['limit'] === '')
    {
        $attributes['limit'] = 1000;
    }

    # Unused element at the moment
    unset($attributes['format']);
    unset($attributes['show_thumbnail']);
    unset($attributes['group_by']);
    unset($attributes['group_by2']);
    
    $url = epfl_infoscience_search_generate_url_from_attrs($attributes+$unmanaged_attributes);

    // Check if the result is already in cache
    $result = wp_cache_get( $url, 'epfl_infoscience_search' );

    # TODO: reactivate cache
    #if ( false == $result ){
    if ( false == false ){
        if (epfl_infoscience_url_exists( $url ) ) {

            $response = wp_remote_get( $url );
            $marc_xml = wp_remote_retrieve_body( $response );

            $publications = InfoscienceMarcConverter::convert_marc_to_array($marc_xml);

            if (has_action("epfl_infoscience_search_action")) {
                ob_start();
                try {
                    do_action("epfl_infoscience_search_action", $publications);
                    $page = ob_get_contents();
                } finally {
                    ob_end_clean();
                }
            } else {
                $page = InfoscienceRender::epfl_infoscience_search_build_html($publications);
            }

            // wrap the page
            $page = '<div class="infoscienceBox">'.
                        $page.
                    '</div>';

            // cache the result
            wp_cache_set( $url, $page, 'epfl_infoscience_search' );

            // return the page
            return '<div style="border:2px solid black;padding:8px;">' . $url . '</div>' . $page;
        } else {
            $error = new WP_Error( 'not found', 'The url passed is not found', $url );
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

add_action( 'init', function() {

    add_shortcode( 'epfl_infoscience_search', 'epfl_infoscience_search_process_shortcode' );

    // shortcake configuration
    if ( function_exists( 'shortcode_ui_register_for_shortcode' ) ) :
        InfoscienceSearchShortCakeConfig::config();
    endif;
});
?>
