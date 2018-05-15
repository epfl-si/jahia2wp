<?php

/**
 * Plugin Name: EPFL Memento shortcode
 * Description: provides a shortcode to display events feed
 * @version: 1.0
 * @copyright: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
 *
 * Text Domain: epfl-memento
 * Domain Path: /languages
 */

define("MEMENTO_API_URL", "https://memento.epfl.ch/api/v1/mementos/");
define("MEMENTO_API_URL_IFRAME", "https://memento.epfl.ch/webservice/?frame=1");

require_once('utils.php');
require_once('render.php');
require_once('shortcake-config.php');

/**
 * Returns the number of events according to the template
 *
 * @param $template: id of template
 * @return the number of events
 */
function epfl_memento_get_limit(string $template): int
{
    switch ($template):
        case "1":
        case "2":
        case "6":
            $limit = 3;
            break;
        case "8":
        case "5":
            $limit = 2;
            break;
        case "3":
            $limit = 5;
            break;
        case "7":
            $limit = 1;
            break;
    endswitch;
    return $limit;
}

/**
 * Build api URL of events
 *
 * @param $memento: slug of memento
 * @param $template: id of the template
 * @param $lang: lang of the event (fr or en)
 * @param $category: id of the event category
 * @param $keyword: keyword to filter events
 * @param $period: period to filter past event or upcoming events
 * @param $color: to choose a faculty color
 * @return the API URL of the memento
 */
function epfl_memento_build_api_url(
    string $memento,
    string $lang,
    string $template,
    string $category,
    string $keyword,
    string $period,
    string $color
    ): string
{
    // returns the number of events according to the template
    $limit = epfl_memento_get_limit($template);

    // call REST API to get the number of mementos
    $memento_response = EventUtils::get_items(MEMENTO_API_URL);

    // build URL with all mementos
    $url = MEMENTO_API_URL . '?limit=' . $memento_response->count;
    $mementos = EventUtils::get_items($url);

    // FIXME: we must improve REST API MEMENTO to be able to filter by memento_slug
    $memento_id = "";
    foreach($mementos->results as $current_memento) {
        if ($current_memento->slug === $memento) {
            $memento_id = $current_memento->id;
            break;
        }
    }

    // define API URL
    $url = MEMENTO_API_URL . $memento_id . '/events/?format=json&lang=' . $lang . '&limit=' . $limit;

    // filter by category
    if ($category !== '') {
        $url .= '&category=' . $category;
    }

    // keyword
    if ($keyword !== '') {
        $url .= '&keywords=' . $keyword;
    }

    // period
    if ($period === 'past' or $period === 'upcoming') {
        $url .= '&period=' . $period;
    }

    // color
    /**
     * Pour les templates sans iframe, c'est le thème qui va affecter directement la bonne couleur.
     * Pour le template avec iframe, il faut passer un paramètre couleur.
     */

    return $url;
}

/**
 * Check the required parameters
 *
 * @param $memento: slug of memento
 * @param $lang: lang of event
 */
function epfl_memento_check_required_parameters(string $memento, string $lang): bool {

    // check lang
    if ($lang !==  "fr" && $lang !== "en" ) {
        return FALSE;
    }

    // check memento
    if ($memento === "") {
        return FALSE;
    }

    return TRUE;
}

/**
 * Main function of shortcode
 */
function epfl_memento_process_shortcode(
    array $atts,
    string $content = '',
    string $tag): string
{

    // extract shortcode parameters
    $atts = shortcode_atts(array(
            'memento'  => '',
            'lang'     => '',
            'template' => '',
            'category' => '',
            'keyword'  => '',
            'period'   => '',
            'color'    => 'EPFL',
    ), $atts, $tag);

    // sanitize parameters
    $memento  = sanitize_text_field( $atts['memento'] );
    $lang     = sanitize_text_field( $atts['lang'] );
    $template = sanitize_text_field( $atts['template'] );
    $category = sanitize_text_field( $atts['category'] );
    $keyword  = sanitize_text_field( $atts['keyword'] );
    $period   = sanitize_text_field( $atts['period'] );
    $color    = sanitize_text_field( $atts['color'] );

    if (epfl_memento_check_required_parameters($memento, $lang) == FALSE) {
        return "";
    }

    // iframe template
    if ($template === "4") {
        return MementoRender::epfl_memento_built_html_pagination_template($memento, $lang, $color);
    }

    $url = epfl_memento_build_api_url(
        $memento,
        $lang,
        $template,
        $category,
        $keyword,
        $period,
        $color
    );
    $events = EventUtils::get_items($url);
    return MementoRender::epfl_memento_build_html($events, $template);
}

// Load .mo file for translation
function epfl_memento_load_plugin_textdomain() {
    load_plugin_textdomain( 'epfl-memento', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'epfl_memento_load_plugin_textdomain' );

add_action( 'init', function() {
    // define the shortcode
    add_shortcode('epfl_memento', 'epfl_memento_process_shortcode');

    // configure shortcake
    if ( function_exists( 'shortcode_ui_register_for_shortcode' ) ) :
        ShortCakeMementoConfig::config();
    endif;
});
?>