<?php

require_once 'utils.php';

Class InfoscienceSearchShortCakeConfig
{   
    private static function get_field_options() 
    {
        return array (
            array('value' => 'any', 'label' => esc_html__('Any field', 'epfl-infoscience-search')),
            array('value' => 'author', 'label' => esc_html__('Author', 'epfl-infoscience-search')),
            array('value' => 'title', 'label' => esc_html__('Title', 'epfl-infoscience-search')),
            array('value' => 'year', 'label' => esc_html__('Year', 'epfl-infoscience-search')),
            array('value' => 'unit', 'label' => esc_html__('Unit', 'epfl-infoscience-search')),
            array('value' => 'collection', 'label' => esc_html__('Collection', 'epfl-infoscience-search')),
            array('value' => 'journal', 'label' => esc_html__('Journal', 'epfl-infoscience-search')),
            array('value' => 'summary', 'label' => esc_html__('Summary', 'epfl-infoscience-search')),
            array('value' => 'keyword', 'label' => esc_html__('Keyword', 'epfl-infoscience-search')),
            array('value' => 'issn', 'label' => esc_html__('ISSN', 'epfl-infoscience-search')),
            array('value' => 'doi', 'label' => esc_html__('DOI', 'epfl-infoscience-search')),
        );
    }

    private static function get_limit_options() 
    {
        return array (
            # first is default
            array('value' => '25', 'label' => '25'), 
            array('value' => '10', 'label' => '10'),
            array('value' => '50', 'label' => '50'),
            array('value' => '100', 'label' => '100'),
            array('value' => '250', 'label' => '250'),
            array('value' => '500', 'label' => '500'),
            array('value' => '1000', 'label' => '1000'),
        );
    }

    private static function get_sort_options() 
    {
        return array (
            array('value' => 'desc', 'label' => esc_html__('Descending', 'epfl-infoscience-search')),
            array('value' => 'asc', 'label' => esc_html__('Ascending', 'epfl-infoscience-search')),
        );
    }
    private static function get_operator_options() 
    {
        return array (
            array('value' => '', 'label' => ''),
            array('value' => 'and', 'label' => esc_html__('AND', 'epfl-infoscience-search')),
            array('value' => 'or', 'label' => esc_html__('OR', 'epfl-infoscience-search')),
            array('value' => 'and_not', 'label' => esc_html__('AND NOT', 'epfl-infoscience-search')),
       );
    }

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

            $pattern_description = sprintf(
                esc_html__('Set a search key. %sRead this documentation%s', 'epfl-infoscience-search'),
                '<a target="_blank" href="' . $documentation_url . '">', '</a>'
            );

            $default_description = esc_html__('', 'epfl-infoscience-search');

            $build_url_separator = '<h3>' . esc_html__('Or build your list here', 'epfl-infoscience') . '</h3>';

            $visual_seperator = '<h3>' . esc_html__('Visual configuration', 'epfl-infoscience') . '</h3>';
            
            $advanced_content_seperator = '<h3>' . esc_html__('Advanced options', 'epfl-infoscience') . '</h3>';
    
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
                        # url builder
                        array(
                            'label'         => $build_url_separator . '<h4>' . esc_html__('Search key', 'epfl-infoscience') . '</h4>',
                            'attr'          => 'pattern',
                            'type'          => 'text',
                            'description'   => $pattern_description,
                        ),
                        array(
                            'label'         => '<h4>' . esc_html__('Field restriction', 'epfl-infoscience') . '</h4>',
                            'attr'          => 'field',
                            'type'          => 'select',
                            'options'       => InfoscienceSearchShortCakeConfig::get_field_options(),
                            'description'   => $default_description,
                        ),
                        array(
                            'label'         => '<h4>' . esc_html__('limit', 'epfl-infoscience') . '</h4>',
                            'attr'          => 'limit',
                            'type'          => 'select',
                            'options'       => InfoscienceSearchShortCakeConfig::get_limit_options(),
                            'description'   => $default_description,
                        ),
                        array(
                            'label'         => '<h4>' . esc_html__('limit', 'epfl-infoscience') . '</h4>',
                            'attr'          => 'order',
                            'type'          => 'select',
                            'options'       => InfoscienceSearchShortCakeConfig::get_sort_options(),
                            'description'   => $default_description,
                        ),
                        # Advanced content
                        array(
                            'label'         => $advanced_content_seperator. '<h4>' . esc_html__('pattern 2', 'epfl-infoscience') . '</h4>',
                            'attr'          => 'operator2',
                            'type'          => 'select',
                            'options'       => InfoscienceSearchShortCakeConfig::get_operator_options(),
                            'description'   => $default_description,
                        ),
                        array(
                            'label'         => esc_html__('Field restriction', 'epfl-infoscience'),
                            'attr'          => 'field2',
                            'type'          => 'select',
                            'options'       => InfoscienceSearchShortCakeConfig::get_field_options(),
                            'description'   => $default_description,
                        ),
                        array(
                            'label'         => '',
                            'attr'          => 'pattern2',
                            'type'          => 'text',
                            'description'   => $default_description,
                        ),
                        array(
                            'label'         => '<h4>' . esc_html__('pattern 3', 'epfl-infoscience') . '</h4>',
                            'attr'          => 'operator3',
                            'type'          => 'select',
                            'options'       => InfoscienceSearchShortCakeConfig::get_operator_options(),
                            'description'   => $default_description,
                        ),
                        array(
                            'label'         => esc_html__('Field restriction', 'epfl-infoscience'),
                            'attr'          => 'field3',
                            'type'          => 'select',
                            'options'       => InfoscienceSearchShortCakeConfig::get_field_options(),
                            'description'   => $default_description,
                        ),                        
                        array(
                            'label'         => '',
                            'attr'          => 'pattern3',
                            'type'          => 'text',
                            'description'   => $default_description,
                        ),
                        array(
                            'label'         => '<h4>' . esc_html__('Collection', 'epfl-infoscience') . '</h4>',
                            'attr'          => 'collection',
                            'type'          => 'text',
                            'description'   => $default_description,
                            'meta'        => array(
                                'placeholder' => 'Infoscience/Research',
                            ),                            
                        ),

                        # Presentation
                        array(
                            'label'         => $visual_seperator . '<h4>' . esc_html__('Format', 'epfl-infoscience') . '</h4>',
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
