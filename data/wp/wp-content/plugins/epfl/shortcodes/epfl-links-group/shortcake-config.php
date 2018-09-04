<?php

Class ShortCakeLinksGroupConfig
{

    private static function get_label($i) {

        $label = "";

        if ($i == 0) {
            $label .= "<h2>Links</h2>";
        }

        $label .= '<hr><hr><strong>' . esc_html__('Label', 'epfl') . '</strong>';

        return $label;
    }

    public static function get_fields()
    {
        $fields = [];

        array_push($fields, [
            'label'       => '<h3>' . esc_html__('Title', 'epfl') . '</h3>',
            'attr'        => 'title',
            'type'        => 'text',
        ]);

        array_push($fields, [
            'label'       => '<h3>' . esc_html__('URL', 'epfl') . '</h3>',
            'attr'        => 'main_url',
            'type'        => 'url',
        ]);

        for ( $i = 0; $i < 10; $i++ ) {

            array_push($fields, [
                'label'       => ShortCakeLinksGroupConfig::get_label($i),
                'attr'        => 'label' . $i,
                'description' => esc_html__('Link label', 'epfl'),
                'type'        => 'text',
            ]);

            array_push($fields, [
                'label'       => '<strong>' . esc_html__('URL', 'epfl') . '</strong>',
                'attr'        => 'url' . $i,
                'description' => esc_html__('Link URL', 'epfl'),
                'type'        => 'url',
            ]);
        }
        return $fields;
    }

    public static function config()
    {

        global $iconDirectory;

        shortcode_ui_register_for_shortcode(

            'epfl_links_group',

            array(
                'label'         => esc_html__( 'Links group', 'epfl'),
                'listItemImage' => '<img src="' . $iconDirectory . 'links_group.png'.'">',
                'attrs'         => ShortCakeLinksGroupConfig::get_fields(),
            )

        );

    }
}
?>
