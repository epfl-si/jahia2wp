<?php

declare(strict_types=1);

function epfl_scheduler_shortcode(
    array $atts, 
    string $content = '', 
    string $tag): string 
{

    // extract shortcode parameters
    $atts = extract(shortcode_atts(array(
        'start_date' => '',
        'end_date' => ''
    ), $atts, $tag));

    // convert date string to datetime    
    $start_date = strtotime($start_date);
    $end_date = strtotime($end_date);

    $now = time();
    
    if ($now > $start_date && $now < $end_date) {
        return $content;
    } else {
        return '';
    }
}

add_shortcode('epfl_scheduler', epfl_scheduler_shortcode);
    