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


/*
    GOAL : Extract question from shortcode attributes. We use this function as workaround to WordPress
           parsing that cannot handle escape double quotes correctly.

           WARNING! for now, this function works because 'attributes' only contains 'question' attribute. If in
                    the future another attributes is added, a modification will be needed to handle this correctly
                    (only take array items with numeric index as 'question').
*/
function extract_question($attributes)
{
    if(array_key_exists('question', $attributes)) return $attributes['question'];

    if(preg_match('/question=[.]*+/i', $attributes[0])===1)
    {

        return preg_replace('/^question=\"|\"$/i', '', implode(" ", $attributes));
    }
    return "";
}

function epfl_faqboxitem_process_shortcode($attributes, $content = null)
{
    global $faq_ref_table;

    /* We have to extract question using a dedicated method outside of WordPress because if the question contains
    escaped double quotes, it's not parsed correctly. Param $attributes won't be an associative array with 'question'
    as key (and the question content as value), it will be an array (not associative) with the question exploded using
    whitspaces... and in this case, WordPress will return an empty string for "question""*/
    $question = extract_question($attributes);

    /* Generating uniq anchor id*/
    $anchor = "faq-".md5($content);

    $faq_ref_table .= '<li><a href="#'.$anchor.'">'.$question.'</a></li>';

    return '<div class="faq-item">'.
           '<a name="'.esc_attr($anchor).'"></a>'.
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
    $atts = shortcode_atts(array(
            'title' => '',   // for the future
        ), $attributes);

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