<?php

Class ToggleShortCakeConfig
{

    private static function get_state_options()
    {
        return array (
            array('value' => 'open', 'label' => esc_html__('Open', 'epfl-toggle')),
            array('value' => 'close', 'label' => esc_html__('Close', 'epfl-toggle')),
        );
    }

    public static function config()
    {

        shortcode_ui_register_for_shortcode(

            'epfl_toggle',

            array(
                'label' => __('Add toggle shortcode', 'epfl-toggle'),
                'listItemImage' => '',

                'attrs'         => array(
                        array(
                            'label'         => '<h3>' . esc_html__('Enter the title of toggle', 'epfl-toggle') . '</h3>',
                            'attr'          => 'title',
                            'type'          => 'text',
                        ),
                        array(
                            'label'         => '<h3>' . esc_html__('Define the state of toggle', 'epfl-toggle') . '</h3>',
                            'attr'          => 'state',
                            'type'          => 'radio',
                            'options'       => ToggleShortCakeConfig::get_state_options(),
                            'description'   => esc_html__('Do you want display the toggle open or close by default ?', 'epfl-toggle'),
                            'value'         => 'open',
                        ),
                    ),
                'inner_content' => array(
                    'label'        => '<h3>' . esc_html__( 'Content of toggle', 'epfl-toggle' ) . '</h3>',
                ),
                'post_type'     => array( 'post', 'page' ),
            )
        );
    }
}

?>