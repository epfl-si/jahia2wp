<?php

/**
 * Short cake configuration
 */
Class ShortCakePeopleConfig
{   
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
        ),
        'post_type' => array( 'post', 'page' ),
      )
    );
  }
}
?>