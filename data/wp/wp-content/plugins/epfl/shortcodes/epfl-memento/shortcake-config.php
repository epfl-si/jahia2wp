<?php

Class ShortCakeMementoConfig
{
    private static function get_memento_options()
    {
       // call REST API to get the number of memento
       $memento_response = Utils::get_items(MEMENTO_API_URL);

       // build URL with all mementos
       $url = MEMENTO_API_URL . '?limit=' . $memento_response->count;

       // order mementos by name
       if (get_locale() == 'fr_FR') {
           $url .= '&ordering=fr_name';
       } else {
           $url .= '&ordering=en_name';
       }

       $memento_response = Utils::get_items($url);

       $memento_options = array();
       if(property_exists($memento_response, 'results'))
       {
           foreach ($memento_response->results as $item) {

               if (get_locale() == 'fr_FR') {
                   $memento_name = $item->fr_name;
               } else {
                   $memento_name = $item->en_name;
               }

               $option = array(
                   'value' => $item->slug,
                   'label' => $memento_name,
               );
               array_push($memento_options, $option);
           }
       }
       return $memento_options;
    }

    private static function get_template_options()
    {
        return array (
            array('value' => '1', 'label' => esc_html__('Template slider with the first highlighted event', 'epfl')),
            array('value' => '2', 'label' => esc_html__('Template slider without the first highlighted event', 'epfl')),
            array('value' => '3', 'label' => esc_html__('Template listing with the first highlighted event', 'epfl')),
            array('value' => '4', 'label' => esc_html__('Template listing without the first highlighted event', 'epfl')),
        );
    }

    private static function get_lang_options()
    {
        return array(
            array('value' => 'en', 'label' => esc_html__('English', 'epfl')),
            array('value' => 'fr', 'label' => esc_html__('French', 'epfl')),
        );
    }

    private static function get_category_options()
    {
        return array(
            array('value' => '', 'label' => esc_html__('No filter', 'epfl')),
            array('value' => '1', 'label' => esc_html__('Conferences - Seminars', 'epfl')),
            array('value' => '2', 'label' => esc_html__('Management Board meetings', 'epfl')),
            array('value' => '4', 'label' => esc_html__('Miscellaneous', 'epfl')),
            array('value' => '5', 'label' => esc_html__('Exhibitions', 'epfl')),
            array('value' => '6', 'label' => esc_html__('Movies', 'epfl')),
            array('value' => '7', 'label' => esc_html__('Celebrations', 'epfl')),
            array('value' => '8', 'label' => esc_html__('Inaugural lectures - Honorary Lecture', 'epfl')),
            array('value' => '9', 'label' => esc_html__('Cultural events', 'epfl')),
            array('value' => '10', 'label' => esc_html__('Sporting events', 'epfl')),
            array('value' => '12', 'label' => esc_html__('Thesis defenses', 'epfl')),
            array('value' => '13', 'label' => esc_html__('Academic Calendar', 'epfl')),
            array('value' => '15', 'label' => esc_html__('Internal trainings', 'epfl')),
            array('value' => '16', 'label' => esc_html__('Call for proposal', 'epfl')),
            array('value' => '17', 'label' => esc_html__('Deadline', 'epfl')),
            array('value' => '18', 'label' => esc_html__('Sciences Activities for Youth', 'epfl')),
            array('value' => '19', 'label' => esc_html__('Public Science Events', 'epfl')),
        );
    }

    private static function get_period_options() 
    {
        return array(
            array('value' => 'upcoming', 'label' => esc_html__('Upcoming events', 'epfl')),
            array('value' => 'past', 'label' => esc_html__('Past events', 'epfl')),
        );
    }

    private static function get_memento_description()
    {
        return sprintf(
            __("Please select your memento.%sThe events come from the application %smemento.epfl.ch%s.%sIf you don't have a memento, please send a request to %s", 'epfl' ),
            '<br/>', '<a href=\"https://actu.epfl.ch\">', '</a>', '<br/>', '<a href=\"mailto:1234@epfl.ch\">1234@epfl.ch</a>'
        );
    }

    private static function get_template_description()
    {
        if (get_locale() == 'fr_FR') {
            $documentation_url = "https://help-wordpress.epfl.ch/autres-types-de-contenus/memento/";
        } else {
            $documentation_url = "https://help-wordpress.epfl.ch/en/other-types-of-content/memento/";
        }

        return sprintf(
            esc_html__('Do you need more information about templates? %sRead this documentation%s', 'epfl'),
            '<a href="' . $documentation_url . '">', '</a>'
        );
    }

    public static function config()
    {
       shortcode_ui_register_for_shortcode(

           'epfl_memento_2018',

           array(
               'label' => 'Memento',
               'listItemImage' => '<img src="' . plugins_url( 'img/memento.svg', __FILE__ ) . '" >',
               'attrs'         => array(
                   array(
                       'label'         => '<h3>' . esc_html__('Select your memento', 'epfl') . '</h3>',
                       'attr'          => 'memento',
                       'type'          => 'select',
                       'options'       => ShortCakeMementoConfig::get_memento_options(),
                       'description'   => ShortCakeMementoConfig::get_memento_description(),
                   ),
                   array(
                       'label'         => '<h3>' . esc_html__('Select a template', 'epfl') . '</h3>',
                       'attr'          => 'template',
                       'type'          => 'radio',
                       'options'       => ShortCakeMementoConfig::get_template_options(),
                       'description'   => ShortCakeMementoConfig::get_template_description(),
                       'value'         => '2',
                   ),
                   array(
                       'label'         => '<h3>' . esc_html__('Select a language', 'epfl') . '</h3>',
                       'attr'          => 'lang',
                       'type'          => 'radio',
                       'options'       => ShortCakeMementoConfig::get_lang_options(),
                       'description'   => esc_html__('The language used to render events results', 'epfl'),
                       'value'         => 'en',
                   ),
                   array(
                        'label'        => '<h3>' . esc_html__('Select a period', 'epfl') . '</h3>',
                        'attr'         => 'period',
                        'type'         => 'radio',
                        'options'      => ShortCakeMementoConfig::get_period_options(),
                        'description'  => esc_html__('Do you want upcoming events or past events ?', 'epfl'),
                        'value'        => 'upcoming',
                   ),
                   array(
                       'label'         => '<h3>' . esc_html__('Filter events by category', 'epfl') . '</h3>',
                       'attr'          => 'category',
                       'type'          => 'radio',
                       'options'       => ShortCakeMementoConfig::get_category_options(),
                       'description'   => esc_html__('Do you want filter events by category? Please select a category.', 'epfl'),
                   ),
                   array(
                       'label'         => '<h3>' . esc_html__('Filter events by keyword', 'epfl') . '</h3>',
                       'attr'          => 'keyword',
                       'type'          => 'text',
                       'description'   => esc_html__('Do you want filter events by keyword? Please type a keyword.', 'epfl'),
                   ),
               ),
               'post_type'     => array( 'post', 'page' ),
           )
       );
    }
}
?>