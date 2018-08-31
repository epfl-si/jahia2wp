<?php

Class ShortCakeToggleConfig
{
    public static function get_fields()
    {
        $fields = [];
        for ( $i = 0; $i < 10; $i++) {

            array_push($fields, [
                'label'       => '<hr><hr><h3>' . esc_html__('Title', 'epfl') . '</h3>',
                'attr'        => 'label' . $i,
                'description' => esc_html__('The title of the collapsable', 'epfl'),
                'type'        => 'text',
            ]);

            array_push($fields, [
                'label'       => '<h2>' .esc_html__('Description', 'epfl') . '</h2>' ,
                'attr'        => 'desc' . $i,
                'description' => esc_html__('Content shown when collapsable is opened', 'epfl'),
                'type'        => 'textarea',
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
                'attrs'         => ShortCakeToggleConfig::get_fields(),
                'listItemImage' => '<img src="' . $iconDirectory . 'toggle.png'.'">',
            )
        );

    }
}
?>
