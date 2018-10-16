<?php

/**
 * Short cake configuration
 */
Class ShortCakePeopleConfig
{
  private static function get_nb_columns_options()
  {
      return array(
          array('value' => '1', 'label' => '1 ' . esc_html__('column', 'epfl')),
          array('value' => '2', 'label' => '2 ' . esc_html__('columns', 'epfl')),
          array('value' => '3', 'label' => '3 ' . esc_html__('columns', 'epfl')),
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
            'description'   => 'You can enter many units separated by a comma',
          ),
          array
          (
            'label'         => '<h3>' . esc_html__('Scipers', 'epfl') . '</h3>',
            'attr'          => 'scipers',
            'type'          => 'text',
            'description'   => 'You can enter many scipers separated by a comma',
          ),
          array(
            'label'         => '<h3>' . esc_html__('Select a template', 'epfl') . '</h3>',
            'attr'          => 'columns',
            'type'          => 'radio',
            'options'       => ShortCakePeopleConfig::get_nb_columns_options(),
            'description'   => '',
            'value'         => '1',
          ),
        ),
        'post_type' => array( 'post', 'page' ),
      )
    );
  }
}
?>
