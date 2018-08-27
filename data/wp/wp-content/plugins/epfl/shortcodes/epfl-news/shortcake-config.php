<?php

Class ShortCakeNewsConfig
{   
    private static function get_channel_options() 
    {
        // call REST API to get the number of channels
        $channel_response = Utils::get_items(NEWS_API_URL);

        // build URL with all channels
        $url = NEWS_API_URL . '?limit=' . $channel_response->count;

        // call REST API to get all channels
        $channel_response = Utils::get_items($url);

        // build select tag html
        $channel_options = array();

        if(property_exists($channel_response, 'results'))
        {
            foreach ($channel_response->results as $item) {
                $option = array(
                    'value' => strval($item->id),
                    'label' => $item->name,
                );
                array_push($channel_options, $option);
            }
        }
        return $channel_options;
    }

    private static function get_lang_options()
    {
        return array(
            array('value' => 'en', 'label' => esc_html__('English', 'epfl')),
            array('value' => 'fr', 'label' => esc_html__('French', 'epfl')),
        );
    }

    private static function get_stickers_options() 
    {
        return array(
            array('value' => 'no', 'label' => esc_html__('No', 'epfl')),
            array('value' => 'yes', 'label' => esc_html__('Yes', 'epfl')),
        );
    }

    private static function get_category_options() 
    {
        return array(
            array('value' => '', 'label' => esc_html__('No filter', 'epfl')),
            array('value' => '1', 'label' => esc_html__('Epfl', 'epfl')),
            array('value' => '2', 'label' => esc_html__('Education', 'epfl')),
            array('value' => '3', 'label' => esc_html__('Research', 'epfl')),
            array('value' => '4', 'label' => esc_html__('Innovation', 'epfl')),
            array('value' => '5', 'label' => esc_html__('Campus Life', 'epfl')),
        );
    }

    private static function get_template_options() 
    {
        return array (
            array('value' => '1', 'label' => esc_html__('Template for laboratory website', 'epfl')),
            array('value' => '2', 'label' => __('Template for homepage with 3 news', 'epfl')),
            array('value' => '3', 'label' => __('Template for homepage with 1 news', 'epfl')),
        );
    }

    private static function get_themes_options() 
    {
        return array (
            array('value' => '', 'label' => esc_html__('No filter', 'epfl')),
            array('value' => '1', 'label' => esc_html__('Basic Sciences', 'epfl')),
            array('value' => '2', 'label' => esc_html__('Health', 'epfl')),
            array('value' => '3', 'label' => esc_html__('Computer Science', 'epfl')),
            array('value' => '4', 'label' => esc_html__('Engineering', 'epfl')),
            array('value' => '5', 'label' => esc_html__('Environment', 'epfl')),
            array('value' => '6', 'label' => esc_html__('Buildings', 'epfl')),
            array('value' => '7', 'label' => esc_html__('Culture', 'epfl')),
            array('value' => '8', 'label' => esc_html__('Economy', 'epfl')),
            array('value' => '9', 'label' => esc_html__('Energy', 'epfl')),
        );
    }

    private static function get_channel_description() 
    {
        return sprintf(
            __("The news come from the application %sactu.epfl.ch%s.%sIf you don't have a news channel, please send a request to %s", 'epfl' ),
            '<a href=\"https://actu.epfl.ch\">', '</a>', '<br/>', '<a href=\"mailto:1234@epfl.ch\">1234@epfl.ch</a>'
        );
    }

    private static function get_template_description() 
    {
        if (get_locale() == 'fr_FR') {
            $documentation_url = "https://help-wordpress.epfl.ch/autres-types-de-contenus/actualites-epfl/";
        } else {
            $documentation_url = "https://help-wordpress.epfl.ch/en/other-types-of-content/epfl-news/";
        }

        $template_description = sprintf(
            esc_html__('Do you need more information about templates? %sRead this documentation%s', 'epfl'),
            '<a href="' . $documentation_url . '">', '</a>'
        );

        return $template_description;
    }

    public static function config() 
    {
        shortcode_ui_register_for_shortcode(

            'epfl_news_2018',

            array(
                'label' => __('News', 'epfl'),
                'listItemImage' => '<img src="' . plugins_url( 'img/news.svg', __FILE__ ) . '" >',
                'attrs'         => array(
                        array(
                            'label'         => '<h3>' . esc_html__('Select your news channel', 'epfl') . '</h3>',
                            'attr'          => 'channel',
                            'type'          => 'select',
                            'options'       => ShortCakeNewsConfig::get_channel_options(),
                            'description'   => ShortCakeNewsConfig::get_channel_description(),
                        ),
                        array(
                            'label'         => '<h3>' . esc_html__('Select a template', 'epfl') . '</h3>',
                            'attr'          => 'template',
                            'type'          => 'radio',
                            'options'       => ShortCakeNewsConfig::get_template_options(),
                            'description'   => ShortCakeNewsConfig::get_template_description(),
                            'value'         => '1',
                        ),
                        array(
                            'label'         => '<strong>' . esc_html__('Display the link "all news" ?', 'epfl') . '</strong>',
                            'attr'          => 'all_news_link',
                            'type'          => 'checkbox',
                        ),
                        array(
                            'label'         => '<h3>' . esc_html__('Select the number of news', 'epfl') . '</h3>',
                            'attr'          => 'nb_news',
                            'type'          => 'text',
                            'description'   => __("The number of news can only be defined for the Template: 'Template for laboratory website'"),
                            'value'         => '5',
                        ),
                        array(
                            'label'         => '<h3>' . esc_html__('Select a language', 'epfl') . '</h3>',
                            'attr'          => 'lang',
                            'type'          => 'radio',
                            'options'       => ShortCakeNewsConfig::get_lang_options(),
                            'description'   => esc_html__('The language used to render news results', 'epfl'),
                            'value'         => 'en',
                        ),
                        array(
                            'label'         => '<h3>' . esc_html__('Display the news category ?', 'epfl') . '</h3>',
                            'attr'          => 'stickers',
                            'type'          => 'radio',
                            'options'       => ShortCakeNewsConfig::get_stickers_options(),
                            'description'   => esc_html__('Do you want display the news category at the top right of the news image?', 'epfl'),
                            'value'         => 'no',
                        ),
                        array(
                            'label'         => '<h3>' . esc_html__('Filter news by category', 'epfl') . '</h3>',
                            'attr'          => 'category',
                            'type'          => 'radio',
                            'options'       => ShortCakeNewsConfig::get_category_options(),
                            'description'   => esc_html__('Do you want filter news by category? Please select a category.', 'epfl'),
                        ),
                        array(
                            'label'         => '<h3>' . esc_html__('Filter news by themes', 'epfl') . '</h3>',
                            'attr'          => 'themes',
                            'type'          => 'select',
                            'options'       => ShortCakeNewsConfig::get_themes_options(),
                            'description'   => esc_html__('Do you want filter news by themes?. Please select themes.', 'epfl'),
                            'meta'          => array( 'multiple' => true ),
                            'width'         => '400',
                        ),
                    ),

                'post_type'     => array( 'post', 'page' ),
            )
        );
    }
}
?>
