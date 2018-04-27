<?php

/**
 * Plugin Name: EPFL Memento shortcode
 * Description: provides a shortcode to display events feed
 * @version: 1.0
 * @copyright: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

Class EventUtils
{
    public static function debug($var) {
        print "<pre>";
        var_dump($var);
        print "</pre>";
    }

    /**
     * This allow to insert anchor before the element
     *   i.e. '<a name="' . $ws->get_anchor($item->title) . '"></a>';
     * and also to get the item link in case it's not provided by the API.
     * e.g. https://actu.epfl.ch/news/a-12-million-franc-donation-to-create-a-center-for/
     */
    public static function get_anchor(string $title): string {

        $unwanted_array = array(    'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
                                    'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
                                    'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
                                    'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
                                    'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );

        $title = strtr( $title, $unwanted_array );
        $title = str_replace(" ", "-", $title);
        $title = str_replace("'", "-", $title);
        $title = strtolower($title);
        $title = substr($title, 0, 50);

        return $title;
    }

    /**
     * Call API
     * @param url  : the fetchable url
     * @param args : array('timeout' => 10), see https://codex.wordpress.org/Function_Reference/wp_remote_get
     * @return decoded JSON data
     */
    public static function get_items(string $url) {

        $response = wp_remote_get($url);

        if (is_array($response)) {
                $header = $response['headers']; // array of http header lines
                $data = $response['body']; // use the content
                if ( $header["content-type"] === "application/json" ) {
                        $items = json_decode($data);
                        return $items;
                }
        }
    }
}

define("MEMENTO_API_URL", "https://memento.epfl.ch/api/v1/mementos/");
define("MEMENTO_API_URL_IFRAME", "https://memento.epfl.ch/webservice/?frame=1");

/**
 * Template with only the title on the event (template 1)
 *
 * @param $events: response of memento API
 * @return html of template
 */
function epfl_memento_template_short_text($events): string {
    $html_content = '<div class="list-events clearfix">';
    $html_content .= '<p>SHORT TEXT - template 1</p>';
	foreach ($events->results as $item) {

        $start_date = new DateTime($item->start_date);
        $start_date = $start_date->format('d M');

        $end_date = new DateTime($item->end_date);
        $end_date = $end_date->format('d M');
        
        $html_content .= '<article class="event">';
        $html_content .= '<div class="event-dates">';
        $html_content .= '<p class="date-start"><time>' . $start_date . '</time></p>';
        if ($end_date != $start_date) {
          $html_content .= '<p class="date-end"><time>' . $end_date . '</time></p>';
        }
        $html_content .= '</div>';
        $html_content .= '<h2 class="event-title">' . $item->title . '</h2>';
        
		$html_content .= '</article>';
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
 * Template with 3 events. This template may be used in the sidebar (template 2)
 *
 * @param $events: response of memento API
 * @return html of template
 */
function epfl_memento_template_with_3_events($events): string {

    $html_content = '<div class="list-events list-events-image clearfix">';
    $html_content .= '<p>template_with_3_events - template 2</p>';
	foreach ($events->results as $item) {

        $start_date = new DateTime($item->start_date);
        $start_date = $start_date->format('d M');

        $end_date = new DateTime($item->end_date);
        $end_date = $end_date->format('d M');

        /**
         * Voici les informations qui te manquaient :
         *
         * la photo: $item->visual_url
         * la description de la photo: $item->image_description
         *
         * l'extrait du texte de event: $item->description
         *
         * le lien vers l'événement: $item->event_url
         *
         * le lieu: $item->place_and_room
         * l'URL du lieu: $item->url_place_and_room
         *
         * les heures: $item->start_time et $item->end_time
         *
         * les intervenants: $item->speaker
         *
         * L'événement est il annulé: $item->canceled
         */

        $html_content .= '<article class="event">';
        $html_content .= '<div class="event-dates">';
        $html_content .= '<p class="date-start"><time>' . $start_date . '</time></p>';
        if ($end_date != $start_date) {
          $html_content .= '<p class="date-end"><time>' . $end_date . '</time></p>';
        }
        $html_content .= '</div>';
        $html_content .= '<h2 class="event-title">' . $item->title . '</h2>';
        $html_content .= '<div class="event-content">';

        $html_content .= '<img src="' . $item->visual_url . '" title="'.$item->title.'">';

        $html_content .= '</div>';
        
		$html_content .= '</article>';
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
    return '<div class="eventsBox">' . $html . '</div>';
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
    $events = EventUtils::get_items($url);
    return epfl_memento_build_html($events, $template);
}

// define the shortcode
add_shortcode('epfl_memento', 'epfl_memento_process_shortcode');

?>