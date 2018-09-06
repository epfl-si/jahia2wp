<?php

// register shortcake UI
add_action( 'register_shortcode_ui', 'post_teaser' );
function post_teaser() {
  $fields = array();

  array_push($fields,
    [
      'label' => esc_html__('Gray background', 'epfl-shortcodes') ,
      'attr' => 'gray',
      'type' => 'checkbox',
      'description' => esc_html__('Change the background to gray', 'epfl-shortcodes')
    ]);

  for($i; $i < 3; $i++) {
    array_push($fields, array(
      'label'    => esc_html__( 'Select post' , 'epfl-shortcodes'),
			'attr'     => 'post'.$i,
			'type'     => 'post_select',
			'query'    => array( 'post_type' => 'post' )
		));
  }

global $iconDirectory;

	shortcode_ui_register_for_shortcode(
		'epfl_post_teaser',
		array(
      'label' =>  esc_html__( 'Post teaser' , 'epfl-shortcodes'),
      'listItemImage' => '<img src="'.$iconDirectory.'teaser_news.png'.'">',
      'attrs' => $fields
    )
	);
}