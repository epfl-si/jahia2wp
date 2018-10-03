<?php

/**
 * Shortcode name: epfl-xml
 * Description: process an XML file with an associated XSLT file
 * @version: 1.1
 * @copyright: Copyright (c) 2018 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

/**
 * Build the html
 *
 * @param $xml the XML path
 * @param $xslt the XSLT path
 * @return string the html
 */
function epfl_xml_build_html( string $xml, string $xslt ): string
{
    try
    {
        // check that we have valid URLs
        if (!filter_var($xml, FILTER_VALIDATE_URL) || !filter_var($xslt, FILTER_VALIDATE_URL))
        {
            return Utils::render_user_msg("epfl_xml error: invalid URLs");
        }

        $xml_doc = new DOMDocument;
        $xml_doc->load($xml);

        $xslt_doc = new DOMDocument;
        $xslt_doc->load($xslt);

        $processor = new XSLTProcessor;
        $processor->importStyleSheet($xslt_doc);

        return $processor->transformToXML($xml_doc);
    }
    catch (Exception $e)
    {
        return Utils::render_user_msg("epfl_xml error:" . $e->getMessage());
    }
}

/**
 * Execute the shortcode
 *
 * @attributes: array of all input parameters
 * @content: the content of the shortcode. In our case the content is empty
 * @return html of shortcode
 */
function epfl_xml_process_shortcode( $attributes, string $content = null ): string
{
    // get parameters
    $atts = shortcode_atts(array(
        'xml'  => '',
        'xslt' => ''
    ), $attributes);

    // sanitize parameters
    $xml  = sanitize_text_field($atts['xml']);
    $xslt = sanitize_text_field($atts['xslt']);

    return epfl_xml_build_html( $xml, $xslt );
}

add_action( 'init', function() {
    add_shortcode( 'epfl_xml', 'epfl_xml_process_shortcode' );
});

add_action( 'register_shortcode_ui', function() {
    shortcode_ui_register_for_shortcode(

        'epfl_xml',

        array(
            'label'         => 'XML/XSLT',
            'listItemImage' => '<img src="' . plugins_url( 'img/xml.svg', __FILE__ ) . '" >',
            'attrs'         => array(
                array(
                    'label' => '<h3>' . esc_html__('XML URL', 'epfl') . '</h3>',
                    'attr'  => 'xml',
                    'type'  => 'text',
                ),
                array(
                    'label' => '<h3>' . esc_html__('XSLT URL', 'epfl') . '</h3>',
                    'attr'  => 'xslt',
                    'type'  => 'text',
                ),
            ),

            'post_type'     => array( 'post', 'page' ),
        )
    );
});

?>
