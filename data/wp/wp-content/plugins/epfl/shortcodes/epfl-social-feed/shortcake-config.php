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
                    'description'   => esc_html__('Set the height (min. 347)', 'epfl'),
                    'meta'        => array(
                        'placeholder' => '347',
                        'min'         => '347',
                    ),
                ),                
            ),
            'post_type'     => array( 'post', 'page' ),
        )
    );
}
?>
