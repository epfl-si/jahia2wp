<?php

Class ShortCakeToggleConfig
{

    private static function get_state_options()
    {
        return array (
            array('value' => 'open', 'label' => esc_html__('Open', 'epfl')),
            array('value' => 'close', 'label' => esc_html__('Close', 'epfl')),
        );
    }

    public static function get_fields()
    {
        $fields = [];
        for ( $i = 0; $i < 10; $i++) {

            array_push($fields, [
                'label'       => '<hr><hr><h3>' . esc_html__('Title', 'epfl') . '</h3>',
                'attr'        => 'label' . $i,
                'description' => esc_html__('The title of the toggle', 'epfl'),
                'type'        => 'text',
            ]);

            array_push($fields, [
                'label'       => '<h2>' .esc_html__('Description', 'epfl') . '</h2>' ,
                'attr'        => 'desc' . $i,
                'description' => esc_html__('Content shown when toggle is opened', 'epfl'),
                'type'        => 'textarea',
                'encode'      => true,
                'meta'        => array(
                    'class' => 'shortcake-richtext',
                ),
            ]);

            array_push($fields, [
                'label'       => '<h3>' . esc_html__('Define the state of toggle', 'epfl') . '</h3>',
                'attr'        => 'state' . $i,
                'type'        => 'radio',
                'options'     => ShortCakeToggleConfig::get_state_options(),
                'description' => esc_html__('Do you want display the toggle open or close by default ?', 'epfl'),
                'value'       => 'close',

            ]);

        }
        return $fields;
    }

    public static function config()
    {

        global $iconDirectory;

        shortcode_ui_register_for_shortcode(

            'epfl_toggle_2018',

            array(
                'label'         => esc_html__( 'Toggle', 'epfl'),
                'listItemImage' => '<img src="' . $iconDirectory . 'toggle.png'.'">',
                'attrs'         => ShortCakeToggleConfig::get_fields(),
            )

        );

    }
}
?>
