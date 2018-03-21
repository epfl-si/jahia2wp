<?php

/**
 * Plugin Name: EPFL Memento shortcode
 * Description: provides a shortcode to display events feed
 * @version: 1.0
 * @copyright: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

declare(strict_types=1);

require_once('utils.php');

use utils\Utils as Utils;

define("MEMENTO_API_URL", "https://memento.epfl.ch/api/v1/mementos/");
define("MEMENTO_API_URL_IFRAME", "https://memento.epfl.ch/webservice/?frame=1");

/**
 * Template with only the title on the event
 *
 * @param $events: response of memento API
 * @return html of template
 */
function epfl_memento_template_short_text($events): string {
    $html_content = '<div>';
    $html_content .= '<p>SHORT TEXT</p>';
	foreach ($events->results as $item) {
        $html_content .= '<div>';
        $html_content .= '<h3>' . $item->title . '</h3>';
        $html_content .= '<p>' . $item->start_date . '</p>';
        $html_content .= '<p>' . $item->end_date . '</p>';
		$html_content .= '</div>';
    }
    $html_content .= '</div>';
    return $html_content;
}

/**
 * Template with only the title on the event
 *
 * @param $events: response of memento API
 * @return html of template
 */
function epfl_memento_template_text($events): string {
    $html_content = '<div>';
    $html_content .= '<p>TEMPLATE TEXT</p>';
	foreach ($events->results as $item) {
        $html_content .= '<div>';
        $html_content .= '<h3>' . $item->title . '</h3>';
        $html_content .= '<p>' . $item->start_date . '</p>';
        $html_content .= '<p>' . $item->end_date . '</p>';
		$html_content .= '</div>';
    }
    $html_content .= '</div>';
    return $html_content;
}

/**
 * Template with 3 events and right column
 *
 * @param $events: response of memento API
 * @return html of template
 */
function epfl_memento_template_with_3_events_and_right_column($events): string {
    $html_content = '<div>';
    $html_content .= '<p>template_with_3_events_and_right_column</p>';
	foreach ($events->results as $item) {
        $html_content .= '<div>';
        $html_content .= '<h3>' . $item->title . '</h3>';
        $html_content .= '<p>' . $item->start_date . '</p>';
        $html_content .= '<p>' . $item->end_date . '</p>';
		$html_content .= '</div>';
    }
    $html_content .= '</div>';
    return $html_content;
}

/**
 * Template with 5 events and right column
 *
 * @param $events: response of memento API
 * @return html of template
 */
function epfl_memento_template_with_5_events_and_right_column($events): string {
    $html_content = '<div>';
    $html_content .= '<p>template_with_5_events_and_right_column</p>';
	foreach ($events->results as $item) {
        $html_content .= '<div>';
        $html_content .= '<h3>' . $item->title . '</h3>';
        $html_content .= '<p>' . $item->start_date . '</p>';
        $html_content .= '<p>' . $item->end_date . '</p>';
		$html_content .= '</div>';
    }
    $html_content .= '</div>';
    return $html_content;
}

/**
 * Template with 2 events. This template may be used in the sidebar
 *
 * @param $events: response of memento API
 * @return html of template
 */
function epfl_memento_template_with_2_events($events): string {
    $html_content = '<div>';
    $html_content .= '<p>template_with_2_events</p>';
	foreach ($events->results as $item) {
        $html_content .= '<div>';
        $html_content .= '<h3>' . $item->title . '</h3>';
        $html_content .= '<p>' . $item->start_date . '</p>';
        $html_content .= '<p>' . $item->end_date . '</p>';
		$html_content .= '</div>';
    }
    $html_content .= '</div>';
    return $html_content;
}

/**
 * Template with 3 events. This template may be used in the sidebar
 *
 * @param $events: response of memento API
 * @return html of template
 */
function epfl_memento_template_with_3_events($events): string {

    $html_content = '<div>';
    $html_content .= '<p>template_with_3_events</p>';
	foreach ($events->results as $item) {
        $html_content .= '<div>';
        $html_content .= '<h3>' . $item->title . '</h3>';
        $html_content .= '<p>' . $item->start_date . '</p>';
        $html_content .= '<p>' . $item->end_date . '</p>';
		$html_content .= '</div>';
    }
    $html_content.= '</div>';
    return $html_content;
}

/**
 * Template for student portal
 *
 * @param $events: response of memento API
 * @return html of template
 */
function epfl_memento_template_student_portal($events): string {

    $html_content = '<div>';
    $html_content .= '<p>template_student_portal</p>';
	foreach ($events->results as $item) {
        $html_content .= '<div>';
        $html_content .= '<h3>' . $item->title . '</h3>';
        $html_content .= '<p>' . $item->start_date . '</p>';
        $html_content .= '<p>' . $item->end_date . '</p>';
		$html_content .= '</div>';
    }
    $html_content.= '</div>';
    return $html_content;
}

/**
 * Template homepage faculty
 *
 * @param $events: response of memento API
 * @return html of template
 */
function epfl_memento_template_homepage_faculty($events): string {

    $html_content = '<div>';
    $html_content .= '<p>template_homepage_faculty</p>';
	foreach ($events->results as $item) {
        $html_content .= '<div>';
        $html_content .= '<h3>' . $item->title . '</h3>';
        $html_content .= '<p>' . $item->start_date . '</p>';
        $html_content .= '<p>' . $item->end_date . '</p>';
		$html_content .= '</div>';
    }
    $html_content.= '</div>';
    return $html_content;
}

/**
 * Build HTML.
 *
 * @param $events: response of memento API
 * @param $template: id of template
 * @return
 */
function epfl_memento_build_html($events, $template): string
{
    if ($template === "1") {
        $html = epfl_memento_template_short_text($events);
    } elseif ($template === "5") {
        $html = epfl_memento_template_text($events);
    } elseif ($template === "6") {
        $html = epfl_memento_template_with_3_events_and_right_column($events);
    } elseif ($template === "3") {
        $html = epfl_memento_template_with_5_events_and_right_column($events);
    } elseif ($template === "2") {
        $html = epfl_memento_template_with_3_events($events);
    } elseif ($template === "8") {
        $html = epfl_memento_template_with_2_events($events);
    } elseif ($template === "7") {
        $html = epfl_memento_template_student_portal($events);
    } elseif ($template === "9") {
        $html = epfl_memento_template_homepage_faculty($events);
    } else {
        $html = epfl_memento_template_with_3_events($events);
    }
    return $html;
}

/**
 * Build HTML. This template contains all events inside ifram tag
 *
 * @param $memento: slug of memento
 * @param $lang: lang of event (fr or en)
 * @return html of iframe template
 */
function epfl_memento_built_html_pagination_template(string $memento, string $lang): string {
    $url = MEMENTO_API_URL_IFRAME. '&memento=' . $memento . '&lang=' . $lang . '&template=4&period=2&color=EPFL';
    $result = '<IFRAME ';
    $result .= 'src="' . $url . '" ';
    $result .= 'width="660" height="1255" scrolling="no" frameborder="0"></IFRAME>';
    return $result;
}

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
 * @return the API URL of the memento
 */
function epfl_memento_build_api_url(
    string $memento,
    string $template,
    string $lang,
    string $category
    ): string
{
    // returns the number of events according to the template
    $limit = epfl_memento_get_limit($template);

    // call API to get  of memento
    $url = MEMENTO_API_URL. "?format=json&limit=300";
    $mementos = Utils::get_items($url);

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
    $atts = extract(shortcode_atts(array(
            'memento' => '',
            'lang' => '',
            'template' => '',
            'category' => '',
    ), $atts, $tag));

    if (epfl_memento_check_required_parameters($memento, $lang) == FALSE) {
        return "";
    }

    // iframe template
    if ($template === "4") {
        return epfl_memento_built_html_pagination_template($memento, $lang);
    }

    $url = epfl_memento_build_api_url(
        $memento,
        $template,
        $lang,
        $category
    );
    $events = Utils::get_items($url);
    return epfl_memento_build_html($events, $template);
}

// define the shortcode
add_shortcode('epfl_memento', 'epfl_memento_process_shortcode');

?>