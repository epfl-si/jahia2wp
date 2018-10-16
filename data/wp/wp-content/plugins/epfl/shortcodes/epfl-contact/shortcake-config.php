<?php
namespace Epfl\Contact\ShortCake;

function get_contact_shortcake_attributes() {
    $shortcake_attributes = [];

    array_push($shortcake_attributes, [
                    'label'         => '<h1>Contact</h1><h3>Introduction</h3>',
                    'attr'          => 'introduction',
                    'type'          => 'textarea',
                    'description'   => esc_html__('Text introducing the contact informations (optional)', 'epfl'),
                    ]
    );

    array_push($shortcake_attributes, [
                    'label'         => esc_html__('Wrap with a gray border', 'epfl'),
                    'attr'          => 'gray_wrapper',
                    'type'          => 'checkbox',
                    ]
    );

    for ($i = 1; $i < 5; $i++) {
        $label = ($i == 1) ? '<h3>' . esc_html__('Timetable', 'epfl') . ' 1</h3>' : esc_html__('Timetable', 'epfl') . ' '.$i;
        $description = ($i == 1) ? esc_html__('Lundi à jeudi <b>09:00 > 18:00</b>', 'epfl') : '';

        array_push($shortcake_attributes, [
            'label'         => $label,
            'attr'          => 'timetable'.$i,
            'type'          => 'text',
            'description'   => $description,
        ]);
    }

    for ($i = 1; $i < 4; $i++) {
        $label = ($i == 1) ? '<h3>' . esc_html__('Various information', 'epfl') . ' 1</h3>' : 'Information '.$i;
        # commented, in case we want to add a descrption
        # $description = ($i == 1) ? '<a href="mailto:1234@epfl.ch">1234@epfl.ch</a>' : '';

        array_push($shortcake_attributes, [
            'label'         => $label,
            'attr'          => 'information'.$i,
            'type'        => 'textarea',
            'encode'      => true,
            'meta'        => array(
                'class' => 'shortcake-richtext',
            ),
        ]);
    }

    array_push($shortcake_attributes, [
        'label'         => '<h3>' . esc_html__('What map information do you want to display?', 'epfl') . '</h3>',
        'attr'          => 'map_query',
        'type'          => 'text',
        'value'         => 'INN011',
        'description'   => esc_html__('A room for example', 'epfl'),
    ]);

    return $shortcake_attributes;
}

function config() {
    shortcode_ui_register_for_shortcode(
        'epfl_contact',
        array(
            'label'         => esc_html__('Contact', 'epfl'),
            'listItemImage' => '<img src="' . plugins_url( 'img/contact.png', __FILE__ ) . '" >',
            'attrs'         => get_contact_shortcake_attributes(),
            'post_type'     => array( 'page' ),
        )
    );
}
?>

