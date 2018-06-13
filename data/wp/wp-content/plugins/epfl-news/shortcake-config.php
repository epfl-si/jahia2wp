<?php

require_once 'utils.php';

Class ShortCakeConfig
{   
    private static function get_channel_options() 
    {
        // call REST API to get the number of channels
        $channel_response = NewsUtils::get_items(NEWS_API_URL);

        // build URL with all channels
        $url = NEWS_API_URL . '?limit=' . $channel_response->count;

        // call REST API to get all channels
        $channel_response = NewsUtils::get_items($url);

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
            array('value' => 'en', 'label' => esc_html__('English', 'epfl-news')),
            array('value' => 'fr', 'label' => esc_html__('French', 'epfl-news')),
        );
    }

    private static function get_stickers_options() 
    {
        return array(
            array('value' => 'no', 'label' => esc_html__('No', 'epfl-news')),
            array('value' => 'yes', 'label' => esc_html__('Yes', 'epfl-news')),
        );
    }

    private static function get_category_options() 
    {
        return array(
            array('value' => '', 'label' => esc_html__('No filter', 'epfl-news')),
            array('value' => '1', 'label' => esc_html__('Epfl', 'epfl-news')),
            array('value' => '2', 'label' => esc_html__('Education', 'epfl-news')),
            array('value' => '3', 'label' => esc_html__('Research', 'epfl-news')),
            array('value' => '4', 'label' => esc_html__('Innovation', 'epfl-news')),
            array('value' => '5', 'label' => esc_html__('Campus Life', 'epfl-news')),
        );
    }

    private static function get_template_options() 
    {
        return array (
            array('value' => '4', 'label' => esc_html__('Template for laboratory website with 3 news', 'epfl-news')),
            array('value' => '8', 'label' => esc_html__('Template for laboratory website with 5 news', 'epfl-news')),
            array('value' => '6', 'label' => esc_html__('Template for faculty website with 3 news', 'epfl-news')),
            array('value' => '3', 'label' => esc_html__('Template for faculty website with 4 news', 'epfl-news')),
            array('value' => '2', 'label' => esc_html__('Template text only', 'epfl-news')),
            array('value' => '10', 'label' => esc_html__('Template with all news', 'epfl-news')),
            array('value' => '1', 'label' => esc_html__('Template for portal website with image at the top', 'epfl-news')),
            array('value' => '7', 'label' => esc_html__('Template for portal website image on the left', 'epfl-news')),
        );
    }

    private static function get_themes_options() 
    {
        return array (
            array('value' => '', 'label' => esc_html__('No filter', 'epfl-news')),
            array('value' => '1', 'label' => esc_html__('Basic Sciences', 'epfl-news')),
            array('value' => '2', 'label' => esc_html__('Health', 'epfl-news')),
            array('value' => '3', 'label' => esc_html__('Computer Science', 'epfl-news')),
            array('value' => '4', 'label' => esc_html__('Engineering', 'epfl-news')),
            array('value' => '5', 'label' => esc_html__('Environment', 'epfl-news')),
            array('value' => '6', 'label' => esc_html__('Buildings', 'epfl-news')),
            array('value' => '7', 'label' => esc_html__('Culture', 'epfl-news')),
            array('value' => '8', 'label' => esc_html__('Economy', 'epfl-news')),
            array('value' => '9', 'label' => esc_html__('Energy', 'epfl-news')),
        );
    }

    private static function get_channel_description() 
    {
        return sprintf(
            __("The news come from the application %sactu.epfl.ch%s.%sIf you don't have a news channel, please send a request to %s", 'epfl-news' ),
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
            esc_html__('Do you need more information about templates? %sRead this documentation%s', 'epfl-news'),
            '<a href="' . $documentation_url . '">', '</a>'
        );

        return $template_description;
    }

    public static function config() 
    {
        shortcode_ui_register_for_shortcode(

            'epfl_news',

            array(
                'label' => __('Add News shortcode', 'epfl-news'),
                'listItemImage' => '<img src="' . plugins_url( 'img/news.svg', __FILE__ ) . '" >',
                'attrs'         => array(
                        array(
                            'label'         => '<h3>' . esc_html__('Select your news channel', 'epfl-news') . '</h3>',
                            'attr'          => 'channel',
                            'type'          => 'select',
                            'options'       => ShortCakeConfig::get_channel_options(),
                            'description'   => ShortCakeConfig::get_channel_description(),
                        ),
                        array(
                            'label'         => '<h3>' . esc_html__('Select a template', 'epfl-news') . '</h3>',
                            'attr'          => 'template',
                            'type'          => 'radio',
                            'options'       => ShortCakeConfig::get_template_options(),
                            'description'   => ShortCakeConfig::get_template_description(),
                            'value'         => '4',
                        ),
                        array(
                            'label'         => '<h3>' . esc_html__('Select a language', 'epfl-news') . '</h3>',
                            'attr'          => 'lang',
                            'type'          => 'radio',
                            'options'       => ShortCakeConfig::get_lang_options(),
                            'description'   => esc_html__('The language used to render news results', 'epfl-news'),
                            'value'         => 'en',
                        ),
                        array(
                            'label'         => '<h3>' . esc_html__('Display the news category ?', 'epfl-news') . '</h3>',
                            'attr'          => 'stickers',
                            'type'          => 'radio',
                            'options'       => ShortCakeConfig::get_stickers_options(),
                            'description'   => esc_html__('Do you want display the news category at the top right of the news image?', 'epfl-news'),
                            'value'         => 'no',
                        ),
                        array(
                            'label'         => '<h3>' . esc_html__('Filter news by category', 'epfl-news') . '</h3>',
                            'attr'          => 'category',
                            'type'          => 'radio',
                            'options'       => ShortCakeConfig::get_category_options(),
                            'description'   => esc_html__('Do you want filter news by category? Please select a category.', 'epfl-news'),
                        ),
                        array(
                            'label'         => '<h3>' . esc_html__('Filter news by themes', 'epfl-news') . '</h3>',
                            'attr'          => 'themes',
                            'type'          => 'select',
                            'options'       => ShortCakeConfig::get_themes_options(),
                            'description'   => esc_html__('Do you want filter news by themes?. Please select themes.', 'epfl-news'),
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
