<?php

/**
 * Plugin Name: EPFL Map shortcode
 * Description: provides a shortcode to display a map of map.epfl.ch
 * @version: 1.0
 * @copyright: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

declare(strict_types=1);

/**
 * Helper to debug the code
 */
function debug($var) {
    print "<pre>";
    var_dump($var);
    print "</pre>";
}

/**
 * Build html
 *
 * @param $width: width of the map iframe
 * @param $height: height of the map iframe
 * @param $query: query example: the office, the person
 * @param $lang: language
 */
function epfl_map_build_html(
    string $width,
    string $height,
    string $query,
    string $lang): string
{
    $html = '<iframe frameborder="0" ';
    $html .= 'width="' . $width . '"';
    $html .= 'height="' . $height . '"';
    $html .= '"scrolling="no" src="https://plan.epfl.ch/iframe/?q=' . $query;
    $html .= '&amp;lang=' . $lang . '&amp;map_zoom=10"></iframe>';
    return $html;
}

/**
 * Check the parameters
 *
 * Return True if all parameters are populated
 *
 * @param $width: width of the map iframe
 * @param $height: height of the map iframe
 * @param $query: query example: the office, the person
 * @param $lang: language
 */
function check_parameters(
    string $width,
    string $height,
    string $query,
    string $lang):bool
{
    return $width !== "" && $height !== "" && $query !== "" && $lang !== "";
}

function epfl_map_process_shortcode(
    $attributes,
    string $content = null): string
{
    extract(shortcode_atts(array(
        'width' => '',
        'height' => '',
        'query' => '',
        'lang' => '',
    ), $attributes));

    if (check_parameters($width, $height, $query, $lang) == FALSE) {
        return "";
    }

    return epfl_map_build_html($width, $height, $query, $lang);
}

add_shortcode('epfl_map', 'epfl_map_process_shortcode');

?>