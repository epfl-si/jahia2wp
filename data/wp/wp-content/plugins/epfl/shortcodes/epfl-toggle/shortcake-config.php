<?php

Class ToggleShortCakeConfig
{

    private static function get_state_options()
    {
        return array (
            array('value' => 'open', 'label' => esc_html__('Open', 'epfl')),
            array('value' => 'close', 'label' => esc_html__('Close', 'epfl')),
        );
    }

    public static function config()
    {

        shortcode_ui_register_for_shortcode(

            'epfl_toggle',

            array(
                'label' => 'Toggle',
                'listItemImage' => '<img src="' . plugins_url( 'img/toggle.svg', __FILE__ ) . '" >',

                'attrs'         => array(
                        array(
                            'label'         => '<h3>' . esc_html__('Enter toggle title', 'epfl') . '</h3>',
                            'attr'          => 'title',
                            'type'          => 'text',
                        ),
                        array(
                            'label'         => '<h3>' . esc_html__('Define toggle state', 'epfl') . '</h3>',
                            'attr'          => 'state',
                            'type'          => 'radio',
                            'options'       => ToggleShortCakeConfig::get_state_options(),
                            'description'   => esc_html__('Do you want display the toggle open or close by default ?', 'epfl'),
                            'value'         => 'open',
                        ),
                    ),
                'inner_content' => array(
                    'label'        => '<h3>' . esc_html__( 'Toggle content', 'epfl' ) . '</h3>',
                ),
                'post_type'     => array( 'post', 'page' ),
            )
        );
    }
}

?>
