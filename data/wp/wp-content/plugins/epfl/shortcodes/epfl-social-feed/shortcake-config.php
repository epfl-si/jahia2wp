<?php
namespace Epfl\SocialFeed\ShortCake;

function config() {
    shortcode_ui_register_for_shortcode(
        'epfl_social_feed',
        array(
            'label'         => esc_html__('Social feed', 'epfl'),
            'listItemImage' => '<img src="' . plugins_url( 'img/feed.png', __FILE__ ) . '" >',
            'attrs'         => array(
                array(
                    'label'         => '<h3>Twitter</h3>',
                    'attr'          => 'twitter_url',
                    'type'          => 'url',
                    'description'   => esc_html__('Url to your Twitter account (optional) (eg. https://twitter.com/EPFL)', 'epfl'),
                ),
                array(
                    'label'         => esc_html__('Tweets limit', 'epfl'),
                    'attr'          => 'twitter_limit',
                    'type'          => 'number',
                    'meta'        => array(
                        'placeholder' => '0',
                        'min'         => '0',
                    ),
                    'description'   => esc_html__('0 for unlimited', 'epfl'),
                ),
                array(
                    'label'         => '<h3>Instagram</h3>',
                    'attr'          => 'instagram_url',
                    'type'          => 'url',
                    'description'   => esc_html__('Url of an Instagram post (optional) (eg. https://www.instagram.com/p/BjuYB7Lhylj)', 'epfl'),
                ),
                array(
                    'label'         => '<h3>Facebook</h3>',
                    'attr'          => 'facebook_url',
                    'type'          => 'url',
                    'description'   => esc_html__('Url of your Facebook account (optional) (eg. https://www.facebook.com/epflcampus)', 'epfl'),
                ),
                array(
                    'label'         => '<h3>' . esc_html__('Height', 'epfl') . '</h3>',
                    'attr'          => 'height',
                    'type'          => 'number',
                    'description'   => esc_html__('Set the height in pixel (optional). 450 is recommended', 'epfl'),
                    'meta'        => array(
                        'placeholder' => '450',
                    ),
                ),
                array(
                    'label'         => '<h3>' . esc_html__('Width', 'epfl') . '</h3>',
                    'attr'          => 'width',
                    'type'          => 'number',
                    'description'   => esc_html__('Set the width in pixel (optional). 374 is recommended', 'epfl'),
                    'meta'        => array(
                        'placeholder' => '374',
                    ),
                ),                
            ),
            'post_type'     => array( 'post', 'page' ),
        )
    );
}
?>
