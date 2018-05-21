<?php

Class ShortCakeSnippetConfig
{
    public static function config() 
    {
        shortcode_ui_register_for_shortcode(

            'epfl_snippet',

            array(
                'label' => __('Add Snippet shortcode', 'epfl-snippet'),
                'listItemImage' => '',
                'attrs'         => array(
                        array(
                            'label'         => '<h3>' . esc_html__('URL', 'epfl-snippet') . '</h3>',
                            'attr'          => 'url',
                            'type'          => 'text',
                            'description'   => esc_html__('URL', 'epfl-snippet'),
                        ),
                        array(
                            'label'         => '<h3>' . esc_html__('Title', 'epfl-snippet') . '</h3>',
                            'attr'          => 'title',
                            'type'          => 'text',
                            'description'   => esc_html__('Title', 'epfl-snippet'),
                        ),
                        array(
                            'label'         => '<h3>' . esc_html__('SubTitle', 'epfl-snippet') . '</h3>',
                            'attr'          => 'subtitle',
                            'type'          => 'text',
                            'description'   => esc_html__('SubTitle', 'epfl-snippet'),
                        ),
                        array(
                            'label'         => '<h3>' . esc_html__('Image', 'epfl-snippet') . '</h3>',
                            'attr'          => 'image',
                            'type'          => 'text',
                            'description'   => esc_html__('Image', 'epfl-snippet'),
                        ),
                        array(
                            'label'         => '<h3>' . esc_html__('Big Image', 'epfl-snippet') . '</h3>',
                            'attr'          => 'big_image',
                            'type'          => 'text',
                            'description'   => esc_html__('Big Image', 'epfl-snippet'),
                        ),

                    ),

                'post_type'     => array( 'post', 'page' ),
            )
        );
    }
}
?>