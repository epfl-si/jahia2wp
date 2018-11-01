<?php
namespace Epfl\Tableau\ShortCake;

function config() {
    shortcode_ui_register_for_shortcode(
        'epfl_tableau',
            ['label'         => esc_html__('Tableau', 'epfl'),
            'listItemImage' => '<img src="' . plugins_url( 'img/tableau.png', __FILE__ ) . '" >',
            'attrs'         => [
                ['label'       => esc_html__('EPFL Tableau url', 'epfl'),
                'attr'        => 'embed_code',
                'type'        => 'textarea',
                'description' => esc_html__('Paste here the content of the Embed Code when you press the "Share" button on an EPFL tableau view', 'epfl')
                'meta'        => array(
                    'placeholder' => 'Copy-paste the embed code here',
                ),                
                ],
                ['label'       => '<h3>'. esc_html__('Ou', 'epfl') . '</h3>',
                'attr'        => 'url',
                'type'        => 'text',
                'description' => esc_html__('Url of the view', 'epfl')
                ],
                ['label'       => esc_html__('Width', 'epfl') . '</h3>',
                'attr'        => 'width',
                'type'        => 'text',
                ],                
                ['label'       => esc_html__('Height', 'epfl') . '</h3>',
                'attr'        => 'height',
                'type'        => 'text',
                ],
            ],
            'post_type'     => ['page', 'post'],
        ],
        
    );
}
?>
