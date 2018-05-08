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

/*
    GOAL: Returns an array with :
    [0] => HTML code to use do access the element in page as anchor
    [1] => HTML code with question and answer
*/
function render_faq_item($question, $answer)
{
    /* Generating uniq anchor id*/
    $anchor = "#faq-".md5($content);

    return array('<a href="'.$anchor.'">'.$question.'</a>',
                 '<div class="faq-item">'.
                 '<a name="'.$anchor.'"></a>'.
                 '<h4>'.$question.'</h4>'.
                 $answer.
                 '</div>');
}

/*
    GOAL : Process Shortcode around FAQ elements

    NOTE : Because we need to create a reference table for all questions in FAQ, we cannot simply call "do_shortcode()"
           function with epfl_faqbox content, we have to manually extract information from $content value and use
           them to generate the things we need.
*/
function epfl_faqbox_process_shortcode($attributes, $content = null)
{
    extract(shortcode_atts(array(
        'title' => '',   // for the future
    ), $attributes));

    $double_quote = html_entity_decode("&#8221;");

    $ref_table = '<ul class="link-list">';
    $faq_items_html = '';

    $pattern = get_shortcode_regex(array('epfl_faqItem'));

    /* We recover all FAQ items in $content */
    if (preg_match_all( '/'. $pattern .'/s', $content, $matches ) )
    {

        foreach($matches[0] as $index => $faq_item)
        {

            /* $matches[3] return the shortcode attributes as string, it looks like this :
            " question=&#8221;Who provides support for email?&#8221;"
            We will change it to :
             "Who provides support for email?"*/
            $question = trim(preg_replace('/question=|'.$double_quote.'/', '', html_entity_decode($matches[3][$index])));

            // $matches[5] return the shortcode content as string
            list($anchor_html, $faq_item_html) = render_faq_item($question, $matches[5][$index]);

            $ref_table .= "<li>".$anchor_html."</li>\n";

            $faq_items_html .= $faq_item_html."\n";
        }

    }

    $ref_table .= '</ul>';

    return '<div class="faqBox">'.
           $ref_table.
           $faq_items_html.
           '</div>';
}

add_shortcode('epfl_faq', 'epfl_faqbox_process_shortcode');

?>