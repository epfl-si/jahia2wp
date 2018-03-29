<?php
/**
 * Plugin Name: Small and Big Buttons Box 
 * Description: provides a shortcode to display an equivalent of the smallButtonsBox and the bigButtonsBox in Jahia.
 * @version: 1.0
 * @copyright: Copyright (c) 2017 Ecole Polytechnique Federale de Lausanne, Switzerland
 */
declare( strict_types = 1 );
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
 * @param $type: Box size : "small" or "big"
 * @param $url: the url pointed by the shortcode
 * @param $image_url: the id of the media (image) to show
 * @param $text: the label for the image (text for the link also)
 * @return string html of div containing the image and the text, both pointing to the URL
 */
function epfl_buttons_box_build_html( string $type, string $url, string $image_url, string $text ): string
{
    $html  = '<div class="' . esc_attr($type) . 'ButtonsBox"><a href="'. esc_attr($url) . '">';
    $html .= '<img src="' . $image_url . '" alt="' . esc_attr($text) . '"/>';
    $html .= esc_attr($text) . '</a></div>';
    return $html;
}
/**
 * Check the parameters
 *
 * Return True if all parameters are populated
 *
 * @param $type: Box size : "small" or "big"
 * @param $url: the url pointed by the shortcode
 * @param $image_url: the id of the media (image) to show
 * @param $text: the label for the image (text for the link also)
 * @return True if all parameters are populated
 */
function epfl_buttons_box_check_parameters( string $type, string $url, string $image_url, string $text ): bool
{
    return $image_url !== '' && $url !== "" && $text !== "" && ($type == "small" || $type == "big");
}
/**
 * Execute the shortcode
 *
 * @attributes: array of all input parameters
 * @content: the content of the shortcode. In our case the content is empty
 * @return html of shortcode
 */
function epfl_buttons_box_process_shortcode( $attributes, string $content = null ): string
{
    // get parameters
    $atts = shortcode_atts(array(
        'type' => 'big',
        'image_url'  => '',
        'url' => '',
        'text'  => '',
    ), $attributes);
    // sanitize parameters
    $type = sanitize_text_field($atts['type']);
    $image_url = sanitize_text_field($atts['image_url']);
    $url      = sanitize_text_field($atts['url']);
    $text     = sanitize_text_field($atts['text']);
    // check parameters
    if ( epfl_buttons_box_check_parameters($type, $url, $image_url, $text) === FALSE ) {
        return "";
    }
    return epfl_buttons_box_build_html( $type, $url, $image_url, $text );
}
add_shortcode( 'epfl_buttons_box', 'epfl_buttons_box_process_shortcode' );
?>
