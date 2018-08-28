<?php

Class ShortCakeCardConfig
{
    public static function config()
    {
        shortcode_ui_register_for_shortcode(

            'epfl_card',

            array(
                'label'         => __('Card', 'epfl'),
                'listItemImage' => '<img src="' . plugins_url( 'img/card.svg', __FILE__ ) . '" >',
                'attrs'         => array(
                        array(
                            'label' => '<h3>' . esc_html__('Card text', 'epfl') . '</h3>',
                            'attr'  => 'text',
                            'type'  => 'text',
                        ),
                        array(
                            'label' => '<h3>' . esc_html__('Card title', 'epfl') . '</h3>',
                            'attr'  => 'title',
                            'type'  => 'text',
                        ),
                        array(
                            'label' => '<h3>' . esc_html__('Card link', 'epfl') . '</h3>',
                            'attr'  => 'link',
                            'type'  => 'url',
                        ),
                        array(
                            'label'       => '<h3>' . esc_html__('Card image', 'epfl') . '</h3>',
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
