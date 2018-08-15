<?php

Class ShortCakeVideoConfig
{
    public static function config()
    {
        shortcode_ui_register_for_shortcode(

            'epfl_video',

            array(
                'label' => 'Video',
                'listItemImage' => '<img src="' . plugins_url( 'img/screen-player.svg', __FILE__ ) . '" >',
                'attrs'         => array(
                        array(
                            'label'         => '<h3>' . esc_html__('URL of the video', 'epfl-video') . '</h3>',
                            'attr'          => 'url',
                            'type'          => 'text',
                            'description'   => esc_html__('You can copy/paste a YouTube or SWITCHTube URL', 'epfl-video'),
                        ),
                        array(
                            'label'         => '<h3>' . esc_html__('Width of the video', 'epfl-video') . '</h3>',
                            'attr'          => 'width',
                            'type'          => 'text',
                            'value'         => '600',
                            'description'   => esc_html__('Width of the video. Recommended value: 600', 'epfl-video'),
                        ),
                        array(
                            'label'         => '<h3>' . esc_html__('Height of the video', 'epfl-video') . '</h3>',
                            'attr'          => 'height',
                            'type'          => 'text',
                            'value'         => '400',
                            'description'   => esc_html__('Height of the video. Recommended value: 400', 'epfl-video'),
                        ),
                 ),
                'post_type'     => array( 'post', 'page' ),
            )
        );
    }
}
?>
