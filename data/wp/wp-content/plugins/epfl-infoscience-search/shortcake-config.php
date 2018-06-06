<?php

require_once 'utils.php';

Class InfoscienceSearchShortCakeConfig
{   
    private static function get_format_options() 
    {
        return array (
            array('value' => 'short', 'label' => esc_html__('Short', 'epfl-infoscience-search')),
            array('value' => 'detailed', 'label' => esc_html__('Detailed', 'epfl-infoscience-search')),
            array('value' => 'full', 'label' => esc_html__('Detailed with abstract', 'epfl-infoscience-search')),
       );
    }

    private static function get_group_by_options() 
    {
        return array (
            array('value' => '', 'label' => ''),
            array('value' => 'year', 'label' => esc_html__('year as title', 'epfl-infoscience-search')),
            array('value' => 'doctype', 'label' => esc_html__('document type as title', 'epfl-infoscience-search')),
       );
    }


    public static function config() 
    {
        if ( function_exists( 'shortcode_ui_register_for_shortcode' ) ) :

            $documentation_url = "https://support.epfl.ch/kb_view.do?sysparm_article=KB0014227";
    
            $url_description = sprintf(
                esc_html__('Add the url from a infoscience search result. %sRead this documentation%s', 'epfl-infoscience-search'),
                '<a target="_blank" href="' . $documentation_url . '">', '</a>'
            );

            $default_description = esc_html__('', 'epfl-infoscience-search');
    
            shortcode_ui_register_for_shortcode(
                'epfl_infoscience_search',
                array(
                    'label' => __('Add Infoscience search result shortcode', 'epfl-infoscience-search'),
                    'listItemImage' => '<img src="' . plugins_url( 'img/infoscience.svg', __FILE__ ) . '" >',
                    'attrs'         => array(
                        array(
                            'label'         => '<h3>' . esc_html__('Enter your infoscience search url', 'epfl-infoscience') . '</h3>',
                            'attr'          => 'url',
                            'type'          => 'text',
                            'description'   => $url_description,
                        ),
                        array(
                            'label'         => '<h3>' . esc_html__('Visual configuration', 'epfl-infoscience') . '</h3>' . '<h4>' . esc_html__('Format', 'epfl-infoscience') . '</h4>',
                            'attr'          => 'format',
                            'type'          => 'select',
                            'options'       => InfoscienceSearchShortCakeConfig::get_format_options(),
                            'description'   => __('Detail level for a publication', 'epfl-infoscience-search'),
                        ),
                        array(
                            'label'         => '<h4>' . esc_html__('Thumbnails', 'epfl-infoscience') . '</h4>',
                            'attr'          => 'show_thumbnail',
                            'type'          => 'checkbox',
                            'description'   => $default_description,
                        ),                           
                        array(
                            'label'         => '<h4>' . esc_html__('Group by', 'epfl-infoscience') . '</h4>' . '<h5>' . esc_html__('Group by', 'epfl-infoscience') . '(1)</h5>',
                            'attr'          => 'group_by',
                            'type'          => 'select',
                            'options'       => InfoscienceSearchShortCakeConfig::get_group_by_options(),
                            'description'   => $default_description,
                        ),
                        array(
                            'label'         => '<h5>' . esc_html__('Group by', 'epfl-infoscience') . ' (2)</h5>',
                            'attr'          => 'group_by2',
                            'type'          => 'select',
                            'options'       => InfoscienceSearchShortCakeConfig::get_group_by_options(),
                            'description'   => $default_description,
                        ),                        
                    ),
    
                    'post_type'     => array( 'post', 'page' ),
                )
            );
        endif;
    }
}
?>
