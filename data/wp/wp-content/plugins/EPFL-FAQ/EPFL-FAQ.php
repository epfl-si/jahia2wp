<?php
/*
Plugin Name: EPFL FAQ shortcode
Plugin URI: -
Description: provides 2 shortcodes to dispay a faq with boxes
Version: 1.0
Author: Lucien Chaboudez
Author URI: https://people.epfl.ch/lucien.chaboudez?lang=en
License: Copyright (c) 2018 Ecole Polytechnique Federale de Lausanne, Switzerland
*/


/* We have to use this global var to build FAQ references links. */
$faq_ref_table = "";


function epfl_faqboxitem_process_shortcode($attributes, $content = null)
{
    global $faq_ref_table;
    extract(shortcode_atts(array(
        'question' => '',
    ), $attributes));

    /* Generating uniq anchor id*/
    $anchor = "faq-".md5($content);

    $faq_ref_table .= '<li><a href="#'.$anchor.'">'.$question.'</a></li>';

    return '<div class="faq-item">'.
           '<a name="'.$anchor.'"></a>'.
           '<h4>'.$question.'</h4>'.
           $content.
           '</div>';
}


/*
    GOAL : Process Shortcode around FAQ elements

*/
function epfl_faqbox_process_shortcode($attributes, $content = null)
{
    global $faq_ref_table;
    extract(shortcode_atts(array(
        'title' => '',   // for the future
    ), $attributes));

    $faq_ref_table = '<ul class="link-list">';
    $faq_items_html = do_shortcode($content);

    $faq_ref_table .= '</ul>';

    return '<div class="faqBox">'.
           $faq_ref_table.
           $faq_items_html.
           '</div>';
}

add_shortcode('epfl_faq', 'epfl_faqbox_process_shortcode');
add_shortcode('epfl_faqItem', 'epfl_faqboxitem_process_shortcode');


?>