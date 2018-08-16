<?php

Class MementoRender
{
    /**
     * Template with only the title on the event (template 1)
     *
     * @param $events: response of memento API
     * @return html of template
     */
    public static function epfl_memento_template_short_text($events): string {

        $html_content = '<div class="list-events clearfix">';

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
            $html_content .= '  <h2 class="event-title"><a href="' . esc_attr($item->event_url) . '" title="' . esc_attr($item->title) . '">' . $item->title . '</a></h2>';

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
    public static function epfl_memento_template_text($events): string {

        $html_content = '<div class="list-events clearfix">';

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
            
            if( $item->canceled == "True" ) {
              $html_content .= '<article class="event has-extra event-canceled">';
            } else {
              $html_content .= '<article class="event has-extra">';
            }
            
            $html_content .= '  <div class="event-dates">';
            $html_content .= '    <p class="date-start"><time>' . $start_date . '</time></p>';

            if ($end_date != $start_date) {
              $html_content .= '  <p class="date-end"><time>' . $end_date . '</time></p>';
            }

            $html_content .= '  </div>';
            $html_content .= '  <div class="event-content">';
            $html_content .= '    <div class="event-meta">';
            $html_content .= '      <a href="' . esc_attr($item->icalendar_url) . '" class="event-export"><span class="sr-only">Export event</span></a>';

            if (!is_null($item->start_time)) {
              $html_content .= '    <p class="event-times time-start">'.$start_time.'</p>';
              $html_content .= '    <p class="event-times time-end">' . $end_time . '</p>';
            }

            $html_content .= '      <p class="event-location">';
            $html_content .= '        <a href="' . esc_attr($item->url_place_and_room) . '">' . $item->place_and_room . '</a>';
            $html_content .= '      </p>';
            $html_content .= '    </div>';
            $html_content .= '    <h2 class="event-title">';
            $html_content .= '      <a href="' . esc_attr($item->event_url) . '" title="' . esc_attr($item->title) . '">' . $item->title . '</a>';
            $html_content .= '    </h2>';

            $html_content .= '  </div>';
            $html_content .= '  <div class="event-extra">';
            $html_content .= '    <p class="speakers">';

            if ($item->speaker !== "") {
              $html_content .=  __( 'By: ', 'epfl-memento' ) . $item->speaker;
            }

            $html_content .= '    </p>';
            $html_content .= '  </div>';
            $html_content .= '</article>';
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
    public static function epfl_memento_template_with_3_events($events): string {
        $html_content = '<div class="list-events clearfix">';

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
            
            if( $item->canceled == "True" ) {
              $html_content .= '<article class="event has-image has-teaser has-extra event-canceled">';
            } else {
              $html_content .= '<article class="event has-image has-teaser has-extra">';
            }
            
            $html_content .= '  <div class="event-dates">';
            $html_content .= '    <p class="date-start"><time>' . $start_date . '</time></p>';

            if ($end_date != $start_date) {
              $html_content .= '  <p class="date-end"><time>' . $end_date . '</time></p>';
            }
    
            if( $item->canceled == "True" ) {
              $html_content .= '<p class="canceled">' . __( 'Canceled', 'epfl-memento' ) . '</p>';
            }

            $html_content .= '  </div>';
            $html_content .= '  <figure class="img event-img">';
            $html_content .= '    <a href="' . esc_attr($item->event_url) . '" title="' . esc_attr($item->title) . '">';
            $html_content .= '      <img src="' . esc_attr($item->visual_url) . '" title="'. esc_attr($item->title) . '" alt="' . esc_attr($item->image_description) . '">';
            $html_content .= '    </a>';
            $html_content .= '  </figure>';
            $html_content .= '  <div class="event-content">';
            $html_content .= '    <div class="event-meta">';
            $html_content .= '      <a href="' . esc_attr($item->icalendar_url) . '" class="event-export"><span class="sr-only">Export event</span></a>';

            if (!is_null($item->start_time)) {
              $html_content .= '    <p class="event-times time-start">' . $start_time.'</p>';
              $html_content .= '    <p class="event-times time-end">' . $end_time . '</p>';
            }

            $html_content .= '      <p class="event-location">';
            $html_content .= '        <a href="' . esc_attr($item->url_place_and_room) . '">' . $item->place_and_room . '</a>';
            $html_content .= '      </p>';
            $html_content .= '    </div>';
            $html_content .= '    <h2 class="event-title">';
            $html_content .= '      <a href="' . esc_attr($item->event_url) . '" title="' . esc_attr($item->title) . '">' . $item->title . '</a>';
            $html_content .= '    </h2>';
            $html_content .= '    <p class="teaser">' . $item->description . '</p>';
            $html_content .= '  </div>';
            $html_content .= '  <div class="event-extra">';
            $html_content .= '    <p class="speakers">';

            if ($item->speaker !== "") {
              $html_content .=  __( 'By: ', 'epfl-memento' ) . $item->speaker;
            }

            $html_content .= '    </p>';
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
    public static function epfl_memento_template_with_5_events_and_right_column($events): string {

        $html_content = '<div class="list-events clearfix">';

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
            
            if( $item->canceled == "True" ) {
              $html_content .= '<article class="event has-image has-teaser has-extra event-canceled">';
            } else {
              $html_content .= '<article class="event has-image has-teaser has-extra">';
            }
            
            $html_content .= '  <div class="event-dates">';
            $html_content .= '    <p class="date-start"><time>' . $start_date . '</time></p>';

            if ($end_date != $start_date) {
              $html_content .= '  <p class="date-end"><time>' . $end_date . '</time></p>';
            }
    
            if( $item->canceled == "True" ) {
              $html_content .= '<p class="canceled">' . __( 'Canceled', 'epfl-memento' ) . '</p>';
            }

            $html_content .= '  </div>';
            $html_content .= '  <figure class="img event-img">';
            $html_content .= '    <a href="' . esc_attr($item->event_url) . '" title="' . esc_attr($item->title) . '">';
            $html_content .= '      <img src="' . esc_attr($item->visual_url) . '" title="' . esc_attr($item->title) .'" alt="' . esc_attr($item->image_description) . '">';
            $html_content .= '    </a>';
            $html_content .= '  </figure>';
            $html_content .= '  <div class="event-content">';
            $html_content .= '    <div class="event-meta">';
            $html_content .= '      <a href="' . esc_attr($item->icalendar_url) . '" class="event-export"><span class="sr-only">Export event</span></a>';

            if (!is_null($item->start_time)) {
              $html_content .= '    <p class="event-times time-start">'.$start_time.'</p>';
              $html_content .= '    <p class="event-times time-end">' . $end_time . '</p>';
            }

            $html_content .= '      <p class="event-location">';
            $html_content .= '        <a href="' . esc_attr($item->url_place_and_room) . '">' . $item->place_and_room . '</a>';
            $html_content .= '      </p>';
            $html_content .= '    </div>';
            $html_content .= '    <h2 class="event-title">';
            $html_content .= '      <a href="' . esc_attr($item->event_url) . '" title="' . esc_attr($item->title) . '">' . $item->title . '</a>';
            $html_content .= '    </h2>';
            $html_content .= '    <p class="teaser">' . $item->description . '</p>';
            $html_content .= '  </div>';
            $html_content .= '  <div class="event-extra">';
            $html_content .= '    <p class="speakers">';

            if ($item->speaker !== "") {
              $html_content .=  __( 'By: ', 'epfl-memento' ) . $item->speaker;
            }

            $html_content .= '    </p>';
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
    public static function epfl_memento_template_with_2_events($events): string {
        $html_content = '<div class="list-events clearfix">';

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
            
            if( $item->canceled == "True" ) {
              $html_content .= '<article class="event has-image event-canceled">';
            } else {
              $html_content .= '<article class="event has-image">';
            }
            
            $html_content .= '  <div class="event-dates">';
            $html_content .= '    <p class="date-start"><time>' . $start_date . '</time></p>';

            if ($end_date != $start_date) {
              $html_content .= '  <p class="date-end"><time>' . $end_date . '</time></p>';
            }
    
            if( $item->canceled == "True" ) {
              $html_content .= '<p class="canceled">' . __( 'Canceled', 'epfl-memento' ) . '</p>';
            }

            $html_content .= '  </div>';
            $html_content .= '  <figure class="img event-img">';
            $html_content .= '    <a href="' . esc_attr($item->event_url) . '" title="' . esc_attr($item->title) . '">';
            $html_content .= '      <img src="' . esc_attr($item->visual_url) . '" title="' . esc_attr($item->title) .'" alt="' . esc_attr($item->image_description) . '">';
            $html_content .= '    </a>';
            $html_content .= '  </figure>';
            $html_content .= '  <div class="event-content">';
            $html_content .= '    <div class="event-meta">';
            $html_content .= '      <a href="' . esc_attr($item->icalendar_url) . '" class="event-export"><span class="sr-only">Export event</span></a>';

            if (!is_null($item->start_time)) {
              $html_content .= '    <p class="event-times time-start">' . $start_time . '</p>';
              $html_content .= '    <p class="event-times time-end">' . $end_time . '</p>';
            }

            $html_content .= '      <p class="event-location">';
            $html_content .= '        <a href="' . esc_attr($item->url_place_and_room) . '">' . $item->place_and_room . '</a>';
            $html_content .= '      </p>';
            $html_content .= '    </div>';
            $html_content .= '    <h2 class="event-title">';
            $html_content .= '      <a href="' . esc_attr($item->event_url) . '" title="' . esc_attr($item->title) . '">' . $item->title . '</a>';
            $html_content .= '    </h2>';

            $html_content .= '  </div>';

            $html_content .= '</article>';
        }

        $html_content.= '</div>';

        return $html_content;
    }

    /**
     * Template with 3 events and right column (template 6)
     *
     * @param $events: response of memento API
     * @return html of template
     */
    public static function epfl_memento_template_with_3_events_and_right_column($events): string {

        $html_content = '<div class="list-events clearfix">';

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
            
            if( $item->canceled == "True" ) {
              $html_content .= '<article class="event has-image event-canceled">';
            } else {
              $html_content .= '<article class="event has-image">';
            }
            
            $html_content .= '  <div class="event-dates">';
            $html_content .= '    <p class="date-start"><time>' . $start_date . '</time></p>';

            if ($end_date != $start_date) {
              $html_content .= '  <p class="date-end"><time>' . $end_date . '</time></p>';
            }
    
            if( $item->canceled == "True" ) {
              $html_content .= '<p class="canceled">' . __( 'Canceled', 'epfl-memento' ) . '</p>';
            }

            $html_content .= '  </div>';
            $html_content .= '  <figure class="img event-img">';
            $html_content .= '    <a href="' . esc_attr($item->event_url) . '" title="' . esc_attr($item->title) . '">';
            $html_content .= '      <img src="' . esc_attr($item->visual_url) . '" title="' . esc_attr($item->title) . '" alt="' . esc_attr($item->image_description) . '">';
            $html_content .= '    </a>';
            $html_content .= '  </figure>';
            $html_content .= '  <div class="event-content">';
            $html_content .= '    <div class="event-meta">';
            $html_content .= '      <a href="' . esc_attr($item->icalendar_url) . '" class="event-export"><span class="sr-only">Export event</span></a>';

            if (!is_null($item->start_time)) {
              $html_content .= '    <p class="event-times time-start">' . $start_time . '</p>';
              $html_content .= '    <p class="event-times time-end">' . $end_time . '</p>';
            }

            $html_content .= '      <p class="event-location">';
            $html_content .= '        <a href="' . esc_attr($item->url_place_and_room) . '">' . $item->place_and_room . '</a>';
            $html_content .= '      </p>';
            $html_content .= '    </div>';
            $html_content .= '    <h2 class="event-title">';
            $html_content .= '      <a href="' . esc_attr($item->event_url) . '" title="' . esc_attr($item->title) . '">' . $item->title . '</a>';
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
    public static function epfl_memento_template_student_portal($events): string {

        $html_content = '<div class="list-events clearfix">';

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
            $html_content .= '     <img src="https://studying.epfl.ch/files/content/sites/studying/files/memento/empty_fr_en.png" title="' . esc_attr($item->title) . '" alt="">';
            $html_content .= '  </figure>';
            $html_content .= '  <div class="event-content">';
            $html_content .= '    <h2 class="event-title">';
            $html_content .= '      <a href="' . esc_attr($item->event_url) . '" title="' . esc_attr($item->title) . '">' . $item->title . '</a>';
            $html_content .= '    </h2>';
            $html_content .= '    <p class="studying-calendar">';
            $html_content .= '      <a href="https://memento.epfl.ch/academic-calendar/?period=14"><span class="label">' . __( 'Academic calendar ', 'epfl-memento' ) . '</span></a>';
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
    public static function epfl_memento_template_homepage_faculty($events): string {

        $html_content = '<div>';

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
    public static function epfl_memento_build_html($events, $template): string
    {
        if ($template === "1") {
            $html = MementoRender::epfl_memento_template_short_text($events);
        } elseif ($template === "5") {
            $html = MementoRender::epfl_memento_template_text($events);
        } elseif ($template === "6") {
            $html = MementoRender::epfl_memento_template_with_3_events_and_right_column($events);
        } elseif ($template === "3") {
            $html = MementoRender::epfl_memento_template_with_5_events_and_right_column($events);
        } elseif ($template === "2") {
            $html = MementoRender::epfl_memento_template_with_3_events($events);
        } elseif ($template === "8") {
            $html = MementoRender::epfl_memento_template_with_2_events($events);
        } elseif ($template === "7") {
            $html = MementoRender::epfl_memento_template_student_portal($events);
        } elseif ($template === "9") {
            $html = MementoRender::epfl_memento_template_homepage_faculty($events);
        } else {
            $html = MementoRender::epfl_memento_template_with_3_events($events);
        }

        if ($html === '<div class="list-events clearfix"></div>') {
          $result = '<div class="eventsBox">';
          if  (get_locale() == 'fr_FR') {
            $result .= "Pas d'événements programmés";
          } else {
            $result .= "No events scheduled";
          }
          $result .= $html;
          $result .= '</div>';
          return $result;
        } else {
          return '<div class="eventsBox">' . $html . '</div>';
        }
    }

    /**
     * Build HTML. This template contains all events inside ifram tag
     *
     * @param $memento: slug of memento
     * @param $lang: lang of event (fr or en)
     * @param $color: color of the faculty
     * @param $period: period of events (past or upcoming)
     * @return html of iframe template
     */
    public static function epfl_memento_built_html_pagination_template(string $memento, string $lang, string $color, string $period): string {
        if (empty($period) || $period === 'upcoming') {
            $period = 2;
        } else {
            $period = 1;
        }
        $url = MEMENTO_API_URL_IFRAME. '&memento=' . $memento . '&lang=' . $lang . '&template=4&period=' . $period . '&color=' . strtoupper($color);
        $result = '<IFRAME ';
        $result .= 'src="' . esc_attr($url) . '" ';
        $result .= 'width="660" height="1255" scrolling="no" frameborder="0"></IFRAME>';
        return $result;
    }
}
?>