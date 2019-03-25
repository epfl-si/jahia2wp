<?php

/**
 * Short cake configuration
 */
Class ShortCakePeopleConfig
{

  private static function get_function_description() 
  {
    return sprintf(
      __("You can enter a function to filter persons. The keyword must be in french. Example: professeur or enseignement.%s %sMore information%s", 'epfl' ),
      '<br/>','<a href=\"https://www.epfl.ch/campus/services/ressources-informatiques/publier-sur-le-web-epfl/wordpress/autres-contenus/people/\" target="_blank">', '</a>'
    );
  }

  private static function get_nb_columns_options()
  {
      return array(
          array('value' => '1', 'label' => esc_html__('As card, one column', 'epfl')),
          array('value' => '3', 'label' => esc_html__('As card, multiple columns', 'epfl')),
          array('value' => 'list', 'label' => esc_html__('As list', 'epfl')),
      );
  }

  public static function config()
  {
    shortcode_ui_register_for_shortcode
    (
      'epfl_people_2018',
      array
      (
        'label'         => 'People',
        'listItemImage' => '<img src="' . plugins_url( 'img/people.svg', __FILE__ ) . '" >',
        'attrs'         => array
        (
          array
          (
            'label'         => '<h3>' . esc_html__('Units', 'epfl') . '</h3>',
            'attr'          => 'units',
            'type'          => 'text',
            'description'   => esc_html__('You can enter many units separated by a comma', 'epfl'),
          ),
          array
          (
            'label'         => '<h3>' . esc_html__('Scipers', 'epfl') . '</h3>',
            'attr'          => 'scipers',
            'type'          => 'text',
            'description'   => esc_html__('You can enter many scipers separated by a comma', 'epfl'),
          ),
          array
          (
            'label'         => '<h3>' . esc_html__('Function', 'epfl') . '</h3>',
            'attr'          => 'function',
            'type'          => 'text',
            'description'   => ShortCakePeopleConfig::get_function_description(),
          ),
          array(
            'label'         => '<h3>' . esc_html__('Select a template', 'epfl') . '</h3>',
            'attr'          => 'columns',
            'type'          => 'radio',
            'options'       => ShortCakePeopleConfig::get_nb_columns_options(),
            'description'   => '',
            'value'         => '3',
          ),
        ),
        'post_type' => array( 'post', 'page' ),
      )
    );
  }
}
?>
