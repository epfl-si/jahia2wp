<?php

require_once 'utils.php';

Class ShortCakeConfig
{
    private static function get_display_style_options()
    {
        return array(
            array('value' => 'slider', 'label' => esc_html__('Slider gallery', 'epfl-my')),
            array('value' => 'gallery', 'label' => esc_html__('Full gallery', 'epfl-my')),
            array('value' => 'inline', 'label' => esc_html__('Slider Gallery with big picture', 'epfl-my')),
            array('value' => 'list', 'label' => esc_html__('File names list', 'epfl-my')),
        );
    }

    private static function get_sort_order_options()
    {
        return array(
            array('value' => 'alpha', 'label' => esc_html__('Alphabetical A-Z', 'epfl-my')),
            array('value' => 'alphaRev', 'label' => esc_html__('Alphabetical Z-A', 'epfl-my')),
            array('value' => 'date', 'label' => esc_html__('Chronological (oldest first)', 'epfl-my')),
            array('value' => 'dateRev', 'label' => esc_html__('Anti-chronological (newest first)', 'epfl-my')),
        );
    }

    public static function config()
    {
        shortcode_ui_register_for_shortcode(

            'epfl_my',

            array(
                'label' => __('Add MyEPFL Folder shortcode', 'epfl-my'),
                'listItemImage' => '<img src="' . plugins_url( 'img/my.svg', __FILE__ ) . '" >',
                'attrs'         => array(
                        array(
                            'label'         => '<h3>' . esc_html__('Select your myEpfl Folder', 'epfl-my') . '</h3>',
                            'attr'          => 'my_epfl_folder_path',
                            'type'          => 'text',
                            'description'   => esc_html__('The path to your MyEpfl Folder (http://documents.epfl.ch/...)', 'epfl-my'),
                        ),
                        array(
                            'label'         => '<h3>' . esc_html__('Select a display Style', 'epfl-my') . '</h3>',
                            'attr'          => 'my_epfl_display_style',
                            'type'          => 'select',
                            'options'       => ShortCakeConfig::get_display_style_options(),
                            'value'         => 'slider',
                        ),
                        array(
                            'label'         => '<h3>' . esc_html__('Select a sort order', 'epfl-my') . '</h3>',
                            'attr'          => 'my_epfl_sort_order',
                            'type'          => 'select',
                            'options'       => ShortCakeConfig::get_sort_order_options(),
                            'value'         => 'alpha',
                        ),
                        array(
                            'label'         => '<h3>' . esc_html__('Hide the file details', 'epfl-my') . '</h3>',
                            'attr'          => 'my_epfl_hide_file_detail',
                            'type'          => 'checkbox',
                            'description'   => esc_html__('Should the details of the images be visible ?', 'epfl-my'),
                            'value'         => '1',
                        ),
                    ),

                'post_type'     => array( 'post', 'page' ),
            )
        );
    }
}
?>
