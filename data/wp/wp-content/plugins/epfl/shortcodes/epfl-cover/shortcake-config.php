<?php

Class ShortCakeCoverConfig
{
    public static function config()
    {
        shortcode_ui_register_for_shortcode(

            'epfl_cover',

            array(
                'label'         => __('Cover', 'epfl'),
                'listItemImage' => '<img src="' . plugins_url( 'img/cover.svg', __FILE__ ) . '" >',
                'attrs'         => array(
                        array(
                            'label'       => '<h3>' . esc_html__('Cover description', 'epfl') . '</h3>',
                            'attr'        => 'description',
                            'type'        => 'textarea',
                            'description' => '<a target="_blank" href="https://epfl-idevelop.github.io/elements/#/molecules/cover">Documentation</a>'
                        ),
                        array(
                            'label'       => '<h3>' . esc_html__('Cover image', 'epfl') . '</h3>',
                            'attr'        => 'image',
                            'type'        => 'attachment',
                            'libraryType' => array( 'image' ),
                        ),
                    ),
                'post_type'     => array( 'post', 'page' ),
            )
        );
    }
}

?>
