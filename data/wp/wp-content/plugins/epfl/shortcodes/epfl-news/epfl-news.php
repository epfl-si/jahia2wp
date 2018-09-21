<?php

/**
 * Plugin Name: EPFL News shortcode
 * Description: provides a shortcode to display news feed
 * @version: 1.1
 * @copyright: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

define("NEWS_API_URL", "https://actu.epfl.ch/api/v1/channels/");
define("NEWS_API_URL_IFRAME", "https://actu.epfl.ch/webservice_iframe/");

require_once 'shortcake-config.php';

/**
 * Returns the number of news according to the template
 * @param $template: id of template
 * @return the number of news to display
 */
function epfl_news_get_limit($template)
{
    switch ($template){
        case "1":
            $limit = 5;
            break;
        case "2":
        case "6":
            $limit = 3;
            break;
        case "3":
        case "4":
            $limit = 1;
            break;
        case "5":
            $limit = 2;
            break;
        default:
            $limit = 4;
    }
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
function epfl_news_build_api_url($channel, $template, $nb_news, $lang, $category, $themes, $projects)
{
    // returns the number of news according to the template
    $limit = epfl_news_get_limit($template);
    if ("1" == $template) {
        $limit = $nb_news;
    }

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
function epfl_news_check_required_parameters($channel, $lang) {

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
    $channel_response = Utils::get_items($url);
    if(property_exists($channel_response, 'detail') && $channel_response->detail === "Not found.") {
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
function epfl_news_2018_process_shortcode($atts = [], $content = '', $tag = '') {

        // shortcode parameters
        $atts = shortcode_atts(array(
                'channel'       => '',
                'lang'          => '',
                'template'      => '',
                'nb_news'       => '',
                'all_news_link' => '',
                'category'      => '',
                'themes'        => '',
                'projects'      => '',
        ), $atts, $tag);

        // sanitize parameters
        $channel       = sanitize_text_field( $atts['channel'] );
        $lang          = sanitize_text_field( $atts['lang'] );
        $template      = sanitize_text_field( $atts['template'] );
        $all_news_link = sanitize_text_field( $atts['all_news_link']);
        $nb_news       = sanitize_text_field( $atts['nb_news'] );
        $category      = sanitize_text_field( $atts['category'] );
        $themes        = sanitize_text_field( $atts['themes'] );
        $projects      = sanitize_text_field( $atts['projects'] );

        if (epfl_news_check_required_parameters($channel, $lang) == FALSE) {
            return Utils::render_user_msg("News shortcode: Please check required parameters");
        }

        $url = epfl_news_build_api_url(
            $channel,
            $template,
            $nb_news,
            $lang,
            $category,
            $themes,
            $projects
        );

        $actus = Utils::get_items($url);

        // if supported delegate the rendering to the theme
        if (has_action("epfl_news_action")) {

            ob_start();

            try {

               do_action("epfl_news_action", $actus, $template, $all_news_link);

               return ob_get_contents();

            } finally {

                ob_end_clean();
            }

        // otherwise the plugin does the rendering
        } else {

            return 'You must activate the epfl theme';
        }
    }

add_action( 'register_shortcode_ui', ['ShortCakeNewsConfig', 'config'] );

add_action( 'init', function() {
    // define the shortcode
   add_shortcode('epfl_news_2018', 'epfl_news_2018_process_shortcode');
});


?>