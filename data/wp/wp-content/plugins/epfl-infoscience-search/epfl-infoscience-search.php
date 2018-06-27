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

 /**
  * From any attributes, set them as url parameters for Infoscience
  *
  * @param array $attrs attributes that need to be sent to Infoscience
  *
  * @return string $url the url build
  */
  function convert_keys_values($array_to_convert) {
    $convert_fields = function($value) {
        if ($value == 'any'){
           return '';
        }
        return $value; 
    };

    $convert_operators = function($value) {
        if ($value == 'and'){
           return 'a';
        }
        return $value; 
    };

    $map = array(
        'pattern' => ['p1', sanitize_text_field],
        'field' => ['f1', $convert_fields],
        'limit' => ['rg', function($value) {
            if ($value == ''){
               return 1000;
            }
            return $value; 
        }],
        'sort' => ['so', function($value) {
            if ($value == 'asc'){
               return 'asc';
            }
            return 'desc'; 
        }],
        'collection' => ['c', sanitize_text_field],
        'pattern2' => ['p2', sanitize_text_field],
        'field2' => ['f2', $convert_fields],
        'operator2' => ['op1', $convert_operators],
        'pattern3' => ['p3', sanitize_text_field],
        'field3' => ['f3', $convert_fields],
        'operator3' => ['op2', $convert_operators],
    );

    $converted_array = array();

    foreach ($array_to_convert as $key => $value) {
        if (array_key_exists($key, $map)) {
            # is the convert function defined
            if (array_key_exists(1, $map[$key]) && $map[$key][1])
            {
                $converted_array[$map[$key][0]] = $map[$key][1]($value);
            } else {
                $converted_array[$map[$key][0]] = $value;
            }
        }
        else {
            $converted_array[$key] = $value;
        }
    }
    return $converted_array;
}

 /**
  * From any attributes, set them as url parameters for Infoscience
  *
  * @param array $attrs attributes that need to be sent to Infoscience
  *
  * @return string $url the url build
  */
function epfl_infoscience_search_generate_url_from_attrs($attrs) {
    $url = INFOSCIENCE_SEARCH_URL;

    $default_parameters = array(
        'as' => '1',  # advanced search 
        'ln' => 'en',  #TODO: dynamic langage
        'of' => 'xm',  # template format
    );
    $parameters = convert_keys_values($attrs);
    $parameters = $default_parameters + $parameters;
    $parameters = array_filter($parameters);

    # sort before build, for the caching system
    ksort($parameters);

    return INFOSCIENCE_SEARCH_URL . http_build_query($parameters);
}

/*
 Group by operations
*/

function sanitize_group_by($group_by_value) {
    if ($group_by_value) {
        $group_by_value = in_array(strtolower($group_by_value), ['year', 'doctype']) ? strtolower($group_by_value) : null;
        return $group_by_value;
    } else {
        return;
    }
}

function do_group_by($publications, $group_by=null, $group_by2=null) {
    $grouped_publications = [];
    if ($group_by === 'year') {
        $grouped_publications['group_by'] = InfoscienceSearchUtils::array_group_by($publications, 'publication_date');
    } elseif (($group_by === 'doctype')) {
        $grouped_publications['group_by'] = InfoscienceSearchUtils::array_group_by($publications, 'doctype');
    }
    else {
        # no group, set the same array level if so, without any label
        $grouped_publications['group_by'] = [
            'title' => null,
            'values' => $publications,
        ];
    }

    #TODO: second level group_by
    
    return $grouped_publications;
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
        'sort' => 'desc',  # "asc", "desc"
        # Advanced content
        'collection' => '',
        'pattern2' => '',
        'field2' => 'any',  # see field
        'operator2' => 'and',  # "and", "or", "and_not"
        'pattern3' => '',
        'field3' => '',  # see field
        'operator3' => 'and',  # "and", "or", "and_not"
        # Presentation
        'format' => 'short',  # "short", "detailed"
        'summary' => 'false', 
        'thumbnail' => "false",  # "true", "false"
        'group_by' => '', # "", "year", "doctype"
        'group_by2' => '', # "", "year", "doctype"
        # Dev
        'debug' => 'false',
        'debug_data' => 'false',
        'debug_template' => 'false',
    );

    # TODO: use array_diff_key and compare unmanaged attributes
    $attributes = shortcode_atts($infoscience_search_managed_attributes, $atts, $tag);

    $unmanaged_attributes = array_diff_key($atts, $attributes);

    # Sanitize parameters
    foreach ($unmanaged_attributes as $key => $value) {
        $unmanaged_attributes[$key] = sanitize_text_field($value);
    }

    $attributes['summary'] = $attributes['summary'] === 'true' ? true : false;
    $attributes['thumbnail'] = $attributes['thumbnail'] === 'true' ? true : false;
    $attributes['format'] = in_array(strtolower($attributes['format']), ['short', 'detailed']) ? strtolower($attributes['format']) : 'short';

    $attributes['group_by'] = sanitize_group_by($attributes['group_by']);
    $attributes['group_by2'] = sanitize_group_by($attributes['group_by2']);
        
    $attributes['debug'] = strtolower($attributes['debug']) === 'true' ? true : false;
    $attributes['debug_data'] = strtolower($attributes['debug_data']) === 'true' ? true : false;
    $attributes['debug_template'] = strtolower($attributes['debug_template']) === 'true' ? true : false;
    
    # Unset element unused in url
    $format = $attributes['format'];
    unset($attributes['format']);    

    $summary = $attributes['summary'];
    unset($attributes['summary']);

    $thumbnail = $attributes['thumbnail'];
    unset($attributes['thumbnail']);

    $group_by = $attributes['group_by'];
    unset($attributes['group_by']);
    $group_by2 = $attributes['group_by2'];
    unset($attributes['group_by2']);

    if ( $attributes['debug']) {
        $debug_data = $attributes['debug'];  # alias
        unset($attributes['debug']);
    } else {
        $debug_data = $attributes['debug_data'];
        unset($attributes['debug_data']);
    }

    $debug_template = $attributes['debug_template'];
    unset($attributes['debug_template']);

    $url = epfl_infoscience_search_generate_url_from_attrs($attributes+$unmanaged_attributes);

    // Check if the result is already in cache
    $page = wp_cache_get( $url, 'epfl_infoscience_search' );
    
    # not in cache ?
    # TODO: reactivate cache
    # if ($page == false || $debug_data || $debug_template){
    if (true){
    
        if (epfl_infoscience_url_exists( $url ) ) {
            $response = wp_remote_get( $url );

            if ( is_wp_error( $response ) ) {
                $error_message = $response->get_error_message();
                echo "Something went wrong: $error_message";
             } else {
                $marc_xml = wp_remote_retrieve_body( $response );

                $publications = InfoscienceMarcConverter::convert_marc_to_array($marc_xml);
                
                $publications = do_group_by($publications, $group_by, $group_by2);

                if ($debug_data) {
                    $page = RawInfoscienceRender::render($publications, $url);
                    return $page;
                }

                # try to load render from theme if available
                if (has_action("epfl_infoscience_search_action")) {
                    ob_start();
                    try {
                        do_action("epfl_infoscience_search_action", $publications);
                        $page = ob_get_contents();
                    } finally {
                        ob_end_clean();
                    }
                } else {
                    # use the self renderer
                    $page = HtmlInfoscienceRender::render($publications, $format, $summary, $thumbnail, $debug_template);
                }

                // wrap the page
                $page = '<div class="infoscienceBox">'.
                            $page.
                        '</div>';

                // cache the result
                wp_cache_set( $url, $page, 'epfl_infoscience_search' );

                // return the page
                return $page;
            }
        } else {
            $error = new WP_Error( 'not found', 'The url passed is not found', $url );
            epfl_infoscience_log( $error );
        }
    } else {
        // Use cache
        return $page;
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
