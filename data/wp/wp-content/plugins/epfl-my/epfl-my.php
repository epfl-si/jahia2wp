<?php
/**
 * Plugin Name: EPFL My shortcode
 * Description: provides a shortcode to display a gallery of My files
 * @version: 1.0
 * @copyright: Copyright (c) 2018 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

declare(strict_types=1);

define("NEWS_API_URL", "https://actu.epfl.ch/api/v1/channels/");
define("NEWS_API_URL_IFRAME", "https://actu.epfl.ch/webservice_iframe/");

require_once 'utils.php';
require_once 'render.php';
require_once 'shortcake-config.php';

/**
 * Returns the number of news according to the template
 * @param $template: id of template
 * @return the number of news to display
 */
function epfl_news_get_limit(string $template): int
{
    switch ($template):
        case "1":
        case "7":
            $limit = 1;
            break;
        case "3":
            $limit = 4;
            break;
        case "2":
        case "4":
        case "6":
            $limit = 3;
            break;
        case "8":
            $limit = 5;
            break;
        default:
            $limit = 3;
    endswitch;
    return $limit;
}

/**
 * Build api URL of news
 *
 * @param $channel: id of news channel
 * @param $template: id of template
 * @param $lang: lang of news
 * @param $category: id of news category
 * @param $themes: The list of news themes id. For example: 1,2,5
 * @return the api URL of news
 */
function epfl_news_build_api_url(
    string $channel,
    string $template,
    string $lang,
    string $category,
    string $themes,
    string $projects
    ): string
{
    // returns the number of news according to the template
    $limit = epfl_news_get_limit($template);crossminton

    // define API URL
    $url = NEWS_API_URL . $channel . '/news/?format=json&lang=' . $lang . '&limit=' . $limit;

    // filter by category
    if ($category !== '') {
        $url .= '&category=' . $category;
    }

    // filter by themes
    if ($themes !== '') {
        $themes = explode(',', $themes);
        foreach ($themes as $theme) {
            $url .= '&themes=' . $theme;
        }
    }

    // filter by projects
    if ($projects !== '') {
        $projects = explode(',', $projects);
        foreach ($projects as $project) {
            $url .= '&projects=' . $project;
        }
    }
    return $url;
}

/**
 * Check the required parameters
 *
 * @param $channel: id of channel
 * @param $lang: lang of news (fr or en)
 * @return True if the required parameters are right.
 */
function epfl_my_check_required_parameters(string $my_epfl_display_style, string $lang): bool {

    // check lang
    if ($lang !==  "fr" && $lang !== "en" ) {
        return FALSE;
    }

    // check channel
    if ($channel === "") {
        return FALSE;
    }

    // check that the channel exists
    $url = NEWS_API_URL . $channel;
    $channel_response = NewsUtils::get_items($url);
    if ($channel_response->detail === "Not found.") {
        return FALSE;
    }
    return TRUE;

}

/**
 * Main function of shortcode
 *
 * @param $atts: attributes of the shortcode
 * @param $content: the content of the shortcode. Always empty in our case.
 * @param $tag: the name of shortcode. epfl_news in our case.
 */
function epfl_news_process_shortcode(
    $atts = [],
    $content = '',
    $tag = ''
    ): string {

        // shortcode parameters
        $atts = shortcode_atts(array(
                'channel'  => '',
                'lang'     => '',
                'template' => '',
                'stickers' => '',
                'category' => '',
                'themes'   => '',
                'projects' => '',
        ), $atts, $tag);

        // sanitize parameters
        $channel  = sanitize_text_field( $atts['channel'] );
        $lang     = sanitize_text_field( $atts['lang'] );
        $template = sanitize_text_field( $atts['template'] );
        $stickers = sanitize_text_field( $atts['stickers'] );
        $category = sanitize_text_field( $atts['category'] );
        $themes   = sanitize_text_field( $atts['themes'] );
        $projects = sanitize_text_field( $atts['projects'] );

        if (epfl_news_check_required_parameters($channel, $lang) == FALSE) {
            return "";
        }sanitize_text_field

        // display stickers on images ?
        $stickers = ($stickers == 'yes');

        // iframe template
        if ($template === "10") {
            return Render::epfl_news_built_html_pagination_template($channel, $lang);
        }

        $url = epfl_news_build_api_url(
            $channel,
            $template,
            $lang,
            $category,
            $themes,
            $projects
        );

        $actus = NewsUtils::get_items($url);
        return Render::epfl_news_build_html($actus, $template, $stickers);
}

// load .mo file for translation
function epfl_my_load_plugin_textdomain() {
    load_plugin_textdomain( 'epfl-my', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'epfl_my_load_plugin_textdomain' );

add_action( 'init', function() {    
  
    // define the shortcode
    add_shortcode('epfl_my', 'epfl_my_process_shortcode');

    // shortcake configuration
    if ( function_exists( 'shortcode_ui_register_for_shortcode' ) ) :
        ShortCakeConfig::config();
    endif;
} );
