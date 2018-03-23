<?php

/**
 * Plugin Name: EPFL Map shortcode
 * Description: provides a shortcode to display a map of map.epfl.ch
 * @version: 1.0
 * @copyright: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

declare(strict_types=1);

function epfl_map_process_shortcode($attributes, $content = null)
{
    extract(shortcode_atts(array(
        'width' => '',
        'height' => '',
        'query' => '',
    ), $attributes));
    
}

add_shortcode('epfl_map', 'epfl_map_process_shortcode');

php?>