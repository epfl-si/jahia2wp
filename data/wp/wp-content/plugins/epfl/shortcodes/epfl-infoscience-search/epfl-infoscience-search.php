<?php

/**
 * Plugin Name: EPFL Infoscience search shortcode
 * Plugin URI: https://github.com/epfl-idevelop/jahia2wp
 * Description: provides a shortcode to search and dispay results from Infoscience
 * Version: 1.8
 * Author: Julien Delasoie
 * Author URI: https://people.epfl.ch/julien.delasoie?lang=en
 * Contributors: 
 * License: Copyright (c) 2018 Ecole Polytechnique Federale de Lausanne, Switzerland
*/

set_include_path(get_include_path() . PATH_SEPARATOR . dirname( __FILE__) . '/lib');
 
require_once 'utils.php';
require_once 'shortcake-config.php';
require_once 'render.php';
require_once 'marc_converter.php';
require_once 'group_by.php';
require_once 'mathjax-config.php';

define("INFOSCIENCE_SEARCH_URL", "https://infoscience.epfl.ch/search?");

 /**
  * From any attributes, set them as url parameters for Infoscience
  *
  * @param array $attrs attributes that need to be sent to Infoscience
  *
  * @return string $url the url build
  */
  function epfl_infoscience_search_convert_keys_values($array_to_convert) {
    $convert_fields = function($value) {
        return ($value === 'any') ? '' : $value;
    };

    $convert_operators = function($value) {
        if ($value === 'and') {
            return 'a';
        } elseif ($value === 'or') {
            return  'o';
        } elseif ($value === 'and_not') {
            return  'n';
        } else {
            return $value;
        }
    };

    $sanitize_text_field = function($value) {
        return sanitize_text_field($value);
    };

    $map = array(
        'pattern' => ['p1', $sanitize_text_field],
        'field' => ['f1', $convert_fields],
        'limit' => ['rg', function($value) {
            return ($value === '') ? '1000' : $value;
        }],
        'sort' => ['so', function($value) {
            return ($value === 'asc') ? 'a' : 'd';
        }],
        'collection' => ['c', $sanitize_text_field],
        'pattern2' => ['p2', $sanitize_text_field],
        'field2' => ['f2', $convert_fields],
        'operator2' => ['op1', $convert_operators],
        'pattern3' => ['p3', $sanitize_text_field],
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
        'ln' => 'en',  #TODO: dynamic language
        'of' => 'xm',  # template format
        'sf' => 'year', # year sorting
    );

    $parameters = epfl_infoscience_search_convert_keys_values($attrs);
    $parameters = $default_parameters + $parameters;

    $additional_parameters_array = [
        # remove pendings by setting collection to accepted
        'c' => 'Infoscience/Published',
    ];

    foreach($additional_parameters_array as $key => $add_params) {
        if (array_key_exists($key, $parameters)) {
            $parameters[$key] = [
                $parameters[$key],
                $add_params,
            ];
        }
    }

    $parameters = array_filter($parameters);

    # sort before build, for the caching system
    ksort($parameters);

    return INFOSCIENCE_SEARCH_URL . http_build_query($parameters);
}

function epfl_infoscience_search_process_shortcode($provided_attributes = [], $content = null, $tag = '')
{
    # deliver the css
    wp_enqueue_style('epfl-infoscience-search-shortcode-style.css');

    # add the MathJS for nice render
    # try [epfl_infoscience_search pattern="001:'255565'" summary="true" /] for a nice example
    wp_enqueue_script('epfl-infoscience-search-shortcode-math-main.js', $in_footer=true);

    // // normalize attribute keys, lowercase
    $atts = array_change_key_case((array)$provided_attributes, CASE_LOWER);

    $infoscience_search_managed_attributes = array(
        # Content 1
        'url' => '',
        # or Content 2
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

    $attributes['group_by'] = InfoscienceGroupBy::sanitize_group_by($attributes['group_by']);
    $attributes['group_by2'] = InfoscienceGroupBy::sanitize_group_by($attributes['group_by2']);
        
    $attributes['debug'] = strtolower($attributes['debug']) === 'true' ? true : false;
    $attributes['debug_data'] = strtolower($attributes['debug_data']) === 'true' ? true : false;
    $attributes['debug_template'] = strtolower($attributes['debug_template']) === 'true' ? true : false;
    
    # Unset element unused in url, with backup first
    $before_unset_attributes = $attributes;

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

    /**
    * Url priority :
    * 1. inner content
    * 2. url attribute
    * 3. with all the attributes, we built a custom one
    */
    $url = null;

    if ($content) {
        $url = $content;
        $url = str_replace("<p>","", $url);
        $url = str_replace("</p>","", $url);
        $url = str_replace("<br />","", $url);
        $url = str_replace("\n","", $url);
        $url = html_entity_decode($url);
    } elseif (array_key_exists('url', $attributes) && !empty($attributes['url'])) {
        # we are in the "direct url provided" mode, from the attribut
        $url = htmlspecialchars_decode($attributes['url']);
    }

    if ($url) {
        $url = trim($url);
        # assert it is an infoscience one : 
        if (preg_match('#^https?://infoscience.epfl.ch/#i', $url) !== 1) {
            $error = new WP_Error( 'not found', 'The url passed is not an Infoscience url', $url );
            return Utils::render_user_msg("Infoscience search shortcode: Please check the url");
        }

        $parts = parse_url($url);
        $query = proper_parse_str($parts['query']);

        # override values
        # set the given url to the good format
        $query['of'] = 'xm';

        # set default if not already set :
        if (!array_key_exists('rg', $query) || empty($query['rg'])) {
            $query['rg'] = '1000';
        }

        #empty or not, the limit attribute has the last word
        if (!empty($atts['limit'])) {
            $query['rg'] = $atts['limit'];
        }

        if (!array_key_exists('sf', $query) || empty($query['sf'])) {
            $query['sf'] = 'year';
        }

        if (!array_key_exists('so', $query) || empty($query['so'])) {
            if ($atts['sort'] === 'asc') {
                $query['so'] = 'a';
            } else {
                $query['so'] = 'd';
            }
        }

        # We may use http_build_query($query, null, '&amp;'); when provided urls are overencoded
        # looks fine at the moment
        $query = http_build_query($query, null);
        
        # from foo[1]=bar1&foo[2]=bar2 to foo[]=bar&foo[]=bar2
        $url = preg_replace('/%5B(?:[0-9]|[1-9][0-9]+)%5D=/', '=', $query);
        
        $url = INFOSCIENCE_SEARCH_URL . urldecode($url);
    } else {
        # no direct url were provided, build the custom one ourself
        $url = epfl_infoscience_search_generate_url_from_attrs($attributes+$unmanaged_attributes);
    }

    $cache_define_by = [
        'url' => $url,
        'format' => $format,
        'summary' => $summary,
        'thumbnail' => $thumbnail,
        'group_by' => $group_by,
        'group_by2' => $group_by2,
        'sort' => $attributes['sort'],
    ];

    # add language to cache definer if a group_by doctype is used
    if ($group_by === 'doctype' || ($group_by === 'year' && $group_by2 === 'doctype')) {
        # fetch language
        # if you can, use the method
        # use function EPFL\Language\get_current_or_default_language;
        
        $default_lang = 'en';
        $allowed_langs = array('en', 'fr');
        $language = $default_lang;
        /* If Polylang installed */
        if(function_exists('pll_current_language'))
        {
            $current_lang = pll_current_language('slug');
            // Check if current lang is supported. If not, use default lang
            $language = (in_array($current_lang, $allowed_langs)) ? $current_lang : $default_lang;
        }
        $cache_define_by['language'] = $language;
    }

    $cache_key = md5(serialize($cache_define_by));

    $page = wp_cache_get( $cache_key, 'epfl_infoscience_search');
    
    # not in cache ?
    if ($page === false || $debug_data || $debug_template){
        $start = microtime(true);
        $response = wp_remote_get( $url );
        $end = microtime(true);

        // logging call
        do_action('epfl_stats_webservice_call_duration', $url, $end-$start);

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            echo "Something went wrong: $error_message";
            } else {
            $marc_xml = wp_remote_retrieve_body( $response );

            $publications = InfoscienceMarcConverter::convert_marc_to_array($marc_xml);

            $grouped_by_publications = InfoscienceGroupBy::do_group_by($publications, $group_by, $group_by2, $attributes['sort']);

            if ($debug_data) {
                $page = RawInfoscienceRender::render($grouped_by_publications, $url);
                return $page;
            }

            # try to load render from 2018 theme if available
            if (has_filter("epfl_infoscience_search_action")) {
                $page = apply_filters("epfl_infoscience_search_action", $grouped_by_publications,
                                                            $url,
                                                            $format,
                                                            $summary,
                                                            $thumbnail,
                                                            $debug_template);
            } else {
                # use the self renderer
                $page = ClassesInfoscienceRender::render($grouped_by_publications,
                                                            $url,
                                                            $format,
                                                            $summary,
                                                            $thumbnail,
                                                            $debug_template);
            }

            // wrap the page, and add config as html comment
            $html_verbose_comments = '<!-- epfl_infoscience_search params : ' . var_export($before_unset_attributes, true) .  ' //-->';
            $html_verbose_comments .= '<!-- epfl_infoscience_search used url :'. var_export($url, true) . ' //-->';

            $page = '<div class="infoscienceBox container no-tex2jax_process">' . $html_verbose_comments . $page . '</div>';

            $page .= epfl_infoscience_search_get_mathjax_config();

            // cache the result
            wp_cache_set( $cache_key, $page, 'epfl_infoscience_search' );

            // return the page
            return $page;
        }
    } else {
        // To tell we're using the cache
        do_action('epfl_stats_webservice_call_duration', $url, 0, true);
        // Use cache
        return $page;
    }        
 }

// Load .mo file for translation
function epfl_infoscience_search_load_plugin_textdomain() {
    load_plugin_textdomain( 'epfl-infoscience-search', FALSE, basename( plugin_dir_path( __FILE__ )) . '/languages/');
}

add_action( 'plugins_loaded', 'epfl_infoscience_search_load_plugin_textdomain' );

add_action( 'register_shortcode_ui', ['InfoscienceSearchShortCakeConfig', 'config'] );

add_action( 'init', function() {

    add_shortcode( 'epfl_infoscience_search', 'epfl_infoscience_search_process_shortcode' );
    wp_register_style('epfl-infoscience-search-shortcode-style.css', plugins_url('css/epfl-infoscience-search-shortcode-style.css', __FILE__));

    # MathJax for nice render
    wp_register_script('epfl-infoscience-search-shortcode-math-main.js', 'https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.4/latest.js?config=default');

    # for an strange reason, this has to live here
    add_filter( 'shortcode_ui_fields', ['InfoscienceSearchShortCakeConfig', 'shortcode_ui_fields']);
});
?>
