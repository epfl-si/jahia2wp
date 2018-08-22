<?php
/**
 * Plugin Name: Small and Big Buttons Box
 * Description: provides a shortcode to display an equivalent of the smallButtonsBox and the bigButtonsBox in Jahia.
 * @version: 1.1
 * @copyright: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

declare( strict_types = 1 );

define('EPFL_BUTTONS_BIG', 'big');
define('EPFL_BUTTONS_SMALL', 'small');

$total_big_buttons = 0;
$buttons_type = '';

/**
 * Helper to debug the code
 * @param $var: variable to display
 */
function epfl_buttons_box_debug( $var ) {
    print "<pre>";
    var_dump( $var );
    print "</pre>";
}

/**
 * Build html
 *
 * @param $type: Box size : EPFL_BUTTONS_BIG or EPFL_BUTTONS_SMALL
 * @param $url: the url pointed by the shortcode
 * @param $image_url: the id of the media (image) to show
 * @param $alt_text: the label for the image
 * @param $text: link text
 * @param $title: Text to display under image
 * @param $key: Key to identify small button class
 * @return string html of div containing the image and the text, both pointing to the URL
 */
function epfl_buttons_box_build_html( string $type, string $url, string $image_url, string $alt_text, string $text, string $key ): string
{
    $html = '';

    /* Only big buttons are surrounded by a DIV */
    if($type == EPFL_BUTTONS_BIG) $html .= '<div class="bigButtonsBox">';

    $html .= '<a class="button-link ';

    if($type == EPFL_BUTTONS_SMALL)
    {
        $html .= esc_attr($key);
    }

    $html .= '" href="'. esc_attr($url) . '" title="' . esc_attr($alt_text) .'">';
    /* We only add this if image is given (can be empty) */
    if($type == EPFL_BUTTONS_BIG && $image_url != "")
    {
        $html .= '<img src="' . $image_url . '" />';
    }

    $html .= '<span class="label">' . $text . '</span></a>';

    if($type == EPFL_BUTTONS_BIG) $html .= '</div>';

    return $html;
}

/**
 * Execute the shortcode container
 *
 * @attributes: array of all input parameters
 * @content: the content of the shortcode. In our case the content is empty
 * @return html of shortcode
 */
function epfl_buttons_container_process_shortcode( $attributes, string $content = null ): string
{
    global $total_big_buttons;
    global $buttons_type;

    $buttons_html = do_shortcode($content);

    /* Now we have $buttons_type var initialized so we can determine the CSS class to use */
    $css_class = ($buttons_type == EPFL_BUTTONS_BIG)?"buttonsContainer":"smallButtonsBox";

    $content = '<section class="'.$css_class.'">'. $buttons_html;

    /* Adding empty "missing" buttons to complete line until it ends */
    if ($total_big_buttons%4 > 0) {
        for($i=$total_big_buttons%4; $i<4; $i++)
        {
            $content .= epfl_buttons_box_build_html(EPFL_BUTTONS_BIG, "", "", "", "", "" );
        }
    }

    $content .= '</section>';

    return $content;
}


/**
 * Execute the shortcode
 *
 * @attributes: array of all input parameters
 * @content: the content of the shortcode. In our case the content is empty
 * @return html of shortcode
 */
function epfl_buttons_process_shortcode( $attributes, string $content = null ): string
{
    global $total_big_buttons;
    global $buttons_type;

    // get parameters
    $atts = shortcode_atts( array(
        'type'      => EPFL_BUTTONS_BIG,
        'image'     => '',
        'url'       => '',
        'alt_text'  => '',
        'text'      => '',
        'key'       => '',
    ), $attributes);

    // sanitize parameters
    $type       = sanitize_text_field($atts['type']);
    $image      = sanitize_text_field($atts['image']); // only for big buttons
    $url        = $atts['url'];
    $alt_text   = sanitize_text_field($atts['alt_text']);
    $text       = sanitize_text_field($atts['text']);
    $key        = sanitize_text_field($atts['key']); // only for small buttons

    $buttons_type = $type;

    if($type == EPFL_BUTTONS_BIG)
    {
        /* If image given */
        if($image != "" && $image != "/")
        {
            $image_url = wp_get_attachment_url( $image );
            if (false == $image_url)
            {
                $image_url = "BAD MEDIA ID";
            }
        }
        else /* No image given */
        {
            $image_url = "";
        }

        $total_big_buttons++;
    }
    else
    {
        $image_url = "";
    }

    return epfl_buttons_box_build_html($type, $url, $image_url, $alt_text, $text, $key );
}

/* Buttons container */
add_shortcode( 'epfl_buttons_container', 'epfl_buttons_container_process_shortcode' );
add_shortcode( 'epfl_buttons', 'epfl_buttons_process_shortcode' );

?>