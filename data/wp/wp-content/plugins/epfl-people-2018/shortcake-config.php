<?php

require_once 'utils.php';

Class ShortCakePeopleConfig
{   
    public static function config()
    {
    	shortcode_ui_register_for_shortcode(
            'epfl_people_2018',
            array(
                'label' => __('Add People shortcode', 'epfl-people-2018'),
                'listItemImage' => '<img src="' . plugins_url( 'img/people.svg', __FILE__ ) . '" >',
                'attrs'         => array(
                    array(
                        'label'         => '<h3>' . esc_html__('Unit', 'epfl-people-2018') . '</h3>',
                        'attr'          => 'unit',
                        'type'          => 'text',
                        'description'   => '',
                    ),
                ),
                'post_type'     => array( 'post', 'page' ),
            )
        );
    }
}
?>