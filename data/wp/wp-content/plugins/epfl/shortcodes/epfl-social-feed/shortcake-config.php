<?php
namespace Epfl\SocialFeed\ShortCake;

function config() {
    shortcode_ui_register_for_shortcode(
        'epfl_social_feed',
        array(
            'label'         => esc_html__( 'Social feed', 'epfl'),
            'listItemImage' => '<img src="' . plugins_url( 'img/feed.png', __FILE__ ) . '" >',
            'attrs'         => array(
                array(
                    'label'         => '<h3>Twitter URL</h3>',
                    'attr'          => 'twitter_url',
                    'type'          => 'url',
                    'description'   => 'description',
                ),
                array(
                    'label'         => '<h3>Instagram Post URL</h3>',
                    'attr'          => 'instagram_url',
                    'type'          => 'url',
                    'description'   => 'description',
                ),
                array(
                    'label'         => '<h3>Facebook URL</h3>',
                    'attr'          => 'facebook_url',
                    'type'          => 'url',
                    'description'   => 'description',
                ),
                array(
                    'label'         => '<h3>Height</h3>',
                    'attr'          => 'height',
                    'type'          => 'text',
                    'description'   => 'Set the height',
                ),                
            ),
            'post_type'     => array( 'post', 'page' ),
        )
    );
}
?>
