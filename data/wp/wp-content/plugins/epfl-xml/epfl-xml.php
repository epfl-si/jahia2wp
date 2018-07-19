<?php

/**
 * Plugin Name: EPFL xml
 * Description: process an XML file with an associated XSLT file
 * @version: 1.0
 * @copyright: Copyright (c) 2018 Ecole Polytechnique Federale de Lausanne, Switzerland
 */

declare( strict_types = 1 );

/**
 * Helper to debug the code
 * @param $var: variable to display
 */
function epfl_xml_debug( $var ) {
    print "<pre>";
    var_dump( $var );
    print "</pre>";
}

/**
 * Build the html
 *
 * @param $xml the XML path
 * @param $xslt the XSLT path
 * @return string the html
 */
function epfl_xml_build_html( string $xml, string $xslt )
{
    try
    {
        // check that we have valid URLs
        if (!filter_var($xml, FILTER_VALIDATE_URL) || !filter_var($xslt, FILTER_VALIDATE_URL))
        {
          return "[epfl_xml error: invalid URLs]";
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
        return "[epfl_xml error:" . $e->getMessage() . "]";
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
        'xml'          => '',
        'xslt'         => ''
    ), $attributes);

    // sanitize parameters
    $xml  = sanitize_text_field($atts['xml']);
    $xslt = sanitize_text_field($atts['xslt']);

    return epfl_xml_build_html( $xml, $xslt );
}

// Load .mo file for translation
function epfl_xml_load_plugin_textdomain() {
    load_plugin_textdomain( 'epfl-xml', FALSE, basename( plugin_dir_path( __FILE__ )) . '/languages/');
}
add_action( 'plugins_loaded', 'epfl_xml_load_plugin_textdomain' );

add_action( 'init', function() {
    add_shortcode( 'epfl_xml', 'epfl_xml_process_shortcode' );
});

add_action( 'admin_init', function() {
    if ( function_exists( 'shortcode_ui_register_for_shortcode' ) ) :

        shortcode_ui_register_for_shortcode(

            'epfl_xml',

            array(
                'label' => __('Add XML/XSLT shortcode', 'epfl-xml'),
                'listItemImage' => '<img src="' . plugins_url( 'img/xml.svg', __FILE__ ) . '" >',
                'attrs'         => array(
                    array(
                        'label'         => '<h3>' . esc_html__('URL of xml', 'epfl-xml') . '</h3>',
                        'attr'          => 'xml',
                        'type'          => 'text',
                    ),
                    array(
                        'label'         => '<h3>' . esc_html__('URL of xslt', 'epfl-xml') . '</h3>',
                        'attr'          => 'xslt',
                        'type'          => 'text',
                    ),
                ),

                'post_type'     => array( 'post', 'page' ),
            )
        );

    endif;
});

?>
