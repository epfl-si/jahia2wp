<?php
namespace Epfl\Tableau\ShortCake;

function config() {
    shortcode_ui_register_for_shortcode(
        'epfl_tableau',
            ['label'         => esc_html__('Tableau', 'epfl-tableau'),
            'listItemImage' => '<img src="' . plugins_url( 'img/tableau.png', __FILE__ ) . '" >',
            'attrs'         => [
                ['label'      => esc_html__('EPFL Tableau content', 'epfl-tableau'),
                'attr'        => 'embed_code',
                'type'        => 'textarea',
                'encode'      => true,
                'description' => esc_html__('Paste here the content of the Embed Code when you press the "Share" button on an EPFL tableau view', 'epfl'),
                'meta'        => [
                    'placeholder' => esc_html__('Copy-paste the embed code', 'epfl-tableau'),
                    ],
                ],
                ['label'       => '<h3>'. esc_html__('Or', 'epfl-tableau') . '</h3><br />Url',
                'attr'        => 'url',
                'type'        => 'text',
                'description' => esc_html__('Url of the view (eg. "EPFLofficialstatistics/StatistiquesOfficielles")', 'epfl-tableau')
                ],
                ['label'       => esc_html__('Width', 'epfl-tableau') . '</h3>',
                'attr'        => 'width',
                'type'        => 'text',
                ],                
                ['label'       => esc_html__('Height', 'epfl-tableau') . '</h3>',
                'attr'        => 'height',
                'type'        => 'text',
                ],
            ],
            'post_type'     => ['page', 'post'],
        ]
    );
}
?>
