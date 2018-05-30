<?php

Class ShortCakeButtonsConfig
{
    private static function get_type_options() {
        return array(
            array('value' => 'big', 'label' => esc_html__('Big', 'epfl-buttons')),
            array('value' => 'small', 'label' => esc_html__('Small', 'epfl-buttons')),
        );
    }

    public static function config()
    {
        shortcode_ui_register_for_shortcode(

            'epfl_buttons',

            array(
                'label'         => __('Add Buttons shortcode', 'epfl-buttons'),
                'listItemImage' => '<img src="' . plugins_url( 'img/button.svg', __FILE__) . '" >',
                'attrs'         => array(
                    array(
                            'label'         => '<h3>' . esc_html__('Select button type', 'epfl-buttons') . '</h3>',
                            'attr'          => 'type',
                            'type'          => 'radio',
                            'options'       => ShortCakeButtonsConfig::get_type_options(),
                            'value'         => 'big',
                        ),
                    array(
                        'label'         => '<h3>' . esc_html__('Label', 'epfl-buttons') . '</h3>',
                        'attr'          => 'text',
                        'type'          => 'text',
                    ),
                    array(
                        'label'         => '<h3>' . esc_html__('Link', 'epfl-buttons') . '</h3>',
                        'attr'          => 'url',
                        'type'          => 'text',
                        'description'   => esc_html__('You can define a link in this field. For that, please enter an URL.', 'epfl-snippet'),
                    ),
                    array(
                        'label'         => '<h3>' . esc_html__('Image', 'epfl-buttons') . '</h3>',
                        'attr'          => 'image',
                        'type'          => 'attachment',
			            'libraryType'   => array( 'image' ),
			            'addButton'     => esc_html__( 'Select Image', 'shortcode-buttons'),
			            'frameTitle'    => esc_html__( 'Select Image', 'shortcode-buttons'),
                    ),

                ),
                'post_type'     => array( 'post', 'page' ),
            )
        );
    }
}
?>