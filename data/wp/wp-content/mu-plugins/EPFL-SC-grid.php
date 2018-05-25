<?php
/*
Plugin Name: EPFL Grid shortcode
Plugin URI: -
Description: provides 2 shortcodes to dispay a grid with boxes
Version: 1.0
Author: Lucien Chaboudez
Author URI: https://people.epfl.ch/lucien.chaboudez?lang=en
License: Copyright (c) 2018 Ecole Polytechnique Federale de Lausanne, Switzerland
*/

/*

*/
function epfl_gridboxelement_process_shortcode($attributes, $content = null)
{
    extract(shortcode_atts(array(
        'layout' => 'default',
        'link' => '',
        'title' => '',
        'image' => '',
    ), $attributes));

    return '<div class="grid '.$layout.'">'.
            '<div class="bg" style="background-image: url('.$image.')"></div>'.
            '<h3><a href="'.$link.'">'.$title.'</a></h3>'.
            '</div>';
}

/*
    GOAL : Process Shortcode around grid elements
*/
function epfl_gridbox_process_shortcode($attributes, $content = null)
{
    extract(shortcode_atts(array(
        'title' => '',   // for the future
        'text' => '', //for the future
    ), $attributes));

    return '<div class="gridBox">'.
           do_shortcode($content).
           '</div>';
}

add_shortcode('epfl_grid', 'epfl_gridbox_process_shortcode');
add_shortcode('epfl_gridElem', 'epfl_gridboxelement_process_shortcode');

?>