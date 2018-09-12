<?php

Class ShortCakeQuoteConfig
{
    public static function config()
    {
        shortcode_ui_register_for_shortcode(

            'epfl_quote',

            array(
                'label'         => __('Quote', 'epfl'),
                'listItemImage' => '<img src="' . plugins_url( 'img/quote.svg', __FILE__ ) . '" >',
                'attrs'         => array(
                        array(
                            'label' => '<h3>' . esc_html__('Quote', 'epfl') . '</h3>',
                            'attr'  => 'quote',
                            'type'  => 'textarea',
                        ),
                        array(
                            'label' => '<h3>' . esc_html__('Source or reference', 'epfl') . '</h3>',
                            'attr'  => 'cite',
                            'type'  => 'text',
                        ),
                        array(
                            'label' => '<h3>' . esc_html__('Footer', 'epfl') . '</h3>',
                            'attr'  => 'footer',
                            'type'  => 'text',
                        ),
                        array(
                            'label'       => '<h3>' . esc_html__('Quote image', 'epfl') . '</h3>',
                            'attr'        => 'image',
                            'type'        => 'attachment',
                            'libraryType' => array( 'image' ),
                        ),
                    ),
                'post_type'     => array( 'post', 'page' ),
            )
        );
    }
}

?>
