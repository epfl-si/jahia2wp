<?php

/**
 * Plugin Name: EPFL Memento shortcode
 * Description: provides a shortcode to display events feed
 * @version: 1.0
 * @copyright: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
 *
 * Text Domain: epfl-plugins
 * Domain Path: /languages
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
    $html_content .= '  <div class="event-dates">';
    $html_content .= '    <p class="date-start"><time>' . $start_date . '</time></p>';
    if ($end_date != $start_date) {
      $html_content .= '  <p class="date-end"><time>' . $end_date . '</time></p>';
    }
    $html_content .= '  </div>';
    $html_content .= '  <h2 class="event-title"><a href="' . $item->event_url . '" title="' . $item->title . '">' . $item->title . '</a></h2>';

		$html_content .= '</article>';
    }
    $html_content .= '</div>';
    return $html_content;
}

/**
 * Template with only the title on the event (template 5)
 *
 * @param $events: response of memento API
 * @return html of template
 */
function epfl_memento_template_text($events): string {
    $html_content = '<div class="list-events clearfix">';
    $html_content .= '<p>TEMPLATE TEXT (template 5)</p>';
	foreach ($events->results as $item) {

  	$start_date = new DateTime($item->start_date);
    $start_date = $start_date->format('d M');

    $end_date = new DateTime($item->end_date);
    $end_date = $end_date->format('d M');

    if (is_null($item->start_time)){
      $start_time = "";
    } else {
      $start_time = new DateTime($item->start_time);
      $start_time = $start_time->format('G:i');
    }

    if (is_null($item->end_time)){
      $end_time = "";
    } else {
      $end_time = new DateTime($item->end_time);
      $end_time = $end_time->format('G:i');
    }

    $html_content .= '<article class="event">';
    $html_content .= '  <div class="event-dates">';
    $html_content .= '    <p class="date-start"><time>' . $start_date . '</time></p>';
    if ($end_date != $start_date) {
      $html_content .= '  <p class="date-end"><time>' . $end_date . '</time></p>';
    }
    $html_content .= '  </div>';
    $html_content .= '  <div class="event-content">';
    $html_content .= '    <div class="event-meta">';
    $html_content .= '      <a href="https://memento.epfl.ch/event/export/<translation-id>" class="event-export"><span class="sr-only">Export event</span></a>';
    if (!is_null($item->start_time)) {
      $html_content .= '    <p class="event-times time-start">'.$start_time.'</p>';
      $html_content .= '    <p class="event-times time-end">' . $end_time . '</p>';
    }
    $html_content .= '      <p class="event-location">';
    $html_content .= '        <a href="' . $item->url_place_and_room . '">' . $item->place_and_room . '</a>';
    $html_content .= '      </p>';
    $html_content .= '    </div>';
    $html_content .= '    <h2 class="event-title">';
    $html_content .= '      <a href="' . $item->event_url . '" title="' . $item->title . '">' . $item->title . '</a>';
    $html_content .= '    </h2>';

    $html_content .= '  </div>';
    $html_content .= '  <div class="event-extra">';
    $html_content .= '    <p class="speakers">' . __( 'By: ', 'epfl-plugins' ) . $item->speaker . '</p>';
    $html_content .= '  </div>';

		$html_content .= '</article>';
    }
    $html_content .= '</div>';
    return $html_content;
}

/**
 * Template with 3 events and right column (template 6)
 *
 * @param $events: response of memento API
 * @return html of template
 */
function epfl_memento_template_with_3_events_and_right_column($events): string {
    $html_content = '<div class="list-events clearfix">';
    $html_content .= '<p>template_with_3_events_and_right_column (template 6)</p>';
	foreach ($events->results as $item) {

    $start_date = new DateTime($item->start_date);
    $start_date = $start_date->format('d M');

    $end_date = new DateTime($item->end_date);
    $end_date = $end_date->format('d M');

    if (is_null($item->start_time)){
      $start_time = "";
    } else {
      $start_time = new DateTime($item->start_time);
      $start_time = $start_time->format('G:i');
    }

    if (is_null($item->end_time)){
      $end_time = "";
    } else {
      $end_time = new DateTime($item->end_time);
      $end_time = $end_time->format('G:i');
    }
    $html_content .= '<article class="event has-image has-teaser has-extra">';
    $html_content .= '  <div class="event-dates">';
    $html_content .= '    <p class="date-start"><time>' . $start_date . '</time></p>';
    if ($end_date != $start_date) {
      $html_content .= '  <p class="date-end"><time>' . $end_date . '</time></p>';
    }
    $html_content .= '  </div>';
    $html_content .= '  <figure class="img event-img">';
    $html_content .= '    <a href="' . $item->event_url . '" title="' . $item->title . '">';
    $html_content .= '      <img src="' . $item->visual_url . '" title="'.$item->title.'" alt="' . $item->image_description . '">';
    $html_content .= '    </a>';
    $html_content .= '  </figure>';
    $html_content .= '  <div class="event-content">';
    $html_content .= '    <div class="event-meta">';
    $html_content .= '      <a href="https://memento.epfl.ch/event/export/<translation-id>" class="event-export"><span class="sr-only">Export event</span></a>';
    if (!is_null($item->start_time)) {
      $html_content .= '    <p class="event-times time-start">'.$start_time.'</p>';
      $html_content .= '    <p class="event-times time-end">' . $end_time . '</p>';
    }
    $html_content .= '      <p class="event-location">';
    $html_content .= '        <a href="' . $item->url_place_and_room . '">' . $item->place_and_room . '</a>';
    $html_content .= '      </p>';
    $html_content .= '    </div>';
    $html_content .= '    <h2 class="event-title">';
    $html_content .= '      <a href="' . $item->event_url . '" title="' . $item->title . '">' . $item->title . '</a>';
    $html_content .= '    </h2>';
    $html_content .= '    <p class="teaser">' . $item->description . '</p>';

    $html_content .= '  </div>';
    $html_content .= '  <div class="event-extra">';
    $html_content .= '    <p class="speakers">' . __( 'By: ', 'epfl-plugins' ) . $item->speaker . '</p>';
    $html_content .= '  </div>';

		$html_content .= '</article>';
    }
    $html_content .= '</div>';
    return $html_content;
}

/**
 * Template with 5 events and right column (template 3)
 *
 * @param $events: response of memento API
 * @return html of template
 */
function epfl_memento_template_with_5_events_and_right_column($events): string {
    $html_content = '<div class="list-events clearfix">';
    $html_content .= '<p>template_with_5_events_and_right_column (template 3)</p>';
	foreach ($events->results as $item) {

    $start_date = new DateTime($item->start_date);
    $start_date = $start_date->format('d M');

    $end_date = new DateTime($item->end_date);
    $end_date = $end_date->format('d M');

    if (is_null($item->start_time)){
      $start_time = "";
    } else {
      $start_time = new DateTime($item->start_time);
      $start_time = $start_time->format('G:i');
    }

    if (is_null($item->end_time)){
      $end_time = "";
    } else {
      $end_time = new DateTime($item->end_time);
      $end_time = $end_time->format('G:i');
    }
    $html_content .= '<article class="event has-image has-teaser has-extra">';
    $html_content .= '  <div class="event-dates">';
    $html_content .= '    <p class="date-start"><time>' . $start_date . '</time></p>';
    if ($end_date != $start_date) {
      $html_content .= '  <p class="date-end"><time>' . $end_date . '</time></p>';
    }
    $html_content .= '  </div>';
    $html_content .= '  <figure class="img event-img">';
    $html_content .= '    <a href="' . $item->event_url . '" title="' . $item->title . '">';
    $html_content .= '      <img src="' . $item->visual_url . '" title="'.$item->title.'" alt="' . $item->image_description . '">';
    $html_content .= '    </a>';
    $html_content .= '  </figure>';
    $html_content .= '  <div class="event-content">';
    $html_content .= '    <div class="event-meta">';
    $html_content .= '      <a href="https://memento.epfl.ch/event/export/<translation-id>" class="event-export"><span class="sr-only">Export event</span></a>';
    if (!is_null($item->start_time)) {
      $html_content .= '    <p class="event-times time-start">'.$start_time.'</p>';
      $html_content .= '    <p class="event-times time-end">' . $end_time . '</p>';
    }
    $html_content .= '      <p class="event-location">';
    $html_content .= '        <a href="' . $item->url_place_and_room . '">' . $item->place_and_room . '</a>';
    $html_content .= '      </p>';
    $html_content .= '    </div>';
    $html_content .= '    <h2 class="event-title">';
    $html_content .= '      <a href="' . $item->event_url . '" title="' . $item->title . '">' . $item->title . '</a>';
    $html_content .= '    </h2>';
    $html_content .= '    <p class="teaser">' . $item->description . '</p>';

    $html_content .= '  </div>';
    $html_content .= '  <div class="event-extra">';
    $html_content .= '    <p class="speakers">' . __( 'By: ', 'epfl-plugins' ) . $item->speaker . '</p>';
    $html_content .= '  </div>';

		$html_content .= '</article>';
    }
    $html_content .= '</div>';
    return $html_content;
}

/**
 * Template with 2 events. This template may be used in the sidebar (template 8)
 *
 * @param $events: response of memento API
 * @return html of template
 */
function epfl_memento_template_with_2_events($events): string {
    $html_content = '<div class="list-events clearfix">';
    $html_content .= '<p>template_with_2_events (template 8)</p>';
	foreach ($events->results as $item) {

    $start_date = new DateTime($item->start_date);
    $start_date = $start_date->format('d M');

    $end_date = new DateTime($item->end_date);
    $end_date = $end_date->format('d M');

    if (is_null($item->start_time)){
      $start_time = "";
    } else {
      $start_time = new DateTime($item->start_time);
      $start_time = $start_time->format('G:i');
    }

    if (is_null($item->end_time)){
      $end_time = "";
    } else {
      $end_time = new DateTime($item->end_time);
      $end_time = $end_time->format('G:i');
    }

    $html_content .= '<article class="event has-image">';
    $html_content .= '  <div class="event-dates">';
    $html_content .= '    <p class="date-start"><time>' . $start_date . '</time></p>';
    if ($end_date != $start_date) {
      $html_content .= '  <p class="date-end"><time>' . $end_date . '</time></p>';
    }
    $html_content .= '  </div>';
    $html_content .= '  <figure class="img event-img">';
    $html_content .= '    <a href="' . $item->event_url . '" title="' . $item->title . '">';
    $html_content .= '      <img src="' . $item->visual_url . '" title="'.$item->title.'" alt="' . $item->image_description . '">';
    $html_content .= '    </a>';
    $html_content .= '  </figure>';
    $html_content .= '  <div class="event-content">';
    $html_content .= '    <div class="event-meta">';
    $html_content .= '      <a href="https://memento.epfl.ch/event/export/<translation-id>" class="event-export"><span class="sr-only">Export event</span></a>';
    if (!is_null($item->start_time)) {
      $html_content .= '    <p class="event-times time-start">'.$start_time.'</p>';
      $html_content .= '    <p class="event-times time-end">' . $end_time . '</p>';
    }
    $html_content .= '      <p class="event-location">';
    $html_content .= '        <a href="' . $item->url_place_and_room . '">' . $item->place_and_room . '</a>';
    $html_content .= '      </p>';
    $html_content .= '    </div>';
    $html_content .= '    <h2 class="event-title">';
    $html_content .= '      <a href="' . $item->event_url . '" title="' . $item->title . '">' . $item->title . '</a>';
    $html_content .= '    </h2>';

    $html_content .= '  </div>';

		$html_content .= '</article>';
    }
    $html_content.= '</div>';
    return $html_content;
}

/**
 * Template with 3 events. This template may be used in the sidebar (template 2)
 *
 * @param $events: response of memento API
 * @return html of template
 */
function epfl_memento_template_with_3_events($events): string {

    $html_content = '<div class="list-events clearfix">';
    $html_content .= '<p>template_with_3_events - template 2</p>';
	foreach ($events->results as $item) {

    $start_date = new DateTime($item->start_date);
    $start_date = $start_date->format('d M');

    $end_date = new DateTime($item->end_date);
    $end_date = $end_date->format('d M');

    if (is_null($item->start_time)){
      $start_time = "";
    } else {
      $start_time = new DateTime($item->start_time);
      $start_time = $start_time->format('G:i');
    }

    if (is_null($item->end_time)){
      $end_time = "";
    } else {
      $end_time = new DateTime($item->end_time);
      $end_time = $end_time->format('G:i');
    }

    $html_content .= '<article class="event has-image">';
    $html_content .= '  <div class="event-dates">';
    $html_content .= '    <p class="date-start"><time>' . $start_date . '</time></p>';
    if ($end_date != $start_date) {
      $html_content .= '  <p class="date-end"><time>' . $end_date . '</time></p>';
    }
    $html_content .= '  </div>';
    $html_content .= '  <figure class="img event-img">';
    $html_content .= '    <a href="' . $item->event_url . '" title="' . $item->title . '">';
    $html_content .= '      <img src="' . $item->visual_url . '" title="'.$item->title.'" alt="' . $item->image_description . '">';
    $html_content .= '    </a>';
    $html_content .= '  </figure>';
    $html_content .= '  <div class="event-content">';
    $html_content .= '    <div class="event-meta">';
    $html_content .= '      <a href="https://memento.epfl.ch/event/export/<translation-id>" class="event-export"><span class="sr-only">Export event</span></a>';
    if (!is_null($item->start_time)) {
      $html_content .= '    <p class="event-times time-start">'.$start_time.'</p>';
      $html_content .= '    <p class="event-times time-end">' . $end_time . '</p>';
    }
    $html_content .= '      <p class="event-location">';
    $html_content .= '        <a href="' . $item->url_place_and_room . '">' . $item->place_and_room . '</a>';
    $html_content .= '      </p>';
    $html_content .= '    </div>';
    $html_content .= '    <h2 class="event-title">';
    $html_content .= '      <a href="' . $item->event_url . '" title="' . $item->title . '">' . $item->title . '</a>';
    $html_content .= '    </h2>';

    $html_content .= '  </div>';

		$html_content .= '</article>';
    }
    $html_content.= '</div>';
    return $html_content;
}

/**
 * Template for student portal (template 7)
 *
 * @param $events: response of memento API
 * @return html of template
 */
function epfl_memento_template_student_portal($events): string {

    $html_content = '<div class="list-events clearfix">';
    $html_content .= '<p>template_student_portal (template 7)</p>';
	foreach ($events->results as $item) {

    $start_date = new DateTime($item->start_date);
    $start_date = $start_date->format('d M');

    $end_date = new DateTime($item->end_date);
    $end_date = $end_date->format('d M');

    $html_content .= '<article class="event has-image has-cover-image">';
    $html_content .= '  <div class="event-dates">';
    $html_content .= '    <p class="date-start"><time>' . $start_date . '</time></p>';
    if ($end_date != $start_date) {
      $html_content .= '  <p class="date-end"><time>' . $end_date . '</time></p>';
    }
    $html_content .= '  </div>';
    $html_content .= '  <figure class="img event-img cover-img">';
    $html_content .= '     <img src="https://studying.epfl.ch/files/content/sites/studying/files/memento/empty_fr_en.png" title="'.$item->title.'" alt="">';
    $html_content .= '  </figure>';
    $html_content .= '  <div class="event-content">';
    $html_content .= '    <h2 class="event-title">';
    $html_content .= '      <a href="' . $item->event_url . '" title="' . $item->title . '">' . $item->title . '</a>';
    $html_content .= '    </h2>';
    $html_content .= '    <p class="studying-calendar">';
    $html_content .= '      <a href="https://memento.epfl.ch/academic-calendar/?period=14"><span class="label">' . __( 'Academic calendar ', 'epfl-plugins' ) . '</span></a>';
    $html_content .= '    </p>';
    $html_content .= '  </div>';
		$html_content .= '</article>';
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

// Load .mo file for translation
function epfl_memento_load_plugin_textdomain() {
    load_plugin_textdomain( 'epfl-memento', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'epfl_memento_load_plugin_textdomain' );

add_action( 'init', function() {
    // define the shortcode
    add_shortcode('epfl_memento', 'epfl_memento_process_shortcode');

    if ( function_exists( 'shortcode_ui_register_for_shortcode' ) ) :

        // FIXME: How get all channels without bad tips ?limit=500
        $memento_api_rest_url = MEMENTO_API_URL . "?limit=500";

        $memento_response = EventUtils::get_items($memento_api_rest_url);

        $memento_options = array();
        foreach ($memento_response->results as $item) {

            if (get_locale() == 'fr_FR') {
                $memento_name = $item->fr_name;
            } else {
                $memento_name = $item->en_name;
            }

            $option = array(
                'value' => $item->slug,
                'label' => $memento_name,
            );
            array_push($memento_options, $option);
        }

        $template_options = array (
            array('value' => '1', 'label' => esc_html__('Template short text', 'epfl-memento')),
            array('value' => '5', 'label' => esc_html__('Template text', 'epfl-memento')),
            array('value' => '6', 'label' => esc_html__('Template with 3 events for sidebar', 'epfl-memento')),
            array('value' => '3', 'label' => esc_html__('Template with 5 events for sidebar', 'epfl-memento')),
            array('value' => '2', 'label' => esc_html__('Template with 3 events', 'epfl-memento')),
            array('value' => '8', 'label' => esc_html__('Template with 2 events', 'epfl-memento')),
            array('value' => '7', 'label' => esc_html__('Template for portal website', 'epfl-memento')),
            array('value' => '9', 'label' => esc_html__('Template for homepage faculty', 'epfl-memento')),
            array('value' => '4', 'label' => esc_html__('Template with all events', 'epfl-memento')),
        );

        $lang_options = array(
            array('value' => 'en', 'label' => esc_html__('English', 'epfl-memento')),
            array('value' => 'fr', 'label' => esc_html__('French', 'epfl-memento')),
        );

        $memento_description = sprintf(
            __("Please select your memento.%sThe events come from the application %smemento.epfl.ch%s.%sIf you don't have a memento, please send a request to %s", 'epfl-memento' ),
            '<br/>', '<a href=\"https://actu.epfl.ch\">', '</a>', '<br/>', '<a href=\"mailto:1234@epfl.ch\">1234@epfl.ch</a>'
        );

        $template_description = sprintf(
            esc_html__('Do you need more information about templates? %sRead this documentation%s', 'epfl-memento'),
            '<a href="">', '</a>'
        );

        shortcode_ui_register_for_shortcode(

            'epfl_memento',

            array(
                'label' => __('Add Memento shortcode', 'epfl-memento'),
                'listItemImage' => '',
                'attrs'         => array(
                    array(
                        'label'         => '<h3>' . esc_html__('Memento', 'epfl-memento') . '</h3>',
                        'attr'          => 'memento',
                        'type'          => 'select',
                        'options'       => $memento_options,
                        'description'   => $memento_description,
                    ),
                    array(
                        'label'         => '<h3>Template</h3>',
                        'attr'          => 'template',
                        'type'          => 'select',
                        'options'       => $template_options,
                        'description'   => $template_description,
                    ),
                    array(
                        'label'         => '<h3>' . esc_html__('Language', 'epfl-memento') . '</h3>',
                        'attr'          => 'lang',
                        'type'          => 'select',
                        'options'       => $lang_options,
                        'description'   => esc_html__('The language used to render events results', 'epfl-memento'),
                    ),

                ),

                'post_type'     => array( 'post', 'page' ),
            )
        );

    endif;
});
?>