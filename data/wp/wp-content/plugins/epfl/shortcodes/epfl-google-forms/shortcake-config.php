<?php

Class ShortCakeGoogleFormsConfig
{
    public static function config()
    {
        shortcode_ui_register_for_shortcode(

            'epfl_google_forms',

            array(
                'label' => 'Google Forms',
                'listItemImage' => '<img src="' . plugins_url( 'shortcodes/epfl-google-forms/img/google.svg', __FILE__ ) . '" >',
                'attrs'         => array(
                        array(
                            'label'         => '<h3>' . esc_html__('Google Forms <iframe> HTML code', 'epfl') . '</h3>',
                            'attr'          => 'data',
                            'type'          => 'textarea',
                            'encode'        => true,
                            'description'   => esc_html__('You can copy/paste the given HTML code containing <iframe>', 'epfl'),
                        ),
                 ),
                'post_type'     => array( 'post', 'page' ),
            )
        );
    }
}
?>
