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
                            'label'         => '<h3>' . esc_html__('Video URL', 'epfl-video') . '</h3>',
                            'attr'          => 'url',
                            'type'          => 'text',
                            'description'   => esc_html__('You can copy/paste a YouTube, Vimeo or SWITCHTube URL', 'epfl-video'),
                        ),
                 ),
                'post_type'     => array( 'post', 'page' ),
            )
        );
    }
}
?>
