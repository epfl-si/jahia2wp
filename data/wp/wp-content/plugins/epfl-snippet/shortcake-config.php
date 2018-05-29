<?php

Class ShortCakeSnippetConfig
{
    public static function config() 
    {
        shortcode_ui_register_for_shortcode(

            'epfl_snippets',

            array(
                'label'         => __('Add Snippet shortcode', 'epfl-snippet'),
                'listItemImage' => '<img src="' . plugins_url( 'snippet.svg', __FILE__ ) . '" >',
                'attrs'         => array(
                    array(
                        'label'         => '<h3>' . esc_html__('Title', 'epfl-snippet') . '</h3>',
                        'attr'          => 'title',
                        'type'          => 'text',
                    ),
                    array(
                        'label'         => '<h3>' . esc_html__('SubTitle', 'epfl-snippet') . '</h3>',
                        'attr'          => 'subtitle',
                        'type'          => 'text',
                    ),
                    array(
                        'label'         => '<h3>' . esc_html__('Image', 'epfl-snippet') . '</h3>',
                        'attr'          => 'image',
                        'type'          => 'attachment',
			            'libraryType'   => array( 'image' ),
			            'addButton'     => esc_html__( 'Select Image', 'shortcode-snippet'),
			            'frameTitle'    => esc_html__( 'Select Image', 'shortcode-snippet'),
                    ),
                    /**
                     * We don't know if this attribut is used
                    array(
                        'label'         => '<h3>' . esc_html__('Big Image', 'epfl-snippet') . '</h3>',
                        'attr'          => 'big_image',
                        'type'          => 'text',
                        'description'   => esc_html__('Big Image', 'epfl-snippet'),
                    ),
                    */
                    array(
                        'label'         => '<h3>' . esc_html__('Link', 'epfl-snippet') . '</h3>',
                        'attr'          => 'url',
                        'type'          => 'text',
                        'description'   => esc_html__('You can define a link in this field. For that, please enter an URL.', 'epfl-snippet'),
                    ),

                ),
                'inner_content' => array(
                    'label'        => '<h3>' . esc_html__( 'Content of snippet', 'epfl-snippet' ) . '</h3>',
                    'description'  => esc_html__('You can enter text to display above', 'epfl-snippet'),
                ),
                'post_type'     => array( 'post', 'page' ),
            )
        );
    }
}
?>