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
        'label' => 'People',
        'listItemImage' => '<img src="' . plugins_url( 'img/people.svg', __FILE__ ) . '" >',
        'attrs' => array
        (
          array
          (
            'label'         => '<h3>' . esc_html__('Unit', 'epfl') . '</h3>',
            'attr'          => 'unit',
            'type'          => 'text',
            'description'   => '',
          ),
        ),
        'post_type' => array( 'post', 'page' ),
      )
    );
  }
}
?>