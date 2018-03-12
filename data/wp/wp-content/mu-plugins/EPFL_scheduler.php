<?php

declare(strict_types=1);

function epfl_scheduler_shortcode($atts, $content = '', $tag)
{

    // extract shortcode parameter
    $atts = extract(shortcode_atts(array(
        'start_date' => '',
        'end_date' => ''
    ), $atts, $tag));

    // convert date string to datetime    
    $start_date = strtotime($start_date);
    $end_date = strtotime($end_date);

    $now = time();
    
    // check if we can display content
    if ($now > $start_date && $now < $end_date) {
        return $content;
    }
}

add_shortcode('epfl_scheduler', epfl_scheduler_shortcode);
