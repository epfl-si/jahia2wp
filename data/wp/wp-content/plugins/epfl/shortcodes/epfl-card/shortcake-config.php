<?php

Class ShortCakeCardConfig
{
    public static function get_fields()
    {
        $fields = [];
        
        array_push($fields, [
            'label'         => esc_html__('Wrap with a gray border', 'epfl'),
            'attr'          => 'gray_wrapper',
            'type'          => 'checkbox',
            ]
        );

        for ( $i = 1; $i < 4; $i++) {
            array_push($fields, [
                'label'       => '<div class="col-6"><hr><h2>'.esc_html__('Card', 'epfl').' '.$i.'</h2> '.esc_html__('Title', 'epfl').' '.'<br>',
                'attr'        => 'title' . $i,
                'type'        => 'text',
            ]);

            array_push($fields, [
                'label'       => esc_html__('Link', 'epfl'),
                'attr'        => 'link' . $i,
                'type'        => 'text',
            ]);

            array_push($fields, [
                'label'       => esc_html__('Image', 'epfl'),
                'attr'        => 'image' . $i,
                'type'        => 'attachment',
                'libraryType' => array( 'image' ),
                'description' => esc_html__('Recommended image size: 1920x1080', 'epfl')
            ]);

            array_push($fields, [
                'label'       => esc_html__('Text', 'epfl'),
                'attr'        => 'content' . $i,
                'type'        => 'textarea',
                'encode'      => true,
                'meta'        => array(
                    'class' => 'shortcake-richtext',
                ),
            ]);
        }
        return $fields;
    }

    public static function config()
    {
        shortcode_ui_register_for_shortcode(

            'epfl_card',

            array(
                'label'         => __('Card', 'epfl'),
                'listItemImage' => '<img src="' . plugins_url( 'img/card.svg', __FILE__ ) . '" >',
                'attrs'         => ShortCakeCardConfig::get_fields(),
                'post_type'     => array( 'post', 'page' ),
            )
        );
    }
}

?>
